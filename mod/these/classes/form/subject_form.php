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
 * Formulaire de dépôt de sujet de thèse (phase 1, étape 1), reprenant le formulaire papier de
 * l'établissement : identité et année d'études, titre (confidentiel ou non), mots-clés,
 * directeur de thèse (et directeur extérieur éventuel), engagements de l'étudiant et signature.
 *
 * L'étudiant peut l'enregistrer en brouillon ou le soumettre à son directeur ; les champs, les
 * engagements et la signature ne sont exigés qu'à la soumission.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subject_form extends \moodleform {
    /** Champs exigés à la soumission. */
    const REQUIRED_FIELDS = ['title', 'keywords', 'studyyear'];

    /** Années d'études proposées. */
    const STUDY_YEARS = ['A2', 'A3', 'A4', 'A5', 'A6'];

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('header', 'subjectheader', get_string('subject', 'mod_these'));
        $mform->setExpanded('subjectheader');
        $years = [];
        foreach (self::STUDY_YEARS as $year) {
            $years[] = $mform->createElement('radio', 'studyyear', '', $year, $year);
        }
        $mform->addGroup($years, 'studyyeargroup', get_string('studyyear', 'mod_these'), ' ', false);
        $mform->addElement('textarea', 'title', get_string('subjecttitle', 'mod_these'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'confidential', '', get_string('confidential', 'mod_these'));
        $mform->addElement('textarea', 'titleen', get_string('subjecttitleen', 'mod_these'), ['rows' => 2, 'cols' => 80]);
        $mform->setType('titleen', PARAM_TEXT);
        $mform->addElement('text', 'keywords', get_string('subjectkeywords', 'mod_these'), ['size' => 80]);
        $mform->setType('keywords', PARAM_TEXT);
        $mform->addHelpButton('keywords', 'subjectkeywords', 'mod_these');
        $hetop = (string) get_config('mod_these', 'hetopurl');
        if ($hetop !== '') {
            $mform->addElement('static', 'hetop', '', \html_writer::link(
                $hetop,
                get_string('hetoplink', 'mod_these'),
                ['target' => '_blank', 'rel' => 'noopener']
            ));
        }
        $mform->addElement('textarea', 'summary', get_string('subjectsummary', 'mod_these'), ['rows' => 6, 'cols' => 80]);
        $mform->setType('summary', PARAM_TEXT);
        $mform->addHelpButton('summary', 'subjectsummary', 'mod_these');

        $mform->addElement('header', 'directorheader', get_string('subjectapproval', 'mod_these'));
        $mform->setExpanded('directorheader');
        $mform->addElement('advcheckbox', 'externaldirector', '', get_string('externaldirector', 'mod_these'));
        $mform->addHelpButton('externaldirector', 'externaldirector', 'mod_these');
        $mform->addElement('select', 'directorid', get_string('director', 'mod_these'), $this->_customdata['directors']);
        $mform->addHelpButton('directorid', 'director', 'mod_these');
        $mform->addElement('text', 'directortitle', get_string('directortitle', 'mod_these'), ['size' => 10]);
        $mform->setType('directortitle', PARAM_TEXT);
        $mform->addHelpButton('directortitle', 'directortitle', 'mod_these');
        foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
            $mform->addElement('text', $field, get_string($field, 'mod_these'), ['size' => 64]);
            $mform->setType($field, $field === 'extdirectoremail' ? PARAM_RAW_TRIMMED : PARAM_TEXT);
            $mform->hideIf($field, 'externaldirector', 'notchecked');
        }
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

        $mform->addElement('header', 'commitheader', get_string('commitments', 'mod_these'));
        $mform->setExpanded('commitheader');
        $mform->addElement('advcheckbox', 'commitguide', '', get_string('commitguide', 'mod_these'));
        $mform->addElement('advcheckbox', 'commitduplicate', '', get_string('commitduplicate', 'mod_these'));
        $mform->addElement('advcheckbox', 'commitethics', '', get_string('commitethics', 'mod_these'));
        $notice = get_string('subjectnotice', 'mod_these');
        if (trim($notice) !== '') {
            $mform->addElement('static', 'subjectnotice', '', \html_writer::div(
                format_text($notice, FORMAT_MOODLE),
                'small text-muted'
            ));
        }

        these_add_signature_fields($mform, $this->_customdata['signername'], get_string('signsubjectintro', 'mod_these'));

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
     * Les champs, les engagements et la signature ne sont exigés qu'à la soumission : un brouillon
     * peut rester incomplet.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['submitsubject'])) {
            return $errors;
        }
        foreach (self::REQUIRED_FIELDS as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $errors[$field === 'studyyear' ? 'studyyeargroup' : $field] = get_string('required');
            }
        }
        $keywords = these_split_keywords((string) ($data['keywords'] ?? ''));
        if (!isset($errors['keywords']) && (count($keywords) < 3 || count($keywords) > 5)) {
            $errors['keywords'] = get_string('keywordscount', 'mod_these');
        }
        if (empty($data['directorid'])) {
            $errors['directorid'] = get_string('required');
        }
        if (!empty($data['externaldirector'])) {
            foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
                if (trim((string) $data[$field]) === '') {
                    $errors[$field] = get_string('required');
                }
            }
            if (!isset($errors['extdirectoremail']) && !validate_email($data['extdirectoremail'])) {
                $errors['extdirectoremail'] = get_string('invalidemail');
            }
        }
        foreach (['commitguide', 'commitduplicate'] as $field) {
            if (empty($data[$field])) {
                $errors[$field] = get_string('commitrequired', 'mod_these');
            }
        }
        return array_merge($errors, these_signature_errors($data));
    }
}
