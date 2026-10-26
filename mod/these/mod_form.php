<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The main mod_these configuration form.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form for mod_these : en plus du nom et de la description, les
 * informations d'établissement imprimées sur les documents générés, les adresses de service
 * destinataires des notifications et le délai de relance.
 */
class mod_these_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('thesename', 'mod_these'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'establishmentheader', get_string('establishment', 'mod_these'));
        $mform->addElement('text', 'establishmentname', get_string('establishmentname', 'mod_these'), ['size' => '64']);
        $mform->setType('establishmentname', PARAM_TEXT);
        $mform->addElement(
            'textarea',
            'establishmentaddress',
            get_string('establishmentaddress', 'mod_these'),
            ['rows' => 3, 'cols' => 60]
        );
        $mform->setType('establishmentaddress', PARAM_TEXT);
        $mform->addElement('text', 'establishmentcity', get_string('establishmentcity', 'mod_these'), ['size' => '40']);
        $mform->setType('establishmentcity', PARAM_TEXT);
        $mform->addElement('text', 'signatoryname', get_string('signatoryname', 'mod_these'), ['size' => '64']);
        $mform->setType('signatoryname', PARAM_TEXT);
        $mform->addElement('text', 'signatorytitle', get_string('signatorytitle', 'mod_these'), ['size' => '64']);
        $mform->setType('signatorytitle', PARAM_TEXT);

        $mform->addElement('header', 'workflowheader', get_string('workflowsettings', 'mod_these'));
        $mform->addElement('text', 'minjury', get_string('minjury', 'mod_these'), ['size' => '4']);
        $mform->setType('minjury', PARAM_INT);
        $mform->setDefault('minjury', 3);
        $mform->addHelpButton('minjury', 'minjury', 'mod_these');
        foreach (['deveemail', 'libraryemail', 'universityemail'] as $field) {
            $mform->addElement('text', $field, get_string($field, 'mod_these'), ['size' => '64']);
            $mform->setType($field, PARAM_RAW_TRIMMED);
            $mform->addHelpButton($field, $field, 'mod_these');
        }
        $mform->addElement('text', 'reminderdays', get_string('reminderdays', 'mod_these'), ['size' => '4']);
        $mform->setType('reminderdays', PARAM_INT);
        $mform->setDefault('reminderdays', 14);
        $mform->addHelpButton('reminderdays', 'reminderdays', 'mod_these');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Adresses de service valides et nombres positifs.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['deveemail', 'libraryemail', 'universityemail'] as $field) {
            if (trim((string) $data[$field]) !== '' && !validate_email($data[$field])) {
                $errors[$field] = get_string('invalidemail');
            }
        }
        if ((int) $data['minjury'] < 1) {
            $errors['minjury'] = get_string('minjuryinvalid', 'mod_these');
        }
        if ((int) $data['reminderdays'] < 0) {
            $errors['reminderdays'] = get_string('reminderdaysinvalid', 'mod_these');
        }
        return $errors;
    }
}
