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

/**
 * Signature sur place (sign.php) : avant d'ouvrir la page de signature, la DEVE certifie avoir
 * vérifié l'identité du signataire présent. La vérification est inscrite à l'historique à son nom.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class identity_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids(true, ['slot' => PARAM_ALPHA]);
        $mform->addElement('header', 'identityheader', get_string('signidentity', 'mod_these'));
        $mform->addElement('static', 'identityintro', '', get_string('signidentity_desc', 'mod_these'));
        $mform->addElement('advcheckbox', 'identityverified', '', get_string('signidentityverified', 'mod_these'));
        $this->add_action_buttons(true, get_string('signidentityopen', 'mod_these'));
    }

    /**
     * La case de vérification est obligatoire.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['identityverified'])) {
            $errors['identityverified'] = get_string('signidentityrequired', 'mod_these');
        }
        return $errors;
    }
}
