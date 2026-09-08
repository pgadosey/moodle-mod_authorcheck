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
 * Scheduled task to generate questions from assignments.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_authorcheck\task;

/**
 * Generate questions from linked-assignment submissions.
 */
class generate_from_assignments extends \core\task\scheduled_task {
    /**
     * Return the task display name.
     */
    public function get_name() {
        return get_string('taskgenerate', 'mod_authorcheck');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $DB;

        $instances = $DB->get_records_select('authorcheck', 'assignmentid > 0');
        if (!$instances) {
            return;
        }

        $processor = new \mod_authorcheck\local\processor();

        foreach ($instances as $instance) {
            $assign = $DB->get_record('assign', ['id' => $instance->assignmentid]);
            if (!$assign) {
                continue; // Linked assignment gone.
            }

            // Deadline gate: if a due date is set and hasn't passed, wait.
            // No due date (0) means process immediately.
            if ((int) $assign->duedate > 0 && time() < (int) $assign->duedate) {
                continue;
            }

            mtrace("mod_authorcheck: processing activity {$instance->id} "
                . "(assignment {$instance->assignmentid})");

            try {
                $res = $processor->process($instance, true);
                mtrace("  -> generated={$res->generated} no_submission={$res->nosubmission} "
                    . "unreadable={$res->unprocessable} skipped={$res->skipped} errors={$res->errors}");
            } catch (\Throwable $e) {
                mtrace("  -> ERROR: " . $e->getMessage());
            }
        }
    }
}
