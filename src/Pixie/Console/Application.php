<?php
namespace MaxServ\Pixie\Console;

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

use MaxServ\Pixie\Command;
use MaxServ\Pixie\Factory;
use MaxServ\Pixie\IO\ConsoleIO;
use MaxServ\Pixie\Pixie;
use MaxServ\Pixie\Util\Silencer;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * The console application that handles the commands
 *
 * @author Ryan Weaver <ryan@knplabs.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author François Pluchino <francois.pluchino@opendisplay.com>
 */
class Application extends BaseApplication
{
    /**
     * @var Pixie
     */
    protected $pixie;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     *
     * @var string
     */
    private static $logo = '    ____  _      _    
   / __ \(_)  __(_)__ 
  / /_/ / / |/_/ / _ \
 / ____/ />  </ /  __/
/_/   /_/_/|_/_/\___/ 

';


    /**
     * Application constructor.
     */
    public function __construct()
    {
        $this->setHelperSet($this->getDefaultHelperSet());

        $input = new ArgvInput();

        $output = new ConsoleOutput();

        $this->io = new ConsoleIO($input, $output, $this->getHelperSet());

        static $shutdownRegistered = false;

        if (function_exists('ini_set') && extension_loaded('xdebug')) {
            ini_set('xdebug.show_exception_trace', false);
            ini_set('xdebug.scream', false);
        }

        if (function_exists('date_default_timezone_set') && function_exists('date_default_timezone_get')) {
            date_default_timezone_set(Silencer::call('date_default_timezone_get'));
        }

        if (!$shutdownRegistered) {
            $shutdownRegistered = true;

            register_shutdown_function(function () {
                $lastError = error_get_last();

                if ($lastError && $lastError['message'] &&
                    (strpos($lastError['message'], 'Allowed memory') !== false /*Zend PHP out of memory error*/ ||
                        strpos($lastError['message'], 'exceeded memory') !== false /*HHVM out of memory errors*/)
                ) {
                    echo "\n" . 'Check https://getcomposer.org/doc/articles/troubleshooting.md#memory-limit-errors for more info on how to handle out of memory errors.';
                }
            });
        }

        parent::__construct('Pixie', Pixie::VERSION);
    }

    /**
     * @param  bool $required
     * @param  bool $disablePlugins
     * @return \MaxServ\Pixie\Pixie
     * @throws \UnexpectedValueException
     */
    public function getPixie($required = true, $disablePlugins = false)
    {
        if (null === $this->pixie) {
            try {
                $this->pixie = Factory::create($this->io, null);
            } catch (\InvalidArgumentException $e) {
                if ($required) {
                    $this->io->writeError($e->getMessage());
                    exit(1);
                }
            }
        }

        return $this->pixie;
    }

    /**
     * Removes the cached composer instance
     */
    public function resetPixie()
    {
        $this->pixie = null;
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }

    /**
     * Initializes all the composer commands.
     */
    protected function getDefaultCommands()
    {
        $commands = array_merge(parent::getDefaultCommands(), array(
            new Command\SyncCommand(),
            new Command\SiteCommand(),
        ));

        return $commands;
    }

    /**
     * {@inheritDoc}
     */
    public function getLongVersion()
    {
        if (Pixie::BRANCH_ALIAS_VERSION) {
            return sprintf(
                '<info>%s</info> version <comment>%s (%s)</comment> %s',
                $this->getName(),
                Pixie::BRANCH_ALIAS_VERSION,
                $this->getVersion(),
                Pixie::RELEASE_DATE
            );
        }

        return parent::getLongVersion() . ' ' . Pixie::RELEASE_DATE;
    }
}
