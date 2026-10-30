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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Documents qui attendent la signature de l'utilisateur connecté (agent DEVE, membre du jury ou
 * autre signataire invité ayant un compte), signés en une fois : documents groupés par dossier, une
 * case par document, décochée (le signataire coche ce qu'il a consulté), puis le bloc de signature
 * (signature enregistrée reprise d'office).
 *
 * customdata : documents (id de signature => libellé HTML), groups (liste de {heading, ids,
 * refuseurl : refus de signer d'un agent DEVE, ou null}), signername.
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

        $mform->addElement('static', 'documentsgroup', get_string('documentstosign', 'mod_these'), '');
        foreach ($this->_customdata['groups'] ?? [] as $i => $group) {
            $boxes = [];
            foreach ($group['ids'] as $signatureid) {
                $label = $this->_customdata['documents'][$signatureid];
                $boxes[] = $mform->createElement('advcheckbox', 'sig' . $signatureid, '', $label);
            }
            $mform->addGroup(
                $boxes,
                'dossiergroup' . $i,
                \html_writer::tag('strong', s($group['heading'])),
                \html_writer::empty_tag('br'),
                false
            );
            if (!empty($group['refuseurl'])) {
                $mform->addElement('static', 'refuse' . $i, '', \html_writer::link(
                    $group['refuseurl'],
                    get_string('refusesign', 'mod_these'),
                    ['class' => 'btn btn-sm btn-outline-danger']
                ));
            }
        }
        signatures::add_signature_fields(
            $mform,
            $this->_customdata['signername'],
            get_string('signmyintro', 'mod_these'),
            true,
            !empty($this->_customdata['editablename'])
        );
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
        return array_merge($errors, signatures::signature_errors($data));
    }
}
