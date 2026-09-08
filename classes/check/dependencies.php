<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU GPL v3 or later
 */
namespace mod_authorcheck\check;

use core\check\check;
use core\check\result;

/**
 * Checks that pdftotext and pandoc are available on the server.
 */
class dependencies extends check {

    public function get_name(): string {
        return get_string('check:dependencies', 'mod_authorcheck');
    }

    public function get_result(): result {
        $missing = [];
        foreach (['pdftotext', 'pandoc'] as $cmd) {
            if (!self::command_exists($cmd)) {
                $missing[] = $cmd;
            }
        }

        if (empty($missing)) {
            return new result(result::OK,
                get_string('check:dependencies:ok', 'mod_authorcheck'), '');
        }

        return new result(result::ERROR,
            get_string('check:dependencies:missing', 'mod_authorcheck'),
            get_string('check:dependencies:missingdetails', 'mod_authorcheck',
                implode(', ', $missing)));
    }

    /**
     * Is a command available on the PATH? Uses exec (as the extractor does).
     *
     * @param string $cmd
     * @return bool
     */
    protected static function command_exists(string $cmd): bool {
        $output = [];
        $rc = 0;
        @exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null', $output, $rc);
        return $rc === 0 && !empty($output);
    }
}
