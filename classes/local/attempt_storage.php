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
 * Attempt, question and response storage for mod_authorcheck.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\local;

/**
 * Reads and writes attempts, questions, and responses.
 *
 * Table names are passed WITHOUT the site prefix; Moodle's $DB layer adds it.
 */
class attempt_storage {
    /** Attempt lifecycle states (stored in authorcheck_attempts.status). */
    const STATUS_NOTSTARTED = 0;
    /** In-progress attempt. */
    const STATUS_INPROGRESS = 1;
    /** Submitted attempt. */
    const STATUS_SUBMITTED  = 2;
    /** Reviewed attempt. */
    const STATUS_REVIEWED   = 3;

    /** Below this fraction of objective questions correct, the attempt is flagged. */
    const FLAG_THRESHOLD = 0.5;

    /**
     * Return the attempt id for this (activity, user), creating it if absent.
     *
     * @param int $authorcheckid
     * @param int $userid
     * @return int
     */
    public function get_or_create_attempt(int $authorcheckid, int $userid): int {
        global $DB;

        $existing = $DB->get_record('authorcheck_attempts', [
            'authorcheckid' => $authorcheckid,
            'userid'        => $userid,
        ]);
        if ($existing) {
            return (int) $existing->id;
        }

        $now = time();
        $record = (object) [
            'authorcheckid' => $authorcheckid,
            'userid'        => $userid,
            'status'        => self::STATUS_NOTSTARTED,
            'attemptsused'  => 0,
            'nosubmission'  => 0,
            'unprocessable' => 0,
            'timestarted'   => 0,
            'score'         => null,
            'maxscore'      => null,
            'flagged'       => 0,
            'timegenerated' => 0,
            'timemodified'  => $now,
        ];

        return (int) $DB->insert_record('authorcheck_attempts', $record);
    }

    /**
     * Store a freshly generated question set against an attempt (idempotent).
     *
     * @param int $attemptid
     * @param array $questions Output of question_generator::generate().
     */
    public function store_questions(int $attemptid, array $questions): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records('authorcheck_responses', ['attemptid' => $attemptid]);
            $DB->delete_records('authorcheck_questions', ['attemptid' => $attemptid]);

            $sortorder = 0;
            foreach ($questions as $q) {
                $answer = array_key_exists('answer', $q) ? $q['answer'] : null;
                // Normalise booleans: true/false answers may arrive as JSON
                // booleans, and (string)false would become '' and lose the key.
                if (is_bool($answer)) {
                    $answer = $answer ? 'true' : 'false';
                }

                $record = (object) [
                    'attemptid'    => $attemptid,
                    'qtype'        => (string) $q['type'],
                    'questiontext' => (string) $q['question'],
                    'options'      => json_encode($q['options'] ?? []),
                    'answerkey'    => ($answer === null) ? null : (string) $answer,
                    'sortorder'    => $sortorder++,
                ];
                $DB->insert_record('authorcheck_questions', $record);
            }

