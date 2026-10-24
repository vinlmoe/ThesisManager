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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_these\local\jurors;

/**
 * Fiche d'un enseignant de l'annuaire du jury : titre, nom, prénom, adresse, statut, établissement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class juror_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'jurorid');
        $mform->setType('jurorid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('select', 'title', get_string('jurortitle', 'mod_these'), jurors::titles());
        $mform->addElement('text', 'lastname', get_string('lastname'), ['size' => 40]);
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', null, 'required', null, 'client');
        $mform->addElement('text', 'firstname', get_string('firstname'), ['size' => 40]);
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', null, 'required', null, 'client');
        $mform->addElement('text', 'email', get_string('email'), ['size' => 40]);
        $mform->setType('email', PARAM_RAW_TRIMMED);
        $mform->addElement('select', 'status', get_string('jurystatus', 'mod_these'), jurors::statuses());
        $mform->addHelpButton('status', 'jurystatus', 'mod_these');
        $mform->addElement('text', 'institution', get_string('juryinstitution', 'mod_these'), ['size' => 60]);
        $mform->setType('institution', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'active', '', get_string('juroractive', 'mod_these'));
        $mform->setDefault('active', 1);

        $this->add_action_buttons();
    }

    /**
     * Adresse valide.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string) $data['email']) !== '' && !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }
        return $errors;
    }
}
