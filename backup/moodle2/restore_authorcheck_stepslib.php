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
 * Restore structure step for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one authorcheck activity.
 */
class restore_authorcheck_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the restore structure.
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('authorcheck', '/activity/authorcheck');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'authorcheck_attempt',
                '/activity/authorcheck/attempts/attempt'
            );
            $paths[] = new restore_path_element(
                'authorcheck_question',
                '/activity/authorcheck/attempts/attempt/questions/question'
            );
            $paths[] = new restore_path_element(
                'authorcheck_response',
                '/activity/authorcheck/attempts/attempt/responses/response'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore an authorcheck instance record.
     */
    protected function process_authorcheck($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (!empty($data->releasetime)) {
            $data->releasetime = $this->apply_date_offset($data->releasetime);
        }
        if (!empty($data->submissiondeadline)) {
            $data->submissiondeadline = $this->apply_date_offset($data->submissiondeadline);
        }

        // Note: assignmentid points to the original assignment. On restore into
        // a different course it will be stale and the teacher must re-select the
        // linked assignment in the activity settings.

        $newitemid = $DB->insert_record('authorcheck', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore an attempt record.
     */
    protected function process_authorcheck_attempt($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->authorcheckid = $this->get_new_parentid('authorcheck');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if (!empty($data->timegenerated)) {
            $data->timegenerated = $this->apply_date_offset($data->timegenerated);
        }

        $newid = $DB->insert_record('authorcheck_attempts', $data);
        $this->set_mapping('authorcheck_attempt', $oldid, $newid);
    }

    /**
     * Restore a question record.
     */
    protected function process_authorcheck_question($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->attemptid = $this->get_new_parentid('authorcheck_attempt');

        $newid = $DB->insert_record('authorcheck_questions', $data);
        $this->set_mapping('authorcheck_question', $oldid, $newid);
    }

    /**
     * Restore a response record.
     */
    protected function process_authorcheck_response($data) {
        global $DB;

        $data = (object) $data;
        $data->attemptid = $this->get_new_parentid('authorcheck_attempt');
        // Remap to the newly-restored question id.
        $data->questionid = $this->get_mappingid('authorcheck_question', $data->questionid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('authorcheck_responses', $data);
    }
}
