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
 * Backup task for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/authorcheck/backup/moodle2/backup_authorcheck_stepslib.php');

/**
 * Backup task that provides the steps to back up an authorcheck activity.
 */
class backup_authorcheck_activity_task extends backup_activity_task {
    /**
     * Define plugin-specific settings.
     */
    protected function define_my_settings() {
        // No activity-specific settings.
    }

    /**
     * Define the backup steps.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_authorcheck_activity_structure_step(
            'authorcheck_structure',
            'authorcheck.xml'
        ));
    }

    /**
     * Encode links to this activity so they can be restored/decoded later.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the activity index within a course.
        $content = preg_replace(
            "/(" . $base . "\/mod\/authorcheck\/index.php\?id=)([0-9]+)/",
            '$@AUTHORCHECKINDEX*$2@$',
            $content
        );

        // Link to a specific activity view.
        $content = preg_replace(
            "/(" . $base . "\/mod\/authorcheck\/view.php\?id=)([0-9]+)/",
            '$@AUTHORCHECKVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
