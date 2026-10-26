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
 * Directeurs de thèse et jury de soutenance.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Directeurs.

/**
 * Voir \mod_these\local\jury::get_potential_directors().
 *
 * @param context $context
 * @return array Utilisateurs indexés par id, triés par nom.
 */
function these_get_potential_directors(context $context) {
    return \mod_these\local\jury::get_potential_directors($context);
}

/**
 * Voir \mod_these\local\jury::director_options().
 *
 * @param context $context
 * @param int $currentid
 * @return array id => nom
 */
function these_director_options(context $context, $currentid = 0) {
    return \mod_these\local\jury::director_options($context, $currentid);
}

// Jury.

/**
 * Voir \mod_these\local\jury::get_jury().
 *
 * @param int $dossierid
 * @return array
 */
function these_get_jury($dossierid) {
    return \mod_these\local\jury::get_jury($dossierid);
}

/**
 * Voir \mod_these\local\jury::jury_conflicts().
 *
 * @param stdClass $dossier
 * @param array|null $members
 * @return stdClass[]
 */
function these_jury_conflicts(stdClass $dossier, ?array $members = null) {
    return \mod_these\local\jury::jury_conflicts($dossier, $members);
}

/**
 * Voir \mod_these\local\jury::render_jury_conflicts().
 *
 * @param stdClass $dossier
 * @return string
 */
function these_render_jury_conflicts(stdClass $dossier) {
    return \mod_these\local\jury::render_jury_conflicts($dossier);
}

/**
 * Voir \mod_these\local\jury::jury_member_key().
 *
 * @param stdClass $member
 * @return string
 */
function these_jury_member_key(stdClass $member) {
    return \mod_these\local\jury::jury_member_key($member);
}

/**
 * Voir \mod_these\local\jury::save_jury().
 *
 * @param int $dossierid
 * @param array $members Liste d'objets {juryrole, fullname, jurytitle, institution, email, jurorid?,
 *                       jurystatus?, eligible?}.
 * @param int $userid Auteur de la validation d'éligibilité, s'il y en a une.
 * @return void
 */
function these_save_jury($dossierid, array $members, $userid = 0) {
    return \mod_these\local\jury::save_jury($dossierid, $members, $userid);
}

/**
 * Voir \mod_these\local\jury::add_jury_fields().
 *
 * @param moodleform $form
 * @param MoodleQuickForm $mform
 * @param int $count Nombre de lignes déjà renseignées.
 * @param bool $eligibility Ajouter la validation d'éligibilité par la DEVE.
 * @param int[] $keep Enseignants de l'annuaire à proposer même s'ils ne sont plus actifs.
 * @param string $warning Avertissement affiché en tête de la rubrique (HTML).
 * @return void
 */
function these_add_jury_fields(
    moodleform $form,
    MoodleQuickForm $mform,
    $count,
    $eligibility = false,
    array $keep = [],
    $warning = ''
) {
    return \mod_these\local\jury::add_jury_fields($form, $mform, $count, $eligibility, $keep, $warning);
}

/**
 * Voir \mod_these\local\jury::jury_form_data().
 *
 * @param array $members
 * @return array
 */
function these_jury_form_data(array $members) {
    return \mod_these\local\jury::jury_form_data($members);
}

/**
 * Voir \mod_these\local\jury::extract_submitted_jury().
 *
 * @param stdClass|array $data
 * @param bool $witheligibility Reprendre la validation d'éligibilité de la DEVE.
 * @return array Liste d'objets {juryrole, fullname, jurytitle, institution, email, jurorid, jurystatus, eligible?}.
 */
function these_extract_submitted_jury($data, $witheligibility = false) {
    return \mod_these\local\jury::extract_submitted_jury($data, $witheligibility);
}

/**
 * Voir \mod_these\local\jury::jury_eligibility().
 *
 * @param stdClass $member
 * @return string 'ok', 'tocheck' (à valider par la DEVE) ou 'ko'
 */
