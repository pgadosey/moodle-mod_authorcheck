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
namespace mod_authorcheck\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Answer form for an authorcheck attempt.
 *
 * Expects customdata:
 *   'questions' => array from attempt_storage::get_questions()
 *   'cmid'      => course-module id (for the hidden id field)
 */
class answer_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $questions = $this->_customdata['questions'];

        // Carry the cmid so the form posts back to the same activity page.
        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        foreach ($questions as $q) {
            $name = 'q' . $q['id'];
            $label = format_string($q['question']);

            switch ($q['type']) {
                case 'mcq':
                    $radios = [];
                    foreach ($q['options'] as $opt) {
                        // Value and label are both the option text, so the
                        // submitted value matches the stored answer key.
                        $radios[] = $mform->createElement('radio', $name, '', $opt, $opt);
                    }
                    $mform->addGroup($radios, $name . 'grp', $label, ['<br/>'], false);
                    break;

                case 'truefalse':
                    $radios = [
                        $mform->createElement('radio', $name, '', get_string('true', 'mod_authorcheck'), 'true'),
                        $mform->createElement('radio', $name, '', get_string('false', 'mod_authorcheck'), 'false'),
                    ];
                    $mform->addGroup($radios, $name . 'grp', $label, ['<br/>'], false);
                    break;

                case 'fillin':
                    $mform->addElement('text', $name, $label, ['size' => 40]);
                    $mform->setType($name, PARAM_TEXT);
                    break;

                case 'open':
                default:
                    $mform->addElement('textarea', $name, $label, ['rows' => 5, 'cols' => 60]);
                    $mform->setType($name, PARAM_TEXT);
                    break;
            }
        }

        $this->add_action_buttons(false, get_string('submitanswers', 'mod_authorcheck'));
    }
}
