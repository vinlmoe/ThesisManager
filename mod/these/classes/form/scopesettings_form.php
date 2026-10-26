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

use mod_these\local\scopesettings;

/**
 * Paramètres du circuit d'une portée (voir \mod_these\local\scopesettings) : rôles des services,
 * agents DEVE signataires, établissement, université, signataires des documents et contrat type.
 *
 * customdata : roles (id => nom), canroles (bool : modification des rôles permise), fileoptions.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scopesettings_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'rolesheader', get_string('settingsroles', 'mod_these'));
        $mform->addElement('static', 'rolesdesc', '', get_string('settingsroles_desc', 'mod_these'));
        foreach (['deverole', 'libraryrole'] as $name) {
            $mform->addElement('select', $name, get_string($name, 'mod_these'), $this->_customdata['roles']);
            $mform->setType($name, PARAM_INT);
            $mform->addElement('static', $name . 'desc', '', get_string($name . '_desc', 'mod_these'));
            if (empty($this->_customdata['canroles'])) {
                // Choisir le rôle DEVE revient à donner les droits de la DEVE : réservé à qui peut
                // modifier les permissions des rôles dans l'activité.
                $mform->hardFreeze($name);
            }
        }

        $mform->addElement('header', 'agentheader', get_string('settingsdeveagent', 'mod_these'));
        $mform->setExpanded('agentheader');
        $mform->addElement('textarea', 'deveagent', get_string('settingsdeveagent', 'mod_these'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('deveagent', PARAM_TEXT);
        $mform->addElement('static', 'deveagentdesc', '', get_string('settingsdeveagent_desc', 'mod_these'));

        $mform->addElement('header', 'documentsheader', get_string('settingsdocuments', 'mod_these'));
        $mform->setExpanded('documentsheader');
        $mform->addElement('static', 'documentsdesc', '', get_string('settingsdocuments_desc', 'mod_these'));
        foreach (array_keys(scopesettings::text_defaults()) as $name) {
            $mform->addElement('text', $name, get_string('settings' . $name, 'mod_these'), ['size' => 60]);
            $mform->setType($name, in_array($name, scopesettings::EMAILS, true) ? PARAM_EMAIL : PARAM_TEXT);
            $desc = get_string('settings' . $name . '_desc', 'mod_these');
            if ($desc !== '') {
                $mform->addElement('static', $name . 'desc', '', $desc);
            }
        }
        $mform->addElement(
            'filemanager',
            'contracttemplate',
            get_string('settingscontracttemplate', 'mod_these'),
            null,
            $this->_customdata['fileoptions']
        );
        $mform->addElement('static', 'contracttemplatedesc', '', get_string('settingscontracttemplate_desc', 'mod_these'));

        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
