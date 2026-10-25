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
 * Documents officiels et signatures.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Documents signés.

/** Engagement de l'étudiant : avoir pris connaissance du guide des thèses. */
define('THESE_COMMIT_GUIDE', 1);
/** Engagement de l'étudiant : avoir vérifié que le sujet n'a pas déjà été traité. */
define('THESE_COMMIT_DUPLICATE', 2);
/** Engagement de l'étudiant : soumettre le protocole au comité d'éthique (si nécessaire). */
define('THESE_COMMIT_ETHICS', 4);

/**
 * Voir \mod_these\local\signatures::document_definitions().
 *
 * @return array doctype => ['slots' => string[], 'staffonly' => bool]
 */
function these_document_definitions() {
    return \mod_these\local\signatures::document_definitions();
}

/**
 * Voir \mod_these\local\signatures::document_slots().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return string[]
 */
function these_document_slots(stdClass $dossier, $doctype) {
    return \mod_these\local\signatures::document_slots($dossier, $doctype);
}

/**
 * Voir \mod_these\local\signatures::external_slots().
 *
 * @return string[]
 */
function these_external_slots() {
    return \mod_these\local\signatures::external_slots();
}

/**
 * Voir \mod_these\local\signatures::document_label().
 *
 * @param string $doctype
 * @return string
 */
function these_document_label($doctype) {
    return \mod_these\local\signatures::document_label($doctype);
}

/**
 * Voir \mod_these\local\signatures::slot_label().
 *
 * @param string $slot
 * @return string
 */
function these_slot_label($slot) {
    return \mod_these\local\signatures::slot_label($slot);
}

/**
 * Voir \mod_these\local\signatures::document_content().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @param string $slot Emplacement de signature concerné, '' pour le document entier.
 * @return array
 */
function these_document_content(stdClass $dossier, $doctype, $slot = '') {
    return \mod_these\local\signatures::document_content($dossier, $doctype, $slot);
}

/**
 * Voir \mod_these\local\signatures::document_hash().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @param string $slot Voir these_document_content().
 * @return string
 */
function these_document_hash(stdClass $dossier, $doctype, $slot = '') {
    return \mod_these\local\signatures::document_hash($dossier, $doctype, $slot);
}

/**
 * Voir \mod_these\local\signatures::document_available().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return bool
 */
function these_document_available(stdClass $dossier, $doctype) {
    return \mod_these\local\signatures::document_available($dossier, $doctype);
}

/**
 * Voir \mod_these\local\signatures::can_view_document().
 *
 * @param string $doctype
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_can_view_document($doctype, stdClass $rights) {
    return \mod_these\local\signatures::can_view_document($doctype, $rights);
}

/**
 * Voir \mod_these\local\signatures::has_signature().
 *
 * @param int $dossierid
 * @param string $doctype
 * @return bool
 */
function these_has_signature($dossierid, $doctype) {
    return \mod_these\local\signatures::has_signature($dossierid, $doctype);
}

/**
 * Voir \mod_these\local\signatures::get_agreement().
 *
 * @param stdClass $dossier
 * @return array {defvdate, mobility, a6date, a6notyet} ; tableau vide s'il n'a pas été renseigné.
 */
function these_get_agreement(stdClass $dossier) {
    return \mod_these\local\signatures::get_agreement($dossier);
}

/**
 * Voir \mod_these\local\signatures::school_options().
 *
 * @return array
 */
function these_school_options() {
    return \mod_these\local\signatures::school_options();
}

/**
 * Voir \mod_these\local\signatures::get_bordereau().
 *
 * @param stdClass $dossier
 * @return array Voir these_bordereau_fields() ; tableau vide s'il n'a pas été renseigné.
 */
function these_get_bordereau(stdClass $dossier) {
    return \mod_these\local\signatures::get_bordereau($dossier);
}

/**
 * Voir \mod_these\local\signatures::bordereau_fields().
 *
 * @return string[]
 */
function these_bordereau_fields() {
    return \mod_these\local\signatures::bordereau_fields();
}

/** Durée de l'embargo de diffusion qu'un étudiant peut demander, en années après la soutenance. */
define('THESE_EMBARGO_YEARS', 5);

/**
 * Voir \mod_these\local\signatures::embargo_until().
 *
 * @param stdClass $dossier
 * @return int 0 sans embargo, ou tant que la soutenance n'a pas de date.
 */
function these_embargo_until(stdClass $dossier) {
    return \mod_these\local\signatures::embargo_until($dossier);
}

/** Longueur maximale d'un résumé du bordereau, espaces compris. */
define('THESE_BORDEREAU_SUMMARY_MAX', 1700);

/** Nombre de mots-clés du bordereau. */
define('THESE_BORDEREAU_KEYWORDS', 8);

