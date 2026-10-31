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
 * Library of interface functions and constants for mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function thesesynthesis_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : MOD_PURPOSE_OTHER;
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_thesesynthesis in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_thesesynthesis_mod_form|null $mform
 * @return int The id of the newly inserted record.
 */
function thesesynthesis_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    return $DB->insert_record('thesesynthesis', $moduleinstance);
}

/**
 * Updates an instance of mod_thesesynthesis in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_thesesynthesis_mod_form|null $mform
 * @return bool True on success.
 */
function thesesynthesis_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('thesesynthesis', $moduleinstance);
}

/**
 * Removes an instance of mod_thesesynthesis from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function thesesynthesis_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('thesesynthesis', ['id' => $id])) {
        return false;
    }

    // Paramètres partagés avec les activités suivies (les images sont supprimées avec le contexte).
    // Les réponses données aux questions sont supprimées avec elles.
    $questionids = $DB->get_fieldset_select('these_question', 'id', 'synthesisid = ?', [$id]);
    if ($questionids) {
        [$insql, $params] = $DB->get_in_or_equal($questionids);
        $DB->delete_records_select('these_answer', "questionid $insql", $params);
    }
    $DB->delete_records('these_question', ['synthesisid' => $id]);
    $DB->delete_records('these_doctemplate', ['synthesisid' => $id]);
    $DB->delete_records('these_setting', ['synthesisid' => $id]);
    $DB->delete_records('these_griditem', ['synthesisid' => $id]);
    $DB->delete_records('thesesynthesis_link', ['synthesisid' => $id]);
    $DB->delete_records('thesesynthesis', ['id' => $id]);
    // Les activités qu'elle suivait reprennent leurs propres paramètres.
    \mod_these\local\scopesettings::reset_cache();

    return true;
}

/*
 * Rappels (callbacks) de mod_these.
 *
 * mod_these ne connaît pas mod_thesesynthesis : il interroge les modules présents par des rappels
 * génériques (get_plugins_with_function() et component_callback()) pour savoir si les paramètres
 * partagés d'une activité « Gestion des thèses » sont tenus ailleurs, et où. Un autre module peut
 * fournir les mêmes rappels, préfixés de son nom. Le contrat (voir aussi INSTALL.md) :
 *
 * - <module>_these_settings_scope(stdClass $these) : portée des paramètres partagés en vigueur pour
 *   une activité « Gestion des thèses » ({theseid, synthesisid, shared, component, contextid, cmid,
 *   name}, voir \mod_these\local\doctemplates::local_scope()), ou null si le module n'en fournit pas ;
 *   mod_these appelle chaque module qui déclare la fonction et retient la première portée renvoyée ;
 * - <module>_these_settings_scope_for_cm(stdClass $cm) : portée d'un module de cours du module
 *   lui-même (une activité de suivi), ou null ; appelé sur le module du cm (« mod_<modname> ») ;
 * - <module>_these_scope_cmids(stdClass $scope) : modules de cours des activités « Gestion des
 *   thèses » que la portée couvre (int[]) ; appelé sur $scope->component ;
 * - <module>_these_scope_urls(stdClass $scope) : pages de l'activité qui tient la portée, moodle_url
 *   indexées par home (accueil) et settings (paramètres du circuit) ; appelé sur $scope->component ;
 * - <module>_these_require_view(stdClass $cm, context $context) : droit de consulter les documents
 *   communs (liste du corps enseignant) depuis un module de cours du module ; lève une exception
 *   sinon ; appelé sur le module du cm.
 */

/**
 * Rappel de mod_these : portée partagée en vigueur pour une activité « Gestion des thèses ».
 *
 * @param stdClass $these
 * @return stdClass|null Voir \mod_thesesynthesis\local\scope::for_these().
 */
function thesesynthesis_these_settings_scope(stdClass $these) {
    // Module présent mais pas encore installé : aucune table à interroger.
    if (!get_config('mod_thesesynthesis', 'version')) {
        return null;
    }
    return \mod_thesesynthesis\local\scope::for_these($these);
}

/**
 * Rappel de mod_these : portée d'une activité « Suivi des thèses » désignée par son module de cours.
 *
 * @param stdClass $cm
 * @return stdClass|null Voir \mod_thesesynthesis\local\scope::for_synthesis().
 */
function thesesynthesis_these_settings_scope_for_cm(stdClass $cm) {
    if ($cm->modname !== 'thesesynthesis') {
        return null;
    }
    return \mod_thesesynthesis\local\scope::for_synthesis($cm->instance);
}

/**
 * Rappel de mod_these : modules de cours des activités « Gestion des thèses » suivies dans une portée.
 *
 * @param stdClass $scope
 * @return int[]
 */
function thesesynthesis_these_scope_cmids(stdClass $scope) {
    return \mod_thesesynthesis\local\scope::linked_cmids($scope);
}

/**
 * Rappel de mod_these : accueil et paramètres du circuit de l'activité de suivi d'une portée.
 *
 * @param stdClass $scope
 * @return moodle_url[] {home, settings}
 */
function thesesynthesis_these_scope_urls(stdClass $scope) {
    return \mod_thesesynthesis\local\scope::urls($scope);
}

/**
 * Rappel de mod_these : droit de consulter les documents communs depuis une activité de suivi.
 *
 * @param stdClass $cm
 * @param context $context
 * @return void
 */
function thesesynthesis_these_require_view(stdClass $cm, context $context) {
    require_capability('mod/thesesynthesis:view', $context);
}
