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
 * Étape de sauvegarde de la structure d'une instance de mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Décrit l'arbre XML (thesesynthesis.xml) d'une instance de mod_thesesynthesis.
 *
 * L'activité n'a pas de données de suivi propres : toute la synthèse est lue dans les activités
 * mod_these liées. Sont sauvegardés l'instance, la liste de ces liens et les paramètres partagés
 * avec les activités suivies : modèles des documents et leurs images, grille de conformité,
 * questionnaire préalable au jury.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_thesesynthesis_activity_structure_step extends backup_activity_structure_step {
    /**
     * Construit la structure sauvegardée.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $thesesynthesis = new backup_nested_element('thesesynthesis', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
        ]);

        $links = new backup_nested_element('links');
        $link = new backup_nested_element('link', ['id'], ['thesecmid', 'timecreated']);

        $doctemplates = new backup_nested_element('doctemplates');
        $doctemplate = new backup_nested_element('doctemplate', ['id'], ['doctype', 'content', 'timemodified']);

        $griditems = new backup_nested_element('griditems');
        $griditem = new backup_nested_element('griditem', ['id'], [
            'itemkey', 'section', 'label', 'conditional', 'sortorder', 'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'qtype', 'label', 'options', 'required', 'sortorder', 'timemodified',
        ]);

        $thesesynthesis->add_child($links);
        $links->add_child($link);
        $thesesynthesis->add_child($doctemplates);
        $doctemplates->add_child($doctemplate);
        $thesesynthesis->add_child($griditems);
        $griditems->add_child($griditem);
        $thesesynthesis->add_child($questions);
        $questions->add_child($question);

        $thesesynthesis->set_source_table('thesesynthesis', ['id' => backup::VAR_ACTIVITYID]);
        $link->set_source_table('thesesynthesis_link', ['synthesisid' => backup::VAR_PARENTID], 'id');
        $doctemplate->set_source_table('these_doctemplate', ['synthesisid' => backup::VAR_PARENTID], 'id ASC');
        $griditem->set_source_table('these_griditem', ['synthesisid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $question->set_source_table('these_question', ['synthesisid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');

        $thesesynthesis->annotate_files('mod_thesesynthesis', 'intro', null);
        $thesesynthesis->annotate_files('mod_thesesynthesis', 'docimages', null);

        return $this->prepare_activity_structure($thesesynthesis);
    }
}