function these_jury_eligibility(stdClass $member) {
    return \mod_these\local\jury::jury_eligibility($member);
}

/**
 * Voir \mod_these\local\jury::validate_jury().
 *
 * @param array $members Voir these_extract_submitted_jury().
 * @param int $minjury
 * @return string|null Message d'erreur, ou null si le jury est valide.
 */
function these_validate_jury(array $members, $minjury) {
    return \mod_these\local\jury::validate_jury($members, $minjury);
}

/**
 * Voir \mod_these\local\jury::validate_jury_eligibility().
 *
 * @param array $members Voir these_extract_submitted_jury() (avec éligibilité).
 * @return string|null Message d'erreur, ou null.
 */
function these_validate_jury_eligibility(array $members) {
    return \mod_these\local\jury::validate_jury_eligibility($members);
}

/**
 * Voir \mod_these\local\jury::notify_jury().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param bool $force Prévenir de nouveau tous les membres.
 * @return stdClass {sent, failed, missing (noms des membres sans adresse)}
 */
function these_notify_jury(stdClass $these, stdClass $cm, stdClass $dossier, $force = false) {
    return \mod_these\local\jury::notify_jury($these, $cm, $dossier, $force);
}

/**
 * Voir \mod_these\local\jury::get_jury_access().
 *
 * @param string $token
 * @return stdClass|null Membre, avec dossier, these et cm ; null si le lien est inconnu ou n'est plus
 *                       valide (voir these_jury_access_open()).
 */
function these_get_jury_access($token) {
    return \mod_these\local\jury::get_jury_access($token);
}

/**
 * Voir \mod_these\local\jury::get_jury_member_access().
 *
 * @param int $memberid
 * @param stdClass $user
 * @return stdClass|null Voir these_get_jury_access().
 */
function these_get_jury_member_access($memberid, stdClass $user) {
    return \mod_these\local\jury::get_jury_member_access($memberid, $user);
}

/**
 * Voir \mod_these\local\jury::jury_access_member().
 *
 * @param stdClass $member
 * @return stdClass|null
 */
function these_jury_access_member(stdClass $member) {
    return \mod_these\local\jury::jury_access_member($member);
}

/**
 * Voir \mod_these\local\jury::jury_access_open().
 *
 * @param stdClass $dossier
 * @param stdClass $member
 * @return bool
 */
function these_jury_access_open(stdClass $dossier, stdClass $member) {
    return \mod_these\local\jury::jury_access_open($dossier, $member);
}

/**
 * Voir \mod_these\local\jury::jury_documents().
 *
 * @return array {areas => string[], documents => string[]}
 */
function these_jury_documents() {
    return \mod_these\local\jury::jury_documents();
}

/**
 * Salles de soutenance proposées pour une activité : celles des paramètres du circuit de sa portée,
 * à défaut le lieu par défaut de l'ancien réglage de l'activité.
 *
 * @param stdClass $these
 * @return string[]
 */
function these_defense_rooms(stdClass $these) {
    return \mod_these\local\scopesettings::rooms(
        \mod_these\local\scopesettings::for_these($these),
        (string) ($these->defaultlocation ?? '')
    );
}

/**
 * Voir \mod_these\local\jury::room_conflicts().
 *
 * @param stdClass $dossier
 * @param string|null $location
 * @return stdClass[]
 */
function these_room_conflicts(stdClass $dossier, $location = null) {
    return \mod_these\local\jury::room_conflicts($dossier, $location);
}

/**
 * Voir \mod_these\local\jury::render_room_conflicts().
 *
 * @param stdClass $dossier
 * @return string HTML
 */
function these_render_room_conflicts(stdClass $dossier) {
    return \mod_these\local\jury::render_room_conflicts($dossier);
}

/**
 * Voir \mod_these\local\jury::jury_readonly().
 *
 * @param MoodleQuickForm $mform
 * @param array $members
 * @return void
 */
function these_jury_readonly(MoodleQuickForm $mform, array $members) {
    \mod_these\local\jury::jury_readonly($mform, $members);
}
