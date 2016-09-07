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

/**
 * IOInterface that is not interactive and never writes the output
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class NullIO extends BaseIO
{
    /**
     * {@inheritDoc}
     */
    public function isInteractive()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isVerbose()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isVeryVerbose()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isDecorated()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function write($messages, $newline = true, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function writeError($messages, $newline = true, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function overwrite($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function overwriteError($messages, $newline = true, $size = 80, $verbosity = self::NORMAL)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function ask($question, $default = null)
    {
        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function askConfirmation($question, $default = true)
    {
        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function askAndValidate($question, $validator, $attempts = false, $default = null)
    {
        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function askAndHideAnswer($question)
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function select($question, $choices, $default, $attempts = false, $errorMessage = 'Value "%s" is invalid', $multiselect = false)
    {
        return $default;
    }
}
