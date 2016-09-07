<?php
namespace MaxServ\Pixie\Command;

/**
 *  Copyright notice
 *
 *  ⓒ 2016 ℳichiel ℛoos <michiel@maxserv.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of the
 *  GNU General Public License as published by the Free Software Foundation;
 *  either version 2 of the License, or (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 *  or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Class SyncCommand
 * @package MaxServ\Pixie\Command
 */
class SyncCommand extends BaseCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('sync')
            ->setDescription('Synchronize TYPO3 installations between servers.')
            ->setDefinition(array(
                new InputArgument('source', InputArgument::OPTIONAL, 'Source configuration name'),
                new InputArgument('target', InputArgument::OPTIONAL, 'Target configuration name'),
                new InputOption('dry', null, InputOption::VALUE_NONE, 'Dry run. Show what would be synchronised.'),
                new InputOption('no-strict-host-key', null, InputOption::VALUE_NONE, 'No strict host key checking.'),
                new InputOption('skip-database', null, InputOption::VALUE_NONE, 'Skip database synchronisation.'),
                new InputOption('skip-files', null, InputOption::VALUE_NONE, 'Skip file synchronisation.'),
            ))
            ->setHelp(<<<EOT
The <info>sync</info> command can synchronize TYPO3 installations between servers.
<info>php pixie.phar sync dev local</info>

Show what would be synchronised:
<info>php pixie.phar sync dev local --dry</info>

Skip strict host key checking during rsync:
<info>php pixie.phar sync dev local --no-strict-host-key</info>

Skip synchronising database:
<info>php pixie.phar sync dev local --skip-database</info>

Skip synchronising files:
<info>php pixie.phar sync dev local --skip-files</info>

To list available configurations: <info>php pixie.phar sites</info>

EOT
            );
    }

    /**
     * Execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void|int
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
        $this->setDirectories();

        $pixie = $this->getPixie();
        $configuration = $pixie->getConfig();
        $sites = $configuration->getSites();

        $sourceConfiguration = $this->getConfiguration($input->getArgument('source'), $sites, 'source');
        $targetConfiguration = $this->getConfiguration($input->getArgument('target'), $sites, 'target');

        $this->displayConfiguration($sourceConfiguration, $targetConfiguration);

        $sourcePath = $this->getSourcePath($sourceConfiguration);
        $this->appendSlash($targetConfiguration['root']);
        $targetPath = $targetConfiguration['root'] . $sourceConfiguration['directory'];

        $command = ['rsync -a -u'];

        if ($input->getOption('no-strict-host-key')) {
            $command['ssh'] = "-e 'ssh -o StrictHostKeyChecking=no'";
        }

        if ($input->getOption('dry')) {
            $command['dry'] = '--dry-run';
            $command['verbose'] = '-v';
        }

        if ($input->getOption('quiet')) {
            $command['quiet'] = '-q';
        } else {
            $command['verbose'] = '-v';
        }

        $command['source'] = $sourcePath;
        $command['target'] = $targetPath;
        $command = implode(' ', $command);

        if ($input->getOption('skip-files')) {
            $this->io->section('Skipping files synchronisation');
        } else {
            $this->io->section('Synchronising files');
            $this->executeCommand($this->getSshCommand($command, $targetConfiguration));
        }

        if ($input->getOption('skip-database')) {
            $this->io->section('Skipping database synchronisation');
        } else {
            if ($this->getDatabaseDump($sourceConfiguration, $sourceConfiguration['database']['database'])) {
                $this->importDatabase($sourceConfiguration, $targetConfiguration);
            }
        }
    }

    /**
     * Execute a command and show real-time output
     *
     * @param $command
     *
     * @return string
     */
    private function executeCommand($command)
    {
        if ($this->input->getOption('verbose')) {
            $this->io->title('Executing:');
            $this->io->text($command . PHP_EOL);
        }

        $process = new Process($command);
        $process->setTimeout(3600);
        $output = $this->io;
        $process->run(function ($type, $buffer) use ($output) {
            if ('err' === $type) {
                $output->write('<error>' . $buffer . '</error>');
            } else {
                $output->write($buffer);
            }
        });
        return $process->getOutput();
    }

    /**
     * Create a database dump on a remote server
     *
     * @param array $configuration
     * @param string $database
     *
     * @return bool
     */
    private function createDatabaseDump($configuration, $database)
    {
        if (!$configuration['database']) {
            return false;
        }

        if ($configuration['database']['platform'] !== "'mysql'") {
            $this->io->error('Platform is not supported yet.');
        } else {
            $this->io->section(sprintf(
                'Creating database dump for %s on %s',
                $database,
                $configuration['host']
            ));
        }

        $this->executeCommand($this->getSshCommand('mkdir -p ' . $this->temporaryDirectory, $configuration));

        $command = ['mysqldump'];
        $command['options'] = '--default-character-set=utf8 --single-transaction';

        if ($configuration['database']['host']) {
            $command['host'] = '-h ' . $configuration['database']['host'];
        }

        if ($configuration['database']['username']) {
            $command['username'] = '-u ' . $configuration['database']['username'];
        }

        if ($configuration['database']['password']) {
            $command['password'] = '-p' . $configuration['database']['password'];
        }

        if ($database) {
            $command['database'] = $database;
        }

        $dumpFileName = $database . '.sql.gz';

        $pathToDumpFile = $this->temporaryDirectory . $dumpFileName;
        $command['compress'] = '| gzip > ' . $pathToDumpFile;

        $command = implode(' ', $command);

        $this->executeCommand($this->getSshCommand($command, $configuration));

        return (bool)$this->executeCommand($this->getSshCommand('ls ' . $pathToDumpFile, $configuration));
    }

    /**
     * Get a database backup from a remote server
     *
     * @param array $configuration
     * @param string $database
     *
     * @return bool
     */
    private function getDatabaseBackup($configuration, $database)
    {
        if (!$this->createDatabaseDump($configuration, $database)) {
            return false;
        }

        $this->executeCommand('mkdir -p ' . $this->backupDirectory);

        $dumpFileName = $database . '.sql.gz';
        $pathToDumpFile = $this->temporaryDirectory . $dumpFileName;

        $this->executeCommand($this->getScpFromCommand(
            $pathToDumpFile,
            $configuration,
            $this->backupDirectory
        ));

        return $this->executeCommand('ls ' . $this->backupDirectory . trim($dumpFileName, "'"));
    }

    /**
     * Get a database dump from a remote server
     *
     * @param array $configuration
     * @param string $database
     *
     * @return bool
     */
    private function getDatabaseDump($configuration, $database)
    {
        if (!$this->createDatabaseDump($configuration, $database)) {
            return false;
        }

        $dumpFileName = $database . '.sql.gz';

        $pathToDumpFile = $this->temporaryDirectory . $dumpFileName;

        $this->executeCommand($this->getScpFromCommand(
            $pathToDumpFile,
            $configuration,
            $this->appendSlash(sys_get_temp_dir())
        ));
        $this->executeCommand($this->getSshCommand('rm -rf ' . $this->temporaryDirectory, $configuration));

        if (file_exists(sys_get_temp_dir() . '/' . trim($dumpFileName, "'"))) {
            return true;
        }
        return false;
    }

    /**
     * Get the scp command to copy a file _from_ a remote server if the $configuration has a valid host
     *
     * @param string $file
     * @param array $configuration
     * @param string $target
     * @return string
     */
    private function getScpFromCommand($file, $configuration, $target)
    {
        $scpCommand = '';
        if (!$configuration['host']) {
            return $scpCommand;
        }
        $user = $configuration['username'] ? $configuration['username'] . '@' : '';
        return 'scp ' . $user . $configuration['host'] . ':"' . $file . '" ' . $target;
    }

    /**
     * Get the scp command to copy a file _to_ a remote server if the $configuration has a valid host
     *
     * @param string $file
     * @param array $configuration
     * @param string $target
     * @return string
     */
    private function getScpToCommand($file, $configuration, $target)
    {
        $scpCommand = '';
        if (!$configuration['host']) {
            return $scpCommand;
        }
        $user = $configuration['username'] ? $configuration['username'] . '@' : '';
        return 'scp ' . $file . ' ' . $user . $configuration['host'] . ':"' . $target . '"';
    }

    /**
     * Get the ssh command if the $configuration has a valid host
     *
     * @param $command
     * @param $configuration
     * @return string
     */
    private function getSshCommand($command, $configuration)
    {
        if (!$configuration['host']) {
            return $command;
        }
        $user = $configuration['username'] ? $configuration['username'] . '@' : '';
        return 'ssh ' . $user . $configuration['host'] . ' "' . $command . '"';
    }

    /**
     * Get source path
     *
     * @param $configuration
     * @return string
     */
    private function getSourcePath($configuration)
    {
        $path = '';
        if ($configuration['host']) {
            $path = $configuration['host'] . ':';
            if ($configuration['username']) {
                $path = $configuration['username'] . '@' . $path;
            }
        }
        if ($configuration['root']) {
            $this->appendSlash($configuration['root']);
            $path .= $configuration['root'];
        }
        if ($configuration['directory']) {
            $this->appendSlash($configuration['directory']);
            $path .= $configuration['directory'];
        }
        return $path;
    }

    /**
     * Display what will be synchronised
     *
     * @param $sourceConfiguration
     * @param $targetConfiguration
     */
    private function displayConfiguration($sourceConfiguration, $targetConfiguration)
    {
        $configurationTable = new Table($this->output);
        $configurationTable->setHeaders(['', 'source', 'target']);
        $configurationTable->addRows([
            ['<comment>host</comment>', $sourceConfiguration['host'], $targetConfiguration['host']],
            ['<comment>username</comment>', $sourceConfiguration['username'], $targetConfiguration['username']],
            ['<comment>root</comment>', $sourceConfiguration['root'], $targetConfiguration['root']],
            ['<comment>directory</comment>', $sourceConfiguration['directory'], $targetConfiguration['directory']]
        ]);
        $configurationTable->render();
        $this->output->writeln('');
    }

    /**
     * Ensure path segment has end slash
     * @param $segment
     *
     * @return string
     */
    private function appendSlash(&$segment)
    {
        if (substr($segment, -1) !== '/') {
            $segment .= '/';
        }

        return $segment;
    }

    /**
     * Import database on target system
     *
     * @param $sourceConfiguration
     * @param $targetConfiguration
     * @return bool
     */
    private function importDatabase($sourceConfiguration, $targetConfiguration)
    {
        $this->io->section(sprintf(
            'Importing database dump for %s to %s',
            $sourceConfiguration['database']['database'],
            $targetConfiguration['host']
        ));

        $dumpFileName = $sourceConfiguration['database']['database'] . '.sql.gz';

        $pathToDumpFile = sys_get_temp_dir() . '/' . $dumpFileName;

        if (!isset($targetConfiguration['database'])) {
            return false;
        }

        if ($this->doesDatabaseExist($sourceConfiguration['database']['database'], $targetConfiguration)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'Database \'%s\' already exists on %s. I am going to drop it.' . PHP_EOL . 'Do you want me to make a backup first)? [y]: ',
                    $sourceConfiguration['database']['database'],
                    $targetConfiguration['host']
                ),
                true
            );

            if ($helper->ask($this->input, $this->output, $question)) {
                $this->getDatabaseBackup($targetConfiguration, $sourceConfiguration['database']['database']);
            }
            $this->dropDatabase($sourceConfiguration['database']['database'], $targetConfiguration);
        }

        $this->executeCommand($this->getSshCommand('mkdir -p ' . $this->temporaryDirectory, $targetConfiguration));
        $this->executeCommand($this->getScpToCommand($pathToDumpFile, $targetConfiguration, $this->temporaryDirectory));
        $this->createDatabase($sourceConfiguration, $targetConfiguration);

        if (substr($dumpFileName, -2, 2) === 'gz') {
            $command = ['gunzip < '];
            $command['file'] = $this->temporaryDirectory . '/' . $dumpFileName . ' | ';
        }

        $command['mysql'] = $this->getDatabaseConnection($targetConfiguration);

        if ($sourceConfiguration['database']['database']) {
            $command['database'] = $sourceConfiguration['database']['database'];
        }

        $command = implode(' ', $command);

        $this->io->section(sprintf(
            'Importing database dump for %s on %s',
            $sourceConfiguration['database']['database'],
            $targetConfiguration['host']
        ));

        $this->executeCommand($this->getSshCommand($command, $targetConfiguration));
        $this->executeCommand($this->getSshCommand('rm -rf ' . $this->temporaryDirectory, $targetConfiguration));
        return true;
    }

    /**
     * Create database and grant privileges
     *
     * @param array $sourceConfiguration
     * @param array $targetConfiguration
     */
    private function createDatabase($sourceConfiguration, $targetConfiguration)
    {
        $command['mysql'] = $this->getDatabaseConnection($targetConfiguration, true);
        $command['command'] = '--batch -e \"CREATE DATABASE '
            . $sourceConfiguration['database']['database'] . '\"';
        $command = implode(' ', $command);

        $this->executeCommand($this->getSshCommand($command, $targetConfiguration));

        $this->grantDatabasePrivileges(
            $sourceConfiguration['database']['database'],
            $sourceConfiguration['database']['username'],
            $sourceConfiguration['database']['password'],
            $targetConfiguration
        );

        $this->grantDatabasePrivileges(
            $sourceConfiguration['database']['database'],
            $targetConfiguration['database']['username'],
            $targetConfiguration['database']['password'],
            $targetConfiguration
        );
    }

    /**
     * Drop database
     *
     * @param $database
     * @param $configuration
     */
    private function dropDatabase($database, $configuration)
    {
        $command['mysql'] = $this->getDatabaseConnection($configuration, true);
        $command['command'] = '--batch -e \"DROP DATABASE '
            . $database . '\"';
        $command = implode(' ', $command);

        $this->executeCommand($this->getSshCommand($command, $configuration));
    }

    /**
     * Grant database privileges
     *
     * @param string $database
     * @param string $username
     * @param string $password
     * @param array $configuration
     */
    private function grantDatabasePrivileges($database, $username, $password, $configuration)
    {
        $command['mysql'] = $this->getDatabaseConnection($configuration, true);
        $command['command'] = '--batch -e \"GRANT ALL PRIVILEGES ON '
            . $database . '.* TO '
            . $username . ' IDENTIFIED BY '
            . $password . '\"';
        $command = implode(' ', $command);

        $this->executeCommand($this->getSshCommand($command, $configuration));
    }

    /**
     * Check if database exists
     *
     * @param $database
     * @param $configuration
     *
     * @return string
     */
    private function doesDatabaseExist($database, $configuration)
    {
        $command['mysql'] = $this->getDatabaseConnection($configuration);
        $command['options'] = '--batch --skip-column-names';
        $command['command'] = '-e \"SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \''
            . $database . '\'\"';
        $command = implode(' ', $command);

        return trim($this->executeCommand($this->getSshCommand($command, $configuration))) ===
        $database;
    }

    /**
     * Get database connection
     *
     * @param array $configuration
     * @param bool $asAdmin
     * @return mixed
     */
    private function getDatabaseConnection($configuration, $asAdmin = false)
    {
        if ($configuration['database']['platform'] !== "'mysql'") {
            $this->io->error('Platform is not supported yet.');
        }

        $command['mysql'] = 'mysql';

        if ($configuration['database']['host']) {
            $command['host'] = '-h ' . $configuration['database']['host'];
        }

        if ($asAdmin) {
            if ($configuration['database']['admin_username']) {
                $command['username'] = '-u ' . $configuration['database']['admin_username'];
            }

            if ($configuration['database']['admin_password']) {
                $command['password'] = '-p' . $configuration['database']['admin_password'];
            }
        } else {
            if ($configuration['database']['username']) {
                $command['username'] = '-u ' . $configuration['database']['username'];
            }

            if ($configuration['database']['password']) {
                $command['password'] = '-p' . $configuration['database']['password'];
            }
        }

        return implode(' ', $command);
    }
}
