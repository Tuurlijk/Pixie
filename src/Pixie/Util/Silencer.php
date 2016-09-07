<?php
namespace MaxServ\Pixie\Util;
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
 * Temporarily suppress PHP error reporting, usually warnings and below.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class Silencer
{
    /**
     * @var int[] Unpop stack
     */
    private static $stack = array();

    /**
     * Suppresses given mask or errors.
     *
     * @param  int|null $mask Error levels to suppress, default value NULL indicates all warnings and below.
     * @return int      The old error reporting level.
     */
    public static function suppress($mask = null)
    {
        if (!isset($mask)) {
            $mask = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED | E_STRICT;
        }
        $old = error_reporting();
        array_push(self::$stack, $old);
        error_reporting($old & ~$mask);

        return $old;
    }

    /**
     * Restores a single state.
     */
    public static function restore()
    {
        if (!empty(self::$stack)) {
            error_reporting(array_pop(self::$stack));
        }
    }

    /**
     * Calls a specified function while silencing warnings and below.
     *
     * Future improvement: when PHP requirements are raised add Callable type hint (5.4) and variadic parameters (5.6)
     *
     * @param  callable $callable Function to execute.
     * @throws \Exception Any exceptions from the callback are rethrown.
     * @return mixed      Return value of the callback.
     */
    public static function call($callable /*, ...$parameters */)
    {
        try {
            self::suppress();
            $result = call_user_func_array($callable, array_slice(func_get_args(), 1));
            self::restore();

            return $result;
        } catch (\Exception $e) {
            // Use a finally block for this when requirements are raised to PHP 5.5
            self::restore();
            throw $e;
        }
    }
}
