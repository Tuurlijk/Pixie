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

use JJG\Ping;
use MaxServ\Pixie\Console\Application;
use MaxServ\Pixie\Pixie;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class BaseCommand
 * @package MaxServ\Pixie\Command
 */
class BaseCommand extends Command
{
    /**
     * @var Pixie
     */
    private $pixie;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Backup directory
     *
     * @var string
     */
    protected $backupDirectory;

    /**
     * Temporary directory
     *
     * @var string
     */
    protected $temporaryDirectory;

    /**
     * @param  bool $required
     * @param  bool $disablePlugins
     * @throws \RuntimeException
     * @return Pixie
     */
    public function getPixie($required = true, $disablePlugins = false)
    {
        if (null === $this->pixie) {
            $application = $this->getApplication();
            if ($application instanceof Application) {
                /* @var $application    Application */
                $this->pixie = $application->getPixie($required, $disablePlugins);
            } elseif ($required) {
                throw new \RuntimeException(
                    'Could not create a MaxServ\Pixie instance, you must inject ' .
                    'one if this command is not used with a Pixie\Console\Application instance'
                );
            }
        }

        return $this->pixie;
    }

    /**
     * Get valid configuration
     *
     * @param string $key
     * @param array $sites
     * @param string $type
     *
     * @return string
     */
    protected function getConfiguration($key, array $sites, $type = 'source')
    {
        $availableKeys = array_keys($sites);
        $default = ($type === 'target') ? reset($availableKeys) : end($availableKeys);
        if (empty($key)) {
            $this->output->writeln('No <info>' . $type . '</info> configuration given.' . PHP_EOL);
        }
        while (empty($key) || !in_array($key, $availableKeys, true)) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select <info>' . $type . '</info> configuration key [' . $default . ']:',
                $availableKeys,
                $default
            );
            $question->setErrorMessage('Configuration %s does not exist.');

            $key = $helper->ask($this->input, $this->output, $question);
        }
        $configuration = $sites[$key];
        $configuration = $this->sanitizeSiteConfiguration($configuration);

        return $configuration;
    }

    /**
     * See if host is reachable
     *
     * @param $host
     * @return bool
     * @throws \Exception
     */
    protected function isHostReachable($host)
    {
        $ping = new Ping($host);
        $latency = $ping->ping();
        if ($latency === false) {
            $this->output->writeln(sprintf('<error>Host \'%s\' is unreachable.</error>', $host));
            exit(1);
        }
        return true;
    }

    /**
     * Check if configuration exists
     *
     * @param $source
     * @param $sites
     * @return bool
     */
    protected function doesConfigurationExist($source, $sites)
    {
        if (!array_key_exists($source, $sites)) {
            $this->output->writeln(sprintf('<error>No configuration found with the key: %s</error>', $source));
            $this->output->writeln('<comment>Available configurations:</comment>');
            $this->output->writeln(array_keys($sites));
        } else {
            return true;
        }
        return false;
    }

    /**
     * Sanitize site configuration
     *
     * @param array $configuration
     * @return array
     */
    private function sanitizeSiteConfiguration($configuration)
    {
        $expectedKeys = ['host', 'username', 'root', 'directory'];
        foreach ($expectedKeys as $expectedKey) {
            if (!isset($configuration[$expectedKey])) {
                $configuration[$expectedKey] = '';
            } else {
                $configuration[$expectedKey] = escapeshellarg($configuration[$expectedKey]);
            }
        }
        $configuration['database'] = $this->sanitizeDatabaseConfiguration($configuration['database']);
        return $configuration;
    }

    /**
     * Sanitize database configuration
     *
     * @param array $configuration
     * @return array
     */
    private function sanitizeDatabaseConfiguration($configuration)
    {
        $expectedKeys = ['host', 'admin_username', 'admin_password', 'username', 'password', 'database', 'platform'];
        foreach ($expectedKeys as $expectedKey) {
            if (!isset($configuration[$expectedKey])) {
                $configuration[$expectedKey] = '';
            } else {
                $configuration[$expectedKey] = escapeshellarg($configuration[$expectedKey]);
            }
            if ($expectedKey === 'database') {
                $configuration['database'] = preg_replace('/[^a-zA-Z0-0_]/', '', $configuration[$expectedKey]);
            }
        }
        return $configuration;
    }

    /**
     * Set temporary and backup Directories
     */
    public function setDirectories()
    {
        $now = time();
        $this->backupDirectory = '~/pixie/backup_' . $now . '/';
        $this->temporaryDirectory = '~/pixie/tmp_' . $now . '/';
    }
}
