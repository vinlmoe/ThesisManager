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

/**
 * Import des validations de l'A6 par la DEVE (voir \mod_these\local\a6::import()) : fichier Excel,
 * OpenDocument ou CSV, avec simulation.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class a6import_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Le classeur est chargé entièrement en mémoire par PhpSpreadsheet : taille bornée.
        $mform->addElement('filepicker', 'a6file', get_string('a6file', 'mod_these'), null, [
            'accepted_types' => ['.xlsx', '.xls', '.ods', '.csv', '.txt'],
            'maxbytes' => 2 * 1024 * 1024,
        ]);
        $mform->addRule('a6file', null, 'required', null, 'client');
        $mform->addHelpButton('a6file', 'a6file', 'mod_these');
        $mform->addElement('advcheckbox', 'dryrun', get_string('importdryrun', 'mod_these'));
        $mform->setDefault('dryrun', 1);

        $this->add_action_buttons(true, get_string('import', 'mod_these'));
    }
}
