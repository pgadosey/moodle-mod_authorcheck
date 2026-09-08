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
 * Reads submissions from a linked assignment.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\local;

/**
 * Reads file submissions from a linked assignment.
 */
class assignment_reader {
    /** File extensions we can extract text from. */
    const ACCEPTED = ['pdf', 'docx'];

    /**
     * For each enrolled student, find their usable submission file.
     *
     * @param \stdClass $authorcheck The authorcheck activity instance.
     * @return array List of objects: {userid, file (\stored_file|null), reason}
     *               reason is one of: ok | nosubmission | nousablefile
     */
    public function get_submissions(\stdClass $authorcheck): array {
        global $DB;

        $assignid = (int) $authorcheck->assignmentid;
        if (!$assignid) {
            throw new \RuntimeException('No assignment is linked to this activity.');
        }

        $assign = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);
        $assigncm = get_coursemodule_from_instance(
            'assign',
            $assignid,
            $assign->course,
            false,
            MUST_EXIST
        );
        $assigncontext = \context_module::instance($assigncm->id);

        // Students who can submit to the assignment (active enrolments).
        $students = get_enrolled_users(
            $assigncontext,
            'mod/assign:submit',
            0,
            'u.id',
            null,
            0,
            0,
            true
        );

        $fs = get_file_storage();
        $result = [];

        foreach ($students as $student) {
            // The student's current submission (individual assignments; groups
            // are out of scope for this version).
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $assignid,
                'userid'     => $student->id,
                'latest'     => 1,
            ]);

            if (!$submission || $submission->status !== 'submitted') {
                $result[] = (object) [
                    'userid' => (int) $student->id,
                    'file'   => null,
                    'reason' => 'nosubmission',
                ];
                continue;
            }

            $files = $fs->get_area_files(
                $assigncontext->id,
                'assignsubmission_file',
                'submission_files',
                $submission->id,
                'sortorder, id',
                false
            );

            $usable = null;
            foreach ($files as $f) {
                $ext = strtolower(pathinfo($f->get_filename(), PATHINFO_EXTENSION));
                if (in_array($ext, self::ACCEPTED, true)) {
                    $usable = $f;
                    break;
                }
            }

            $result[] = (object) [
                'userid' => (int) $student->id,
                'file'   => $usable,
                'reason' => $usable ? 'ok' : 'nousablefile',
            ];
        }

        return $result;
    }
}
