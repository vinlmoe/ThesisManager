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
 * Dépôt des documents de soutenance (phase 2, étape 1) : manuscrit, autres documents, date et
 * lieu proposés, composition du jury. Le dépôt peut être préparé en plusieurs fois avant d'être
 * soumis au directeur ; le manuscrit, la date et le jury ne sont exigés qu'à la soumission.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defense_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('header', 'documentsheader', get_string('defensedocuments', 'mod_these'));
        $mform->setExpanded('documentsheader');
        $mform->addElement(
            'filemanager',
            'manuscript',
            get_string('manuscript', 'mod_these'),
            null,
            these_filemanager_options('manuscript')
        );
        $mform->addHelpButton('manuscript', 'manuscript', 'mod_these');
        $mform->addElement(
            'filemanager',
            'defensedocs',
            get_string('defensedocs', 'mod_these'),
            null,
            these_filemanager_options('defensedocs')
        );
        $mform->addHelpButton('defensedocs', 'defensedocs', 'mod_these');

        $mform->addElement('header', 'dateheader', get_string('defenseproposal', 'mod_these'));
        $mform->setExpanded('dateheader');
        $mform->addElement('date_time_selector', 'proposeddate', get_string('proposeddate', 'mod_these'));
        $mform->addElement('text', 'defenselocation', get_string('defenselocation', 'mod_these'), ['size' => 64]);
        $mform->setType('defenselocation', PARAM_TEXT);

        these_add_jury_fields($this, $mform, $this->_customdata['jurycount'] ?? 0);

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'submitdefense', get_string($this->_customdata['submitlabel'], 'mod_these'));
        $buttons[] = $mform->createElement('submit', 'savedraft', get_string('savedraft', 'mod_these'));
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * À la soumission : manuscrit déposé, date à venir, lieu renseigné et jury valide.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['submitdefense'])) {
            return $errors;
        }
        if (these_count_draft_files($data['manuscript']) < 1) {
            $errors['manuscript'] = get_string('manuscriptrequired', 'mod_these');
        }
        if ((int) $data['proposeddate'] <= time()) {
            $errors['proposeddate'] = get_string('datemustbefuture', 'mod_these');
        }
        if (trim((string) $data['defenselocation']) === '') {
            $errors['defenselocation'] = get_string('required');
        }
        $juryerror = these_validate_jury(these_extract_submitted_jury($data), $this->_customdata['minjury']);
        if ($juryerror !== null) {
            $errors['juryname[0]'] = $juryerror;
        }
        return $errors;
    }
}
