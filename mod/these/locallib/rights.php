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
 * Rôles de service et droits sur les dossiers.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Droits.

/**
 * Voir \mod_these\local\rights::get_rights().
 *
 * @param context $context
 * @param stdClass|null $dossier
 * @param int $userid
 * @return stdClass {owner, student, director, deve, library, viewall}
 */
function these_get_rights(context $context, ?stdClass $dossier, $userid) {
    return \mod_these\local\rights::get_rights($context, $dossier, $userid);
}

/**
 * Voir \mod_these\local\rights::require_view().
 *
 * @param context $context
 * @return stdClass Droits de l'utilisateur courant sur l'activité (voir these_get_rights()).
 */
function these_require_view(context $context) {
    return \mod_these\local\rights::require_view($context);
}

/**
 * Voir \mod_these\local\rights::require_service().
 *
 * @param context $context
 * @param string $actor 'deve' ou 'library'.
 * @return stdClass Droits de l'utilisateur courant (voir these_get_rights()).
 */
function these_require_service(context $context, $actor) {
    return \mod_these\local\rights::require_service($context, $actor);
}

/**
 * Voir \mod_these\local\rights::can_view_dossier().
 *
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_can_view_dossier(stdClass $rights) {
    return \mod_these\local\rights::can_view_dossier($rights);
}

/**
 * Voir \mod_these\local\rights::can_view_filearea().
 *
 * @param string $filearea
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_can_view_filearea($filearea, stdClass $rights) {
    return \mod_these\local\rights::can_view_filearea($filearea, $rights);
}

/**
 * Voir \mod_these\local\rights::user_can_act().
 *
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_user_can_act(stdClass $dossier, stdClass $rights) {
    return \mod_these\local\rights::user_can_act($dossier, $rights);
}

/** Durée de validité d'un lien à jeton (signature sans compte, accès du jury), en secondes. */
define('THESE_TOKEN_LIFETIME', 60 * DAYSECS);

/**
 * Voir \mod_these\local\rights::new_token().
 *
 * @return string
 */
function these_new_token() {
    return \mod_these\local\rights::new_token();
}

/**
 * Voir \mod_these\local\rights::token_hash().
 *
 * @param string $token
 * @return string SHA-256 en hexadécimal.
 */
function these_token_hash($token) {
    return \mod_these\local\rights::token_hash($token);
}

/**
 * Voir \mod_these\local\rights::user_knows_subject().
 *
 * @param stdClass $dossier
 * @param context $context Contexte de l'activité du dossier.
 * @param int $userid
 * @return bool
 */
function these_user_knows_subject(stdClass $dossier, context $context, $userid) {
    return \mod_these\local\rights::user_knows_subject($dossier, $context, $userid);
}

/**
 * Voir \mod_these\local\rights::user_in_jury().
 *
 * @param int $dossierid
 * @param int $userid
 * @return bool
 */
function these_user_in_jury($dossierid, $userid) {
    return \mod_these\local\rights::user_in_jury($dossierid, $userid);
}

/**
 * Voir \mod_these\local\rights::subject_field().
 *
 * @param stdClass $dossier
 * @param string $field title, titleen, summary ou keywords.
 * @param context $context Contexte de l'activité du dossier.
 * @param int|null $userid Utilisateur (l'utilisateur courant par défaut).
 * @return string Texte brut, à échapper par l'appelant.
 */
function these_subject_field(stdClass $dossier, $field, context $context, $userid = null) {
    return \mod_these\local\rights::subject_field($dossier, $field, $context, $userid);
}
