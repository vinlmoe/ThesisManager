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

use mod_these\local\files;

/**
 * Dépôt du manuscrit corrigé par l'étudiant, après une soutenance avec corrections demandées par
 * le jury : manuscrit corrigé et description des corrections apportées.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class corrections_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids(true);

        $mform->addElement(
            'filemanager',
            'corrections',
            get_string('correctedmanuscript', 'mod_these'),
            null,
            files::filemanager_options('corrections')
        );
        $mform->addRule('corrections', null, 'required', null, 'client');
        $mform->addElement('textarea', 'correctionsdone', get_string('correctionsdone', 'mod_these'), ['rows' => 8, 'cols' => 80]);
        $mform->setType('correctionsdone', PARAM_TEXT);
        $mform->addHelpButton('correctionsdone', 'correctionsdone', 'mod_these');
        $mform->addRule('correctionsdone', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('depositcorrections', 'mod_these'));
    }

    /**
     * Manuscrit corrigé et description obligatoires.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (files::count_draft_files($data['corrections']) < 1) {
            $errors['corrections'] = get_string('correctedmanuscriptrequired', 'mod_these');
        }
        if (trim((string) $data['correctionsdone']) === '') {
            $errors['correctionsdone'] = get_string('required');
        }
        return $errors;
    }
}
