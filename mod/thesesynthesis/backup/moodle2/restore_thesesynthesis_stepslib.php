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
 * Étape de restauration de la structure d'une instance de mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaure l'arbre décrit par backup_thesesynthesis_activity_structure_step.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_thesesynthesis_activity_structure_step extends restore_activity_structure_step {
    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];

        $paths[] = new restore_path_element('thesesynthesis', '/activity/thesesynthesis');
        $paths[] = new restore_path_element('thesesynthesis_link', '/activity/thesesynthesis/links/link');
        $paths[] = new restore_path_element('these_doctemplate', '/activity/thesesynthesis/doctemplates/doctemplate');
        $paths[] = new restore_path_element('these_setting', '/activity/thesesynthesis/settings/setting');
        $paths[] = new restore_path_element('these_griditem', '/activity/thesesynthesis/griditems/griditem');
        $paths[] = new restore_path_element('these_question', '/activity/thesesynthesis/questions/question');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * @param array $data
     */
    protected function process_thesesynthesis($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record('thesesynthesis', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restaure un lien vers une activité « Gestion des thèses ».
     *
     * L'identifiant de course-module est conservé tel quel à ce stade : l'activité mod_these visée
     * peut n'être restaurée qu'après celle-ci. La correspondance est appliquée par after_restore(),
     * une fois toutes les activités du cours restaurées.
     *
     * @param array $data
     */
    protected function process_thesesynthesis_link($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->synthesisid = $this->get_new_parentid('thesesynthesis');

        $DB->insert_record('thesesynthesis_link', $data);
    }

    /**
     * Restaure un paramètre partagé (modèle de document, élément de grille, question) : rattaché
     * à l'instance restaurée.
     *
     * @param string $table
     * @param array $data
     * @return void
     */
    protected function restore_shared($table, $data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->theseid = 0;
        $data->synthesisid = $this->get_new_parentid('thesesynthesis');
        $newid = $DB->insert_record($table, $data);
        $this->set_mapping($table, $oldid, $newid);
    }

    /**
     * Restaure un modèle de document modifié.
     *
     * @param array $data
     */
    protected function process_these_doctemplate($data) {
        $this->restore_shared('these_doctemplate', $data);
    }

    /**
     * Restaure un paramètre du circuit.
     *
     * @param array $data
     */
    protected function process_these_setting($data) {
        $this->restore_shared('these_setting', $data);
    }

    /**
     * Restaure un élément de la grille de conformité.
     *
     * @param array $data
     */
    protected function process_these_griditem($data) {
        $this->restore_shared('these_griditem', $data);
    }

    /**
     * Restaure une question du questionnaire préalable au jury.
     *
     * @param array $data
     */
    protected function process_these_question($data) {
        $this->restore_shared('these_question', $data);
    }

    /**
     * Rattache les fichiers de la description, les images des modèles et le contrat type.
     */
    protected function after_execute() {
        $this->add_related_files('mod_thesesynthesis', 'intro', null);
        $this->add_related_files('mod_thesesynthesis', 'docimages', null);
        $this->add_related_files('mod_thesesynthesis', 'contracttemplate', null);
        $this->add_related_files('mod_thesesynthesis', 'reporttemplate', null);
    }

    /**
     * Fait pointer les liens vers les activités mod_these réellement restaurées.
     *
     * Un lien dont l'activité d'origine n'a pas été restaurée n'est conservé que sur le même site,
     * où l'identifiant de course-module d'origine désigne toujours la bonne activité ; ailleurs il
     * désignerait une activité sans rapport et est donc supprimé.
     */
    protected function after_restore() {
        global $DB;

        $synthesisid = $this->task->get_activityid();
        if (!$synthesisid) {
            return;
        }

        foreach ($DB->get_records('thesesynthesis_link', ['synthesisid' => $synthesisid]) as $link) {
            $newcmid = $this->get_mappingid('course_module', $link->thesecmid);

            if (!$newcmid) {
                $stillvalid = $this->task->is_samesite()
                    && get_coursemodule_from_id('these', $link->thesecmid, 0, false, IGNORE_MISSING);
                if (!$stillvalid) {
                    $DB->delete_records('thesesynthesis_link', ['id' => $link->id]);
                }
                continue;
            }

            if ((int) $newcmid === (int) $link->thesecmid) {
                continue;
            }

            // L'index (synthesisid, thesecmid) est unique : si la cible est déjà liée, ce lien
            // ferait doublon.
            if (
                $DB->record_exists(
                    'thesesynthesis_link',
                    ['synthesisid' => $synthesisid, 'thesecmid' => $newcmid]
                )
            ) {
                $DB->delete_records('thesesynthesis_link', ['id' => $link->id]);
                continue;
            }

            $DB->set_field('thesesynthesis_link', 'thesecmid', $newcmid, ['id' => $link->id]);
        }
    }
}
