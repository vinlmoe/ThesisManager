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
 * Façades des classes de mod_thesesynthesis (classes/local/) : liens vers les activités « Gestion
 * des thèses » de plusieurs promotions, et lecture, pour l'utilisateur connecté, des dossiers qu'il
 * suit dans chacune (directeur, DEVE, bibliothèque) et des jurys où il siège.
 *
 * L'activité ne stocke aucun droit ni aucune donnée de thèse : tout est relu à l'affichage dans les
 * activités liées, et seulement là où l'utilisateur a toujours ses droits à la source.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Voir \mod_thesesynthesis\local\links::get_links().
 *
 * @param int $synthesisid
 * @return array thesecmid => enregistrement
 */
function thesesynthesis_get_links($synthesisid) {
    return \mod_thesesynthesis\local\links::get_links($synthesisid);
}

/**
 * Voir \mod_thesesynthesis\local\links::set_links().
 *
 * @param int $synthesisid
 * @param int[] $thesecmids
 * @return void
 */
function thesesynthesis_set_links($synthesisid, array $thesecmids) {
    \mod_thesesynthesis\local\links::set_links($synthesisid, $thesecmids);
}

/**
 * Voir \mod_thesesynthesis\local\links::get_available_activities().
 *
 * @param int $userid
 * @return array thesecmid => {thesecmid, thesename, coursename, visible, coursevisible}
 */
function thesesynthesis_get_available_activities($userid) {
    return \mod_thesesynthesis\local\links::get_available_activities($userid);
}

/**
 * Voir \mod_thesesynthesis\local\links::get_active_links().
 *
 * @param int $synthesisid
 * @param int $userid
 * @return array Liste d'objets {cm, these, course, context, rights, direct}
 */
function thesesynthesis_get_active_links($synthesisid, $userid) {
    return \mod_thesesynthesis\local\links::get_active_links($synthesisid, $userid);
}

/**
 * Voir \mod_thesesynthesis\local\dossiers::load_users().
 *
 * @param stdClass[] $dossiers
 * @return stdClass[] userid => utilisateur (absent s'il n'existe plus).
 */
function thesesynthesis_load_users(array $dossiers) {
    return \mod_thesesynthesis\local\dossiers::load_users($dossiers);
}

/**
 * Voir \mod_thesesynthesis\local\dossiers::get_dossiers().
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param int $userid
 * @param array $filters {status (int|null), search (string), cmid (int), todo (bool)}
 * @param int $limitfrom Rang du premier dossier de la page.
 * @param int $limitnum Dossiers par page (0 : tous).
 * @return array Liste d'objets {dossier, link, student, director, actor, todo, knowssubject}
 */
function thesesynthesis_get_dossiers(array $links, $userid, array $filters = [], $limitfrom = 0, $limitnum = 0) {
    return \mod_thesesynthesis\local\dossiers::get_dossiers($links, $userid, $filters, $limitfrom, $limitnum);
}

/**
 * Voir \mod_thesesynthesis\local\dossiers::count_dossiers().
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param int $userid
 * @param array $filters Voir thesesynthesis_get_dossiers().
 * @return int
 */
function thesesynthesis_count_dossiers(array $links, $userid, array $filters = []) {
    return \mod_thesesynthesis\local\dossiers::count_dossiers($links, $userid, $filters);
}

/**
 * Voir \mod_thesesynthesis\local\signatures::get_signatures().
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param stdClass $user
 * @return array mine, awaited : listes d'objets {dossier, link, student, knowssubject, documents
 *               (libellés), since (première demande), url, label}
 */
function thesesynthesis_get_signatures(array $links, stdClass $user) {
    return \mod_thesesynthesis\local\signatures::get_signatures($links, $user);
}

/**
 * Voir \mod_thesesynthesis\local\signatures::signature_row().
 *
 * @param array $groups Lignes, indexées par dossier.
 * @param stdClass $dossier
 * @param stdClass $link
 * @param int $userid
 * @param int $requesttime
 * @return stdClass La ligne du dossier.
 */
function thesesynthesis_signature_row(array &$groups, stdClass $dossier, stdClass $link, $userid, $requesttime) {
    return \mod_thesesynthesis\local\signatures::signature_row($groups, $dossier, $link, $userid, $requesttime);
}

/**
 * Voir \mod_thesesynthesis\local\signatures::fill_signature_rows().
 *
 * @param array $rows Lignes (voir thesesynthesis_signature_row()), modifiées en place.
 * @param int $userid
 * @return void
 */
function thesesynthesis_fill_signature_rows(array $rows, $userid) {
    \mod_thesesynthesis\local\signatures::fill_signature_rows($rows, $userid);
}

/**
 * Voir \mod_thesesynthesis\local\dossiers::get_juries().
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param stdClass $user
 * @return array Liste d'objets {member, dossier, link, student}
 */
function thesesynthesis_get_juries(array $links, stdClass $user) {
    return \mod_thesesynthesis\local\dossiers::get_juries($links, $user);
}

/**
 * Voir \mod_thesesynthesis\local\navigation::back_home().
 *
 * @param stdClass $cm
 * @return stdClass {url, label}
 */
function thesesynthesis_back_home(stdClass $cm) {
    return \mod_thesesynthesis\local\navigation::back_home($cm);
}

/**
 * Voir \mod_thesesynthesis\local\navigation::render_navlinks().
 *
 * @param stdClass $cm
 * @param context $context
 * @param stdClass|null $back Cible du retour (voir \mod_these\local\backlinks::target()) ; null : l'accueil de l'activité.
 * @return string HTML
 */
function thesesynthesis_render_navlinks(stdClass $cm, context $context, ?stdClass $back = null) {
    return \mod_thesesynthesis\local\navigation::render_navlinks($cm, $context, $back);
}

/**
 * Voir \mod_thesesynthesis\local\search::normalise().
 *
 * @param string $text
 * @return string
 */
function thesesynthesis_normalise($text) {
    return \mod_thesesynthesis\local\search::normalise($text);
}

/**
 * Voir \mod_thesesynthesis\local\dossiers::defense_year().
 *
 * @param stdClass $dossier
 * @return int 0 si aucune date.
 */
function thesesynthesis_defense_year(stdClass $dossier) {
    return \mod_thesesynthesis\local\dossiers::defense_year($dossier);
}

/**
 * Voir \mod_thesesynthesis\local\search::find().
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param int $userid
 * @param array $filters {text, cmid, status (int|null), year (int), answers (questionid => valeur)}
 * @param array $questions Questions filtrables (voir these_get_questions()).
 * @return array Liste d'objets {dossier, link, student, director, canopen, answers, year,
 *                     knowssubject}
 */
function thesesynthesis_search(array $links, $userid, array $filters, array $questions = []) {
    return \mod_thesesynthesis\local\search::find($links, $userid, $filters, $questions);
}

/**
 * Voir \mod_thesesynthesis\local\search::render_search_results().
 *
 * @param array $rows Voir thesesynthesis_search().
 * @param array $questions Questions du questionnaire (voir these_get_questions()).
 * @return string HTML
 */
function thesesynthesis_render_search_results(array $rows, array $questions) {
    return \mod_thesesynthesis\local\search::render_search_results($rows, $questions);
}