            $now = time();
            $DB->update_record('authorcheck_attempts', (object) [
                'id'            => $attemptid,
                'nosubmission'  => 0,
                'unprocessable' => 0,
                'timegenerated' => $now,
                'timemodified'  => $now,
            ]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Read back the stored questions for an attempt, in display order.
     *
     * @param int $attemptid
     * @return array
     */
    public function get_questions(int $attemptid): array {
        global $DB;

        $records = $DB->get_records(
            'authorcheck_questions',
            ['attemptid' => $attemptid],
            'sortorder ASC'
        );

        $questions = [];
        foreach ($records as $r) {
            $questions[] = [
                'id'        => (int) $r->id,
                'type'      => $r->qtype,
                'question'  => $r->questiontext,
                'options'   => json_decode($r->options, true) ?: [],
                'answer'    => $r->answerkey,
                'sortorder' => (int) $r->sortorder,
            ];
        }
        return $questions;
    }

    /**
     * Save the student's submitted answers, grade the objective ones, update
     * the attempt's score and flag, and increment the attempts-used counter.
     *
     * @param int $attemptid
     * @param \stdClass $formdata Data from answer_form::get_data().
     * @param array $questions Questions from get_questions() (carry answer keys).
     * @return \stdClass Summary: score, maxscore, flagged.
     */
    public function save_and_grade(
        int $attemptid,
        \stdClass $formdata,
        array $questions,
        int $thresholdpercent = 50
    ): \stdClass {
        global $DB;

        // Current attempts-used, so we can increment it.
        $current = $DB->get_record(
            'authorcheck_attempts',
            ['id' => $attemptid],
            'attemptsused',
            MUST_EXIST
        );
        $used = (int) $current->attemptsused;

        $correct = 0;   // Objective questions answered correctly.
        $objective = 0; // Number of auto-gradable (non-open) questions.

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($questions as $q) {
                $field = 'q' . $q['id'];
                $response = isset($formdata->$field) ? (string) $formdata->$field : null;

                $iscorrect = grader::grade($q['type'], $q['answer'], $response);

                if ($q['type'] !== 'open') {
                    $objective++;
                    if ($iscorrect === 1) {
                        $correct++;
                    }
                }

                // Upsert the response (unique on attemptid+questionid).
                $existing = $DB->get_record('authorcheck_responses', [
                    'attemptid'  => $attemptid,
                    'questionid' => $q['id'],
                ]);

                $record = (object) [
                    'attemptid'    => $attemptid,
                    'questionid'   => $q['id'],
                    'response'     => $response,
                    'score'        => $iscorrect,
                    'iscorrect'    => $iscorrect,
                    'timemodified' => time(),
                ];

                if ($existing) {
                    $record->id = $existing->id;
                    $DB->update_record('authorcheck_responses', $record);
                } else {
                    $DB->insert_record('authorcheck_responses', $record);
                }
            }

            $threshold = $thresholdpercent / 100;
            $flagged = ($objective > 0 && ($correct / $objective) < $threshold) ? 1 : 0;

            $DB->update_record('authorcheck_attempts', (object) [
                'id'           => $attemptid,
                'status'       => self::STATUS_SUBMITTED,
                'attemptsused' => $used + 1,
                'score'        => $correct,
                'maxscore'     => $objective,
                'flagged'      => $flagged,
                'timemodified' => time(),
            ]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        return (object) [
            'score'    => $correct,
            'maxscore' => $objective,
            'flagged'  => $flagged,
        ];
    }

    /**
     * Record that a student made no usable submission: a flagged, question-less
     * attempt the teacher can see in the review screen.
     *
     * @param int $attemptid
     */
    public function mark_no_submission(int $attemptid): void {
        global $DB;

        // Remove any stale questions/responses for this attempt.
        $DB->delete_records('authorcheck_responses', ['attemptid' => $attemptid]);
        $DB->delete_records('authorcheck_questions', ['attemptid' => $attemptid]);

        $DB->update_record('authorcheck_attempts', (object) [
            'id'           => $attemptid,
            'status'       => self::STATUS_SUBMITTED,
            'nosubmission' => 1,
            'unprocessable' => 0,
            'flagged'      => 1,
            'score'        => 0,
            'maxscore'     => 0,
            'timemodified' => time(),
        ]);
    }

    /**
     * Record that a student's submitted file could not be read (no extractable
     * text, e.g. a scanned/image-only PDF): a flagged attempt distinct from
     * "no submission", for the teacher to review manually.
     *
     * @param int $attemptid
     */
    public function mark_unprocessable(int $attemptid): void {
        global $DB;

        $DB->delete_records('authorcheck_responses', ['attemptid' => $attemptid]);
        $DB->delete_records('authorcheck_questions', ['attemptid' => $attemptid]);

        $DB->update_record('authorcheck_attempts', (object) [
            'id'            => $attemptid,
            'status'        => self::STATUS_SUBMITTED,
            'nosubmission'  => 0,
            'unprocessable' => 1,
            'flagged'       => 1,
            'score'         => 0,
            'maxscore'      => 0,
            'timemodified'  => time(),
        ]);
    }
}
