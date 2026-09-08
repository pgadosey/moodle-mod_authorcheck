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
 * Privacy provider for mod_authorcheck.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;


/**
 * Privacy provider for mod_authorcheck.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table('authorcheck_attempts', [
            'userid'        => 'privacy:metadata:authorcheck_attempts:userid',
            'status'        => 'privacy:metadata:authorcheck_attempts:status',
            'attemptsused'  => 'privacy:metadata:authorcheck_attempts:attemptsused',
            'score'         => 'privacy:metadata:authorcheck_attempts:score',
            'maxscore'      => 'privacy:metadata:authorcheck_attempts:maxscore',
            'flagged'       => 'privacy:metadata:authorcheck_attempts:flagged',
            'nosubmission'  => 'privacy:metadata:authorcheck_attempts:nosubmission',
            'unprocessable' => 'privacy:metadata:authorcheck_attempts:unprocessable',
            'timemodified'  => 'privacy:metadata:authorcheck_attempts:timemodified',
        ], 'privacy:metadata:authorcheck_attempts');

        $collection->add_database_table('authorcheck_questions', [
            'questiontext' => 'privacy:metadata:authorcheck_questions:questiontext',
            'options'      => 'privacy:metadata:authorcheck_questions:options',
            'answerkey'    => 'privacy:metadata:authorcheck_questions:answerkey',
        ], 'privacy:metadata:authorcheck_questions');

        $collection->add_database_table('authorcheck_responses', [
            'response'  => 'privacy:metadata:authorcheck_responses:response',
            'score'     => 'privacy:metadata:authorcheck_responses:score',
            'iscorrect' => 'privacy:metadata:authorcheck_responses:iscorrect',
        ], 'privacy:metadata:authorcheck_responses');

        // Submission text is sent to the AI subsystem to generate questions.
        $collection->add_subsystem_link(
            'core_ai',
            [],
            'privacy:metadata:core_ai'
        );

        return $collection;
    }

    /**
     * Contexts where the given user has data (module contexts with an attempt).
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {authorcheck_attempts} aa
                  JOIN {authorcheck} a ON a.id = aa.authorcheckid
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE aa.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'modname'  => 'authorcheck',
            'modlevel' => CONTEXT_MODULE,
            'userid'   => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Users who have data in the given context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT aa.userid
                  FROM {authorcheck_attempts} aa
                  JOIN {authorcheck} a ON a.id = aa.authorcheckid
                  JOIN {course_modules} cm ON cm.instance = a.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, [
            'modname' => 'authorcheck',
            'cmid'    => $context->instanceid,
        ]);
    }

    /**
     * Export all stored data for the approved contexts for one user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('authorcheck', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $attempt = $DB->get_record(
                'authorcheck_attempts',
                ['authorcheckid' => $cm->instance, 'userid' => $userid]
            );
            if (!$attempt) {
                continue;
            }

            $export = (object) [
                'status'        => $attempt->status,
                'attemptsused'  => $attempt->attemptsused,
                'score'         => $attempt->score,
                'maxscore'      => $attempt->maxscore,
                'flagged'       => transform::yesno($attempt->flagged),
                'nosubmission'  => transform::yesno($attempt->nosubmission),
                'unprocessable' => transform::yesno($attempt->unprocessable),
                'timemodified'  => $attempt->timemodified
                    ? transform::datetime($attempt->timemodified) : null,
                'questions'     => [],
            ];

            $questions = $DB->get_records(
                'authorcheck_questions',
                ['attemptid' => $attempt->id],
                'sortorder ASC'
            );
            $responses = $DB->get_records(
                'authorcheck_responses',
                ['attemptid' => $attempt->id]
            );

            $respbyq = [];
            foreach ($responses as $r) {
                $respbyq[(int) $r->questionid] = $r;
            }

            foreach ($questions as $q) {
                $r = $respbyq[(int) $q->id] ?? null;
                $export->questions[] = [
                    'type'      => $q->qtype,
                    'question'  => $q->questiontext,
                    'answerkey' => $q->answerkey,
                    'response'  => $r ? $r->response : null,
                    'iscorrect' => $r ? $r->iscorrect : null,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'mod_authorcheck')],
                $export
            );
        }
    }

    /**
     * Delete all users' data for a context (e.g. the activity is deleted).
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('authorcheck', $context->instanceid);
        if (!$cm) {
            return;
        }

        self::delete_attempts_for_users((int) $cm->instance, null);
    }

    /**
     * Delete one user's data across the approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('authorcheck', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_attempts_for_users((int) $cm->instance, [$userid]);
        }
    }

    /**
     * Delete several users' data within one context.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('authorcheck', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_attempts_for_users((int) $cm->instance, $userlist->get_userids());
    }

    /**
     * Delete attempts (and their questions/responses) for an activity.
     *
     * @param int $authorcheckid
     * @param array|null $userids Specific users, or null for all users.
     */
    protected static function delete_attempts_for_users(int $authorcheckid, ?array $userids) {
        global $DB;

        $where = 'authorcheckid = :acid';
        $params = ['acid' => $authorcheckid];

        if ($userids !== null) {
            if (empty($userids)) {
                return;
            }
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $where .= " AND userid $usersql";
            $params += $userparams;
        }

        $attemptids = $DB->get_fieldset_select('authorcheck_attempts', 'id', $where, $params);
        if (empty($attemptids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($attemptids);
        $DB->delete_records_select('authorcheck_responses', "attemptid $insql", $inparams);
        $DB->delete_records_select('authorcheck_questions', "attemptid $insql", $inparams);
        $DB->delete_records_select('authorcheck_attempts', $where, $params);
    }
}
