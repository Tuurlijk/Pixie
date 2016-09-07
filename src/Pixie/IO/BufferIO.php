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

use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class BufferIO extends ConsoleIO
{
    /**
     * @param string                        $input
     * @param int                           $verbosity
     * @param OutputFormatterInterface|null $formatter
     */
    public function __construct($input = '', $verbosity = StreamOutput::VERBOSITY_NORMAL, OutputFormatterInterface $formatter = null)
    {
        $input = new StringInput($input);
        $input->setInteractive(false);

        $output = new StreamOutput(fopen('php://memory', 'rw'), $verbosity, $formatter ? $formatter->isDecorated() : false, $formatter);

        parent::__construct($input, $output, new HelperSet(array()));
    }

    public function getOutput()
    {
        fseek($this->output->getStream(), 0);

        $output = stream_get_contents($this->output->getStream());

        $output = preg_replace_callback("{(?<=^|\n|\x08)(.+?)(\x08+)}", function ($matches) {
            $pre = strip_tags($matches[1]);

            if (strlen($pre) === strlen($matches[2])) {
                return '';
            }

            // TODO reverse parse the string, skipping span tags and \033\[([0-9;]+)m(.*?)\033\[0m style blobs
            return rtrim($matches[1])."\n";
        }, $output);

        return $output;
    }
}
