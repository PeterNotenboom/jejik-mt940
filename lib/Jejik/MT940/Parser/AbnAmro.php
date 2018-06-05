<?php

/*
 * This file is part of the Jejik\MT940 library
 *
 * Copyright (c) 2012 Sander Marechal <s.marechal@jejik.com>
 * Licensed under the MIT license
 *
 * For the full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 */

namespace Jejik\MT940\Parser;

/**
 * Parser for ABN-AMRO documents
 *
 * @author Sander Marechal <s.marechal@jejik.com>
 */
class AbnAmro extends AbstractParser {
    /**
     * Test if the document is an ABN-AMRO document
     *
     * @param string $text
     * @return bool
     */
    public function accept($text) {
        return substr($text, 0, 6) === 'ABNANL';
    }

    /**
     * Get the contra account from a transaction
     *
     * @param array $lines The transaction text at offset 0 and the description at offset 1
     * @return string|null
     */
    protected function contraAccountNumber(array $lines) {
        if (!isset($lines[1])) {
            return null;
        }

        if (preg_match('/^([0-9.]{11,14}) /', $lines[1], $match)) {
            return str_replace('.', '', $match[1]);
        }

        if (preg_match('/^GIRO([0-9 ]{9}) /', $lines[1], $match)) {
            return trim($match[1]);
        }

        if (preg_match("/\/IBAN\/(\w+)\//", $lines[1], $match)) {
            return trim($match[1]);
        }

        $extra_description = $this->extraDescriptionLine($lines[1]);
        if(isset($extra_description['account_number'])) {
            return $extra_description['account_number'];
        }

        return null;
    }

    /**
     * Get the contra account holder name from a transaction
     *
     * There is only a countra account name if there is a contra account number
     * The name immediately follows the number in the first 32 characters of the first line
     * If the charaters up to the 32nd after the number are blank, the name is found in
     * the rest of the line.
     *
     * @param array $lines The transaction text at offset 0 and the description at offset 1
     * @return string|null
     */
    protected function contraAccountName(array $lines) {
        if (!isset($lines[1])) {
            return null;
        }

        $line = strstr($lines[1], "\r\n", true) ?: $lines[1];
        $offset = 0;

        if (preg_match('/^([0-9.]{11,14}) (.*)$/', $line, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[2][1];
        }

        if (preg_match('/^GIRO([0-9 ]{9}) (.*)$/', $line, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[2][1];
        }

        if($offset == 0) {
            // No offset found yet. let's check
            $line = str_replace(array("\n","\r"), array('',''),$lines[1]);
            if (preg_match("/\/NAME\/([a-zA-Z0-9\s.]+)\//", $line, $match)) {
                return $match[1];
            }

            $extra_description = $this->extraDescriptionLine($lines[1]);
            if(isset($extra_description['account_name'])) {
                return $extra_description['account_name'];
            }

        }

        // No account number found, so no name either
        if (!$offset) {
            return null;
        }

        // Name in the first 32 characters
        if ($name = trim(substr($line, $offset, 32 - $offset))) {
            return $name;
        }

        // Name in the second 32 characters
        if ($name = trim(substr($line, 32, 32))) {
            return $name;
        }

        return null;
    }

    protected function description($description) {

        $extra_description = $this->extraDescriptionLine($description);
        if(isset($extra_description['description'])) {
            return $extra_description['description'];
        }

        if(preg_match("/\/REMI\/([a-zA-Z0-9\s.-].+)\//", $single_line_description, $match)) {
            $match = explode('/',$match[1]);
            return $match[0];
        }

        return preg_replace('/>2[0-7]{1}/', '', $single_line_description);
    }

    private function extraDescriptionLine($line) {

        $result = array();
        $single_line_description = str_replace(array("\n","\r"), array('',''), $line);

        if (preg_match("/OMSCHRIJVING:\s/", $single_line_description, $match))
        {

            $tmp = str_replace("\r", "", $line);
            $tmp = str_replace("\t", "", $tmp);
            $tmp = explode("\n", $tmp);

            $exploded_per_variable = array();
            foreach($tmp AS $tmp2) {
                $tmp2 = explode("  ", $tmp2);
                foreach($tmp2 AS $tmp3) {
                    if(trim($tmp3) != '') {
                        $exploded_per_variable[] = trim($tmp3);
                    }
                }
            }

            foreach($exploded_per_variable AS $var) {
                if( substr($var, 0, 6) == 'NAAM: ') {
                    $result['account_name'] = substr($var, 6, strlen($var));
                }
                elseif( substr($var, 0, 44) == 'SEPA INCASSO ALGEMEEN DOORLOPEND INCASSANT: ') {
                    $result['account_incasso'] = substr($var, 44, strlen($var));
                }
                elseif( substr($var, 0, 12) == 'MACHTIGING: ') {
                    $result['machtiging'] = substr($var, 12, strlen($var));
                }
                elseif( substr($var, 0, 14) == 'OMSCHRIJVING: ') {
                    $result['description'] = trim(substr($var, 14, strlen($var)));
                }
                elseif( substr($var, 0, 6) == 'IBAN: ') {
                    $result['account_number'] = substr($var, 6, strlen($var));
                }
                elseif( substr($var, 0, 9) == 'KENMERK: ') {
                    $result['kenmerk'] = substr($var, 9, strlen($var));
                }
            }

        }

        return $result;
    }

}
