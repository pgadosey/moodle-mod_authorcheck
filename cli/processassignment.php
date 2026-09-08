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
 * CLI to process submissions from the linked assignment.
 *
 * @package    mod_authorcheck
 * @copyright  2026 Pius Kwao Gadosey <kwaoproj@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require($CFG->libdir . '/clilib.php');

global $DB;

[$options, $unrecognised] = cli_get_params(
    ['cmid' => null, 'force' => false, 'help' => false],
    ['h' => 'help']
);

if ($options['help'] || empty($options['cmid'])) {
    cli_writeln("Process submissions from the linked assignment.\n");
    cli_writeln("Usage: php mod/authorcheck/cli/processassignment.php --cmid=NN [--force]");
    cli_writeln("  --cmid   Course-module id of the Authorship Check activity.");
    cli_writeln("  --force  Regenerate even for students who already have questions.");
    exit(0);
}

$cm = get_coursemodule_from_id('authorcheck', (int) $options['cmid'], 0, false, MUST_EXIST);
$instance = $DB->get_record('authorcheck', ['id' => $cm->instance], '*', MUST_EXIST);

if (empty($instance->assignmentid)) {
    cli_error("This activity has no linked assignment. Set one in the activity settings.");
}

$processor = new \mod_authorcheck\local\processor();
$res = $processor->process($instance, !$options['force']);

cli_writeln("");
cli_writeln("Done. generated={$res->generated}  no_submission={$res->nosubmission}"
    . "  unreadable={$res->unprocessable}  skipped={$res->skipped}  errors={$res->errors}");
