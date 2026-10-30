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

namespace mod_these\form;

use mod_these\local\questionnaire;

/**
 * Question du questionnaire préalable au jury : type (réponse courte, réponse longue, liste,
 * choix multiples), libellé, choix proposés (un par ligne) et caractère obligatoire.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids();
        $mform->addElement('hidden', 'questionid');
        $mform->setType('questionid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('select', 'qtype', get_string('questiontype', 'mod_these'), questionnaire::question_types());
        $mform->addElement('textarea', 'label', get_string('questionlabel', 'mod_these'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('label', PARAM_TEXT);
        $mform->addRule('label', null, 'required', null, 'client');
        $mform->addElement('textarea', 'options', get_string('questionoptions', 'mod_these'), ['rows' => 6, 'cols' => 60]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'questionoptions', 'mod_these');
        $mform->hideIf('options', 'qtype', 'in', ['text', 'textarea']);
        $mform->addElement('advcheckbox', 'required', '', get_string('questionrequired', 'mod_these'));

        $this->add_action_buttons();
    }

    /**
     * Une liste ou un QCM propose au moins deux choix.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string) $data['label']) === '') {
            $errors['label'] = get_string('required');
        }
        if (
            in_array($data['qtype'], ['list', 'multi'], true)
                && count(questionnaire::question_options((object) ['options' => $data['options']])) < 2
        ) {
            $errors['options'] = get_string('questionoptionsrequired', 'mod_these');
        }
        return $errors;
    }
}
