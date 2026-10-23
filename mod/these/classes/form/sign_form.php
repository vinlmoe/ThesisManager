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
 * Signature, en une seule fois, des documents qui attendent un signataire sans compte (président
 * du jury, direction générale, CCEM, présidence de l'université, directeur extérieur), depuis le
 * lien reçu par courriel ou sur place auprès de la DEVE (sign.php).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sign_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $hidden = ['token' => PARAM_ALPHANUM, 'id' => PARAM_INT, 'dossierid' => PARAM_INT, 'slot' => PARAM_ALPHA];
        foreach ($hidden as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }
        $mform->addElement('static', 'documents', get_string('documentstosign', 'mod_these'), $this->_customdata['documents']);
        these_add_signature_fields($mform, $this->_customdata['signername'], get_string('signexternalintro', 'mod_these'));
        $this->add_action_buttons(false, get_string('signdocuments', 'mod_these'));
    }

    /**
     * Signature tracée, nom et certification obligatoires.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        return array_merge(parent::validation($data, $files), these_signature_errors($data));
    }
}
