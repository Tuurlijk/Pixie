<?php
namespace MaxServ\Pixie;

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

use MaxServ\Pixie\IO\IOInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Creates a configured instance of composer.
 *
 * @author Michiel Roos <michiel@maxserv.com>
 */
class Factory
{
    /**
     * @param  IOInterface|null $io
     * @return Config
     */
    public static function createConfig(IOInterface $io = null, $cwd = null)
    {
        $cwd = $cwd ?: getcwd();

        return new Config(true, $cwd);
    }

    /**
     * Get the name of the configuration file
     *
     * @return string
     */
    public static function getPixieFile()
    {
        $pixieFile = '';
        if (trim(getenv('PIXIE'))) {
            $pixieFile = trim(getenv('PIXIE'));
        } elseif (file_exists('./pixie.yml') && is_file('./pixie.yml')) {
            $pixieFile = './pixie.yml';
        } elseif (file_exists('./Configuration/pixie.yml') && is_file('./Configuration/pixie.yml')) {
            $pixieFile = './Configuration/pixie.yml';
        }
        return $pixieFile;
    }

    /**
     * @return array
     */
    public static function createAdditionalStyles()
    {
        return array(
            'highlight' => new OutputFormatterStyle('red'),
            'warning' => new OutputFormatterStyle('black', 'yellow'),
        );
    }

    /**
     * Creates a Pixie instance
     *
     * @param  IOInterface $io IO instance
     * @param  array|string|null $localConfig either a configuration array or a filename to read from, if null it will
     *                                                   read from the default filename
     * @throws \UnexpectedValueException
     * @return Pixie
     */
    public function createPixie(IOInterface $io, $localConfig = null)
    {
        // load Pixie configuration
        if (null === $localConfig) {
            $localConfig = static::getPixieFile();
        }

        $cwd = getcwd();

        if (is_string($localConfig)) {

            if (!file_exists($localConfig)) {
                if ($localConfig === './pixie.yml' || $localConfig === 'pixie.yml') {
                    $message = 'Pixie could not find a composer.json file in ' . $cwd;
                } else {
                    $message = 'Pixie could not find the config file: ' . $localConfig;
                }
                $instructions = 'To initialize a project, please create a pixie.yml file as described in the https://pixie.org/ "Getting Started" section';
                throw new \InvalidArgumentException($message . PHP_EOL . $instructions);
            }
        }

        $configuration = $this->parseConfigurationFile($localConfig);

        // Load config and override with local config/auth config
        $config = static::createConfig($io, $cwd);
        $config->merge($configuration);

        // initialize pixie
        $pixie = new Pixie();
        $pixie->setConfig($config);

        return $pixie;
    }

    /**
     * @param  IOInterface $io IO instance
     * @param  mixed $config either a configuration array or a filename to read from, if null it will read from
     *                                     the default filename
     * @return Pixie
     * @throws \UnexpectedValueException
     */
    public static function create(IOInterface $io, $config = null)
    {
        $factory = new static();

        return $factory->createPixie($io, $config);
    }

    /**
     * Check if the configuration file exists and if the Yaml parser is
     * available
     *
     * @since 1.0.0
     *
     * @param $configurationFile
     *
     * @return array|null
     */
    protected function parseConfigurationFile($configurationFile)
    {
        $configuration = null;
        if (!empty($configurationFile)
            && is_file($configurationFile)
            && is_callable(array(
                'Symfony\\Component\\Yaml\\Yaml',
                'parse'
            ))
        ) {
            $configuration = Yaml::parse(file_get_contents($configurationFile));
        }

        return $configuration;
    }
}
