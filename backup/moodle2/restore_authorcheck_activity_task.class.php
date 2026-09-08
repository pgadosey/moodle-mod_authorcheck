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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/authorcheck/backup/moodle2/restore_authorcheck_stepslib.php');

/**
 * Restore task that provides the steps to restore an authorcheck activity.
 */
class restore_authorcheck_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // No activity-specific settings.
    }

    protected function define_my_steps() {
        $this->add_step(new restore_authorcheck_activity_structure_step(
            'authorcheck_structure', 'authorcheck.xml'));
    }

    /**
     * Content areas whose embedded links need decoding.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('authorcheck', ['intro'], 'authorcheck');
        return $contents;
    }

    /**
     * Decode rules matching the encode_content_links patterns.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('AUTHORCHECKVIEWBYID',
            '/mod/authorcheck/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('AUTHORCHECKINDEX',
            '/mod/authorcheck/index.php?id=$1', 'course');
        return $rules;
    }

    public static function define_restore_log_rules() {
        return [];
    }

    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
