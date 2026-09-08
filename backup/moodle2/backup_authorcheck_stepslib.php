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
 * Backup structure step for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to back up one authorcheck activity.
 */
class backup_authorcheck_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup structure.
     */
    protected function define_structure() {

        // Whether user data is being backed up.
        $userinfo = $this->get_setting_value('userinfo');

        // The activity instance and its settings.
        $authorcheck = new backup_nested_element('authorcheck', ['id'], [
            'name', 'intro', 'introformat', 'releasetime', 'manualreleased',
            'maxattempts', 'flagthreshold', 'numquestions', 'submissiondeadline',
            'assignmentid', 'timecreated', 'timemodified',
        ]);

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid', 'status', 'attemptsused', 'score', 'maxscore',
            'flagged', 'nosubmission', 'unprocessable', 'timegenerated', 'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'qtype', 'questiontext', 'options', 'answerkey', 'sortorder',
        ]);

        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], [
            'questionid', 'response', 'score', 'iscorrect', 'timemodified',
        ]);

        // Build the tree.
        $authorcheck->add_child($attempts);
        $attempts->add_child($attempt);
        $attempt->add_child($questions);
        $questions->add_child($question);
        $attempt->add_child($responses);
        $responses->add_child($response);

        // The instance is always backed up.
        $authorcheck->set_source_table('authorcheck', ['id' => backup::VAR_ACTIVITYID]);

        // User data only when requested.
        if ($userinfo) {
            $attempt->set_source_table(
                'authorcheck_attempts',
                ['authorcheckid' => backup::VAR_PARENTID]
            );
            $question->set_source_table(
                'authorcheck_questions',
                ['attemptid' => backup::VAR_PARENTID]
            );
            $response->set_source_table(
                'authorcheck_responses',
                ['attemptid' => backup::VAR_PARENTID]
            );
        }

        // Map the userid so it can be remapped on restore.
        $attempt->annotate_ids('user', 'userid');

        return $this->prepare_activity_structure($authorcheck);
    }
}