/**
 * Voir \mod_these\local\signatures::grid_items().
 *
 * @return array rubrique => [clé => conditionnel (bool)]
 */
function these_grid_items() {
    return \mod_these\local\signatures::grid_items();
}

/**
 * Voir \mod_these\local\signatures::grid_sections().
 *
 * @return string[]
 */
function these_grid_sections() {
    return \mod_these\local\signatures::grid_sections();
}

/**
 * Voir \mod_these\local\signatures::get_grid_items().
 *
 * @param stdClass|null $scope null : éléments d'origine, sans enregistrement (aperçus).
 * @return array rubrique => [clé => {itemkey, section, label, conditional, sortorder}]
 */
function these_get_grid_items(?stdClass $scope) {
    return \mod_these\local\signatures::get_grid_items($scope);
}

/**
 * Voir \mod_these\local\signatures::save_grid_items().
 *
 * @param stdClass $scope Voir these_settings_scope().
 * @param array $items Liste d'objets {itemkey ('' pour un nouvel élément), section, label, conditional}, dans l'ordre ;
 *                     une clé inconnue de la portée (transfert depuis une autre) est reprise telle quelle.
 * @return void
 */
function these_save_grid_items(stdClass $scope, array $items) {
    return \mod_these\local\signatures::save_grid_items($scope, $items);
}

/**
 * Voir \mod_these\local\signatures::get_grid().
 *
 * @param stdClass $dossier
 * @return array clé => 0|1 ; tableau vide si elle n'a pas été renseignée.
 */
function these_get_grid(stdClass $dossier) {
    return \mod_these\local\signatures::get_grid($dossier);
}

/**
 * Voir \mod_these\local\signatures::get_signatures().
 *
 * @param int $dossierid
 * @return array « doctype:slot » => enregistrement these_signature
 */
function these_get_signatures($dossierid) {
    return \mod_these\local\signatures::get_signatures($dossierid);
}

/**
 * Voir \mod_these\local\signatures::decode_signature_image().
 *
 * @param string $dataurl
 * @return string|null Contenu PNG, ou null si la donnée n'est pas une image PNG plausible.
 */
function these_decode_signature_image($dataurl) {
    return \mod_these\local\signatures::decode_signature_image($dataurl);
}

/**
 * Voir \mod_these\local\signatures::get_signature_image().
 *
 * @param int $contextid
 * @param int $signatureid
 * @return string|null
 */
function these_get_signature_image($contextid, $signatureid) {
    return \mod_these\local\signatures::get_signature_image($contextid, $signatureid);
}

/**
 * Voir \mod_these\local\signatures::sign_document().
 *
 * @param context $context Contexte du module.
 * @param stdClass $dossier
 * @param string $doctype
 * @param string $slot
 * @param int $userid Signataire (0 sans compte).
 * @param string $name Nom du signataire tel qu'il apparaît sur le document.
 * @param string $png Image de la signature.
 * @param string $email
 * @return stdClass La signature.
 */
function these_sign_document(context $context, stdClass $dossier, $doctype, $slot, $userid, $name, $png, $email = '') {
    return \mod_these\local\signatures::sign_document($context, $dossier, $doctype, $slot, $userid, $name, $png, $email);
}

/**
 * Voir \mod_these\local\signatures::delete_signature().
 *
 * @param context $context
 * @param stdClass $signature
 * @return void
 */
function these_delete_signature(context $context, stdClass $signature) {
    return \mod_these\local\signatures::delete_signature($context, $signature);
}

/**
 * Voir \mod_these\local\signatures::refresh_signatures().
 *
 * @param context $context
 * @param stdClass $dossier
 * @return string[] Emplacements invalidés (« doctype:slot »).
 */
function these_refresh_signatures(context $context, stdClass $dossier) {
    return \mod_these\local\signatures::refresh_signatures($context, $dossier);
}

/**
 * Voir \mod_these\local\signatures::document_template_hash().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return string
 */
function these_document_template_hash(stdClass $dossier, $doctype) {
    return \mod_these\local\signatures::document_template_hash($dossier, $doctype);
}

/**
 * Voir \mod_these\local\signatures::signature_template_changed().
 *
 * @param context $context
 * @param stdClass $dossier
 * @param stdClass $signature
 * @return bool
 */
function these_signature_template_changed(context $context, stdClass $dossier, stdClass $signature) {
    return \mod_these\local\signatures::signature_template_changed($context, $dossier, $signature);
}

/**
 * Voir \mod_these\local\signatures::document_fully_signed().
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return bool
 */
function these_document_fully_signed(stdClass $dossier, $doctype) {
    return \mod_these\local\signatures::document_fully_signed($dossier, $doctype);
}

