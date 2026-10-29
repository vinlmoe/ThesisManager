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
        $paths[] = new restore_path_element('these_doctemplate', '/activity/these/doctemplates/doctemplate');
        $paths[] = new restore_path_element('these_setting', '/activity/these/settings/setting');
        $paths[] = new restore_path_element('these_griditem', '/activity/these/griditems/griditem');
        $paths[] = new restore_path_element('these_question', '/activity/these/questions/question');
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('these_dossier', '/activity/these/dossiers/dossier');
            $paths[] = new restore_path_element('these_jury', '/activity/these/dossiers/dossier/jurymembers/jurymember');
            $paths[] = new restore_path_element('these_answer', '/activity/these/dossiers/dossier/answers/answer');
            $paths[] = new restore_path_element(
                'these_subjecthistory',
                '/activity/these/dossiers/dossier/subjecthistory/previoussubject'
            );
            $paths[] = new restore_path_element('these_log', '/activity/these/dossiers/dossier/logs/log');
            $paths[] = new restore_path_element(
                'these_signature',
                '/activity/these/dossiers/dossier/signatures/signature'
            );
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
     * Restaure un modèle de document modifié.
     *
     * @param array $data
     */
    protected function process_these_doctemplate($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->theseid = $this->get_new_parentid('these');
        $newid = $DB->insert_record('these_doctemplate', $data);
        $this->set_mapping('these_doctemplate', $oldid, $newid);
    }

    /**
     * Restaure un paramètre du circuit (voir \mod_these\local\scopesettings).
     *
     * @param array $data
     */
    protected function process_these_setting($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->theseid = $this->get_new_parentid('these');
        $data->synthesisid = 0;
        $DB->insert_record('these_setting', $data);
    }

    /**
     * Restaure un élément de la grille de conformité.
     *
     * @param array $data
     */
    protected function process_these_griditem($data) {
        global $DB;

        $data = (object) $data;
        $data->theseid = $this->get_new_parentid('these');
        $DB->insert_record('these_griditem', $data);
    }

    /**
     * Restaure une question du questionnaire préalable au jury.
     *
     * @param array $data
     */
    protected function process_these_question($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->theseid = $this->get_new_parentid('these');
        $data->synthesisid = 0;
        $newid = $DB->insert_record('these_question', $data);
        $this->set_mapping('these_question', $oldid, $newid);
    }

    /**
     * Restaure une réponse au questionnaire. Une réponse à une question de l'activité « Suivi des
     * thèses » est conservée si la question existe sur ce site (restauration sur le même site).
     *
     * @param array $data
     */
    protected function process_these_answer($data) {
        global $DB;

        $data = (object) $data;
        $data->dossierid = $this->get_new_parentid('these_dossier');
        $questionid = $this->get_mappingid('these_question', $data->questionid, 0);
        if (!$questionid && $DB->record_exists_select('these_question', 'id = ? AND synthesisid > 0', [$data->questionid])) {
            $questionid = $data->questionid;
        }
        if (!$data->dossierid || !$questionid) {
            return;
        }
        $data->questionid = $questionid;
        $DB->insert_record('these_answer', $data);
    }

    /**
     * Restaure un sujet précédent (changement de sujet).
     *
     * @param array $data
     */
    protected function process_these_subjecthistory($data) {
        global $DB;

        $data = (object) $data;
        $data->dossierid = $this->get_new_parentid('these_dossier');
        if (!$data->dossierid) {
            return;
        }
        $data->directorid = $this->map_user($data->directorid);
        $data->changedby = $this->map_user($data->changedby);
        $DB->insert_record('these_subjecthistory', $data);
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
        // L'annuaire du jury est propre au site : un enseignant absent de cet annuaire devient un
        // membre saisi. Le jeton d'accès n'est pas sauvegardé (secret d'accès sans compte).
        if (!empty($data->jurorid) && !$DB->record_exists('these_juror', ['id' => $data->jurorid])) {
            $data->jurorid = 0;
        }
        $data->eligibleby = $this->map_user($data->eligibleby ?? 0);
        unset($data->accesstoken);
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
     * Restaure une signature.
     *
     * @param array $data
     */
    protected function process_these_signature($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->dossierid = $this->get_new_parentid('these_dossier');
        if (!$data->dossierid) {
            return;
        }
        $data->userid = $this->map_user($data->userid);
        $data->verifiedby = $this->map_user($data->verifiedby ?? 0);
        $data->validatedby = $this->map_user($data->validatedby ?? 0);
        $data->token = null;
        $newid = $DB->insert_record('these_signature', $data);
        $this->set_mapping('these_signature', $oldid, $newid, true);
    }

    /**
     * Restaure les fichiers une fois tous les identifiants connus.
     */
    protected function after_execute() {
        $this->add_related_files('mod_these', 'intro', null);
        $this->add_related_files('mod_these', 'docimages', null);
        $this->add_related_files('mod_these', 'contracttemplate', null);
        $this->add_related_files('mod_these', 'reporttemplate', null);
        foreach (THESE_FILEAREAS as $filearea) {
            $this->add_related_files('mod_these', $filearea, 'these_dossier');
        }
        $this->add_related_files('mod_these', 'signature', 'these_signature');
        $this->add_related_files('mod_these', 'sealed', 'these_dossier');
        $this->add_related_files('mod_these', 'universityseal', 'these_dossier');
    }
}
