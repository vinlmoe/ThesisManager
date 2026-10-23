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
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Fiche de sujet (phase 1, étape 1) : titre, résumé, mots-clés, directeur de thèse et pièces
 * jointes facultatives. L'étudiant peut l'enregistrer en brouillon ou la soumettre à son
 * directeur ; les champs ne sont exigés qu'à la soumission.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subject_form extends \moodleform {
    /** Champs exigés à la soumission. */
    const REQUIRED_FIELDS = ['title', 'summary', 'keywords'];

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('textarea', 'title', get_string('subjecttitle', 'mod_these'), ['rows' => 2, 'cols' => 80]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addElement('textarea', 'titleen', get_string('subjecttitleen', 'mod_these'), ['rows' => 2, 'cols' => 80]);
        $mform->setType('titleen', PARAM_TEXT);
        $mform->addElement('textarea', 'summary', get_string('subjectsummary', 'mod_these'), ['rows' => 10, 'cols' => 80]);
        $mform->setType('summary', PARAM_TEXT);
        $mform->addHelpButton('summary', 'subjectsummary', 'mod_these');
        $mform->addElement('text', 'keywords', get_string('subjectkeywords', 'mod_these'), ['size' => 80]);
        $mform->setType('keywords', PARAM_TEXT);
        $mform->addHelpButton('keywords', 'subjectkeywords', 'mod_these');

        $mform->addElement('select', 'directorid', get_string('director', 'mod_these'), $this->_customdata['directors']);
        $mform->addHelpButton('directorid', 'director', 'mod_these');
        $mform->addElement('text', 'codirectorname', get_string('codirector', 'mod_these'), ['size' => 64]);
        $mform->setType('codirectorname', PARAM_TEXT);
        $mform->addHelpButton('codirectorname', 'codirector', 'mod_these');

        $mform->addElement(
            'filemanager',
            'subjectdocs',
            get_string('subjectdocs', 'mod_these'),
            null,
            these_filemanager_options('subjectdocs')
        );
        $mform->addHelpButton('subjectdocs', 'subjectdocs', 'mod_these');

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'submitsubject', get_string($this->_customdata['submitlabel'], 'mod_these'));
        if (!empty($this->_customdata['allowdraft'])) {
            $buttons[] = $mform->createElement('submit', 'savedraft', get_string('savedraft', 'mod_these'));
        }
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Les champs ne sont exigés qu'à la soumission : un brouillon peut rester incomplet.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['submitsubject'])) {
            foreach (self::REQUIRED_FIELDS as $field) {
                if (trim((string) $data[$field]) === '') {
                    $errors[$field] = get_string('required');
                }
            }
            if (empty($data['directorid'])) {
                $errors['directorid'] = get_string('required');
            }
        }
        return $errors;
    }
}
