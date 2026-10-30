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

/**
 * Demande d'une nouvelle date de soutenance par la DEVE (voir \mod_these\local\daterequest) : motif,
 * communiqué à l'étudiant.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class daterequest_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids(true);

        $mform->addElement('static', 'daterequesthelp', '', get_string('daterequesthelp', 'mod_these'));
        $mform->addElement('static', 'currentdate', get_string('proposeddate', 'mod_these'), s($this->_customdata['currentdate']));
        $mform->addElement('textarea', 'reason', get_string('daterequestreason', 'mod_these'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('daterequest', 'mod_these'));
    }

    /**
     * Motif obligatoire.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string) ($data['reason'] ?? '')) === '') {
            $errors['reason'] = get_string('required');
        }
        return $errors;
    }
}
