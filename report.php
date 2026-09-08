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
 * Teacher review screen for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Teacher review screen for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);              // Course-module id.
$attemptid = optional_param('attempt', 0, PARAM_INT); // Present => detail mode.
$action = optional_param('action', '', PARAM_ALPHA);  // Setflag | clearflag.

$cm = get_coursemodule_from_id('authorcheck', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('authorcheck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/authorcheck:review', $context);

$baseurl = new moodle_url('/mod/authorcheck/report.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

// Handle a flag override (POST + sesskey).
if ($action && $attemptid && confirm_sesskey()) {
    $flagged = ($action === 'setflag') ? 1 : 0;
    $DB->update_record('authorcheck_attempts', (object) [
        'id'           => $attemptid,
        'flagged'      => $flagged,
        'status'       => \mod_authorcheck\local\attempt_storage::STATUS_REVIEWED,
        'timemodified' => time(),
    ]);
    redirect(
        new moodle_url($baseurl, ['attempt' => $attemptid]),
        get_string('flagupdated', 'mod_authorcheck'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

if ($attemptid) {
    // ...======================= DETAIL MODE ===================================
    $attempt = $DB->get_record(
        'authorcheck_attempts',
        ['id' => $attemptid, 'authorcheckid' => $instance->id],
        '*',
        MUST_EXIST
    );
    $student = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);

    echo $OUTPUT->heading(get_string('reviewfor', 'mod_authorcheck', fullname($student)));

    if (!empty($attempt->unprocessable)) {
        echo $OUTPUT->notification(
            get_string('unprocessablenote', 'mod_authorcheck'),
            \core\output\notification::NOTIFY_WARNING
        );
    } else if (!empty($attempt->nosubmission)) {
        echo $OUTPUT->notification(
            get_string('nosubmissionnote', 'mod_authorcheck'),
            \core\output\notification::NOTIFY_WARNING
        );
    }

    // Summary line.
    $flagtext = $attempt->flagged
        ? get_string('flagged', 'mod_authorcheck')
        : get_string('notflagged', 'mod_authorcheck');
    echo html_writer::tag(
        'p',
        get_string('objscore', 'mod_authorcheck') . ': '
        . (int) $attempt->score . ' / ' . (int) $attempt->maxscore
        . ' — ' . html_writer::tag('strong', $flagtext)
    );

    // Flag override button (toggles based on current state).
    $toggleaction = $attempt->flagged ? 'clearflag' : 'setflag';
    $togglelabel = $attempt->flagged
        ? get_string('clearflag', 'mod_authorcheck')
        : get_string('setflag', 'mod_authorcheck');
    $toggleurl = new moodle_url($baseurl, [
        'attempt' => $attemptid,
        'action'  => $toggleaction,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($toggleurl, $togglelabel, 'post');

    // Questions with keys and the student's responses.
    $storage = new \mod_authorcheck\local\attempt_storage();
    $questions = $storage->get_questions($attemptid);

    $responses = $DB->get_records('authorcheck_responses', ['attemptid' => $attemptid]);
    $respbyq = [];
    foreach ($responses as $r) {
        $respbyq[(int) $r->questionid] = $r;
    }

    echo html_writer::start_tag('ol');
    foreach ($questions as $q) {
        $r = $respbyq[$q['id']] ?? null;

        echo html_writer::start_tag('li', ['style' => 'margin-bottom:1.2em;']);
        echo html_writer::tag(
            'div',
            html_writer::tag('strong', format_string($q['question']))
            . ' ' . html_writer::tag('em', '(' . s($q['type']) . ')')
        );

        $key = ($q['answer'] === null)
            ? get_string('notgraded', 'mod_authorcheck')
            : s($q['answer']);
        echo html_writer::tag(
            'div',
            get_string('answerkey', 'mod_authorcheck') . ': ' . $key
        );

        $resp = ($r && $r->response !== null && $r->response !== '') ? s($r->response) : '—';
        echo html_writer::tag(
            'div',
            get_string('studentresponse', 'mod_authorcheck') . ': ' . $resp
        );

        if ($r !== null && $r->iscorrect !== null) {
            $mark = $r->iscorrect
                ? get_string('correct', 'mod_authorcheck')
                : get_string('incorrect', 'mod_authorcheck');
            echo html_writer::tag('div', html_writer::tag('strong', $mark));
        }
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ol');

    echo html_writer::link($baseurl, get_string('backtolist', 'mod_authorcheck'));
} else {
    // ...======================= LIST MODE =====================================
    echo $OUTPUT->heading(get_string('reviewheading', 'mod_authorcheck'));

    // Only genuine students (users with the attempt capability) — never
    // admins/teachers who might hold a stray attempt.
    $students = get_enrolled_users($context, 'mod/authorcheck:attempt', 0, 'u.id');
    $studentids = array_keys($students);

    $attempts = [];
    if (!empty($studentids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);
        $params = array_merge(['acid' => $instance->id], $inparams);
        $attempts = $DB->get_records_select(
            'authorcheck_attempts',
            "authorcheckid = :acid AND userid $insql",
            $params,
            'flagged DESC, timemodified DESC'
        );
    }

    if (empty($attempts)) {
        echo $OUTPUT->notification(
            get_string('nosubmissions', 'mod_authorcheck'),
            \core\output\notification::NOTIFY_INFO
        );
    } else {
        $table = new html_table();
        $table->head = [
            get_string('student', 'mod_authorcheck'),
            get_string('objscore', 'mod_authorcheck'),
            get_string('flag', 'mod_authorcheck'),
            get_string('submitted', 'mod_authorcheck'),
            '',
        ];

        foreach ($attempts as $a) {
            $student = $DB->get_record('user', ['id' => $a->userid], '*', MUST_EXIST);

            if (!empty($a->unprocessable)) {
                $score = html_writer::tag('em', get_string('unprocessablelabel', 'mod_authorcheck'));
            } else if (!empty($a->nosubmission)) {
                $score = html_writer::tag('em', get_string('nosubmissionlabel', 'mod_authorcheck'));
            } else {
                $score = (int) $a->score . ' / ' . (int) $a->maxscore;
            }

            $flag = $a->flagged
                ? html_writer::tag(
                    'span',
                    get_string('flagged', 'mod_authorcheck'),
                    ['style' => 'color:#b00;font-weight:bold;']
                )
                : get_string('notflagged', 'mod_authorcheck');

            $submitted = $a->timemodified ? userdate($a->timemodified) : '—';

            $link = html_writer::link(
                new moodle_url($baseurl, ['attempt' => $a->id]),
                get_string('reviewlink', 'mod_authorcheck')
            );

            $table->data[] = [fullname($student), $score, $flag, $submitted, $link];
        }

        echo html_writer::table($table);
    }
}

echo $OUTPUT->footer();
