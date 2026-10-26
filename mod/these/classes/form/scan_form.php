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
 * Dépôt par la DEVE du scan d'un document signé hors du module : document, date de la signature,
 * signataires couverts par le scan (cochés, leur nom est connu du module), fichiers scannés,
 * commentaire.
 *
 * customdata : slots, doctype => [emplacement => libellé] des signatures encore attendues ; une case
 * par emplacement, affichée pour son document seulement. Cocher un emplacement l'inscrit comme signé
 * à la main (PDF du document et suivi des signatures mis à jour).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('select', 'doctype', get_string('scandoctype', 'mod_these'), \mod_these\local\scans::doctype_options());
        $mform->addRule('doctype', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'signeddate', get_string('scansigneddate', 'mod_these'));
        $mform->addRule('signeddate', null, 'required', null, 'client');

        // Signataires couverts par le scan, parmi les signatures encore attendues du document choisi.
        $mform->addElement(
            'static',
            'scanslotsdesc',
            get_string('scanslots', 'mod_these'),
            get_string('scanslots_desc', 'mod_these')
        );
        foreach ($this->_customdata['slots'] ?? [] as $doctype => $slots) {
            foreach ($slots as $slot => $label) {
                $name = 'sign_' . $doctype . '_' . $slot;
                $mform->addElement('advcheckbox', $name, '', $label);
                $mform->hideIf($name, 'doctype', 'neq', $doctype);
                if ($slot === 'dg') {
                    $mform->addElement('advcheckbox', $name . '_delegated', '', get_string('signdelegated', 'mod_these'));
                    $mform->hideIf($name . '_delegated', 'doctype', 'neq', $doctype);
                    $mform->disabledIf($name . '_delegated', $name, 'notchecked');
                }
            }
        }

        $mform->addElement(
            'filemanager',
            'scan',
            get_string('scanfiles', 'mod_these'),
            null,
            these_filemanager_options('signedscan')
        );
        $mform->addHelpButton('scan', 'scanfiles', 'mod_these');
        $mform->addRule('scan', null, 'required', null, 'client');

        $mform->addElement('textarea', 'remark', get_string('scanremark', 'mod_these'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('remark', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('recordscan', 'mod_these'));
    }

    /**
     * Au moins un fichier scanné est requis.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (these_count_draft_files($data['scan']) < 1) {
            $errors['scan'] = get_string('scanrequired', 'mod_these');
        }
        return $errors;
    }
}
