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
 * Plugin strings are defined here.
 *
 * @package     mod_authorcheck
 * @category    string
 * @copyright   2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Authorship Check';
$string['modulenameplural'] = 'Authorship Checks';
$string['modulename_help'] = 'The Authorship Check activity generates questions from a student\'s own submission to help verify they understand the work they submitted.';
$string['authorcheckname'] = 'Name';
$string['authorcheckname_help'] = 'The name of this Authorship Check activity, shown to students on the course page.';
$string['authorchecksettings'] = 'Settings';
$string['authorcheckfieldset'] = 'Custom example fieldset';
$string['pluginadministration'] = 'Authorship Check administration';
$string['pluginname'] = 'Authorship Check';
$string['authorcheck:addinstance'] = 'Add a new Authorship Check activity';
$string['authorcheck:view'] = 'View Authorship Check activity';
$string['authorcheck:attempt'] = 'Answer the generated questions';
$string['authorcheck:review'] = 'Review student results and authorship flags';

$string['authorcheck:addinstance'] = 'Add a new Authorship Check activity';
$string['authorcheck:attempt'] = 'Answer the generated questions';
$string['authorcheck:review'] = 'Review student results and authorship flags';
$string['authorcheck:view'] = 'View Authorship Check activity';
$string['pluginname'] = 'Authorship Check';
$string['statuslocked'] = 'Questions are locked. Your lecturer has not released them yet.';
$string['statusscheduled'] = 'Questions will be available from {$a}.';
$string['statusreleased'] = 'Questions are available.';
$string['noattempt'] = 'No question set has been generated for you yet.';
$string['noquestions'] = 'No questions were found for your attempt.';
$string['yourquestions'] = 'Your questions';

$string['true'] = 'True';
$string['false'] = 'False';
$string['submitanswers'] = 'Submit answers';
$string['answersrecorded'] = 'Your answers have been recorded.';
$string['alreadysubmitted'] = 'You have already submitted. Your score on objective questions was {$a->score}/{$a->maxscore}.';

$string['reviewsubmissions'] = 'Review submissions';
$string['reviewheading'] = 'Submissions';
$string['reviewfor'] = 'Review: {$a}';
$string['reviewlink'] = 'Review';
$string['student'] = 'Student';
$string['objscore'] = 'Objective score';
$string['flag'] = 'Flag';
$string['submitted'] = 'Submitted';
$string['flagged'] = 'Flagged';
$string['notflagged'] = 'Not flagged';
$string['setflag'] = 'Set flag';
$string['clearflag'] = 'Clear flag';
$string['flagupdated'] = 'Flag updated.';
$string['backtolist'] = 'Back to submissions list';
$string['answerkey'] = 'Answer key';
$string['studentresponse'] = 'Student response';
$string['notgraded'] = 'Open question — not auto-graded';
$string['nosubmissions'] = 'No submissions yet.';
$string['correct'] = 'Correct';
$string['incorrect'] = 'Incorrect';

$string['releasesettings'] = 'Question release';
$string['releasetime'] = 'Release questions at';
$string['manualreleased'] = 'Release now';
$string['manualreleased_desc'] = 'Make questions available to students immediately, regardless of the scheduled time.';

$string['attemptsettings'] = 'Attempts';
$string['maxattempts'] = 'Attempts allowed';
$string['attemptsinfo'] = 'Attempt {$a->used} of {$a->max} used.';
$string['noattemptsleft'] = 'You have used all your attempts for this activity.';
$string['lastscore'] = 'Your latest objective score was {$a->score}/{$a->maxscore}.';

$string['flagthreshold'] = 'Flag threshold';
$string['flagthreshold_help'] = 'A student is flagged for review if their score on the objective questions (multiple choice, true/false, fill-in) falls below this percentage. Open-ended questions are not counted and are left for you to review manually.';

$string['questiongensettings'] = 'Question generation';
$string['numquestions'] = 'Number of questions';
$string['numquestions_help'] = 'How many questions to generate from each student\'s submission. A mix of question types is produced. More questions give a stronger signal but take longer to generate and to answer.';

$string['linkedassignment'] = 'Linked assignment';
$string['linkedassignment_help'] = 'The assignment whose submissions this activity checks. Questions are generated from each student\'s submitted file (PDF or DOCX) after the assignment\'s due date.';
$string['chooseassignment'] = 'Choose an assignment...';
$string['gotoassignment'] = 'Go to the assignment to submit your work';

$string['submissionsettings'] = 'Submission';

