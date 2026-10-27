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
 * Paramètres du circuit d'une portée (voir \mod_these\local\scopesettings) :
 * agents DEVE signataires et de la bibliothèque, établissement, université, signataires des documents
 * et contrat type.
 *
 * customdata : fileoptions, agents (deveagent et libraryagent : identifiant => nom
 * des comptes proposés, voir \mod_these\local\scopesettings::candidate_users()).
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


        $mform->addElement('header', 'agentheader', get_string('settingsdeveagent', 'mod_these'));
        $mform->setExpanded('agentheader');
        $this->add_agents_field('deveagent');

        $mform->addElement('header', 'libraryagentheader', get_string('settingslibraryagent', 'mod_these'));
        $mform->setExpanded('libraryagentheader');
        $this->add_agents_field('libraryagent');

        $mform->addElement('header', 'defenseheader', get_string('settingsdefenses', 'mod_these'));
        $mform->setExpanded('defenseheader');
        $mform->addElement('text', 'defenseduration', get_string('settingsdefenseduration', 'mod_these'), ['size' => 5]);
        $mform->setType('defenseduration', PARAM_INT);
        $mform->addRule('defenseduration', null, 'required', null, 'client');
        $mform->addRule('defenseduration', null, 'numeric', null, 'client');
        $mform->addHelpButton('defenseduration', 'settingsdefenseduration', 'mod_these');
        $mform->addElement(
            'textarea',
            'defenserooms',
            get_string('settingsdefenserooms', 'mod_these'),
            ['rows' => 5, 'cols' => 60]
        );
        $mform->setType('defenserooms', PARAM_TEXT);
        $mform->addHelpButton('defenserooms', 'settingsdefenserooms', 'mod_these');

        $mform->addElement('header', 'digestheader', get_string('settingsdigest', 'mod_these'));
        $mform->addElement(
            'advcheckbox',
            'weeklydigest',
            get_string('settingsweeklydigest', 'mod_these'),
            get_string('settingsweeklydigestlabel', 'mod_these')
        );
        $mform->addHelpButton('weeklydigest', 'settingsweeklydigest', 'mod_these');

        $mform->addElement('header', 'documentsheader', get_string('settingsdocuments', 'mod_these'));
        $mform->setExpanded('documentsheader');
        foreach (array_keys(scopesettings::text_defaults()) as $name) {
            $mform->addElement('text', $name, get_string('settings' . $name, 'mod_these'), ['size' => 60]);
            $mform->setType($name, in_array($name, scopesettings::EMAILS, true) ? PARAM_EMAIL : PARAM_TEXT);
            if (get_string_manager()->string_exists('settings' . $name . '_help', 'mod_these')) {
                $mform->addHelpButton($name, 'settings' . $name, 'mod_these');
            }
        }
        $mform->addElement(
            'filemanager',
            'contracttemplate',
            get_string('settingscontracttemplate', 'mod_these'),
            null,
            $this->_customdata['fileoptions']
        );
        $mform->addHelpButton('contracttemplate', 'settingscontracttemplate', 'mod_these');

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Choix multiple, avec recherche, des agents parmi les comptes proposés (customdata agents).
     *
     * @param string $name 'deveagent' ou 'libraryagent'.
     * @return void
     */
    protected function add_agents_field($name) {
        $mform = $this->_form;
        $mform->addElement(
            'autocomplete',
            $name,
            get_string('settings' . $name, 'mod_these'),
            $this->_customdata['agents'][$name] ?? [],
            [
                'multiple' => true,
                'noselectionstring' => get_string('settingsagentsnone', 'mod_these'),
                'placeholder' => get_string('search'),
            ]
        );
        $mform->setType($name, PARAM_USERNAME);
        $mform->addHelpButton($name, 'settings' . $name, 'mod_these');
    }

    /**
     * Durée d'une soutenance : au moins une minute.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((int) ($data['defenseduration'] ?? 0) < 1) {
            $errors['defenseduration'] = get_string('settingsdefensedurationinvalid', 'mod_these');
        }
        return $errors;
    }
}
