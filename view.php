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
 * Student view page for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Student view page for the Authorship Check activity.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT); // course-module id

$cm = get_coursemodule_from_id('authorcheck', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('authorcheck', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/authorcheck:view', $context);

$PAGE->set_url('/mod/authorcheck/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

// Reviewers get a link to the submissions report.
if (has_capability('mod/authorcheck:review', $context)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/mod/authorcheck/report.php', ['id' => $cm->id]),
            get_string('reviewsubmissions', 'mod_authorcheck')
        ),
        '',
        ['style' => 'margin-bottom:1em;']
    );
}

// Only users who can attempt (students) get an attempt and the answer flow.
// Teachers/others with view access are directed to the review screen instead.
if (!has_capability('mod/authorcheck:attempt', $context)) {
    echo $OUTPUT->notification(
        get_string('notastudent', 'mod_authorcheck'),
        \core\output\notification::NOTIFY_INFO
    );
    echo $OUTPUT->footer();
    exit;
}

$storage = new \mod_authorcheck\local\attempt_storage();
$attemptid = $storage->get_or_create_attempt($instance->id, $USER->id);
$released = \mod_authorcheck\local\release_gate::is_released($instance);

// =========================================================================
// PRE-RELEASE: status only. Students submit via the linked assignment.
// =========================================================================
if (!$released) {
    echo $OUTPUT->notification(
        get_string('awaitingrelease', 'mod_authorcheck'),
        \core\output\notification::NOTIFY_INFO
    );

    // Offer a link to the linked assignment, if one is set.
    if (!empty($instance->assignmentid)) {
        $assigncm = get_coursemodule_from_instance(
            'assign',
            $instance->assignmentid,
            $course->id,
            false,
            IGNORE_MISSING
        );
        if ($assigncm) {
            echo html_writer::div(
                html_writer::link(
                    new moodle_url('/mod/assign/view.php', ['id' => $assigncm->id]),
                    get_string('gotoassignment', 'mod_authorcheck')
                )
            );
        }
    }

    echo $OUTPUT->footer();
    exit;
}

// =========================================================================
// RELEASED: answering phase.
// =========================================================================
$attempt = $DB->get_record('authorcheck_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$questions = $storage->get_questions($attemptid);

if (empty($questions)) {
    // No questions yet: explain why, based on the attempt's state.
    if (!empty($attempt->unprocessable)) {
        $msg = get_string('studentunreadable', 'mod_authorcheck');
    } else if (!empty($attempt->nosubmission)) {
        $msg = get_string('studentnosubmission', 'mod_authorcheck');
    } else {
        $msg = get_string('studentnotready', 'mod_authorcheck');
    }
    echo $OUTPUT->notification($msg, \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$maxattempts = max(1, (int) $instance->maxattempts);
$used = (int) $attempt->attemptsused;
$remaining = $maxattempts - $used;

// Timer bookkeeping: stamp the start on first view, compute time left.
$timelimit = (int) $instance->timelimit; // minutes
$timeup = false;
$remainingsecs = 0;
if ($timelimit > 0 && $remaining > 0) {
    if (empty($attempt->timestarted)) {
        $DB->set_field('authorcheck_attempts', 'timestarted', time(), ['id' => $attempt->id]);
        $attempt->timestarted = time();
    }
    $remainingsecs = ((int) $attempt->timestarted + $timelimit * 60) - time();
    if ($remainingsecs <= 0) {
        $timeup = true;
    }
}

$form = new \mod_authorcheck\form\answer_form($PAGE->url, [
    'questions' => $questions,
    'cmid'      => $cm->id,
]);

if ($data = $form->get_data()) {
    if ($remaining > 0) {
        require_capability('mod/authorcheck:attempt', $context);
        $storage->save_and_grade($attempt->id, $data, $questions, (int) $instance->flagthreshold);
        redirect(
            $PAGE->url,
            get_string('answersrecorded', 'mod_authorcheck'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            $PAGE->url,
            get_string('noattemptsleft', 'mod_authorcheck'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo html_writer::tag('p', get_string(
    'attemptsinfo',
    'mod_authorcheck',
    (object) ['used' => $used, 'max' => $maxattempts]
));

if ($used > 0 && $attempt->maxscore !== null) {
    echo $OUTPUT->notification(
        get_string(
            'lastscore',
            'mod_authorcheck',
            (object) ['score' => (int) $attempt->score, 'maxscore' => (int) $attempt->maxscore]
        ),
        \core\output\notification::NOTIFY_INFO
    );
}

if ($remaining > 0 && $timeup) {
    // Time expired without a submission: finalise with whatever was saved.
    if ((int) $attempt->status !== \mod_authorcheck\local\attempt_storage::STATUS_SUBMITTED) {
        $storage->save_and_grade(
            $attempt->id,
            new \stdClass(),
            $questions,
            (int) $instance->flagthreshold
        );
    }
    echo $OUTPUT->notification(
        get_string('timeexpired', 'mod_authorcheck'),
        \core\output\notification::NOTIFY_WARNING
    );
} else if ($remaining > 0) {
    // Show a countdown when a time limit applies.
    if ($timelimit > 0) {
        echo html_writer::div(
            get_string('timeremaining', 'mod_authorcheck') . ' '
            . html_writer::tag('strong', '', ['id' => 'authorcheck-timer']),
            'authorcheck-timerbox',
            ['style' => 'margin:0 0 1em;font-size:1.1em;']
        );

        $PAGE->requires->js_amd_inline("
            require([], function() {
                var remaining = " . (int) $remainingsecs . ";
                var el = document.getElementById('authorcheck-timer');
                var submitted = false;
                function fmt(t){var m=Math.floor(t/60),s=t%60;return m+':'+(s<10?'0':'')+s;}
                function tick(){
                    if(!el){return;}
                    if(remaining<=0){
                        el.textContent='0:00';
                        if(!submitted){
                            submitted=true;
                            var f=document.querySelector('form.mform')||document.forms[0];
                            if(f){f.submit();}
                        }
                        return;
                    }
                    el.textContent=fmt(remaining);
                    remaining--;
                    setTimeout(tick,1000);
                }
                tick();
            });
        ");
    }
    $form->display();
} else {
    echo $OUTPUT->notification(
        get_string('noattemptsleft', 'mod_authorcheck'),
        \core\output\notification::NOTIFY_INFO
    );
}

echo $OUTPUT->footer();
