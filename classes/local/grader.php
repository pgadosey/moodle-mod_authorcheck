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
 * Objective-answer grading for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\local;

/**
 * Objective-answer grader.
 */
class grader {
    /**
     * Grade a single response.
     *
     * @param string $qtype One of mcq|truefalse|fillin|open.
     * @param string|null $answerkey The stored correct answer (null for open).
     * @param string|null $response The student's answer.
     * @return int|null 1 = correct, 0 = incorrect, null = not auto-graded (open/missing).
     */
    public static function grade(string $qtype, ?string $answerkey, ?string $response): ?int {
        // Open questions are never auto-graded.
        if ($qtype === 'open') {
            return null;
        }
        // Can't grade without both a key and a response.
        if ($answerkey === null || $response === null || trim($response) === '') {
            return 0;
        }

        $key = self::normalise($answerkey);
        $ans = self::normalise($response);

        return ($key === $ans) ? 1 : 0;
    }

    /**
     * Normalise a string for lenient comparison: trim, lowercase, collapse
     * internal whitespace. Enough to forgive spacing/case differences without
     * being so loose that wrong answers slip through.
     *
     * @param string $value
     * @return string
     */
    protected static function normalise(string $value): string {
        $value = \core_text::strtolower(trim($value));
        // Collapse any run of whitespace to a single space.
        $value = preg_replace('/\s+/u', ' ', $value);
        return $value;
    }
}
