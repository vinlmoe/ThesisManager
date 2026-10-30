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

use mod_these\local\signatures;
use mod_these\local\bordereau;

/**
 * Validation des études d'un étudiant, saisie par la DEVE sur sa page (voir \mod_these\local\a6) :
 * obtention du DEFV (date et école), et validation de l'A6 (date, ou pas encore validée, et école).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class a6_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids(false, ['userid' => PARAM_INT, 'returnurl' => PARAM_LOCALURL]);
        $mform->addElement('static', 'studentname', get_string('student', 'mod_these'), s($this->_customdata['studentname']));
        $mform->addElement('header', 'defvheader', get_string('defvvalidation', 'mod_these'));
        $mform->setExpanded('defvheader');
        $mform->addElement('date_selector', 'defvdate', get_string('agdefvdate', 'mod_these'), ['optional' => true]);
        $mform->addElement('select', 'defvschool', get_string('bschool', 'mod_these'), bordereau::school_options());
        $mform->addElement('header', 'a6header', get_string('a6validation', 'mod_these'));
        $mform->setExpanded('a6header');
        $mform->addElement('advcheckbox', 'aga6notyet', '', get_string('aga6notyet', 'mod_these'));
        $mform->addElement('date_selector', 'aga6date', get_string('aga6date', 'mod_these'), ['optional' => true]);
        $mform->hideIf('aga6date', 'aga6notyet', 'checked');
        $mform->addElement('select', 'aga6school', get_string('bschool', 'mod_these'), bordereau::school_options());
        $mform->hideIf('aga6school', 'aga6notyet', 'checked');

        $this->add_action_buttons();
    }

    /**
     * Date de validation, ou case « pas encore validée » cochée.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['aga6notyet']) && empty($data['aga6date'])) {
            $errors['aga6date'] = get_string('a6required', 'mod_these');
        }
        return $errors;
    }
}
