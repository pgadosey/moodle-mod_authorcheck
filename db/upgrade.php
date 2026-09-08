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
 * Upgrade steps for mod_authorcheck.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the mod_authorcheck database.
 *
 * @param int $oldversion The version we are upgrading FROM.
 * @return bool
 */
function xmldb_authorcheck_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Add release-control fields to the activity table.
    if ($oldversion < 2026082001) {
        $table = new xmldb_table('authorcheck');

        $releasetime = new xmldb_field(
            'releasetime',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'introformat'
        );
        if (!$dbman->field_exists($table, $releasetime)) {
            $dbman->add_field($table, $releasetime);
        }

        $manualreleased = new xmldb_field(
            'manualreleased',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'releasetime'
        );
        if (!$dbman->field_exists($table, $manualreleased)) {
            $dbman->add_field($table, $manualreleased);
        }

        upgrade_mod_savepoint(true, 2026082001, 'authorcheck');
    }

    // Add attempt-limit fields.
    if ($oldversion < 2026082002) {
        // Maxattempts on the activity.
        $table = new xmldb_table('authorcheck');
        $maxattempts = new xmldb_field(
            'maxattempts',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'manualreleased'
        );
        if (!$dbman->field_exists($table, $maxattempts)) {
            $dbman->add_field($table, $maxattempts);
        }

        // Attempts used on each student's attempt row.
        $attemptstable = new xmldb_table('authorcheck_attempts');
        $attemptsused = new xmldb_field(
            'attemptsused',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'status'
        );
        if (!$dbman->field_exists($attemptstable, $attemptsused)) {
            $dbman->add_field($attemptstable, $attemptsused);
        }

        upgrade_mod_savepoint(true, 2026082002, 'authorcheck');
    }

    // Add the flag threshold (percent) to the activity.
    if ($oldversion < 2026082003) {
        $table = new xmldb_table('authorcheck');
        $flagthreshold = new xmldb_field(
            'flagthreshold',
            XMLDB_TYPE_INTEGER,
            '3',
            null,
            XMLDB_NOTNULL,
            null,
            '50',
            'maxattempts'
        );
        if (!$dbman->field_exists($table, $flagthreshold)) {
            $dbman->add_field($table, $flagthreshold);
        }

        upgrade_mod_savepoint(true, 2026082003, 'authorcheck');
    }

    // Add the number-of-questions setting to the activity.
    if ($oldversion < 2026082004) {
        $table = new xmldb_table('authorcheck');
        $numquestions = new xmldb_field(
            'numquestions',
            XMLDB_TYPE_INTEGER,
            '3',
            null,
            XMLDB_NOTNULL,
            null,
            '6',
            'flagthreshold'
        );
        if (!$dbman->field_exists($table, $numquestions)) {
            $dbman->add_field($table, $numquestions);
        }

        upgrade_mod_savepoint(true, 2026082004, 'authorcheck');
    }

    // Add the submission deadline to the activity.
    if ($oldversion < 2026082005) {
        $table = new xmldb_table('authorcheck');
        $deadline = new xmldb_field(
            'submissiondeadline',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'numquestions'
        );
        if (!$dbman->field_exists($table, $deadline)) {
            $dbman->add_field($table, $deadline);
        }

        upgrade_mod_savepoint(true, 2026082005, 'authorcheck');
    }

    // Add the linked assignment id to the activity.
    if ($oldversion < 2026082006) {
        $table = new xmldb_table('authorcheck');
        $assignmentid = new xmldb_field(
            'assignmentid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'submissiondeadline'
        );
        if (!$dbman->field_exists($table, $assignmentid)) {
            $dbman->add_field($table, $assignmentid);
        }

        upgrade_mod_savepoint(true, 2026082006, 'authorcheck');
    }

    // Add the no-submission marker to attempts.
    if ($oldversion < 2026082007) {
        $table = new xmldb_table('authorcheck_attempts');
        $nosub = new xmldb_field(
            'nosubmission',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'flagged'
        );
        if (!$dbman->field_exists($table, $nosub)) {
            $dbman->add_field($table, $nosub);
        }

        upgrade_mod_savepoint(true, 2026082007, 'authorcheck');
    }

    // Add the unprocessable-submission marker to attempts.
    if ($oldversion < 2026082009) {
        $table = new xmldb_table('authorcheck_attempts');
        $field = new xmldb_field(
            'unprocessable',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'nosubmission'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026082009, 'authorcheck');
    }

    // Add the answer time limit and per-attempt start time.
    if ($oldversion < 2026082011) {
        $table = new xmldb_table('authorcheck');
        $timelimit = new xmldb_field(
            'timelimit',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'assignmentid'
        );
        if (!$dbman->field_exists($table, $timelimit)) {
            $dbman->add_field($table, $timelimit);
        }

        $attempts = new xmldb_table('authorcheck_attempts');
        $timestarted = new xmldb_field(
            'timestarted',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'unprocessable'
        );
        if (!$dbman->field_exists($attempts, $timestarted)) {
            $dbman->add_field($attempts, $timestarted);
        }

        upgrade_mod_savepoint(true, 2026082011, 'authorcheck');
    }

    // Add the selectable question types to the activity.
    if ($oldversion < 2026082012) {
        $table = new xmldb_table('authorcheck');
        $field = new xmldb_field(
            'qtypes',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            XMLDB_NOTNULL,
            null,
            'mcq,truefalse,fillin,open',
            'timelimit'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026082012, 'authorcheck');
    }

    return true;
}
