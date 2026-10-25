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
 * Libellés, statuts et étapes du circuit.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Libellés, statuts et circuit.

/**
 * Voir \mod_these\local\workflow::all_statuses().
 *
 * @return int[]
 */
function these_all_statuses() {
    return \mod_these\local\workflow::all_statuses();
}

/**
 * Voir \mod_these\local\workflow::status_label().
 *
 * @param int $status
 * @return string
 */
function these_status_label($status) {
    return \mod_these\local\workflow::status_label($status);
}

/**
 * Voir \mod_these\local\workflow::status_badgeclass().
 *
 * @param int $status
 * @return string
 */
function these_status_badgeclass($status) {
    return \mod_these\local\workflow::status_badgeclass($status);
}

/**
 * Voir \mod_these\local\workflow::render_status_badge().
 *
 * @param int $status
 * @return string HTML
 */
function these_render_status_badge($status) {
    return \mod_these\local\workflow::render_status_badge($status);
}

/**
 * Voir \mod_these\local\workflow::phase().
 *
 * @param int $status
 * @return int
 */
function these_phase($status) {
    return \mod_these\local\workflow::phase($status);
}

/**
 * Voir \mod_these\local\workflow::step_actor().
 *
 * @param int $status
 * @return string|null 'student', 'director', 'deve', 'library', ou null si le dossier n'attend
 *                     plus personne (clos, abandonné).
 */
function these_step_actor($status) {
    return \mod_these\local\workflow::step_actor($status);
}

/**
 * Voir \mod_these\local\workflow::actor_label().
 *
 * @param string $actor Voir these_step_actor().
 * @return string
 */
function these_actor_label($actor) {
    return \mod_these\local\workflow::actor_label($actor);
}

/**
 * Voir \mod_these\local\workflow::review_steps().
 *
 * @return array
 */
function these_review_steps() {
    return \mod_these\local\workflow::review_steps();
}

/**
 * Voir \mod_these\local\workflow::status_transitions().
 *
 * @return array statut => statuts suivants possibles
 */
function these_status_transitions() {
    return \mod_these\local\workflow::status_transitions();
}

/**
 * Voir \mod_these\local\workflow::transition_allowed().
 *
 * @param int $from
 * @param int $to
 * @return bool
 */
function these_transition_allowed($from, $to) {
    return \mod_these\local\workflow::transition_allowed($from, $to);
}

/**
 * Voir \mod_these\local\workflow::in_corrections().
 *
 * @param int $status
 * @return bool
 */
function these_in_corrections($status) {
    return \mod_these\local\workflow::in_corrections($status);
}

/**
 * Voir \mod_these\local\workflow::progress_steps().
 *
 * @return array Liste de ['label' => string, 'actor' => string, 'done' => int].
 */
function these_progress_steps() {
    return \mod_these\local\workflow::progress_steps();
}

/**
 * Voir \mod_these\local\workflow::status_rank().
 *
 * @param int $status
 * @return int
 */
function these_status_rank($status) {
    return \mod_these\local\workflow::status_rank($status);
}

/**
 * Voir \mod_these\local\workflow::result_options().
 *
 * @return array
 */
function these_result_options() {
    return \mod_these\local\workflow::result_options();
}

/**
 * Voir \mod_these\local\workflow::result_label().
 *
 * @param int $result
 * @return string
 */
function these_result_label($result) {
    return \mod_these\local\workflow::result_label($result);
}

/**
 * Voir \mod_these\local\workflow::mention_options().
 *
 * @return array clé => libellé
 */
function these_mention_options() {
    return \mod_these\local\workflow::mention_options();
}

/**
 * Voir \mod_these\local\workflow::mention_label().
 *
 * @param string|null $mention
 * @return string
 */
function these_mention_label($mention) {
    return \mod_these\local\workflow::mention_label($mention);
}

/**
 * Voir \mod_these\local\workflow::decision_label().
 *
 * @param stdClass $dossier
 * @return string
 */
function these_decision_label(stdClass $dossier) {
    return \mod_these\local\workflow::decision_label($dossier);
}

/**
 * Voir \mod_these\local\workflow::jury_role_options().
 *
 * @return array
 */
function these_jury_role_options() {
    return \mod_these\local\workflow::jury_role_options();
}
