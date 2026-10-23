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

/**
 * Étape de restauration de la structure d'une instance de mod_these.
 *
 * @package   mod_these
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaure l'arbre décrit par backup_these_activity_structure_step.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_these_activity_structure_step extends restore_activity_structure_step {
    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('these', '/activity/these');
        $paths[] = new restore_path_element('these_email_template', '/activity/these/emailtemplates/emailtemplate');
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('these_dossier', '/activity/these/dossiers/dossier');
            $paths[] = new restore_path_element('these_jury', '/activity/these/dossiers/dossier/jurymembers/jurymember');
            $paths[] = new restore_path_element('these_log', '/activity/these/dossiers/dossier/logs/log');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * @param array $data
     */
    protected function process_these($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('these', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restaure un modèle de courriel personnalisé.
     *
     * @param array $data
     */
    protected function process_these_email_template($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->theseid = $this->get_new_parentid('these');
        $newid = $DB->insert_record('these_email_template', $data);
        $this->set_mapping('these_email_template', $oldid, $newid);
    }

    /**
     * Identifiant d'utilisateur restauré, 0 si l'utilisateur n'a pas été restauré (ou n'existait
     * pas : 0 désigne le système dans l'historique).
     *
     * @param int $olduserid
     * @return int
     */
    protected function map_user($olduserid) {
        if (empty($olduserid)) {
            return 0;
        }
        return (int) $this->get_mappingid('user', $olduserid, 0);
    }

    /**
     * Restaure un dossier. Un dossier dont l'étudiant n'a pas été restauré est ignoré, de même
     * qu'un second dossier pour le même étudiant (index unique instance / étudiant).
     *
     * @param array $data
     */
    protected function process_these_dossier($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->theseid = $this->get_new_parentid('these');
        $data->userid = $this->map_user($data->userid);
        if (!$data->userid || $DB->record_exists('these_dossier', ['theseid' => $data->theseid, 'userid' => $data->userid])) {
            return;
        }
        $data->directorid = $this->map_user($data->directorid);
        $data->returnedby = $this->map_user($data->returnedby);
        $newid = $DB->insert_record('these_dossier', $data);
        $this->set_mapping('these_dossier', $oldid, $newid, true);
    }

    /**
     * Restaure un membre du jury.
     *
     * @param array $data
     */
    protected function process_these_jury($data) {
        global $DB;

        $data = (object) $data;
        $data->dossierid = $this->get_new_parentid('these_dossier');
        if (!$data->dossierid) {
            return;
        }
        $DB->insert_record('these_jury', $data);
    }

    /**
     * Restaure une entrée d'historique.
     *
     * @param array $data
     */
    protected function process_these_log($data) {
        global $DB;

        $data = (object) $data;
        $data->dossierid = $this->get_new_parentid('these_dossier');
        if (!$data->dossierid) {
            return;
        }
        $data->userid = $this->map_user($data->userid);
        $DB->insert_record('these_log', $data);
    }

    /**
     * Restaure les fichiers une fois tous les identifiants connus.
     */
    protected function after_execute() {
        $this->add_related_files('mod_these', 'intro', null);
        foreach (THESE_FILEAREAS as $filearea) {
            $this->add_related_files('mod_these', $filearea, 'these_dossier');
        }
    }
}
