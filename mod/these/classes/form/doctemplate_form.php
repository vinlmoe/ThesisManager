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
 * Modification du modèle d'un document de l'activité (DEVE) : le source HTML avec ses balises
 * Mustache, un aperçu PDF avec des données fictives (dans un nouvel onglet), l'enregistrement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doctemplate_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_ALPHA);

        $mform->addElement('textarea', 'content', get_string('doctplcontent', 'mod_these'), [
            'rows' => 32,
            'cols' => 120,
            'spellcheck' => 'false',
            'style' => 'font-family:monospace;font-size:12px;width:100%;',
        ]);
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', null, 'required', null, 'client');
        $mform->addHelpButton('content', 'doctplcontent', 'mod_these');

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'previewbutton', get_string('doctplpreview', 'mod_these'), ['formtarget' => '_blank']),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Le modèle doit pouvoir produire un document avec les données d'exemple.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($errors['content'])) {
            $error = these_doctemplate_error(
                $this->_customdata['scope'],
                $data['type'],
                (string) $data['content'],
                $this->_customdata['activity'] ?? null
            );
            if ($error !== null) {
                $errors['content'] = $error;
            }
        }
        return $errors;
    }
}
