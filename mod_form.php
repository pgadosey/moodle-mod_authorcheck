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
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Add/edit form for the Authorship Check activity.
 */
class mod_authorcheck_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG;
        $mform = $this->_form;

        // --- General ---------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('authorcheckname', 'mod_authorcheck'),
            ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'authorcheckname', 'mod_authorcheck');

        $this->standard_intro_elements();

        // --- Submission ------------------------------------------------------
        $mform->addElement('header', 'submissionsettings',
            get_string('submissionsettings', 'mod_authorcheck'));

        // Linked assignment: submissions are drawn from this assignment.
        global $COURSE;
        $assignoptions = [0 => get_string('chooseassignment', 'mod_authorcheck')];
        foreach (get_all_instances_in_course('assign', $COURSE) as $assign) {
            $assignoptions[$assign->id] = format_string($assign->name);
        }
        $mform->addElement('select', 'assignmentid',
            get_string('linkedassignment', 'mod_authorcheck'), $assignoptions);
        $mform->addHelpButton('assignmentid', 'linkedassignment', 'mod_authorcheck');

        // --- Question generation ---------------------------------------------
        $mform->addElement('header', 'questiongensettings',
            get_string('questiongensettings', 'mod_authorcheck'));

        $mform->addElement('select', 'numquestions',
            get_string('numquestions', 'mod_authorcheck'),
            array_combine(range(3, 12), range(3, 12)));
        $mform->addHelpButton('numquestions', 'numquestions', 'mod_authorcheck');
        $mform->setDefault('numquestions', 6);

        $mform->addElement('static', 'qtypeslabel', get_string('qtypes', 'mod_authorcheck'), '');
        foreach (['mcq', 'truefalse', 'fillin', 'open'] as $t) {
            $mform->addElement('advcheckbox', 'qtype_' . $t, '',
                get_string('qtype_' . $t, 'mod_authorcheck'));
            $mform->setDefault('qtype_' . $t, 1);
        }
        $mform->addElement('static', 'qtypenote', '',
            get_string('qtypeopennote', 'mod_authorcheck'));

        // --- Question release ------------------------------------------------
        $mform->addElement('header', 'releasesettings',
            get_string('releasesettings', 'mod_authorcheck'));

        $mform->addElement('date_time_selector', 'releasetime',
            get_string('releasetime', 'mod_authorcheck'), ['optional' => true]);
        $mform->setDefault('releasetime', 0);

        $mform->addElement('advcheckbox', 'manualreleased',
            get_string('manualreleased', 'mod_authorcheck'),
            get_string('manualreleased_desc', 'mod_authorcheck'));
        $mform->setDefault('manualreleased', 0);

        // --- Attempts --------------------------------------------------------
        $mform->addElement('header', 'attemptsettings',
            get_string('attemptsettings', 'mod_authorcheck'));

        $mform->addElement('select', 'maxattempts',
            get_string('maxattempts', 'mod_authorcheck'),
            [1 => '1', 2 => '2', 3 => '3']);
        $mform->setDefault('maxattempts', 1);

        $mform->addElement('select', 'flagthreshold',
            get_string('flagthreshold', 'mod_authorcheck'),
            [40 => '40%', 50 => '50%', 60 => '60%', 70 => '70%', 80 => '80%', 90 => '90%']);
        $mform->addHelpButton('flagthreshold', 'flagthreshold', 'mod_authorcheck');
        $mform->setDefault('flagthreshold', 50);

        $mform->addElement('text', 'timelimit',
            get_string('timelimit', 'mod_authorcheck'), ['size' => 5]);
        $mform->setType('timelimit', PARAM_INT);
        $mform->addHelpButton('timelimit', 'timelimit', 'mod_authorcheck');
        $mform->setDefault('timelimit', 0);

        // --- Standard elements + buttons -------------------------------------
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Split the stored comma-separated qtypes into individual checkboxes.
     *
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($defaultvalues['qtypes'])) {
            $selected = explode(',', $defaultvalues['qtypes']);
            foreach (['mcq', 'truefalse', 'fillin', 'open'] as $t) {
                $defaultvalues['qtype_' . $t] = in_array($t, $selected, true) ? 1 : 0;
            }
        }
    }

    /**
     * Combine the question-type checkboxes into the stored qtypes string.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $types = [];
            foreach (['mcq', 'truefalse', 'fillin', 'open'] as $t) {
                $field = 'qtype_' . $t;
                if (!empty($data->$field)) {
                    $types[] = $t;
                }
            }
            $data->qtypes = implode(',', $types);
        }
        return $data;
    }

    /**
     * A linked assignment is required — without one there is no source of
     * submissions and the activity can never generate questions.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['assignmentid'])) {
            $errors['assignmentid'] = get_string('assignmentrequired', 'mod_authorcheck');
        }
        if (isset($data['timelimit']) && $data['timelimit'] < 0) {
            $errors['timelimit'] = get_string('timelimitinvalid', 'mod_authorcheck');
        }
        $anytype = false;
        foreach (['mcq', 'truefalse', 'fillin', 'open'] as $t) {
            if (!empty($data['qtype_' . $t])) {
                $anytype = true;
                break;
            }
        }
        if (!$anytype) {
            $errors['qtype_mcq'] = get_string('qtyperequired', 'mod_authorcheck');
        }
        return $errors;
    }
}
