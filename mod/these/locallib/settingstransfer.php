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
 * Transfert des paramètres entre activités et par fichier.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Transfert des paramètres.

/** Format du fichier de paramètres exporté. */
define('THESE_SETTINGS_FORMAT', 'mod_these_settings');

/**
 * Voir \mod_these\local\settingstransfer::settings_activity_fields().
 *
 * @return array champ => PARAM_*
 */
function these_settings_activity_fields() {
    return \mod_these\local\settingstransfer::settings_activity_fields();
}

/**
 * Voir \mod_these\local\settingstransfer::settings_groups().
 *
 * @return string[]
 */
function these_settings_groups() {
    return \mod_these\local\settingstransfer::settings_groups();
}

/**
 * Voir \mod_these\local\settingstransfer::export_settings().
 *
 * @param stdClass $these
 * @param context $context
 * @param string[] $groups Voir these_settings_groups().
 * @return array
 */
function these_export_settings(stdClass $these, context $context, array $groups) {
    return \mod_these\local\settingstransfer::export_settings($these, $context, $groups);
}

/**
 * Voir \mod_these\local\settingstransfer::parse_settings_file().
 *
 * @param string $json
 * @return array|null Données (voir these_export_settings()), null si le fichier n'en est pas un.
 */
function these_parse_settings_file($json) {
    return \mod_these\local\settingstransfer::parse_settings_file($json);
}

/**
 * Voir \mod_these\local\settingstransfer::import_settings().
 *
 * @param stdClass $these Activité de destination (mise à jour).
 * @param context $context
 * @param array $data Voir these_export_settings().
 * @param string[] $groups Groupes à transférer.
 * @return stdClass {activity (bool), emails, griditems, questions, doctemplates, docimages (nombres),
 *     skipped (string[])}
 */
function these_import_settings(stdClass $these, context $context, array $data, array $groups) {
    return \mod_these\local\settingstransfer::import_settings($these, $context, $data, $groups);
}

/**
 * Voir \mod_these\local\settingstransfer::settings_transfer_sources().
 *
 * @param stdClass $these Activité courante (exclue).
 * @param int $userid
 * @return array id de l'activité => libellé « cours : activité »
 */
function these_settings_transfer_sources(stdClass $these, $userid) {
    return \mod_these\local\settingstransfer::settings_transfer_sources($these, $userid);
}
