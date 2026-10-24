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
 * Documents qui attendent la signature de l'utilisateur connecté (agent DEVE, membre du jury ou
 * autre signataire invité ayant un compte), signés en une fois : une case par document, cochée
 * d'office, puis le bloc de signature (signature enregistrée reprise d'office).
 *
 * customdata : documents (id de signature => libellé HTML), signername.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mysignatures_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $boxes = [];
        foreach ($this->_customdata['documents'] as $signatureid => $label) {
            $boxes[] = $mform->createElement('advcheckbox', 'sig' . $signatureid, '', $label);
            $mform->setDefault('sig' . $signatureid, 1);
        }
        $mform->addGroup($boxes, 'documentsgroup', get_string('documentstosign', 'mod_these'),
            \html_writer::empty_tag('br'), false);
        these_add_signature_fields($mform, $this->_customdata['signername'], get_string('signmyintro', 'mod_these'));
        $this->add_action_buttons(false, get_string('signdocuments', 'mod_these'));
    }

    /**
     * Au moins un document, signature et certification.
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
            $errors['documentsgroup'] = get_string('signnodocument', 'mod_these');
        }
        return array_merge($errors, these_signature_errors($data));
    }
}
