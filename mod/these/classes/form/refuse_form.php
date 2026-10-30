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
 * Refus de signer d'un agent DEVE (voir \mod_these\local\refusal) : documents refusés, cochés
 * d'office, avec l'étape où chacun renvoie le dossier, et motif obligatoire.
 *
 * customdata : documents (id de signature => libellé), heading (dossier).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refuse_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);
        $mform->addElement('static', 'refusedossier', get_string('dossier', 'mod_these'), s($this->_customdata['heading']));
        $mform->addElement('static', 'refusehelp', '', get_string('refuse_help', 'mod_these'));
        $boxes = [];
        foreach ($this->_customdata['documents'] as $signatureid => $label) {
            $boxes[] = $mform->createElement('advcheckbox', 'sig' . $signatureid, '', s($label));
            $mform->setDefault('sig' . $signatureid, 1);
        }
        $label = get_string('refusedocuments', 'mod_these');
        $mform->addGroup($boxes, 'refusedocuments', $label, \html_writer::empty_tag('br'), false);
        $mform->addElement('textarea', 'reason', get_string('refusereason', 'mod_these'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');
        $this->add_action_buttons(true, get_string('refusesubmit', 'mod_these'));
    }

    /**
     * Au moins un document et un motif.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $chosen = false;
        foreach (array_keys($this->_customdata['documents']) as $signatureid) {
            $chosen = $chosen || !empty($data['sig' . $signatureid]);
        }
        if (!$chosen) {
            $errors['refusedocuments'] = get_string('signnodocument', 'mod_these');
        }
        if (trim((string) ($data['reason'] ?? '')) === '') {
            $errors['reason'] = get_string('required');
        }
        return $errors;
    }
}
