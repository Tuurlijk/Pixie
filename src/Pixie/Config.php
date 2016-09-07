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

/**
 * Class Config
 * @package MaxServ\Pixie
 */
class Config
{
    /**
     *
     * @var array
     */
    public static $defaultConfig = array(
        'username' => 'vagrant',
        'hostname' => 'local.typo3.org',
        'root' => '/var/www'
    );

    /**
     *
     * @var array
     */
    public static $defaultSites = array();

    /**
     *
     * @var array
     */
    private $config;

    /**
     *
     * @var
     */
    private $baseDir;

    /**
     *
     * @var array
     */
    private $sites;

    /**
     *
     * @var
     */
    private $configSource;

    /**
     * @param bool $useEnvironment Use COMPOSER_ environment variables to replace config settings
     */
    public function __construct($useEnvironment = true, $baseDir = null)
    {
        // load defaults
        $this->config = static::$defaultConfig;
        $this->sites = static::$defaultSites;
    }

    /**
     * @return mixed
     */
    public function getConfigSource()
    {
        return $this->configSource;
    }

    /**
     * Merges new config values with the existing ones (overriding)
     *
     * @param array $config
     */
    public function merge($config)
    {
        // override defaults with given config
        if (!empty($config['sites']) && is_array($config['sites'])) {
            foreach ($config['sites'] as $name => $configuration) {
                $this->sites[$name] = $configuration;
            }
        }
    }

    /**
     * @return array
     */
    public function getSites()
    {
        return $this->sites;
    }

    /**
     * Returns a setting
     *
     * @param  string $key
     * @param  int $flags Options (see class constants)
     * @throws \RuntimeException
     * @return mixed
     */
    public function get($key, $flags = 0)
    {
        switch ($key) {
            case 'vendor-dir':
            case 'bin-dir':
            case 'process-timeout':
            case 'data-dir':
            case 'cache-dir':
            case 'cache-files-dir':
            case 'cache-repo-dir':
            case 'cache-vcs-dir':
            case 'cafile':
            case 'capath':
                // convert foo-bar to COMPOSER_FOO_BAR and check if it exists since it overrides the local config
                $env = 'COMPOSER_' . strtoupper(strtr($key, '-', '_'));

                $val = rtrim($this->process($this->getComposerEnv($env) ?: $this->config[$key], $flags), '/\\');
                $val = preg_replace('#^(\$HOME|~)(/|$)#', rtrim(getenv('HOME') ?: getenv('USERPROFILE'), '/\\') . '/',
                    $val);

                if (substr($key, -4) !== '-dir') {
                    return $val;
                }

                return (($flags & self::RELATIVE_PATHS) == self::RELATIVE_PATHS) ? $val : $this->realpath($val);

            case 'cache-ttl':
                return (int)$this->config[$key];

            case 'cache-files-ttl':
                if (isset($this->config[$key])) {
                    return (int)$this->config[$key];
                }

                return (int)$this->config['cache-ttl'];

            case 'home':
                $val = preg_replace('#^(\$HOME|~)(/|$)#', rtrim(getenv('HOME') ?: getenv('USERPROFILE'), '/\\') . '/',
                    $this->config[$key]);

                return rtrim($this->process($val, $flags), '/\\');

            default:
                if (!isset($this->config[$key])) {
                    return null;
                }

                return $this->process($this->config[$key], $flags);
        }
    }

    /**
     * @param int $flags
     * @return array
     */
    public function all($flags = 0)
    {
        $all = array(
            'sites' => $this->getSites(),
        );
        foreach (array_keys($this->config) as $key) {
            $all['config'][$key] = $this->get($key, $flags);
        }

        return $all;
    }

    /**
     * @return array
     */
    public function raw()
    {
        return array(
            'sites' => $this->getSites(),
            'config' => $this->config,
        );
    }

    /**
     * Checks whether a setting exists
     *
     * @param  string $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * Replaces {$refs} inside a config string
     *
     * @param  string $value a config string that can contain {$refs-to-other-config}
     * @param  int $flags Options (see class constants)
     * @return string
     */
    private function process($value, $flags)
    {
        $config = $this;

        if (!is_string($value)) {
            return $value;
        }

        return preg_replace_callback('#\{\$(.+)\}#', function ($match) use ($config, $flags) {
            return $config->get($match[1], $flags);
        }, $value);
    }

    /**
     * Turns relative paths in absolute paths without realpath()
     *
     * Since the dirs might not exist yet we can not call realpath or it will fail.
     *
     * @param  string $path
     * @return string
     */
    private function realpath($path)
    {
        if (preg_match('{^(?:/|[a-z]:|[a-z0-9.]+://)}i', $path)) {
            return $path;
        }

        return $this->baseDir . '/' . $path;
    }
}
