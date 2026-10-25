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
 * Planning des soutenances : soutenances d'une semaine ou d'un mois, d'une activité ou de toutes
 * celles que suit une activité « Suivi des thèses », et planning mensuel imprimable.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Planning des soutenances.

/**
 * Voir \mod_these\local\planning::planning_source().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param context $context
 * @param int $userid
 * @return stdClass {these, cm, course, context, rights, direct}
 */
function these_planning_source(stdClass $these, stdClass $cm, context $context, $userid) {
    return \mod_these\local\planning::planning_source($these, $cm, $context, $userid);
}

/**
 * Voir \mod_these\local\planning::planning_week_start().
 *
 * @param int $time
 * @return int
 */
function these_planning_week_start($time) {
    return \mod_these\local\planning::planning_week_start($time);
}

/**
 * Voir \mod_these\local\planning::planning_shift_week().
 *
 * @param int $weekstart
 * @param int $weeks
 * @return int
 */
function these_planning_shift_week($weekstart, $weeks) {
    return \mod_these\local\planning::planning_shift_week($weekstart, $weeks);
}

/**
 * Voir \mod_these\local\planning::planning_month_start().
 *
 * @param int $time
 * @return int
 */
function these_planning_month_start($time) {
    return \mod_these\local\planning::planning_month_start($time);
}

/**
 * Voir \mod_these\local\planning::planning_shift_month().
 *
 * @param int $monthstart
 * @param int $months
 * @return int
 */
function these_planning_shift_month($monthstart, $months) {
    return \mod_these\local\planning::planning_shift_month($monthstart, $months);
}

/**
 * Voir \mod_these\local\planning::get_defenses().
 *
 * @param stdClass[] $sources Voir these_planning_source().
 * @param int $from Début de la période (inclus).
 * @param int $to Fin de la période (exclue).
 * @param int $userid
 * @return stdClass[] {dossier, source, student, director, president, jury, confirmed}, dans l'ordre
 *                    chronologique.
 */
function these_get_defenses(array $sources, $from, $to, $userid) {
    return \mod_these\local\planning::get_defenses($sources, $from, $to, $userid);
}

/**
 * Voir \mod_these\local\planning::count_defenses_by_week().
 *
 * @param stdClass[] $sources
 * @param int $weekstart Première semaine.
 * @param int $weeks Nombre de semaines.
 * @param int $userid
 * @return int[] début de semaine => nombre de soutenances (semaines sans soutenance omises).
 */
function these_count_defenses_by_week(array $sources, $weekstart, $weeks, $userid) {
    return \mod_these\local\planning::count_defenses_by_week($sources, $weekstart, $weeks, $userid);
}

/**
 * Voir \mod_these\local\planning::defense_row().
 *
 * @param stdClass $defense Voir these_get_defenses().
 * @return array Textes bruts : day, date, time, student, title, director, president, jury,
 *               location, promotion, devenumber, confirmed, status.
 */
function these_defense_row(stdClass $defense) {
    return \mod_these\local\planning::defense_row($defense);
}

/**
 * Voir \mod_these\local\planning::render_planning().
 *
 * @param stdClass[] $defenses Voir these_get_defenses().
 * @param bool $showpromotion Afficher la promotion (planning de plusieurs activités).
 * @return string
 */
function these_render_planning(array $defenses, $showpromotion) {
    return \mod_these\local\planning::render_planning($defenses, $showpromotion);
}

/**
 * Voir \mod_these\local\planning::planning_document_context().
 *
 * @param stdClass[] $defenses Voir these_get_defenses().
 * @param string $periodlabel Période (« novembre 2026 »).
 * @param stdClass|null $establishment Activité dont reprendre l'établissement.
 * @param stdClass|null $scope Portée dont reprendre les images (voir these_settings_scope()).
 * @param bool $showpromotion
 * @return array
 */
function these_planning_document_context(
    array $defenses,
    $periodlabel,
    ?stdClass $establishment,
    ?stdClass $scope,
    $showpromotion
) {
    return \mod_these\local\planning::planning_document_context($defenses, $periodlabel, $establishment, $scope, $showpromotion);
}

/**
 * Voir \mod_these\local\planning::sample_planning_context().
 *
 * @param stdClass|null $scope
 * @param stdClass|null $activity Activité dont reprendre l'établissement.
 * @return array
 */
function these_sample_planning_context(?stdClass $scope = null, ?stdClass $activity = null) {
    return \mod_these\local\planning::sample_planning_context($scope, $activity);
}
