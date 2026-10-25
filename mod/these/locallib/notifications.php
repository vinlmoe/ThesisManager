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
 * Courriels personnalisables, notifications et relances.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Courriels.

/**
 * Voir \mod_these\local\notifications::get_email_definitions().
 *
 * @return array emailkey => ['subjectstring' => string, 'bodystring' => string, 'vars' => string[]]
 */
function these_get_email_definitions() {
    return \mod_these\local\notifications::get_email_definitions();
}

/**
 * Voir \mod_these\local\notifications::get_custom_email_template().
 *
 * @param int $theseid
 * @param string $emailkey
 * @return stdClass|null {subject, body}
 */
function these_get_custom_email_template($theseid, $emailkey) {
    return \mod_these\local\notifications::get_custom_email_template($theseid, $emailkey);
}

/**
 * Voir \mod_these\local\notifications::save_email_template().
 *
 * @param int $theseid
 * @param string $emailkey
 * @param string $subject
 * @param string $body
 * @return void
 */
function these_save_email_template($theseid, $emailkey, $subject, $body) {
    return \mod_these\local\notifications::save_email_template($theseid, $emailkey, $subject, $body);
}

/**
 * Voir \mod_these\local\notifications::render_email_placeholders().
 *
 * @param string $text
 * @param array $vars cle => valeur
 * @return string
 */
function these_render_email_placeholders($text, array $vars) {
    return \mod_these\local\notifications::render_email_placeholders($text, $vars);
}

/**
 * Voir \mod_these\local\notifications::resolve_email_text().
 *
 * @param int $theseid
 * @param string $emailkey
 * @param array $vars
 * @return stdClass {subject, body}
 */
function these_resolve_email_text($theseid, $emailkey, array $vars) {
    return \mod_these\local\notifications::resolve_email_text($theseid, $emailkey, $vars);
}

/**
 * Voir \mod_these\local\notifications::make_external_user().
 *
 * @param string $email
 * @param string $name
 * @return stdClass
 */
function these_make_external_user($email, $name = '') {
    return \mod_these\local\notifications::make_external_user($email, $name);
}

/**
 * Voir \mod_these\local\notifications::get_service_recipients().
 *
 * @param stdClass $these
 * @param context $context
 * @param string $actor 'deve' ou 'library'.
 * @return array Utilisateurs (réels ou factices).
 */
function these_get_service_recipients(stdClass $these, context $context, $actor) {
    return \mod_these\local\notifications::get_service_recipients($these, $context, $actor);
}

/**
 * Voir \mod_these\local\notifications::get_actor_recipients().
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @return array
 */
function these_get_actor_recipients(stdClass $these, context $context, stdClass $dossier) {
    return \mod_these\local\notifications::get_actor_recipients($these, $context, $dossier);
}

/**
 * Voir \mod_these\local\notifications::email_base_vars().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return array
 */
function these_email_base_vars(stdClass $these, stdClass $cm, stdClass $dossier) {
    return \mod_these\local\notifications::email_base_vars($these, $cm, $dossier);
}

/**
 * Voir \mod_these\local\notifications::send_email().
 *
 * @param stdClass $these
 * @param string $emailkey
 * @param array $recipients
 * @param array $vars
 * @param string $attachment Chemin d'une pièce jointe (dans le répertoire temporaire de Moodle).
 * @param string $attachname Nom de la pièce jointe.
 * @param stdClass|null $dossier Dossier dont le courriel cite le titre.
 * @return int Nombre de courriels partis.
 */
function these_send_email(
    stdClass $these,
    $emailkey,
    array $recipients,
    array $vars,
    $attachment = '',
    $attachname = '',
    ?stdClass $dossier = null
) {
    return \mod_these\local\notifications::send_email($these, $emailkey, $recipients, $vars, $attachment, $attachname, $dossier);
}

/**
 * Voir \mod_these\local\notifications::recipient_knows_subject().
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @param stdClass $recipient Compte Moodle, ou destinataire sans compte (voir these_make_external_user()).
 * @return bool
 */
function these_recipient_knows_subject(stdClass $these, stdClass $dossier, stdClass $recipient) {
    return \mod_these\local\notifications::recipient_knows_subject($these, $dossier, $recipient);
}

/**
 * Voir \mod_these\local\notifications::notify_actor().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int Nombre de courriels partis.
 */
function these_notify_actor(stdClass $these, stdClass $cm, stdClass $dossier) {
    return \mod_these\local\notifications::notify_actor($these, $cm, $dossier);
}

/**
 * Voir \mod_these\local\notifications::notify_returned().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string $target 'student' ou 'director'.
 * @param string $comment
 * @return int
 */
function these_notify_returned(stdClass $these, stdClass $cm, stdClass $dossier, $target, $comment) {
    return \mod_these\local\notifications::notify_returned($these, $cm, $dossier, $target, $comment);
}

/**
 * Voir \mod_these\local\notifications::notify_subject_active().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int
 */
function these_notify_subject_active(stdClass $these, stdClass $cm, stdClass $dossier) {
    return \mod_these\local\notifications::notify_subject_active($these, $cm, $dossier);
}

/**
 * Voir \mod_these\local\notifications::notify_closed().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int
 */
function these_notify_closed(stdClass $these, stdClass $cm, stdClass $dossier) {
    return \mod_these\local\notifications::notify_closed($these, $cm, $dossier);
}

/**
 * Voir \mod_these\local\notifications::get_student_and_director().
 *
 * @param stdClass $dossier
 * @return array
 */
function these_get_student_and_director(stdClass $dossier) {
    return \mod_these\local\notifications::get_student_and_director($dossier);
}

// Relances.

/**
 * Voir \mod_these\local\notifications::get_dossiers_needing_reminder().
 *
 * @param int|null $now
 * @return array Dossiers indexés par id, avec le délai de leur activité (reminderdays).
 */
function these_get_dossiers_needing_reminder($now = null) {
    return \mod_these\local\notifications::get_dossiers_needing_reminder($now);
}

/**
 * Voir \mod_these\local\notifications::send_reminder().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return bool
 */
function these_send_reminder(stdClass $these, stdClass $cm, stdClass $dossier) {
    return \mod_these\local\notifications::send_reminder($these, $cm, $dossier);
}
