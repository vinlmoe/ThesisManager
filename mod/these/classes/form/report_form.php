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
 * Rapport du directeur sur le manuscrit, rédigé directement dans le module : texte imprimé dans le
 * rapport PDF (une page, signature comprise), avis favorable, pièces jointes facultatives.
 * Le rapport peut être enregistré en plusieurs fois et prévisualisé ; il est signé à la validation
 * de la soutenance.
 *
 * customdata : these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('textarea', 'reporttext', get_string('reporttext', 'mod_these'), ['rows' => 22, 'cols' => 90]);
        $mform->setType('reporttext', PARAM_TEXT);
        $mform->addHelpButton('reporttext', 'reporttext', 'mod_these');
        $mform->addElement('advcheckbox', 'reportfavorable', '', get_string('reportfavorable', 'mod_these'));
        $mform->addElement(
            'filemanager',
            'directorreport',
            get_string('directorreport', 'mod_these'),
            null,
            these_filemanager_options('directorreport')
        );
        $mform->addHelpButton('directorreport', 'directorreport', 'mod_these');

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('reportsave', 'mod_these')),
            $mform->createElement('submit', 'previewbutton', get_string('doctplpreview', 'mod_these')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Le rapport doit tenir sur une page avec la signature.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $text = (string) ($data['reporttext'] ?? '');
        if (trim($text) !== '') {
            $error = these_report_error($this->_customdata['these'], $text, !empty($data['reportfavorable']));
            if ($error !== null) {
                $errors['reporttext'] = $error;
            }
        }
        return $errors;
    }
}
