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
 * CLI to generate questions for one activity.
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
    ['cmid' => null, 'file' => null, 'userid' => null, 'help' => false],
    ['h' => 'help']
);

if ($options['help'] || empty($options['cmid']) || empty($options['file'])) {
    cli_writeln("Generate questions for a real authorcheck activity.\n");
    cli_writeln("Usage:");
    cli_writeln("  php mod/authorcheck/cli/generateforcm.php --cmid=NN --file=/abs/path [--userid=NN]");
    cli_writeln("");
    cli_writeln("  --cmid    Course-module id (the 'id' in the activity's view.php?id=NN URL).");
    cli_writeln("  --file    Absolute path to a PDF/DOCX/TXT to generate questions from.");
    cli_writeln("  --userid  Student to attach the attempt to. Defaults to admin.");
    exit(0);
}

$cm = get_coursemodule_from_id('authorcheck', (int) $options['cmid'], 0, false, MUST_EXIST);
$instance = $DB->get_record('authorcheck', ['id' => $cm->instance], '*', MUST_EXIST);
$userid = !empty($options['userid']) ? (int) $options['userid'] : (int) get_admin()->id;

$path = $options['file'];
if (!file_exists($path)) {
    cli_error("File not found: {$path}");
}

// Extract.
$extmap = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt'  => 'text/plain',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimetype = $extmap[$ext] ?? (mime_content_type($path) ?: 'application/octet-stream');

$extractor = new \mod_authorcheck\local\text_extractor();
$text = $extractor->extract_from_path($path, $mimetype);
cli_writeln("Extracted " . strlen($text) . " characters.");

// Generate.
cli_writeln("Generating questions...");
$generator = new \mod_authorcheck\local\question_generator();
$num = !empty($instance->numquestions) ? (int) $instance->numquestions : 6;
$allowedtypes = array_values(array_filter(explode(',', (string) $instance->qtypes)));
if (empty($allowedtypes)) {
    $allowedtypes = ['mcq', 'truefalse', 'fillin', 'open'];
}
$questions = $generator->generate($text, $num, $allowedtypes);
cli_writeln("Generated " . count($questions) . " questions.");

// Persist against the real instance + user.
$storage = new \mod_authorcheck\local\attempt_storage();
$attemptid = $storage->get_or_create_attempt((int) $instance->id, $userid);
$storage->store_questions($attemptid, $questions);

cli_writeln("");
cli_writeln("Done.");
cli_writeln("  instance id : {$instance->id}");
cli_writeln("  user id     : {$userid}");
cli_writeln("  attempt id  : {$attemptid}");
cli_writeln("");
cli_writeln("To release questions for viewing, set manualreleased=1:");
cli_writeln("  bin/moodle-docker-compose exec db psql -U moodle -d moodle -c "
    . "\"UPDATE m_authorcheck SET manualreleased=1 WHERE id={$instance->id};\"");