$string['nosubmissionlabel'] = 'No submission';
$string['nosubmissionnote'] = 'This student made no usable submission to the linked assignment (PDF or DOCX required).';

$string['taskgenerate'] = 'Generate questions from assignment submissions';

$string['unprocessablelabel'] = 'Unreadable file';
$string['unprocessablenote'] = 'The student\'s submitted file could not be read (for example a scanned or image-only PDF with no text layer). Please review manually.';

$string['privacy:metadata:authorcheck_attempts'] = 'Records of each student\'s authorship-check attempt.';
$string['privacy:metadata:authorcheck_attempts:userid'] = 'The ID of the student the attempt belongs to.';
$string['privacy:metadata:authorcheck_attempts:status'] = 'The state of the attempt (not started, submitted, reviewed).';
$string['privacy:metadata:authorcheck_attempts:attemptsused'] = 'How many times the student has submitted answers.';
$string['privacy:metadata:authorcheck_attempts:score'] = 'The student\'s score on the objective questions.';
$string['privacy:metadata:authorcheck_attempts:maxscore'] = 'The maximum objective score available.';
$string['privacy:metadata:authorcheck_attempts:flagged'] = 'Whether the attempt is flagged for review.';
$string['privacy:metadata:authorcheck_attempts:nosubmission'] = 'Whether the student made no submission.';
$string['privacy:metadata:authorcheck_attempts:unprocessable'] = 'Whether the student\'s file could not be read.';
$string['privacy:metadata:authorcheck_attempts:timemodified'] = 'When the attempt was last changed.';
$string['privacy:metadata:authorcheck_questions'] = 'Questions generated from the student\'s own submission.';
$string['privacy:metadata:authorcheck_questions:questiontext'] = 'The text of the generated question.';
$string['privacy:metadata:authorcheck_questions:options'] = 'The answer options for the question.';
$string['privacy:metadata:authorcheck_questions:answerkey'] = 'The correct answer to the question.';
$string['privacy:metadata:authorcheck_responses'] = 'The answers a student gave to their questions.';
$string['privacy:metadata:authorcheck_responses:response'] = 'The student\'s submitted answer.';
$string['privacy:metadata:authorcheck_responses:score'] = 'The score awarded for the answer.';
$string['privacy:metadata:authorcheck_responses:iscorrect'] = 'Whether the answer was correct.';
$string['privacy:metadata:core_ai'] = 'Text from the student\'s submission is sent to the AI subsystem to generate comprehension questions.';

$string['notastudent'] = 'This activity is for students to complete. If you are teaching this course, use "Review submissions" to see student results.';

$string['check:dependencies'] = 'Authorship Check dependencies';
$string['check:dependencies:ok'] = 'The required text-extraction tools (pdftotext, pandoc) are available.';
$string['check:dependencies:missing'] = 'Required text-extraction tools are missing.';
$string['check:dependencies:missingdetails'] = 'The following command-line tools are not available on the server: {$a}. Install poppler-utils (pdftotext) and pandoc. Question generation will fail for affected file types until they are present.';

$string['studentunreadable'] = 'Your submitted file could not be read — it may be a scanned or image-only PDF with no text. Please submit a text-based PDF or DOCX to the assignment, or contact your tutor.';
$string['studentnosubmission'] = 'No submission was found for the linked assignment, so no questions could be generated. Please contact your tutor if you believe this is an error.';
$string['studentnotready'] = 'Your questions are not ready yet. Please check back shortly.';

$string['assignmentrequired'] = 'You must link an assignment. The activity generates questions from that assignment\'s submissions.';

$string['timelimit'] = 'Time limit (minutes)';
$string['timelimit_help'] = 'How long a student has to answer once they open the questions. When the time is up, whatever they have answered is submitted automatically. Enter 0 for no limit.';
$string['timelimitinvalid'] = 'The time limit cannot be negative.';
$string['timeremaining'] = 'Time remaining:';
$string['timeexpired'] = 'Your time is up. Your answers have been submitted automatically.';

$string['qtypes'] = 'Question types';
$string['qtype_mcq'] = 'Multiple choice';
$string['qtype_truefalse'] = 'True/false';
$string['qtype_fillin'] = 'Fill in the blank';
$string['qtype_open'] = 'Open-ended';
$string['qtyperequired'] = 'Select at least one question type.';
$string['qtypeopennote'] = 'Note: open-ended questions are not auto-graded and do not contribute to the authorship flag. If you select only open-ended questions, all review is manual and no automatic flag is raised.';