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
 * Cycle de vie des dossiers : création, soumissions, validations, renvois, procès-verbal, numéro DEVE.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Dossiers.

/**
 * Voir \mod_these\local\dossiers::get_user_dossier().
 *
 * @param int $theseid
 * @param int $userid
 * @return stdClass|null
 */
function these_get_user_dossier($theseid, $userid) {
    return \mod_these\local\dossiers::get_user_dossier($theseid, $userid);
}

/**
 * Voir \mod_these\local\dossiers::create_dossier().
 *
 * @param int $theseid
 * @param int $userid
 * @param int $actorid Auteur de la création (l'étudiant, ou la DEVE pour son compte).
 * @return stdClass Le dossier créé.
 */
function these_create_dossier($theseid, $userid, $actorid) {
    return \mod_these\local\dossiers::create_dossier($theseid, $userid, $actorid);
}

/**
 * Voir \mod_these\local\dossiers::log().
 *
 * @param int $dossierid
 * @param int $userid Auteur, 0 pour le système.
 * @param string $action Clé d'action (libellé : chaîne de langue « log<action> »).
 * @param int $fromstatus
 * @param int $tostatus
 * @param string $remark Commentaire éventuel (motif de renvoi, avis...).
 * @return void
 */
function these_log($dossierid, $userid, $action, $fromstatus, $tostatus, $remark = '') {
    return \mod_these\local\dossiers::log($dossierid, $userid, $action, $fromstatus, $tostatus, $remark);
}

/**
 * Voir \mod_these\local\dossiers::set_status().
 *
 * @param stdClass $dossier Mis à jour en place.
 * @param int $tostatus
 * @param int $userid Auteur de l'action.
 * @param string $action Voir these_log().
 * @param string $remark
 * @param array $fields Autres champs du dossier à enregistrer.
 * @return stdClass Le dossier à jour.
 */
function these_set_status(stdClass $dossier, $tostatus, $userid, $action, $remark = '', array $fields = []) {
    return \mod_these\local\dossiers::set_status($dossier, $tostatus, $userid, $action, $remark, $fields);
}

/**
 * Voir \mod_these\local\dossiers::dossier_operation().
 *
 * @param stdClass $dossier Dossier tel que l'utilisateur l'a vu.
 * @param callable $operation
 * @return mixed Résultat de l'opération.
 * @throws moodle_exception dossierchanged si le dossier a changé de statut entre-temps, dossierbusy
 *                          si un autre traitement le retient.
 */
function these_dossier_operation(stdClass $dossier, callable $operation) {
    return \mod_these\local\dossiers::dossier_operation($dossier, $operation);
}

/**
 * Voir \mod_these\local\dossiers::cleared_return_fields().
 *
 * @return array
 */
function these_cleared_return_fields() {
    return \mod_these\local\dossiers::cleared_return_fields();
}

/**
 * Voir \mod_these\local\dossiers::submission_target().
 *
 * @param stdClass $dossier
 * @return int|null Null si le dossier n'est pas dans un statut où l'étudiant peut soumettre.
 */
function these_submission_target(stdClass $dossier) {
    return \mod_these\local\dossiers::submission_target($dossier);
}

/**
 * Voir \mod_these\local\subjects::subject_editable().
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_subject_editable(stdClass $dossier) {
    return \mod_these\local\subjects::subject_editable($dossier);
}

/**
 * Voir \mod_these\local\defenses::defense_editable().
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_defense_editable(stdClass $dossier) {
    return \mod_these\local\defenses::defense_editable($dossier);
}

/**
 * Voir \mod_these\local\defenses::report_editable().
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_report_editable(stdClass $dossier) {
    return \mod_these\local\defenses::report_editable($dossier);
}

/**
 * Voir \mod_these\local\defenses::save_report().
 *
 * @param stdClass $dossier
 * @param int $userid
 * @param bool $favorable
 * @return stdClass Le dossier à jour.
 */
function these_save_report(stdClass $dossier, $userid, $favorable) {
    return \mod_these\local\defenses::save_report($dossier, $userid, $favorable);
}

/**
 * Voir \mod_these\local\dossiers::signature_from_form().
 *
 * @param stdClass $data
 * @return stdClass|null {name, png}, ou null si aucune signature n'a été tracée.
 */
function these_signature_from_form(stdClass $data) {
    return \mod_these\local\dossiers::signature_from_form($data);
}

/**
 * Voir \mod_these\local\subjects::split_keywords().
 *
 * @param string $keywords
 * @return string[]
 */
function these_split_keywords($keywords) {
    return \mod_these\local\subjects::split_keywords($keywords);
}

/**
 * Voir \mod_these\local\dossiers::submitted_value().
 *
 * @param stdClass $data
 * @param stdClass $dossier
 * @param string $field
 * @return mixed
 */
function these_submitted_value(stdClass $data, stdClass $dossier, $field) {
    return \mod_these\local\dossiers::submitted_value($data, $dossier, $field);
}

/**
 * Voir \mod_these\local\dossiers::resubmission_target().
 *
 * @param stdClass $dossier Dossier avant changement de statut.
 * @param string[] $invalidated Signatures invalidées (« doctype:slot »).
 * @return int
 */
function these_resubmission_target(stdClass $dossier, array $invalidated) {
    return \mod_these\local\dossiers::resubmission_target($dossier, $invalidated);
}

/**
 * Voir \mod_these\local\subjects::save_subject().
 *
 * @param stdClass $these Instance.
 * @param stdClass $cm Course module.
 * @param stdClass $dossier Dossier en brouillon ou renvoyé.
 * @param stdClass $data Champs du formulaire de sujet ; les champs absents sont conservés.
 * @param bool $submit True pour soumettre, false pour un simple enregistrement.
 * @param int $userid Auteur.
 * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
 * @return stdClass Le dossier à jour.
 */