/**
 * Voir \mod_these\local\signatures::get_sealed_document().
 *
 * @param context $context
 * @param int $dossierid
 * @param string $doctype
 * @return stored_file|null
 */
function these_get_sealed_document(context $context, $dossierid, $doctype) {
    return \mod_these\local\signatures::get_sealed_document($context, $dossierid, $doctype);
}

/**
 * Voir \mod_these\local\signatures::seal_document().
 *
 * @param context $context
 * @param stdClass $dossier
 * @param string $doctype
 * @return stored_file|null Document scellé, null s'il attend encore une signature.
 */
function these_seal_document(context $context, stdClass $dossier, $doctype) {
    return \mod_these\local\signatures::seal_document($context, $dossier, $doctype);
}

/**
 * Voir \mod_these\local\signatures::unseal_document().
 *
 * @param context $context
 * @param int $dossierid
 * @param string $doctype
 * @return void
 */
function these_unseal_document(context $context, $dossierid, $doctype) {
    return \mod_these\local\signatures::unseal_document($context, $dossierid, $doctype);
}
/**
 * Voir \mod_these\local\signatures::external_signers().
 *
 * @param stdClass $dossier
 * @return array slot => ['name' => string, 'email' => string]
 */
function these_external_signers(stdClass $dossier) {
    return \mod_these\local\signatures::external_signers($dossier);
}

/**
 * Voir \mod_these\local\signatures::pending_documents_for_slot().
 *
 * @param stdClass $dossier
 * @param string $slot
 * @param string[]|null $doctypes Documents à considérer (tous par défaut).
 * @return string[]
 */
function these_pending_documents_for_slot(stdClass $dossier, $slot, ?array $doctypes = null) {
    return \mod_these\local\signatures::pending_documents_for_slot($dossier, $slot, $doctypes);
}

/**
 * Voir \mod_these\local\signatures::request_signature().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string $slot
 * @param string[]|null $doctypes Documents concernés (tous ceux en attente par défaut).
 * @return string|null Jeton, ou null si aucun document n'attend ce signataire.
 */
function these_request_signature(stdClass $these, stdClass $cm, stdClass $dossier, $slot, ?array $doctypes = null) {
    return \mod_these\local\signatures::request_signature($these, $cm, $dossier, $slot, $doctypes);
}

/**
 * Voir \mod_these\local\signatures::request_external_signatures().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string|string[] $doctypes
 * @return int Nombre de signataires invités.
 */
function these_request_external_signatures(stdClass $these, stdClass $cm, stdClass $dossier, $doctypes) {
    return \mod_these\local\signatures::request_external_signatures($these, $cm, $dossier, $doctypes);
}

/**
 * Voir \mod_these\local\signatures::get_signatures_by_token().
 *
 * @param string $token
 * @return stdClass[] Signatures en attente, vide si le jeton est inconnu, déjà utilisé ou expiré.
 */
function these_get_signatures_by_token($token) {
    return \mod_these\local\signatures::get_signatures_by_token($token);
}

/**
 * Voir \mod_these\local\signatures::add_signature_fields().
 *
 * @param MoodleQuickForm $mform
 * @param string $defaultname Nom proposé.
 * @param string $intro Texte d'introduction (ce qui est signé).
 * @param bool $allowsaved Proposer de reprendre ou d'enregistrer la signature de l'utilisateur.
 * @return void
 */
function these_add_signature_fields(MoodleQuickForm $mform, $defaultname, $intro = '', $allowsaved = true) {
    return \mod_these\local\signatures::add_signature_fields($mform, $defaultname, $intro, $allowsaved);
}

/**
 * Voir \mod_these\local\signatures::signature_errors().
 *
 * @param array $data
 * @return array Erreurs, indexées par champ.
 */
function these_signature_errors(array $data) {
    return \mod_these\local\signatures::signature_errors($data);
}

/**
 * Voir \mod_these\local\signatures::sign_from_form().
 *
 * @param context $context
 * @param stdClass $dossier
 * @param string[] $doctypes
 * @param string $slot
 * @param stdClass $data Données du formulaire (signaturedata, signername).
 * @param int $userid
 * @return void
 */
function these_sign_from_form(context $context, stdClass $dossier, array $doctypes, $slot, stdClass $data, $userid) {
    return \mod_these\local\signatures::sign_from_form($context, $dossier, $doctypes, $slot, $data, $userid);
}

/**
 * Voir \mod_these\local\signatures::render_signed_documents().
 *
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $dossier
 * @param stdClass $rights
 * @return string HTML
 */
function these_render_signed_documents(stdClass $cm, context $context, stdClass $dossier, stdClass $rights) {
    return \mod_these\local\signatures::render_signed_documents($cm, $context, $dossier, $rights);
}
