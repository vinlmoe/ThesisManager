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
 * Listes de dossiers et rendu HTML des pages.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Listes.

/**
 * Voir \mod_these\local\rendering::get_dossiers().
 *
 * @param int $theseid
 * @param array $filters status (int|null), directorid (int|null), search (string), queue (string[]
 *                       acteurs dont on veut les dossiers en attente), queuedirectorid (int),
 *                       confidentialsubjects (bool : la recherche porte aussi sur le titre des thèses
 *                       confidentielles, pour qui connaît leur sujet).
 * @return array Dossiers avec les champs de nom de l'étudiant et du directeur.
 */
function these_get_dossiers($theseid, array $filters = []) {
    return \mod_these\local\rendering::get_dossiers($theseid, $filters);
}

/**
 * Voir \mod_these\local\rendering::count_by_status().
 *
 * @param int $theseid
 * @param int|null $directorid Limite aux dossiers d'un directeur.
 * @return array statut => nombre
 */
function these_count_by_status($theseid, $directorid = null) {
    return \mod_these\local\rendering::count_by_status($theseid, $directorid);
}

/**
 * Voir \mod_these\local\rendering::paginate().
 *
 * @param array $items
 * @param int $page
 * @param moodle_url $baseurl
 * @param int $perpage
 * @return array [éléments de la page, barre de pagination HTML]
 */
function these_paginate(array $items, $page, moodle_url $baseurl, $perpage = THESE_LIST_PERPAGE) {
    return \mod_these\local\rendering::paginate($items, $page, $baseurl, $perpage);
}

// Rendu.

/**
 * Voir \mod_these\local\rendering::render_navlinks().
 *
 * @param stdClass $cm
 * @param context $context
 * @param stdClass|null $back
 * @return string HTML
 */
function these_render_navlinks(stdClass $cm, context $context, ?stdClass $back = null) {
    return \mod_these\local\rendering::render_navlinks($cm, $context, $back);
}

/**
 * Voir \mod_these\local\rendering::render_detail_section().
 *
 * @param string $heading
 * @param array $rows libellé => valeur HTML
 * @return string HTML
 */
function these_render_detail_section($heading, array $rows) {
    return \mod_these\local\rendering::render_detail_section($heading, $rows);
}

/**
 * Voir \mod_these\local\rendering::render_jury().
 *
 * @param array $members
 * @param bool $showemail
 * @param bool $staff Vue de la DEVE : éligibilité et notification des membres.
 * @return string HTML
 */
function these_render_jury(array $members, $showemail = true, $staff = false) {
    return \mod_these\local\rendering::render_jury($members, $showemail, $staff);
}

/**
 * Voir \mod_these\local\rendering::render_history().
 *
 * @param int $dossierid
 * @param bool $staffview Vue du personnel (directeur, DEVE, bibliothèque) : sinon, le motif d'un
 *                        renvoi de la DEVE au directeur, qui ne s'adresse pas à l'étudiant, est
 *                        masqué (voir these_render_dossier()).
 * @param bool $knowssubject L'utilisateur connaît le sujet : sinon, l'ancien titre cité par un
 *                           changement de sujet d'une thèse confidentielle est masqué.
 * @return string HTML
 */
function these_render_history($dossierid, $staffview = true, $knowssubject = true) {
    return \mod_these\local\rendering::render_history($dossierid, $staffview, $knowssubject);
}

/**
 * Voir \mod_these\local\rendering::get_dossier_actions().
 *
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @return array libellé => moodle_url
 */
function these_get_dossier_actions(stdClass $cm, stdClass $dossier, stdClass $rights) {
    return \mod_these\local\rendering::get_dossier_actions($cm, $dossier, $rights);
}

/**
 * Voir \mod_these\local\rendering::render_dossier().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @param bool $withactions Affiche les boutons d'action (pas sur les pages d'action elles-mêmes).
 * @return string HTML
 */
function these_render_dossier(
    stdClass $these,
    stdClass $cm,
    context $context,
    stdClass $dossier,
    stdClass $rights,
    $withactions = true
) {
    return \mod_these\local\rendering::render_dossier($these, $cm, $context, $dossier, $rights, $withactions);
}

/**
 * Voir \mod_these\local\rendering::register_row().
 *
 * @param stdClass $dossier
 * @param context $context Contexte de l'activité.
 * @param stdClass|null $student
 * @param stdClass|null $director
 * @return array Colonnes de l'export (voir export.php).
 */
function these_register_row(stdClass $dossier, context $context, ?stdClass $student, ?stdClass $director) {
    return \mod_these\local\rendering::register_row($dossier, $context, $student, $director);
}

/**
 * Voir \mod_these\local\rendering::render_embargo().
 *
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @return string HTML
 */
function these_render_embargo(stdClass $dossier, stdClass $rights) {
    return \mod_these\local\rendering::render_embargo($dossier, $rights);
}

/**
 * Voir \mod_these\local\rendering::rank_reached().
 *
 * @param stdClass $dossier
 * @param int $status
 * @return bool
 */
function these_rank_reached(stdClass $dossier, $status) {
    return \mod_these\local\rendering::rank_reached($dossier, $status);
}

/**
 * Voir \mod_these\local\rendering::render_dossier_table().
 *
 * @param array $dossiers Voir these_get_dossiers().
 * @param stdClass $cm
 * @param array $options Voir \mod_these\output\dossier_table.
 * @return string HTML
 */
function these_render_dossier_table(array $dossiers, stdClass $cm, array $options = []) {
    return \mod_these\local\rendering::render_dossier_table($dossiers, $cm, $options);
}

/**
 * Voir \mod_these\local\rendering::primary_action().
 *
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param stdClass $rights
 * @return array|null
 */
function these_primary_action(stdClass $cm, stdClass $dossier, stdClass $rights) {
    return \mod_these\local\rendering::primary_action($cm, $dossier, $rights);
}