function these_save_subject(
    stdClass $these,
    stdClass $cm,
    stdClass $dossier,
    stdClass $data,
    $submit,
    $userid,
    ?stdClass $signature = null
) {
    return \mod_these\local\subjects::save_subject($these, $cm, $dossier, $data, $submit, $userid, $signature);
}

/**
 * Voir \mod_these\local\defenses::extract_bordereau().
 *
 * @param stdClass $data
 * @param array $existing
 * @return array
 */
function these_extract_bordereau(stdClass $data, array $existing) {
    return \mod_these\local\defenses::extract_bordereau($data, $existing);
}

/**
 * Voir \mod_these\local\defenses::save_defense().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier Dossier actif ou renvoyé en phase 2.
 * @param stdClass $data {proposeddate, defenselocation}, les champs de jury (voir
 *                       these_extract_submitted_jury()) et, facultativement, titleen,
 *                       conclusions et les champs du bordereau (préfixés « b »).
 * @param bool $submit
 * @param int $userid
 * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
 * @return stdClass Le dossier à jour.
 */
function these_save_defense(
    stdClass $these,
    stdClass $cm,
    stdClass $dossier,
    stdClass $data,
    $submit,
    $userid,
    ?stdClass $signature = null
) {
    return \mod_these\local\defenses::save_defense($these, $cm, $dossier, $data, $submit, $userid, $signature);
}

/**
 * Voir \mod_these\local\reviews::validate_step().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param array $fields Champs propres à l'étape (numéro DEVE, notice, rapport, date retenue...).
 * @param string $remark Avis ou commentaire facultatif, inscrit à l'historique.
 * @param stdClass|null $signature Signature de l'acteur (voir these_signature_from_form()).
 * @return stdClass Le dossier à jour.
 */
function these_validate_step(
    stdClass $these,
    stdClass $cm,
    stdClass $dossier,
    $userid,
    array $fields = [],
    $remark = '',
    ?stdClass $signature = null
) {
    return \mod_these\local\reviews::validate_step($these, $cm, $dossier, $userid, $fields, $remark, $signature);
}

/**
 * Voir \mod_these\local\reviews::return_dossier().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param string $target 'student' ou 'director'.
 * @param string $comment Motif, obligatoire, transmis au destinataire.
 * @return stdClass Le dossier à jour.
 */
function these_return_dossier(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $target, $comment) {
    return \mod_these\local\reviews::return_dossier($these, $cm, $dossier, $userid, $target, $comment);
}

/**
 * Voir \mod_these\local\dossiers::cancel_dossier().
 *
 * @param stdClass $dossier
 * @param int $userid
 * @param string $comment
 * @return stdClass
 */
function these_cancel_dossier(stdClass $dossier, $userid, $comment) {
    return \mod_these\local\dossiers::cancel_dossier($dossier, $userid, $comment);
}

/**
 * Voir \mod_these\local\dossiers::reopen_dossier().
 *
 * @param stdClass $dossier
 * @param int $userid
 * @param string $comment
 * @return stdClass
 */
function these_reopen_dossier(stdClass $dossier, $userid, $comment = '') {
    return \mod_these\local\dossiers::reopen_dossier($dossier, $userid, $comment);
}

/**
 * Voir \mod_these\local\minutes::record_minutes().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param stdClass $decision {result, mention, prizeproposal, correctionsrequested, correctionsnote}
 * @param string $remark
 * @return stdClass Le dossier à jour.
 */
function these_record_minutes(stdClass $these, stdClass $cm, stdClass $dossier, $userid, stdClass $decision, $remark = '') {
    return \mod_these\local\minutes::record_minutes($these, $cm, $dossier, $userid, $decision, $remark);
}

/**
 * Voir \mod_these\local\minutes::adjourn_dossier().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param string $remark
 * @return stdClass Le dossier à jour.
 */
function these_adjourn_dossier(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $remark = '') {
    return \mod_these\local\minutes::adjourn_dossier($these, $cm, $dossier, $userid, $remark);
}

/**
 * Voir \mod_these\local\minutes::is_adjourned().
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_is_adjourned(stdClass $dossier) {
    return \mod_these\local\minutes::is_adjourned($dossier);
}

/**
 * Voir \mod_these\local\minutes::submit_corrections().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param string $done Corrections apportées.
 * @return stdClass Le dossier à jour.
 */
function these_submit_corrections(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $done) {
    return \mod_these\local\minutes::submit_corrections($these, $cm, $dossier, $userid, $done);
}

/**
 * Voir \mod_these\local\dossiers::delete_dossiers().
 *
 * @param int[] $dossierids
 * @param context|null $context Contexte du module, pour supprimer aussi les fichiers. Sans
 *                              contexte (suppression de l'instance), Moodle s'en charge.
 * @return void
 */
function these_delete_dossiers(array $dossierids, ?context $context = null) {
    return \mod_these\local\dossiers::delete_dossiers($dossierids, $context);
}

// Numéro DEVE.

/**
 * Voir \mod_these\local\dossiers::suggest_devenumber().
 *
 * @param int $theseid
 * @param int|null $time Date de référence (année), maintenant par défaut.
 * @return string
 */
function these_suggest_devenumber($theseid, $time = null) {
    return \mod_these\local\dossiers::suggest_devenumber($theseid, $time);
}

/**
 * Voir \mod_these\local\dossiers::devenumber_taken().
 *
 * @param int $theseid
 * @param string $number
 * @param int $excludeid Dossier à ignorer (celui qu'on enregistre).
 * @return bool
 */
function these_devenumber_taken($theseid, $number, $excludeid = 0) {
    return \mod_these\local\dossiers::devenumber_taken($theseid, $number, $excludeid);
}
