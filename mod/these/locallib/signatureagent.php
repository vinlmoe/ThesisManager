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
 * Signature enregistrée et agent DEVE signataire.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Signature enregistrée et agent DEVE signataire.

/**
 * Voir \mod_these\local\signatureagent::get_saved_signature().
 *
 * @param int $userid
 * @return string|null Contenu PNG, null si l'utilisateur n'en a pas.
 */
function these_get_saved_signature($userid) {
    return \mod_these\local\signatureagent::get_saved_signature($userid);
}

/**
 * Voir \mod_these\local\signatureagent::save_signature_image().
 *
 * @param int $userid
 * @param string $png
 * @return void
 */
function these_save_signature_image($userid, $png) {
    return \mod_these\local\signatureagent::save_signature_image($userid, $png);
}

/**
 * Voir \mod_these\local\signatureagent::delete_saved_signature().
 *
 * @param int $userid
 * @return void
 */
function these_delete_saved_signature($userid) {
    return \mod_these\local\signatureagent::delete_saved_signature($userid);
}

/**
 * Voir \mod_these\local\signatureagent::agent_slots().
 *
 * @return string[]
 */
function these_agent_slots() {
    return \mod_these\local\signatureagent::agent_slots();
}

/**
 * Voir \mod_these\local\signatureagent::deve_agent().
 *
 * @return stdClass|null
 */
function these_deve_agent() {
    return \mod_these\local\signatureagent::deve_agent();
}

/**
 * Voir \mod_these\local\signatureagent::signs_slot_at_validation().
 *
 * @param string $slot
 * @param int $userid
 * @return bool
 */
function these_signs_slot_at_validation($slot, $userid) {
    return \mod_these\local\signatureagent::signs_slot_at_validation($slot, $userid);
}

/**
 * Voir \mod_these\local\signatureagent::agent_document_due().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return bool
 */
function these_agent_document_due(stdClass $dossier, $doctype) {
    return \mod_these\local\signatureagent::agent_document_due($dossier, $doctype);
}

/**
 * Voir \mod_these\local\signatureagent::request_agent_signatures().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string[] $doctypes
 * @return int Nombre de documents en attente de sa signature.
 */
function these_request_agent_signatures(stdClass $these, stdClass $cm, stdClass $dossier, array $doctypes) {
    return \mod_these\local\signatureagent::request_agent_signatures($these, $cm, $dossier, $doctypes);
}

/**
 * Voir \mod_these\local\signatureagent::get_user_pending_signatures().
 *
 * @param stdClass $user
 * @return stdClass[] Signatures en attente, avec dossier, activité et module de cours.
 */
function these_get_user_pending_signatures(stdClass $user) {
    return \mod_these\local\signatureagent::get_user_pending_signatures($user);
}
