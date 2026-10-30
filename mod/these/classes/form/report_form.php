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

use mod_these\local\files;

/**
 * Rapport du directeur sur le manuscrit : fichier déposé (rédigé à partir du modèle des paramètres du
 * circuit, s'il y en a un) et avis favorable. Le rapport est obligatoire pour valider la soutenance.
 *
 * customdata : templateurl (lien de téléchargement du modèle, ou null).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids(true);

        if (!empty($this->_customdata['templateurl'])) {
            $mform->addElement('static', 'reporttemplate', get_string('reporttemplate', 'mod_these'), \html_writer::link(
                $this->_customdata['templateurl'],
                get_string('reporttemplatedownload', 'mod_these'),
                ['class' => 'btn btn-secondary']
            ));
        }
        $mform->addElement(
            'filemanager',
            'directorreport',
            get_string('directorreport', 'mod_these'),
            null,
            files::filemanager_options('directorreport')
        );
        $mform->addHelpButton('directorreport', 'directorreport', 'mod_these');
        $mform->addElement('advcheckbox', 'reportfavorable', '', get_string('reportfavorable', 'mod_these'));

        $this->add_action_buttons(true, get_string('reportsave', 'mod_these'));
    }

    /**
     * Le rapport déposé.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (files::count_draft_files($data['directorreport'] ?? 0) < 1) {
            $errors['directorreport'] = get_string('directorreportrequired', 'mod_these');
        }
        return $errors;
    }
}
