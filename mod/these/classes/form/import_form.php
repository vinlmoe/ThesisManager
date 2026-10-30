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

require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Import CSV de dossiers existants : à la mise en service du module, les sujets déjà enregistrés
 * (dossiers « dormants », parfois depuis plusieurs années) doivent pouvoir être repris sans faire
 * repasser chaque étudiant par tout le circuit de la phase 1.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids();

        $mform->addElement('static', 'importhelp', '', get_string('importhelp', 'mod_these'));
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('csvfile', 'mod_these'),
            null,
            // Le classeur est chargé entièrement en mémoire par PhpSpreadsheet : taille bornée.
            ['accepted_types' => ['.csv', '.txt', '.xlsx', '.xls', '.ods'], 'maxbytes' => 2 * 1024 * 1024]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addElement(
            'select',
            'delimiter',
            get_string('csvdelimiter', 'tool_uploaduser'),
            \csv_import_reader::get_delimiter_list()
        );
        $mform->setDefault('delimiter', 'semicolon');
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), \core_text::get_encodings());
        $mform->setDefault('encoding', 'UTF-8');
        $mform->addElement('advcheckbox', 'dryrun', get_string('importdryrun', 'mod_these'));
        $mform->setDefault('dryrun', 1);

        $this->add_action_buttons(true, get_string('import', 'mod_these'));
    }
}
