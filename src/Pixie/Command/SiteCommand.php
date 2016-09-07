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
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SiteCommand
 * @package MaxServ\Pixie\Command
 */
class SiteCommand extends BaseCommand
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('site')
            ->setDescription('Manage site configurations')
            ->setDefinition(array(
                new InputArgument('action', InputArgument::OPTIONAL, 'Source configuration name', 'list'),
            ))
            ->setHelp(<<<EOT
The <info>site</info> command manages site configurations</info>.

List available sites:
<info>php pixie.phar site list</info>

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
        $pixie = $this->getPixie();
        $configuration = $pixie->getConfig();
        $sites = $configuration->getSites();

//        $action = $input->getArgument('action');

        $firstRow = true;
        $table = new Table($output);
        foreach (array_keys($sites) as $name) {
            if (!$firstRow) {
                $table->addRow(new TableSeparator());
            }
            $configuration = $this->getConfiguration($name, $sites);
            $table->addRows([
                array(new TableCell('<comment>' . $name . '</comment>', array('colspan' => 2))),
                new TableSeparator(),
                ['host', '<info>' . $configuration['host'] . '</info>'],
                ['username', $configuration['username']],
                ['root', $configuration['root']],
                ['directory', $configuration['directory']]
            ]);
            $firstRow = false;
        }
        $table->render();
    }
}
