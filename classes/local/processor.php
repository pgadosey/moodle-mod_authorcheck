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
namespace mod_authorcheck\local;

/**
 * Processes an activity's linked-assignment submissions into questions.
 */
class processor {

    /**
     * Process one activity instance.
     *
     * @param \stdClass $instance The authorcheck activity.
     * @param bool $onlynew When true, skip students who already have questions
     *                      (used by the scheduled task). When false, regenerate
     *                      for everyone (used by the CLI --force).
     * @return \stdClass Counts: generated, nosubmission, skipped, errors.
     */
    public function process(\stdClass $instance, bool $onlynew = true): \stdClass {
        global $DB;

        $reader = new assignment_reader();
        $storage = new attempt_storage();
        $extractor = new text_extractor();
        $generator = new question_generator();

        $num = max(3, (int) $instance->numquestions);
        $allowedtypes = array_values(array_filter(explode(',', (string) $instance->qtypes)));
        if (empty($allowedtypes)) {
            $allowedtypes = ['mcq', 'truefalse', 'fillin', 'open'];
        }

        $generated = 0;
        $nosub = 0;
        $unprocessable = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($reader->get_submissions($instance) as $s) {
            $attemptid = $storage->get_or_create_attempt((int) $instance->id, $s->userid);
            $attempt = $DB->get_record('authorcheck_attempts', ['id' => $attemptid],
                'timegenerated, nosubmission, unprocessable', MUST_EXIST);
            $hasquestions = ((int) $attempt->timegenerated > 0);

            // Already has questions: leave it alone in incremental mode.
            if ($onlynew && $hasquestions) {
                $skipped++;
                continue;
            }

            if ($s->reason === 'ok' && $s->file) {
                try {
                    $text = $extractor->extract($s->file);

                    // No extractable text (e.g. scanned/image-only PDF): flag
                    // it for the teacher rather than erroring every run.
                    if (trim($text) === '') {
                        if ($onlynew && (int) $attempt->unprocessable === 1) {
                            $skipped++;
                            continue;
                        }
                        $storage->mark_unprocessable($attemptid);
                        $unprocessable++;
                        mtrace("  user {$s->userid}: unreadable file "
                            . "'" . $s->file->get_filename() . "' - flagged");
                        continue;
                    }

                    $questions = $generator->generate($text, $num, $allowedtypes);
                    $storage->store_questions($attemptid, $questions);
                    $generated++;
                    mtrace("  user {$s->userid}: generated " . count($questions)
                        . " questions from '" . $s->file->get_filename() . "'");
                } catch (\Throwable $e) {
                    $errors++;
                    mtrace("  user {$s->userid}: ERROR - " . $e->getMessage());
                }
            } else {
                // No usable submission. Skip re-marking if already marked.
                if ($onlynew && (int) $attempt->nosubmission === 1) {
                    $skipped++;
                    continue;
                }
                $storage->mark_no_submission($attemptid);
                $nosub++;
                mtrace("  user {$s->userid}: no usable submission ({$s->reason}) - flagged");
            }
        }

        return (object) [
            'generated'     => $generated,
            'nosubmission'  => $nosub,
            'unprocessable' => $unprocessable,
            'skipped'       => $skipped,
            'errors'        => $errors,
        ];
    }
}
