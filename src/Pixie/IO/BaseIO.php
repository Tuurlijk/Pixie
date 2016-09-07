<?php

/**
 * Copyright notice
 *
 * ⓒ 2016 ℳichiel ℛoos <michiel@maxserv.com>
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is free
 * software; you can redistribut it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation;
 * either version 2 of the License, or (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 */

namespace MaxServ\Pixie\IO;

use MaxServ\Pixie\Config;
use MaxServ\Pixie\Util\ProcessExecutor;

abstract class BaseIO implements IOInterface
{
    protected $authentications = array();

    /**
     * {@inheritDoc}
     */
    public function getAuthentications()
    {
        return $this->authentications;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAuthentication($repositoryName)
    {
        return isset($this->authentications[$repositoryName]);
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthentication($repositoryName)
    {
        if (isset($this->authentications[$repositoryName])) {
            return $this->authentications[$repositoryName];
        }

        return array('username' => null, 'password' => null);
    }

    /**
     * {@inheritDoc}
     */
    public function setAuthentication($repositoryName, $username, $password = null)
    {
        $this->authentications[$repositoryName] = array('username' => $username, 'password' => $password);
    }

    /**
     * Check for overwrite and set the authentication information for the repository.
     *
     * @param string $repositoryName The unique name of repository
     * @param string $username       The username
     * @param string $password       The password
     */
    protected function checkAndSetAuthentication($repositoryName, $username, $password = null)
    {
        if ($this->hasAuthentication($repositoryName)) {
            $auth = $this->getAuthentication($repositoryName);
            if ($auth['username'] === $username && $auth['password'] === $password) {
                return;
            }

            $this->writeError(
                sprintf(
                    "<warning>Warning: You should avoid overwriting already defined auth settings for %s.</warning>",
                    $repositoryName
                )
            );
        }
        $this->setAuthentication($repositoryName, $username, $password);
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(Config $config)
    {
        $bitbucketOauth = $config->get('bitbucket-oauth') ?: array();
        $githubOauth = $config->get('github-oauth') ?: array();
        $gitlabOauth = $config->get('gitlab-oauth') ?: array();
        $httpBasic = $config->get('http-basic') ?: array();

        // reload oauth tokens from config if available

        foreach ($bitbucketOauth as $domain => $cred) {
            $this->checkAndSetAuthentication($domain, $cred['consumer-key'], $cred['consumer-secret']);
        }

        foreach ($githubOauth as $domain => $token) {
            if (!preg_match('{^[a-z0-9]+$}', $token)) {
                throw new \UnexpectedValueException('Your github oauth token for '.$domain.' contains invalid characters: "'.$token.'"');
            }
            $this->checkAndSetAuthentication($domain, $token, 'x-oauth-basic');
        }

        foreach ($gitlabOauth as $domain => $token) {
            $this->checkAndSetAuthentication($domain, $token, 'oauth2');
        }

        // reload http basic credentials from config if available
        foreach ($httpBasic as $domain => $cred) {
            $this->checkAndSetAuthentication($domain, $cred['username'], $cred['password']);
        }

        // setup process timeout
        ProcessExecutor::setTimeout((int) $config->get('process-timeout'));
    }
}
