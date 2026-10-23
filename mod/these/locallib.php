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
 * Fonctions métier internes pour mod_these.
 *
 * Le circuit suit le document de travail « Circuit de dépôt du sujet et de la soutenance » :
 *
 * Phase 1 — sujet : l'étudiant dépose sa fiche de sujet, le directeur la valide (ou la renvoie),
 * la DEVE l'enregistre (numéro), la bibliothèque l'inscrit au référentiel documentaire. Le dossier
 * reste ensuite actif et « dormant » jusqu'au dépôt des documents de soutenance.
 *
 * Phase 2 — soutenance : l'étudiant dépose manuscrit et proposition de date et de jury, le
 * directeur valide ET joint son rapport en une seule étape, la DEVE valide le dossier complet, la
 * bibliothèque atteste la conformité du manuscrit, la DEVE génère et transmet les documents de
 * soutenance, puis dépose le procès-verbal, ce qui clôt le dossier.
 *
 * Chaque étape de validation peut renvoyer le dossier : à l'étudiant (qui le corrige et le soumet
 * de nouveau, le dossier revenant alors directement à l'étape qui l'avait renvoyé) ou, pour la
 * DEVE, au directeur de thèse.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/lib.php');

// Libellés, statuts et circuit.

/**
 * Tous les statuts, dans l'ordre du circuit.
 *
 * @return int[]
 */
function these_all_statuses() {
    return [
        THESE_STATUS_DRAFT, THESE_STATUS_SUBJECT_SUBMITTED, THESE_STATUS_SUBJECT_RETURNED,
        THESE_STATUS_SUBJECT_APPROVED, THESE_STATUS_SUBJECT_REGISTERED, THESE_STATUS_ACTIVE,
        THESE_STATUS_DEFENSE_SUBMITTED, THESE_STATUS_DEFENSE_RETURNED, THESE_STATUS_DEFENSE_APPROVED,
        THESE_STATUS_DEFENSE_VALIDATED, THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED,
        THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED,
    ];
}

/**
 * Retourne le libellé lisible d'un statut de dossier.
 *
 * @param int $status
 * @return string
 */
function these_status_label($status) {
    $keys = [
        THESE_STATUS_DRAFT => 'statusdraft',
        THESE_STATUS_SUBJECT_SUBMITTED => 'statussubjectsubmitted',
        THESE_STATUS_SUBJECT_RETURNED => 'statussubjectreturned',
        THESE_STATUS_SUBJECT_APPROVED => 'statussubjectapproved',
        THESE_STATUS_SUBJECT_REGISTERED => 'statussubjectregistered',
        THESE_STATUS_ACTIVE => 'statusactive',
        THESE_STATUS_DEFENSE_SUBMITTED => 'statusdefensesubmitted',
        THESE_STATUS_DEFENSE_RETURNED => 'statusdefensereturned',
        THESE_STATUS_DEFENSE_APPROVED => 'statusdefenseapproved',
        THESE_STATUS_DEFENSE_VALIDATED => 'statusdefensevalidated',
        THESE_STATUS_CONFORM => 'statusconform',
        THESE_STATUS_SCHEDULED => 'statusscheduled',
        THESE_STATUS_CLOSED => 'statusclosed',
        THESE_STATUS_CANCELLED => 'statuscancelled',
    ];
    return isset($keys[(int) $status]) ? get_string($keys[(int) $status], 'mod_these') : (string) $status;
}

/**
 * Classe CSS du badge d'un statut : orange quand l'étudiant doit agir après un renvoi, bleu quand
 * le dossier attend un membre du personnel, vert pour un dossier actif ou clos.
 *
 * @param int $status
 * @return string
 */
function these_status_badgeclass($status) {
    switch ((int) $status) {
        case THESE_STATUS_DRAFT:
            return 'badge-light';
        case THESE_STATUS_SUBJECT_RETURNED:
        case THESE_STATUS_DEFENSE_RETURNED:
            return 'badge-warning';
        case THESE_STATUS_ACTIVE:
            return 'badge-success';
        case THESE_STATUS_CLOSED:
            return 'badge-dark';
        case THESE_STATUS_CANCELLED:
            return 'badge-secondary';
        default:
            return 'badge-info';
    }
}

/**
 * Badge HTML d'un statut.
 *
 * @param int $status
 * @return string HTML
 */
function these_render_status_badge($status) {
    return html_writer::span(these_status_label($status), 'badge ' . these_status_badgeclass($status));
}

/**
 * Phase du circuit à laquelle appartient un statut : 1 (sujet), 2 (soutenance) ou 0 (clos,
 * abandonné). Le dossier actif est rattaché à la phase 1, qu'il termine.
 *
 * @param int $status
 * @return int
 */
function these_phase($status) {
    $status = (int) $status;
    if ($status >= THESE_STATUS_DRAFT && $status <= THESE_STATUS_ACTIVE) {
        return 1;
    }
    if ($status >= THESE_STATUS_DEFENSE_SUBMITTED && $status < THESE_STATUS_CLOSED) {
        return 2;
    }
    return 0;
}

/**
 * Acteur attendu pour faire avancer un dossier dans un statut donné.
 *
 * @param int $status
 * @return string|null 'student', 'director', 'deve', 'library', ou null si le dossier n'attend
 *                     plus personne (clos, abandonné).
 */
function these_step_actor($status) {
    switch ((int) $status) {
        case THESE_STATUS_DRAFT:
        case THESE_STATUS_SUBJECT_RETURNED:
        case THESE_STATUS_ACTIVE:
        case THESE_STATUS_DEFENSE_RETURNED:
            return 'student';
        case THESE_STATUS_SUBJECT_SUBMITTED:
        case THESE_STATUS_DEFENSE_SUBMITTED:
            return 'director';
        case THESE_STATUS_SUBJECT_APPROVED:
        case THESE_STATUS_DEFENSE_APPROVED:
        case THESE_STATUS_CONFORM:
        case THESE_STATUS_SCHEDULED:
            return 'deve';
        case THESE_STATUS_SUBJECT_REGISTERED:
        case THESE_STATUS_DEFENSE_VALIDATED:
            return 'library';
        default:
            return null;
    }
}

/**
 * Libellé d'un acteur du circuit.
 *
 * @param string $actor Voir these_step_actor().
 * @return string
 */
function these_actor_label($actor) {
    return get_string('actor' . $actor, 'mod_these');
}

/**
 * Étapes de validation du circuit, indexées par le statut dans lequel le dossier les attend.
 *
 * Chacune est traitée par la même page (review.php) et le même formulaire (review_form), qui
 * n'ajoutent que les champs propres à l'étape :
 * - key : identifiant de l'étape (libellés, champs du formulaire) ;
 * - actor : qui la traite (voir these_step_actor()) ;
 * - next : statut atteint à la validation ;
 * - returnto : destinataires possibles d'un renvoi ('student', 'director').
 *
 * @return array
 */
function these_review_steps() {
    return [
        THESE_STATUS_SUBJECT_SUBMITTED => [
            'key' => 'directorsubject', 'actor' => 'director',
            'next' => THESE_STATUS_SUBJECT_APPROVED, 'returnto' => ['student'],
        ],
        THESE_STATUS_SUBJECT_APPROVED => [
            'key' => 'devesubject', 'actor' => 'deve',
            'next' => THESE_STATUS_SUBJECT_REGISTERED, 'returnto' => ['student', 'director'],
        ],
        THESE_STATUS_SUBJECT_REGISTERED => [
            'key' => 'librarysubject', 'actor' => 'library',
            'next' => THESE_STATUS_ACTIVE, 'returnto' => ['student'],
        ],
        THESE_STATUS_DEFENSE_SUBMITTED => [
            'key' => 'directordefense', 'actor' => 'director',
            'next' => THESE_STATUS_DEFENSE_APPROVED, 'returnto' => ['student'],
        ],
        THESE_STATUS_DEFENSE_APPROVED => [
            'key' => 'devedefense', 'actor' => 'deve',
            'next' => THESE_STATUS_DEFENSE_VALIDATED, 'returnto' => ['student', 'director'],
        ],
        THESE_STATUS_DEFENSE_VALIDATED => [
            'key' => 'libraryconformity', 'actor' => 'library',
            'next' => THESE_STATUS_CONFORM, 'returnto' => ['student'],
        ],
    ];
}

/**
 * Étapes affichées dans la frise de progression d'un dossier, dans l'ordre, avec le statut à
 * partir duquel chacune est considérée comme franchie.
 *
 * @return array Liste de ['label' => string, 'actor' => string, 'done' => int].
 */
function these_progress_steps() {
    return [
        ['label' => get_string('stepsubjectdeposit', 'mod_these'), 'actor' => 'student',
            'done' => THESE_STATUS_SUBJECT_SUBMITTED],
        ['label' => get_string('stepdirectorsubject', 'mod_these'), 'actor' => 'director',
            'done' => THESE_STATUS_SUBJECT_APPROVED],
        ['label' => get_string('stepdevesubject', 'mod_these'), 'actor' => 'deve',
            'done' => THESE_STATUS_SUBJECT_REGISTERED],
        ['label' => get_string('steplibrarysubject', 'mod_these'), 'actor' => 'library',
            'done' => THESE_STATUS_ACTIVE],
        ['label' => get_string('stepdefensedeposit', 'mod_these'), 'actor' => 'student',
            'done' => THESE_STATUS_DEFENSE_SUBMITTED],
        ['label' => get_string('stepdirectordefense', 'mod_these'), 'actor' => 'director',
            'done' => THESE_STATUS_DEFENSE_APPROVED],
        ['label' => get_string('stepdevedefense', 'mod_these'), 'actor' => 'deve',
            'done' => THESE_STATUS_DEFENSE_VALIDATED],
        ['label' => get_string('steplibraryconformity', 'mod_these'), 'actor' => 'library',
            'done' => THESE_STATUS_CONFORM],
        ['label' => get_string('stepdocuments', 'mod_these'), 'actor' => 'deve',
            'done' => THESE_STATUS_SCHEDULED],
        ['label' => get_string('stepminutes', 'mod_these'), 'actor' => 'deve',
            'done' => THESE_STATUS_CLOSED],
    ];
}

/**
 * Rang d'un statut dans le circuit, pour comparer l'avancement de deux statuts : les statuts
 * « renvoyé » sont rangés avec l'étape de dépôt qu'ils font reprendre à l'étudiant.
 *
 * @param int $status
 * @return int
 */
function these_status_rank($status) {
    switch ((int) $status) {
        case THESE_STATUS_SUBJECT_RETURNED:
            return THESE_STATUS_DRAFT;
        case THESE_STATUS_DEFENSE_RETURNED:
            return THESE_STATUS_ACTIVE;
        default:
            return (int) $status;
    }
}

/**
 * Options de résultat de soutenance.
 *
 * @return array
 */
function these_result_options() {
    return [
        THESE_RESULT_ADMITTED => get_string('resultadmitted', 'mod_these'),
        THESE_RESULT_ADJOURNED => get_string('resultadjourned', 'mod_these'),
    ];
}

/**
 * Libellé d'un résultat de soutenance.
 *
 * @param int $result
 * @return string
 */
function these_result_label($result) {
    $options = these_result_options();
    return $options[(int) $result] ?? '-';
}

/**
 * Rôles possibles d'un membre du jury.
 *
 * « Assesseur » vient en premier : c'est le rôle proposé d'office sur une ligne vide du
 * formulaire, une ligne qui proposerait « Président » rendrait le jury invalide dès qu'on la
 * remplirait sans penser à changer le rôle. (Une valeur par défaut posée par repeat_elements()
 * écraserait en revanche les rôles déjà enregistrés au rechargement du formulaire.)
 *
 * @return array
 */
function these_jury_role_options() {
    return [
        'member' => get_string('juryrolemember', 'mod_these'),
        'president' => get_string('juryrolepresident', 'mod_these'),
        'director' => get_string('juryroledirector', 'mod_these'),
        'guest' => get_string('juryroleguest', 'mod_these'),
    ];
}

// Droits.

/**
 * Droits de l'utilisateur sur un dossier (ou sur l'activité, si aucun dossier n'est donné).
 *
 * Un directeur de thèse n'a de droits que sur les dossiers qu'il dirige ; la DEVE et la
 * bibliothèque, sur tous les dossiers de l'activité.
 *
 * @param context $context
 * @param stdClass|null $dossier
 * @param int $userid
 * @return stdClass {owner, student, director, deve, library, viewall}
 */
function these_get_rights(context $context, ?stdClass $dossier, $userid) {
    $rights = new stdClass();
    $rights->deve = has_capability('mod/these:managedeve', $context, $userid);
    $rights->library = has_capability('mod/these:managelibrary', $context, $userid);
    $rights->viewall = $rights->deve || $rights->library || has_capability('mod/these:viewall', $context, $userid);
    $rights->owner = $dossier !== null && (int) $dossier->userid === (int) $userid;
    $rights->student = $rights->owner && has_capability('mod/these:submit', $context, $userid);
    $rights->director = $dossier !== null && (int) $dossier->directorid === (int) $userid
        && has_capability('mod/these:direct', $context, $userid);
    return $rights;
}

/**
 * Indique si l'utilisateur peut consulter un dossier.
 *
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_can_view_dossier(stdClass $rights) {
    return $rights->owner || $rights->director || $rights->viewall;
}

/**
 * Indique si l'utilisateur peut consulter une zone de fichiers d'un dossier.
 *
 * Le rapport du directeur n'est pas communiqué à l'étudiant : il s'adresse au jury et à
 * l'administration. Toutes les autres zones suivent le droit de consultation du dossier.
 *
 * @param string $filearea
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_can_view_filearea($filearea, stdClass $rights) {
    if (!these_can_view_dossier($rights)) {
        return false;
    }
    if ($filearea === 'directorreport') {
        return $rights->director || $rights->viewall;
    }
    return true;
}

/**
 * Indique si l'utilisateur est l'acteur attendu à l'étape courante du dossier.
 *
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_user_can_act(stdClass $dossier, stdClass $rights) {
    $actor = these_step_actor($dossier->status);
    if ($actor === null) {
        return false;
    }
    return !empty($rights->$actor);
}

// Dossiers.

/**
 * Dossier d'un étudiant dans une instance, s'il existe.
 *
 * @param int $theseid
 * @param int $userid
 * @return stdClass|null
 */
function these_get_user_dossier($theseid, $userid) {
    global $DB;

    return $DB->get_record('these_dossier', ['theseid' => $theseid, 'userid' => $userid]) ?: null;
}

/**
 * Crée un dossier vide (brouillon) pour un étudiant.
 *
 * @param int $theseid
 * @param int $userid
 * @param int $actorid Auteur de la création (l'étudiant, ou la DEVE pour son compte).
 * @return stdClass Le dossier créé.
 */
function these_create_dossier($theseid, $userid, $actorid) {
    global $DB;

    $now = time();
    $dossier = (object) [
        'theseid' => $theseid,
        'userid' => $userid,
        'status' => THESE_STATUS_DRAFT,
        'statustime' => $now,
        'title' => '',
        'titleen' => '',
        'summary' => '',
        'keywords' => '',
        'directorid' => 0,
        'codirectorname' => '',
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    $dossier->id = $DB->insert_record('these_dossier', $dossier);
    these_log($dossier->id, $actorid, 'created', THESE_STATUS_DRAFT, THESE_STATUS_DRAFT);

    return $DB->get_record('these_dossier', ['id' => $dossier->id], '*', MUST_EXIST);
}

/**
 * Inscrit une action dans l'historique d'un dossier.
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
    global $DB;

    $DB->insert_record('these_log', (object) [
        'dossierid' => $dossierid,
        'userid' => (int) $userid,
        'logaction' => $action,
        'fromstatus' => (int) $fromstatus,
        'tostatus' => (int) $tostatus,
        'remark' => (string) $remark,
        'timecreated' => time(),
    ]);
}

/**
 * Fait passer un dossier dans un nouveau statut, avec les champs à mettre à jour en même temps,
 * et l'inscrit à l'historique. Un changement de statut remet à zéro la relance (une nouvelle
 * étape commence), un enregistrement sans changement de statut n'y touche pas.
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
    global $DB;

    $now = time();
    $fromstatus = (int) $dossier->status;
    $update = array_merge($fields, ['id' => $dossier->id, 'status' => (int) $tostatus, 'timemodified' => $now]);
    if ((int) $tostatus !== $fromstatus) {
        $update['statustime'] = $now;
        $update['remindertime'] = 0;
    }
    $DB->update_record('these_dossier', (object) $update);
    these_log($dossier->id, $userid, $action, $fromstatus, $tostatus, $remark);

    foreach ($update as $field => $value) {
        $dossier->$field = $value;
    }
    return $dossier;
}

/**
 * Champs d'un renvoi remis à zéro dès que le dossier repart vers l'avant.
 *
 * @return array
 */
function these_cleared_return_fields() {
    return ['returnstatus' => 0, 'returncomment' => '', 'returnedby' => 0, 'returntime' => 0];
}

/**
 * Statut atteint quand l'étudiant soumet son dossier : l'étape suivante du circuit, ou l'étape
 * qui l'avait renvoyé s'il s'agit d'une nouvelle soumission.
 *
 * @param stdClass $dossier
 * @return int|null Null si le dossier n'est pas dans un statut où l'étudiant peut soumettre.
 */
function these_submission_target(stdClass $dossier) {
    switch ((int) $dossier->status) {
        case THESE_STATUS_DRAFT:
            return THESE_STATUS_SUBJECT_SUBMITTED;
        case THESE_STATUS_ACTIVE:
            return THESE_STATUS_DEFENSE_SUBMITTED;
        case THESE_STATUS_SUBJECT_RETURNED:
            return (int) $dossier->returnstatus ?: THESE_STATUS_SUBJECT_SUBMITTED;
        case THESE_STATUS_DEFENSE_RETURNED:
            return (int) $dossier->returnstatus ?: THESE_STATUS_DEFENSE_SUBMITTED;
        default:
            return null;
    }
}

/**
 * Indique si la fiche de sujet peut être modifiée par l'étudiant.
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_subject_editable(stdClass $dossier) {
    return in_array((int) $dossier->status, [THESE_STATUS_DRAFT, THESE_STATUS_SUBJECT_RETURNED], true);
}

/**
 * Indique si le dossier de soutenance peut être déposé ou modifié par l'étudiant.
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_defense_editable(stdClass $dossier) {
    return in_array((int) $dossier->status, [THESE_STATUS_ACTIVE, THESE_STATUS_DEFENSE_RETURNED], true);
}

/**
 * Enregistre la fiche de sujet (phase 1, étape 1) et, si demandé, la soumet.
 *
 * @param stdClass $these Instance.
 * @param stdClass $cm Course module.
 * @param stdClass $dossier Dossier en brouillon ou renvoyé.
 * @param stdClass $data {title, titleen, summary, keywords, directorid, codirectorname}
 * @param bool $submit True pour soumettre, false pour un simple enregistrement.
 * @param int $userid Auteur.
 * @return stdClass Le dossier à jour.
 */
function these_save_subject(stdClass $these, stdClass $cm, stdClass $dossier, stdClass $data, $submit, $userid) {
    $fields = [
        'title' => trim((string) $data->title),
        'titleen' => trim((string) ($data->titleen ?? '')),
        'summary' => trim((string) $data->summary),
        'keywords' => trim((string) $data->keywords),
        'directorid' => (int) $data->directorid,
        'codirectorname' => trim((string) ($data->codirectorname ?? '')),
    ];

    if (!$submit) {
        return these_set_status($dossier, $dossier->status, $userid, 'subjectsaved', '', $fields);
    }

    $target = these_submission_target($dossier);
    $resubmitted = (int) $dossier->status === THESE_STATUS_SUBJECT_RETURNED;
    $dossier = these_set_status(
        $dossier,
        $target,
        $userid,
        $resubmitted ? 'subjectresubmitted' : 'subjectsubmitted',
        '',
        array_merge($fields, these_cleared_return_fields())
    );
    these_notify_actor($these, $cm, $dossier);
    return $dossier;
}

/**
 * Enregistre le dossier de soutenance (phase 2, étape 1) : date et lieu proposés, jury, et, si
 * demandé, le soumet. Les fichiers (manuscrit, autres documents) sont enregistrés par l'appelant.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier Dossier actif ou renvoyé en phase 2.
 * @param stdClass $data {proposeddate, defenselocation} et les champs de jury (voir
 *                       these_extract_submitted_jury()).
 * @param bool $submit
 * @param int $userid
 * @return stdClass Le dossier à jour.
 */
function these_save_defense(stdClass $these, stdClass $cm, stdClass $dossier, stdClass $data, $submit, $userid) {
    these_save_jury($dossier->id, these_extract_submitted_jury($data));

    $fields = [
        'proposeddate' => (int) $data->proposeddate,
        'defenselocation' => trim((string) $data->defenselocation),
    ];
    // Tant que la DEVE n'a pas arrêté la date, la date retenue suit la proposition de l'étudiant.
    if ((int) $dossier->status === THESE_STATUS_ACTIVE || empty($dossier->defensedate)) {
        $fields['defensedate'] = (int) $data->proposeddate;
    }

    if (!$submit) {
        return these_set_status($dossier, $dossier->status, $userid, 'defensesaved', '', $fields);
    }

    $target = these_submission_target($dossier);
    $resubmitted = (int) $dossier->status === THESE_STATUS_DEFENSE_RETURNED;
    $dossier = these_set_status(
        $dossier,
        $target,
        $userid,
        $resubmitted ? 'defenseresubmitted' : 'defensesubmitted',
        '',
        array_merge($fields, these_cleared_return_fields())
    );
    these_notify_actor($these, $cm, $dossier);
    return $dossier;
}

/**
 * Valide l'étape de validation courante du dossier (voir these_review_steps()).
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param array $fields Champs propres à l'étape (numéro DEVE, notice, date retenue...).
 * @param string $remark Avis ou commentaire facultatif, inscrit à l'historique.
 * @return stdClass Le dossier à jour.
 */
function these_validate_step(stdClass $these, stdClass $cm, stdClass $dossier, $userid, array $fields = [], $remark = '') {
    $steps = these_review_steps();
    $step = $steps[(int) $dossier->status] ?? null;
    if ($step === null) {
        throw new coding_exception('Aucune étape de validation pour le statut ' . $dossier->status);
    }

    $dossier = these_set_status(
        $dossier,
        $step['next'],
        $userid,
        'validated' . $step['key'],
        $remark,
        array_merge($fields, these_cleared_return_fields())
    );

    if ((int) $dossier->status === THESE_STATUS_ACTIVE) {
        these_notify_subject_active($these, $cm, $dossier);
    } else {
        these_notify_actor($these, $cm, $dossier);
    }
    return $dossier;
}

/**
 * Renvoie le dossier, depuis l'étape de validation courante, à l'étudiant ou au directeur.
 *
 * Renvoyé à l'étudiant, le dossier passe au statut « renvoyé » de sa phase et reviendra, à la
 * nouvelle soumission, directement à l'étape qui l'a renvoyé. Renvoyé au directeur (par la DEVE),
 * il revient à l'étape de validation du directeur de la même phase.
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
    $steps = these_review_steps();
    $step = $steps[(int) $dossier->status] ?? null;
    if ($step === null || !in_array($target, $step['returnto'], true)) {
        throw new coding_exception('Renvoi impossible depuis le statut ' . $dossier->status . ' vers ' . $target);
    }

    $phase = these_phase($dossier->status);
    $fields = [
        'returncomment' => $comment,
        'returnedby' => $userid,
        'returntime' => time(),
    ];
    if ($target === 'student') {
        $tostatus = $phase === 1 ? THESE_STATUS_SUBJECT_RETURNED : THESE_STATUS_DEFENSE_RETURNED;
        $fields['returnstatus'] = (int) $dossier->status;
    } else {
        $tostatus = $phase === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
        $fields['returnstatus'] = 0;
    }

    $dossier = these_set_status($dossier, $tostatus, $userid, 'returnedto' . $target, $comment, $fields);
    these_notify_returned($these, $cm, $dossier, $target, $comment);
    return $dossier;
}

/**
 * Abandonne un dossier (DEVE). Le statut atteint est mémorisé pour une éventuelle réouverture.
 *
 * @param stdClass $dossier
 * @param int $userid
 * @param string $comment
 * @return stdClass
 */
function these_cancel_dossier(stdClass $dossier, $userid, $comment) {
    return these_set_status($dossier, THESE_STATUS_CANCELLED, $userid, 'cancelled', $comment, [
        'cancelledfromstatus' => (int) $dossier->status,
    ]);
}

/**
 * Rouvre un dossier abandonné dans le statut qu'il avait atteint.
 *
 * @param stdClass $dossier
 * @param int $userid
 * @param string $comment
 * @return stdClass
 */
function these_reopen_dossier(stdClass $dossier, $userid, $comment = '') {
    return these_set_status($dossier, (int) $dossier->cancelledfromstatus, $userid, 'reopened', $comment, [
        'cancelledfromstatus' => 0,
    ]);
}

/**
 * Dépose le procès-verbal de soutenance et clôt le dossier (phase 2, étape 6). Le fichier est
 * enregistré par l'appelant.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param int $result Voir THESE_RESULT_*.
 * @param string $mention
 * @param string $remark
 * @return stdClass
 */
function these_close_dossier(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $result, $mention, $remark = '') {
    $dossier = these_set_status($dossier, THESE_STATUS_CLOSED, $userid, 'closed', $remark, [
        'defenseresult' => (int) $result,
        'mention' => trim((string) $mention),
    ]);
    these_notify_closed($these, $cm, $dossier);
    return $dossier;
}

/**
 * Supprime des dossiers et tout ce qui leur est rattaché (jury, historique, fichiers).
 *
 * @param int[] $dossierids
 * @param context|null $context Contexte du module, pour supprimer aussi les fichiers. Sans
 *                              contexte (suppression de l'instance), Moodle s'en charge.
 * @return void
 */
function these_delete_dossiers(array $dossierids, ?context $context = null) {
    global $DB;

    if (empty($dossierids)) {
        return;
    }
    if ($context !== null) {
        $fs = get_file_storage();
        foreach ($dossierids as $dossierid) {
            foreach (THESE_FILEAREAS as $filearea) {
                $fs->delete_area_files($context->id, 'mod_these', $filearea, $dossierid);
            }
        }
    }
    [$insql, $inparams] = $DB->get_in_or_equal($dossierids);
    $DB->delete_records_select('these_jury', "dossierid $insql", $inparams);
    $DB->delete_records_select('these_log', "dossierid $insql", $inparams);
    $DB->delete_records_select('these_dossier', "id $insql", $inparams);
}

// Numéro DEVE.

/**
 * Propose le numéro d'enregistrement DEVE suivant, au format AAAA-NNN : l'année en cours et le
 * premier numéro d'ordre libre de cette année dans l'activité.
 *
 * @param int $theseid
 * @param int|null $time Date de référence (année), maintenant par défaut.
 * @return string
 */
function these_suggest_devenumber($theseid, $time = null) {
    global $DB;

    $year = userdate($time ?? time(), '%Y');
    $numbers = $DB->get_fieldset_select(
        'these_dossier',
        'devenumber',
        'theseid = ? AND ' . $DB->sql_like('devenumber', '?'),
        [$theseid, $year . '-%']
    );
    $max = 0;
    foreach ($numbers as $number) {
        if (preg_match('/^' . $year . '-(\d+)$/', (string) $number, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }
    return $year . '-' . sprintf('%03d', $max + 1);
}

/**
 * Indique si un numéro DEVE est déjà attribué à un autre dossier de l'activité.
 *
 * @param int $theseid
 * @param string $number
 * @param int $excludeid Dossier à ignorer (celui qu'on enregistre).
 * @return bool
 */
function these_devenumber_taken($theseid, $number, $excludeid = 0) {
    global $DB;

    return $DB->record_exists_select(
        'these_dossier',
        'theseid = ? AND devenumber = ? AND id <> ?',
        [$theseid, trim($number), $excludeid]
    );
}

// Directeurs.

/**
 * Utilisateurs inscrits pouvant être choisis comme directeur de thèse.
 *
 * @param context $context
 * @return array Utilisateurs indexés par id, triés par nom.
 */
function these_get_potential_directors(context $context) {
    $users = get_enrolled_users($context, 'mod/these:direct', 0, 'u.*', 'u.lastname ASC, u.firstname ASC', 0, 0, true);
    return $users;
}

/**
 * Options du sélecteur de directeur, en conservant le directeur actuel même s'il n'est plus
 * inscrit (pour ne pas le perdre silencieusement à l'enregistrement).
 *
 * @param context $context
 * @param int $currentid
 * @return array id => nom
 */
function these_director_options(context $context, $currentid = 0) {
    global $DB;

    $options = [0 => get_string('choosedots')];
    foreach (these_get_potential_directors($context) as $user) {
        $options[$user->id] = fullname($user);
    }
    if ($currentid && !isset($options[$currentid])) {
        $user = $DB->get_record('user', ['id' => $currentid]);
        if ($user) {
            $options[$currentid] = fullname($user);
        }
    }
    return $options;
}

// Jury.

/**
 * Membres du jury d'un dossier, dans leur ordre.
 *
 * @param int $dossierid
 * @return array
 */
function these_get_jury($dossierid) {
    global $DB;

    return array_values($DB->get_records('these_jury', ['dossierid' => $dossierid], 'sortorder ASC, id ASC'));
}

/**
 * Remplace le jury d'un dossier.
 *
 * @param int $dossierid
 * @param array $members Liste d'objets {juryrole, fullname, jurytitle, institution, email}.
 * @return void
 */
function these_save_jury($dossierid, array $members) {
    global $DB;

    $DB->delete_records('these_jury', ['dossierid' => $dossierid]);
    $sortorder = 0;
    foreach ($members as $member) {
        $DB->insert_record('these_jury', (object) [
            'dossierid' => $dossierid,
            'juryrole' => $member->juryrole,
            'fullname' => $member->fullname,
            'jurytitle' => $member->jurytitle,
            'institution' => $member->institution,
            'email' => $member->email,
            'sortorder' => $sortorder++,
            'timecreated' => time(),
        ]);
    }
}

/**
 * Ajoute à un formulaire les lignes répétables de saisie du jury.
 *
 * @param moodleform $form
 * @param MoodleQuickForm $mform
 * @param int $count Nombre de lignes déjà renseignées.
 * @return void
 */
function these_add_jury_fields(moodleform $form, MoodleQuickForm $mform, $count) {
    $mform->addElement('header', 'juryheader', get_string('jury', 'mod_these'));
    $mform->setExpanded('juryheader');
    $mform->addElement('static', 'juryhelp', '', get_string('juryhelp', 'mod_these'));

    $repeat = [];
    $repeat[] = $mform->createElement('static', 'jurymemberlabel', '', html_writer::tag('hr', ''));
    $repeat[] = $mform->createElement('select', 'juryrole', get_string('juryrole', 'mod_these'), these_jury_role_options());
    $repeat[] = $mform->createElement('text', 'juryname', get_string('juryname', 'mod_these'), ['size' => 48]);
    $repeat[] = $mform->createElement('text', 'jurytitle', get_string('jurytitle', 'mod_these'), ['size' => 48]);
    $repeat[] = $mform->createElement('text', 'juryinstitution', get_string('juryinstitution', 'mod_these'), ['size' => 48]);
    $repeat[] = $mform->createElement('text', 'juryemail', get_string('juryemail', 'mod_these'), ['size' => 48]);

    $options = [
        'juryname' => ['type' => PARAM_TEXT],
        'jurytitle' => ['type' => PARAM_TEXT],
        'juryinstitution' => ['type' => PARAM_TEXT],
        'juryemail' => ['type' => PARAM_RAW_TRIMMED],
    ];
    $form->repeat_elements(
        $repeat,
        max(4, $count + 1),
        $options,
        'juryrepeats',
        'juryaddfields',
        2,
        get_string('juryaddmembers', 'mod_these'),
        true
    );
}

/**
 * Valeurs de formulaire correspondant à un jury enregistré (voir these_add_jury_fields()).
 *
 * @param array $members
 * @return array
 */
function these_jury_form_data(array $members) {
    $data = ['juryrole' => [], 'juryname' => [], 'jurytitle' => [], 'juryinstitution' => [], 'juryemail' => []];
    foreach (array_values($members) as $i => $member) {
        $data['juryrole'][$i] = $member->juryrole;
        $data['juryname'][$i] = $member->fullname;
        $data['jurytitle'][$i] = $member->jurytitle;
        $data['juryinstitution'][$i] = $member->institution;
        $data['juryemail'][$i] = $member->email;
    }
    return $data;
}

/**
 * Extrait des données soumises les membres du jury renseignés (lignes au nom non vide).
 *
 * @param stdClass|array $data
 * @return array Liste d'objets {juryrole, fullname, jurytitle, institution, email}.
 */
function these_extract_submitted_jury($data) {
    $data = (array) $data;
    $members = [];
    foreach ((array) ($data['juryname'] ?? []) as $i => $name) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }
        $role = $data['juryrole'][$i] ?? 'member';
        $members[] = (object) [
            'juryrole' => array_key_exists($role, these_jury_role_options()) ? $role : 'member',
            'fullname' => $name,
            'jurytitle' => trim((string) ($data['jurytitle'][$i] ?? '')),
            'institution' => trim((string) ($data['juryinstitution'][$i] ?? '')),
            'email' => trim((string) ($data['juryemail'][$i] ?? '')),
        ];
    }
    return $members;
}

/**
 * Vérifie la composition d'un jury : un et un seul président, au moins $minjury membres votants
 * (les invités ne votent pas), et des adresses de courriel valides lorsqu'elles sont données.
 *
 * @param array $members Voir these_extract_submitted_jury().
 * @param int $minjury
 * @return string|null Message d'erreur, ou null si le jury est valide.
 */
function these_validate_jury(array $members, $minjury) {
    $presidents = 0;
    $voting = 0;
    foreach ($members as $member) {
        if ($member->juryrole === 'president') {
            $presidents++;
        }
        if ($member->juryrole !== 'guest') {
            $voting++;
        }
        if ($member->email !== '' && !validate_email($member->email)) {
            return get_string('juryinvalidemail', 'mod_these', s($member->fullname));
        }
    }
    if ($presidents !== 1) {
        return get_string('juryonepresident', 'mod_these');
    }
    if ($voting < (int) $minjury) {
        return get_string('juryminmembers', 'mod_these', (int) $minjury);
    }
    return null;
}

// Fichiers.

/**
 * Options des gestionnaires de fichiers selon la zone.
 *
 * @param string $filearea
 * @return array
 */
function these_filemanager_options($filearea) {
    $options = ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']];
    switch ($filearea) {
        case 'subjectdocs':
        case 'defensedocs':
            $options['maxfiles'] = 10;
            $options['accepted_types'] = '*';
            break;
        case 'directorreport':
            $options['accepted_types'] = ['.pdf', '.doc', '.docx', '.odt'];
            break;
    }
    return $options;
}

/**
 * Fichiers d'une zone d'un dossier, triés par nom.
 *
 * @param context $context
 * @param string $filearea
 * @param int $dossierid
 * @return stored_file[]
 */
function these_get_files(context $context, $filearea, $dossierid) {
    $fs = get_file_storage();
    return array_values($fs->get_area_files($context->id, 'mod_these', $filearea, $dossierid, 'filename', false));
}

/**
 * Liens de téléchargement des fichiers d'une zone.
 *
 * @param context $context
 * @param string $filearea
 * @param int $dossierid
 * @return string HTML, vide si la zone est vide.
 */
function these_render_file_links(context $context, $filearea, $dossierid) {
    $links = [];
    foreach (these_get_files($context, $filearea, $dossierid) as $file) {
        $url = moodle_url::make_pluginfile_url(
            $context->id,
            'mod_these',
            $filearea,
            $dossierid,
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
        $links[] = html_writer::link($url, s($file->get_filename()));
    }
    return implode(html_writer::empty_tag('br'), $links);
}

/**
 * Indique si une zone de fichiers d'un dossier contient au moins un fichier.
 *
 * @param context $context
 * @param string $filearea
 * @param int $dossierid
 * @return bool
 */
function these_has_files(context $context, $filearea, $dossierid) {
    $fs = get_file_storage();
    return !$fs->is_area_empty($context->id, 'mod_these', $filearea, $dossierid);
}

/**
 * Nombre de fichiers dans une zone de brouillon (contrôle d'un dépôt obligatoire).
 *
 * @param int $draftitemid
 * @return int
 */
function these_count_draft_files($draftitemid) {
    $info = file_get_draft_area_info($draftitemid);
    return (int) $info['filecount'];
}

// Courriels.

/**
 * Courriels envoyés par l'activité, personnalisables par la DEVE (notifications.php).
 *
 * "vars" liste les variables disponibles sous la forme {{xxx}}, comme dans mod_stage : le texte
 * personnalisé n'est pas une chaîne de langue et ne passe pas par son mécanisme de substitution.
 *
 * @return array emailkey => ['subjectstring' => string, 'bodystring' => string, 'vars' => string[]]
 */
function these_get_email_definitions() {
    return [
        'actionrequired' => [
            'subjectstring' => 'emailactionrequiredsubject',
            'bodystring' => 'emailactionrequiredbody',
            'vars' => ['student', 'activity', 'title', 'status', 'url'],
        ],
        'returned' => [
            'subjectstring' => 'emailreturnedsubject',
            'bodystring' => 'emailreturnedbody',
            'vars' => ['student', 'activity', 'title', 'comment', 'url'],
        ],
        'subjectactive' => [
            'subjectstring' => 'emailsubjectactivesubject',
            'bodystring' => 'emailsubjectactivebody',
            'vars' => ['student', 'activity', 'title', 'devenumber', 'url'],
        ],
        'convocation' => [
            'subjectstring' => 'emailconvocationsubject',
            'bodystring' => 'emailconvocationbody',
            'vars' => ['name', 'student', 'title', 'date', 'location'],
        ],
        'university' => [
            'subjectstring' => 'emailuniversitysubject',
            'bodystring' => 'emailuniversitybody',
            'vars' => ['student', 'title', 'devenumber', 'date', 'location'],
        ],
        'closed' => [
            'subjectstring' => 'emailclosedsubject',
            'bodystring' => 'emailclosedbody',
            'vars' => ['student', 'activity', 'title', 'result', 'url'],
        ],
        'reminder' => [
            'subjectstring' => 'emailremindersubject',
            'bodystring' => 'emailreminderbody',
            'vars' => ['student', 'activity', 'title', 'status', 'days', 'url'],
        ],
    ];
}

/**
 * Personnalisation éventuelle d'un courriel pour une instance.
 *
 * @param int $theseid
 * @param string $emailkey
 * @return stdClass|null {subject, body}
 */
function these_get_custom_email_template($theseid, $emailkey) {
    global $DB;

    return $DB->get_record('these_email_template', ['theseid' => $theseid, 'emailkey' => $emailkey]) ?: null;
}

/**
 * Enregistre (ou efface, si les deux champs sont vides) la personnalisation d'un courriel.
 *
 * @param int $theseid
 * @param string $emailkey
 * @param string $subject
 * @param string $body
 * @return void
 */
function these_save_email_template($theseid, $emailkey, $subject, $body) {
    global $DB;

    $existing = $DB->get_record('these_email_template', ['theseid' => $theseid, 'emailkey' => $emailkey]);
    if (trim($subject) === '' && trim($body) === '') {
        if ($existing) {
            $DB->delete_records('these_email_template', ['id' => $existing->id]);
        }
        return;
    }

    $record = (object) [
        'theseid' => $theseid,
        'emailkey' => $emailkey,
        'subject' => $subject,
        'body' => $body,
        'timemodified' => time(),
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('these_email_template', $record);
    } else {
        $DB->insert_record('these_email_template', $record);
    }
}

/**
 * Remplace dans un texte personnalisé les jetons {{cle}} par les valeurs fournies.
 *
 * @param string $text
 * @param array $vars cle => valeur
 * @return string
 */
function these_render_email_placeholders($text, array $vars) {
    $search = [];
    $replace = [];
    foreach ($vars as $key => $value) {
        $search[] = '{{' . $key . '}}';
        $replace[] = (string) $value;
    }
    return str_replace($search, $replace, $text);
}

/**
 * Sujet et corps d'un courriel : le texte personnalisé par la DEVE s'il existe, sinon le texte
 * par défaut (chaîne de langue, qui reçoit les mêmes variables sous la forme {$a->xxx}).
 *
 * @param int $theseid
 * @param string $emailkey
 * @param array $vars
 * @return stdClass {subject, body}
 */
function these_resolve_email_text($theseid, $emailkey, array $vars) {
    $definition = these_get_email_definitions()[$emailkey];
    $custom = these_get_custom_email_template($theseid, $emailkey);

    if ($custom && trim((string) $custom->subject) !== '') {
        $subject = these_render_email_placeholders($custom->subject, $vars);
    } else {
        $subject = get_string($definition['subjectstring'], 'mod_these', (object) $vars);
    }
    if ($custom && trim((string) $custom->body) !== '') {
        $body = these_render_email_placeholders($custom->body, $vars);
    } else {
        $body = get_string($definition['bodystring'], 'mod_these', (object) $vars);
    }
    return (object) ['subject' => $subject, 'body' => $body];
}

/**
 * Destinataire factice pour une adresse sans compte Moodle (boîte de service, membre du jury),
 * sur le modèle du maître de stage de mod_stage.
 *
 * @param string $email
 * @param string $name
 * @return stdClass
 */
function these_make_external_user($email, $name = '') {
    $names = explode(' ', trim($name), 2);
    return (object) [
        'id' => -1,
        'email' => $email,
        'firstname' => $names[0] !== '' ? $names[0] : $email,
        'lastname' => $names[1] ?? '',
        'firstnamephonetic' => '',
        'lastnamephonetic' => '',
        'middlename' => '',
        'alternatename' => '',
        'maildisplay' => true,
        'mailformat' => 1,
        'auth' => 'manual',
        'confirmed' => 1,
        'suspended' => 0,
        'deleted' => 0,
        'emailstop' => 0,
    ];
}

/**
 * Destinataires des notifications adressées à un service (DEVE ou bibliothèque) : sa boîte de
 * service si elle est renseignée dans les paramètres de l'activité, sinon chaque utilisateur
 * inscrit qui détient la capacité correspondante.
 *
 * @param stdClass $these
 * @param context $context
 * @param string $actor 'deve' ou 'library'.
 * @return array Utilisateurs (réels ou factices).
 */
function these_get_service_recipients(stdClass $these, context $context, $actor) {
    $email = $actor === 'deve' ? trim((string) $these->deveemail) : trim((string) $these->libraryemail);
    if ($email !== '') {
        return [these_make_external_user($email, these_actor_label($actor))];
    }
    $capability = $actor === 'deve' ? 'mod/these:managedeve' : 'mod/these:managelibrary';
    return array_values(get_enrolled_users($context, $capability, 0, 'u.*', null, 0, 0, true));
}

/**
 * Destinataires attendus à l'étape courante d'un dossier.
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @return array
 */
function these_get_actor_recipients(stdClass $these, context $context, stdClass $dossier) {
    global $DB;

    $actor = these_step_actor($dossier->status);
    if ($actor === 'student') {
        $user = $DB->get_record('user', ['id' => $dossier->userid, 'deleted' => 0]);
        return $user ? [$user] : [];
    }
    if ($actor === 'director') {
        $user = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid, 'deleted' => 0]) : null;
        return $user ? [$user] : [];
    }
    if ($actor === 'deve' || $actor === 'library') {
        return these_get_service_recipients($these, $context, $actor);
    }
    return [];
}

/**
 * Variables communes à tous les courriels portant sur un dossier.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return array
 */
function these_email_base_vars(stdClass $these, stdClass $cm, stdClass $dossier) {
    global $DB;

    $student = $DB->get_record('user', ['id' => $dossier->userid]);
    $url = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
    return [
        'student' => $student ? fullname($student) : '',
        'activity' => format_string($these->name),
        'title' => format_string($dossier->title),
        'url' => $url->out(false),
    ];
}

/**
 * Envoie un courriel à une liste de destinataires.
 *
 * @param stdClass $these
 * @param string $emailkey
 * @param array $recipients
 * @param array $vars
 * @param string $attachment Chemin d'une pièce jointe (dans le répertoire temporaire de Moodle).
 * @param string $attachname Nom de la pièce jointe.
 * @return int Nombre de courriels partis.
 */
function these_send_email(stdClass $these, $emailkey, array $recipients, array $vars, $attachment = '', $attachname = '') {
    $text = these_resolve_email_text($these->id, $emailkey, $vars);
    $noreply = core_user::get_noreply_user();
    $sent = 0;
    foreach ($recipients as $recipient) {
        if (email_to_user($recipient, $noreply, $text->subject, $text->body, '', $attachment, $attachname)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Prévient l'acteur attendu à l'étape courante qu'un dossier l'attend.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int Nombre de courriels partis.
 */
function these_notify_actor(stdClass $these, stdClass $cm, stdClass $dossier) {
    $context = context_module::instance($cm->id);
    $recipients = these_get_actor_recipients($these, $context, $dossier);
    if (empty($recipients)) {
        return 0;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['status'] = these_status_label($dossier->status);
    return these_send_email($these, 'actionrequired', $recipients, $vars);
}

/**
 * Prévient le destinataire d'un renvoi (étudiant ou directeur), motif à l'appui.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string $target 'student' ou 'director'.
 * @param string $comment
 * @return int
 */
function these_notify_returned(stdClass $these, stdClass $cm, stdClass $dossier, $target, $comment) {
    global $DB;

    $userid = $target === 'student' ? $dossier->userid : $dossier->directorid;
    $user = $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]) : null;
    if (!$user) {
        return 0;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['comment'] = $comment;
    return these_send_email($these, 'returned', [$user], $vars);
}

/**
 * Prévient l'étudiant et son directeur que le sujet est enregistré et le dossier ouvert.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int
 */
function these_notify_subject_active(stdClass $these, stdClass $cm, stdClass $dossier) {
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['devenumber'] = (string) $dossier->devenumber;
    return these_send_email($these, 'subjectactive', these_get_student_and_director($dossier), $vars);
}

/**
 * Prévient l'étudiant et son directeur de la clôture du dossier.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int
 */
function these_notify_closed(stdClass $these, stdClass $cm, stdClass $dossier) {
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['result'] = these_result_label($dossier->defenseresult);
    return these_send_email($these, 'closed', these_get_student_and_director($dossier), $vars);
}

/**
 * L'étudiant et son directeur, s'ils existent toujours.
 *
 * @param stdClass $dossier
 * @return array
 */
function these_get_student_and_director(stdClass $dossier) {
    global $DB;

    $users = [];
    foreach ([$dossier->userid, $dossier->directorid] as $userid) {
        if ($userid && ($user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]))) {
            $users[] = $user;
        }
    }
    return $users;
}

// Relances.

/**
 * Dossiers dont l'étape courante attend depuis plus longtemps que le délai de relance de leur
 * activité, et qui n'ont pas été relancés depuis ce même délai.
 *
 * Les brouillons et les dossiers actifs ne sont jamais relancés : c'est à l'étudiant de décider
 * quand il dépose son sujet, puis sa soutenance (la phase « dormante » peut durer des années).
 *
 * @param int|null $now
 * @return array Dossiers indexés par id, avec le délai de leur activité (reminderdays).
 */
function these_get_dossiers_needing_reminder($now = null) {
    global $DB;

    $now = $now ?? time();
    $excluded = [THESE_STATUS_DRAFT, THESE_STATUS_ACTIVE, THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED];
    [$notinsql, $params] = $DB->get_in_or_equal($excluded, SQL_PARAMS_NAMED, 'st', false);
    $params['now1'] = $now;
    $params['now2'] = $now;

    $sql = "SELECT d.*, t.reminderdays
              FROM {these_dossier} d
              JOIN {these} t ON t.id = d.theseid
             WHERE t.reminderdays > 0
               AND d.status $notinsql
               AND d.statustime <= :now1 - t.reminderdays * 86400
               AND d.remindertime <= :now2 - t.reminderdays * 86400";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Relance l'acteur attendu pour un dossier, et note la relance si au moins un courriel est parti.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return bool
 */
function these_send_reminder(stdClass $these, stdClass $cm, stdClass $dossier) {
    global $DB;

    $context = context_module::instance($cm->id);
    $recipients = these_get_actor_recipients($these, $context, $dossier);
    if (empty($recipients)) {
        return false;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['status'] = these_status_label($dossier->status);
    $vars['days'] = (int) floor((time() - $dossier->statustime) / DAYSECS);
    if (!these_send_email($these, 'reminder', $recipients, $vars)) {
        return false;
    }
    $DB->set_field('these_dossier', 'remindertime', time(), ['id' => $dossier->id]);
    $actor = these_actor_label(these_step_actor($dossier->status));
    these_log($dossier->id, 0, 'reminded', $dossier->status, $dossier->status, $actor);
    return true;
}

// Documents de soutenance.

/**
 * Données communes aux documents générés pour un dossier.
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @return array
 */
function these_document_data(stdClass $these, stdClass $dossier) {
    global $DB;

    $student = $DB->get_record('user', ['id' => $dossier->userid]);
    $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;
    $date = (int) ($dossier->defensedate ?: $dossier->proposeddate);

    return [
        'establishmentname' => (string) $these->establishmentname,
        'establishmentaddress' => (string) $these->establishmentaddress,
        'city' => (string) $these->establishmentcity,
        'signatoryname' => (string) $these->signatoryname,
        'signatorytitle' => (string) $these->signatorytitle,
        'student' => $student ? fullname($student) : '',
        'director' => $director ? fullname($director) : '',
        'codirector' => (string) $dossier->codirectorname,
        'title' => (string) $dossier->title,
        'titleen' => (string) $dossier->titleen,
        'summary' => (string) $dossier->summary,
        'keywords' => (string) $dossier->keywords,
        'devenumber' => (string) $dossier->devenumber,
        'libraryref' => (string) $dossier->libraryref,
        'date' => $date ? userdate($date, get_string('strftimedaydate', 'langconfig')) : '-',
        'time' => $date ? userdate($date, get_string('strftimetime', 'langconfig')) : '-',
        'location' => (string) $dossier->defenselocation,
        'today' => userdate(time(), get_string('strftimedate', 'langconfig')),
        'jury' => these_get_jury($dossier->id),
        'result' => these_result_label($dossier->defenseresult),
        'mention' => (string) $dossier->mention,
    ];
}

/**
 * Nom de fichier sûr pour un document généré.
 *
 * @param string $prefix
 * @param string $name
 * @return string
 */
function these_document_filename($prefix, $name = '') {
    $slug = $name !== '' ? '-' . trim(preg_replace('/[^a-z0-9]+/', '-', core_text::strtolower(
        core_text::specialtoascii($name)
    )), '-') : '';
    return clean_filename($prefix . $slug . '.pdf');
}

/**
 * Génère les documents de soutenance (phase 2, étape 5) et les range dans la zone « generated »,
 * à la place des précédents : une convocation pour l'étudiant, une par membre du jury, la feuille
 * de jury (émargement) et le procès-verbal à compléter et signer le jour de la soutenance.
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param int $userid
 * @return stored_file[]
 */
function these_generate_documents(stdClass $these, context $context, stdClass $dossier, $userid) {
    global $DB;

    $data = these_document_data($these, $dossier);
    $documents = [];
    $documents[these_document_filename('convocation-etudiant')] =
        \mod_these\pdf\document_pdf::convocation($data, null);
    foreach ($data['jury'] as $index => $member) {
        $documents[these_document_filename('convocation-jury-' . ($index + 1), $member->fullname)] =
            \mod_these\pdf\document_pdf::convocation($data, $member);
    }
    $documents[these_document_filename('feuille-de-jury')] = \mod_these\pdf\document_pdf::jury_sheet($data);
    $documents[these_document_filename('proces-verbal')] = \mod_these\pdf\document_pdf::minutes($data);

    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_these', 'generated', $dossier->id);
    $files = [];
    foreach ($documents as $filename => $content) {
        $files[] = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => 'generated',
            'itemid' => $dossier->id,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => $userid,
        ], $content);
    }

    $now = time();
    $DB->update_record('these_dossier', (object) ['id' => $dossier->id, 'documentstime' => $now, 'timemodified' => $now]);
    $dossier->documentstime = $now;
    these_log($dossier->id, $userid, 'documentsgenerated', $dossier->status, $dossier->status);
    return $files;
}

/**
 * Transmet les documents générés : à chaque membre du jury qui a une adresse, sa convocation ; à
 * l'étudiant, la sienne ; à l'adresse universitaire de l'activité, l'ensemble des documents en
 * archive. La première transmission fait passer le dossier au statut « soutenance programmée ».
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $dossier
 * @param int $userid
 * @return stdClass {sent, failed, missing (noms des membres du jury sans adresse)}
 */
function these_transmit_documents(stdClass $these, stdClass $cm, context $context, stdClass $dossier, $userid) {
    global $DB;

    $files = [];
    foreach (these_get_files($context, 'generated', $dossier->id) as $file) {
        $files[$file->get_filename()] = $file;
    }
    $data = these_document_data($these, $dossier);
    $result = (object) ['sent' => 0, 'failed' => 0, 'missing' => []];
    $basevars = [
        'student' => $data['student'],
        'title' => format_string($dossier->title),
        'date' => $data['date'] . ' ' . $data['time'],
        'location' => $data['location'],
    ];

    $send = function ($recipient, $name, $filename) use ($these, $files, $basevars, $result) {
        $attachment = '';
        if (isset($files[$filename])) {
            $attachment = $files[$filename]->copy_content_to_temp('mod_these');
        }
        $sent = these_send_email(
            $these,
            'convocation',
            [$recipient],
            array_merge($basevars, ['name' => $name]),
            $attachment ?: '',
            $attachment ? $filename : ''
        );
        $sent ? $result->sent++ : $result->failed++;
    };

    $student = $DB->get_record('user', ['id' => $dossier->userid, 'deleted' => 0]);
    if ($student) {
        $send($student, fullname($student), these_document_filename('convocation-etudiant'));
    }
    foreach ($data['jury'] as $index => $member) {
        if ($member->email === '') {
            $result->missing[] = $member->fullname;
            continue;
        }
        $send(
            these_make_external_user($member->email, $member->fullname),
            $member->fullname,
            these_document_filename('convocation-jury-' . ($index + 1), $member->fullname)
        );
    }

    $universityemail = trim((string) $these->universityemail);
    if ($universityemail !== '' && $files) {
        $zipname = clean_filename('soutenance-' . ($dossier->devenumber ?: $dossier->id) . '.zip');
        $zippath = make_request_directory() . '/' . $zipname;
        $packer = get_file_packer('application/zip');
        $packer->archive_to_pathname($files, $zippath);
        $sent = these_send_email(
            $these,
            'university',
            [these_make_external_user($universityemail)],
            array_merge($basevars, ['devenumber' => (string) $dossier->devenumber]),
            $zippath,
            $zipname
        );
        $sent ? $result->sent++ : $result->failed++;
    }

    $fields = ['senttime' => time()];
    $remark = get_string('transmitsummary', 'mod_these', $result);
    if ((int) $dossier->status === THESE_STATUS_CONFORM) {
        these_set_status($dossier, THESE_STATUS_SCHEDULED, $userid, 'documentssent', $remark, $fields);
    } else {
        these_set_status($dossier, $dossier->status, $userid, 'documentsresent', $remark, $fields);
    }
    return $result;
}

// Listes.

/**
 * Dossiers d'une instance, filtrés.
 *
 * @param int $theseid
 * @param array $filters status (int|null), directorid (int|null), search (string), queue (string[]
 *                       acteurs dont on veut les dossiers en attente), queuedirectorid (int).
 * @return array Dossiers avec les champs de nom de l'étudiant et du directeur.
 */
function these_get_dossiers($theseid, array $filters = []) {
    global $DB;

    $studentfields = \core_user\fields::for_name()->get_sql('su', false, 'student', '', false)->selects;
    $directorfields = \core_user\fields::for_name()->get_sql('du', false, 'director', '', false)->selects;

    $where = ['d.theseid = :theseid'];
    $params = ['theseid' => $theseid];

    if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
        $where[] = 'd.status = :status';
        $params['status'] = (int) $filters['status'];
    }
    if (!empty($filters['directorid'])) {
        $where[] = 'd.directorid = :directorid';
        $params['directorid'] = (int) $filters['directorid'];
    }
    if (!empty($filters['search'])) {
        $like = '%' . $DB->sql_like_escape($filters['search']) . '%';
        $where[] = '(' . $DB->sql_like('d.title', ':s1', false, false)
            . ' OR ' . $DB->sql_like($DB->sql_fullname('su.firstname', 'su.lastname'), ':s2', false, false)
            . ' OR ' . $DB->sql_like('d.devenumber', ':s3', false, false) . ')';
        $params['s1'] = $like;
        $params['s2'] = $like;
        $params['s3'] = $like;
    }
    if (isset($filters['queue'])) {
        $queue = [];
        foreach ((array) $filters['queue'] as $actor) {
            $statuses = array_values(array_filter(these_all_statuses(), function ($status) use ($actor) {
                return these_step_actor($status) === $actor;
            }));
            [$insql, $inparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'q' . $actor);
            $condition = "d.status $insql";
            if ($actor === 'director') {
                $condition .= ' AND d.directorid = :queuedirectorid';
                $params['queuedirectorid'] = (int) ($filters['queuedirectorid'] ?? 0);
            }
            $queue[] = "($condition)";
            $params = array_merge($params, $inparams);
        }
        $where[] = $queue ? '(' . implode(' OR ', $queue) . ')' : '1 = 0';
    }

    $sql = "SELECT d.*, $studentfields, $directorfields
              FROM {these_dossier} d
              JOIN {user} su ON su.id = d.userid
         LEFT JOIN {user} du ON du.id = d.directorid
             WHERE " . implode(' AND ', $where) . "
          ORDER BY d.statustime ASC, d.id ASC";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Nombre de dossiers par statut dans une instance.
 *
 * @param int $theseid
 * @param int|null $directorid Limite aux dossiers d'un directeur.
 * @return array statut => nombre
 */
function these_count_by_status($theseid, $directorid = null) {
    global $DB;

    $params = ['theseid' => $theseid];
    $where = 'theseid = :theseid';
    if ($directorid !== null) {
        $where .= ' AND directorid = :directorid';
        $params['directorid'] = $directorid;
    }
    $records = $DB->get_records_sql(
        "SELECT status, COUNT(1) AS total FROM {these_dossier} WHERE $where GROUP BY status",
        $params
    );
    $counts = [];
    foreach ($records as $record) {
        $counts[(int) $record->status] = (int) $record->total;
    }
    return $counts;
}

/**
 * Découpe une liste en pages.
 *
 * @param array $items
 * @param int $page
 * @param moodle_url $baseurl
 * @param int $perpage
 * @return array [éléments de la page, barre de pagination HTML]
 */
function these_paginate(array $items, $page, moodle_url $baseurl, $perpage = THESE_LIST_PERPAGE) {
    global $OUTPUT;

    $items = array_values($items);
    $total = count($items);
    $pageitems = array_slice($items, $page * $perpage, $perpage);
    $pagingbar = $total > $perpage ? $OUTPUT->render(new paging_bar($total, $page, $perpage, $baseurl)) : '';
    return [$pageitems, $pagingbar];
}

// Rendu.

/**
 * Liens rendus en boutons.
 *
 * @param array $links libellé => moodle_url (null : ignoré)
 * @param string $class
 * @return string HTML
 */
function these_render_actions(array $links, $class = 'btn btn-sm btn-secondary mr-1 mb-1') {
    $out = '';
    foreach ($links as $label => $url) {
        if ($url !== null) {
            $out .= html_writer::link($url, $label, ['class' => $class]);
        }
    }
    return $out;
}

/**
 * Barre de navigation commune aux pages du personnel.
 *
 * @param stdClass $cm
 * @param context $context
 * @return string HTML
 */
function these_render_navlinks(stdClass $cm, context $context) {
    $links = [];
    if (
        has_capability('mod/these:viewall', $context) || has_capability('mod/these:managedeve', $context)
            || has_capability('mod/these:managelibrary', $context) || has_capability('mod/these:direct', $context)
    ) {
        $links[get_string('dashboard', 'mod_these')] = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/these:managedeve', $context)) {
        $links[get_string('createdossier', 'mod_these')] = new moodle_url('/mod/these/create.php', ['id' => $cm->id]);
        $links[get_string('importdossiers', 'mod_these')] = new moodle_url('/mod/these/import.php', ['id' => $cm->id]);
        $links[get_string('notifications', 'mod_these')] = new moodle_url('/mod/these/notifications.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/these:viewall', $context) || has_capability('mod/these:managelibrary', $context)) {
        $links[get_string('export', 'mod_these')] = new moodle_url('/mod/these/export.php', ['id' => $cm->id]);
    }
    if (empty($links)) {
        return '';
    }
    return html_writer::div(these_render_actions($links, 'btn btn-secondary mr-1 mb-1'), 'these-navlinks mb-3');
}

/**
 * Frise de progression d'un dossier dans le circuit.
 *
 * @param stdClass $dossier
 * @return string HTML
 */
function these_render_progress(stdClass $dossier) {
    $status = (int) $dossier->status;
    $rank = these_status_rank($status === THESE_STATUS_CANCELLED ? $dossier->cancelledfromstatus : $status);
    $items = '';
    $currentfound = false;
    foreach (these_progress_steps() as $index => $step) {
        if ($rank >= $step['done']) {
            $class = 'these-step-done';
        } else if (!$currentfound && $status !== THESE_STATUS_CANCELLED) {
            $class = 'these-step-current';
            $currentfound = true;
        } else {
            $class = 'these-step-todo';
        }
        $label = html_writer::span(($index + 1) . '. ' . $step['label'], 'these-step-label')
            . html_writer::span(these_actor_label($step['actor']), 'these-step-actor');
        $items .= html_writer::tag('li', $label, ['class' => 'these-step ' . $class]);
        if ($index === 3) {
            $items .= html_writer::tag('li', get_string('dormantphase', 'mod_these'), ['class' => 'these-step-separator']);
        }
    }
    return html_writer::tag('ol', $items, ['class' => 'these-progress']);
}

/**
 * Section de détail en tableau à deux colonnes ; les lignes vides (null) sont omises.
 *
 * @param string $heading
 * @param array $rows libellé => valeur HTML
 * @return string HTML
 */
function these_render_detail_section($heading, array $rows) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-detail';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $table->data[] = [html_writer::tag('strong', $label), $value];
    }
    if (empty($table->data)) {
        return '';
    }
    return html_writer::tag('h4', $heading, ['class' => 'mt-4']) . html_writer::table($table);
}

/**
 * Tableau du jury.
 *
 * @param array $members
 * @param bool $showemail
 * @return string HTML
 */
function these_render_jury(array $members, $showemail = true) {
    if (empty($members)) {
        return '';
    }
    $roles = these_jury_role_options();
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('juryrole', 'mod_these'), get_string('juryname', 'mod_these'),
        get_string('jurytitle', 'mod_these'), get_string('juryinstitution', 'mod_these')];
    if ($showemail) {
        $table->head[] = get_string('juryemail', 'mod_these');
    }
    foreach ($members as $member) {
        $row = [$roles[$member->juryrole] ?? s($member->juryrole), s($member->fullname), s($member->jurytitle),
            s($member->institution)];
        if ($showemail) {
            $row[] = s($member->email);
        }
        $table->data[] = $row;
    }
    return html_writer::tag('h4', get_string('jury', 'mod_these'), ['class' => 'mt-4']) . html_writer::table($table);
}

/**
 * Historique d'un dossier.
 *
 * @param int $dossierid
 * @param bool $staffview Vue du personnel (directeur, DEVE, bibliothèque) : sinon, le motif d'un
 *                        renvoi de la DEVE au directeur, qui ne s'adresse pas à l'étudiant, est
 *                        masqué (voir these_render_dossier()).
 * @return string HTML
 */
function these_render_history($dossierid, $staffview = true) {
    global $DB;

    $logs = $DB->get_records('these_log', ['dossierid' => $dossierid], 'timecreated DESC, id DESC');
    if (empty($logs)) {
        return '';
    }
    $userids = array_filter(array_unique(array_map(function ($log) {
        return (int) $log->userid;
    }, $logs)));
    $users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-history';
    $table->head = [get_string('date'), get_string('historyaction', 'mod_these'), get_string('historyby', 'mod_these'),
        get_string('status', 'mod_these'), get_string('historyremark', 'mod_these')];
    $strman = get_string_manager();
    foreach ($logs as $log) {
        $actionkey = 'log' . $log->logaction;
        $by = $log->userid && isset($users[$log->userid]) ? fullname($users[$log->userid]) : get_string('system', 'mod_these');
        $row = [
            userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $strman->string_exists($actionkey, 'mod_these') ? get_string($actionkey, 'mod_these') : s($log->logaction),
            $by,
            these_status_label($log->tostatus),
            $staffview || $log->logaction !== 'returnedtodirector' ? format_text((string) $log->remark, FORMAT_PLAIN) : '',
        ];
        $table->data[] = $row;
    }
    return html_writer::tag('h4', get_string('history', 'mod_these'), ['class' => 'mt-4']) . html_writer::table($table);
}

/**
 * Boutons d'action proposés à l'utilisateur sur un dossier, selon son rôle et l'étape courante.
 *
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @return array libellé => moodle_url
 */
function these_get_dossier_actions(stdClass $cm, stdClass $dossier, stdClass $rights) {
    $params = ['id' => $cm->id, 'dossierid' => $dossier->id];
    $status = (int) $dossier->status;
    $links = [];

    if ($rights->student && these_subject_editable($dossier)) {
        $links[get_string('editsubject', 'mod_these')] = new moodle_url('/mod/these/subject.php', $params);
    }
    if ($rights->student && these_defense_editable($dossier)) {
        $links[get_string('depositdefense', 'mod_these')] = new moodle_url('/mod/these/defense.php', $params);
    }
    $steps = these_review_steps();
    if (isset($steps[$status]) && !empty($rights->{$steps[$status]['actor']})) {
        $links[get_string('review' . $steps[$status]['key'], 'mod_these')] = new moodle_url('/mod/these/review.php', $params);
    }
    if ($rights->deve && in_array($status, [THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED], true)) {
        $links[get_string('documents', 'mod_these')] = new moodle_url('/mod/these/documents.php', $params);
    }
    if ($rights->deve && $status === THESE_STATUS_SCHEDULED) {
        $links[get_string('depositminutes', 'mod_these')] = new moodle_url('/mod/these/minutes.php', $params);
    }
    if ($rights->deve) {
        $links[get_string('editdossier', 'mod_these')] = new moodle_url('/mod/these/edit.php', $params);
        if ($status === THESE_STATUS_CANCELLED) {
            $links[get_string('reopendossier', 'mod_these')] = new moodle_url('/mod/these/cancel.php', $params);
        } else if ($status !== THESE_STATUS_CLOSED) {
            $links[get_string('canceldossier', 'mod_these')] = new moodle_url('/mod/these/cancel.php', $params);
        }
    }
    if ($status >= THESE_STATUS_SUBJECT_REGISTERED || ($status === THESE_STATUS_CANCELLED && $dossier->devenumber)) {
        $links[get_string('subjectsheet', 'mod_these')] =
            new moodle_url('/mod/these/document.php', $params + ['type' => 'subject']);
    }
    if ($status >= THESE_STATUS_CONFORM && $status <= THESE_STATUS_CLOSED) {
        $links[get_string('conformitycertificate', 'mod_these')] =
            new moodle_url('/mod/these/document.php', $params + ['type' => 'conformity']);
    }
    return $links;
}

/**
 * Rend un dossier complet : progression, renvoi en cours, actions, sujet, enregistrements,
 * soutenance, documents et historique. Utilisé par la page de l'étudiant et par dossier.php.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $dossier
 * @param stdClass $rights Voir these_get_rights().
 * @param bool $withactions Affiche les boutons d'action (pas sur les pages d'action elles-mêmes).
 * @return string HTML
 */
function these_render_dossier(
    stdClass $these,
    stdClass $cm,
    context $context,
    stdClass $dossier,
    stdClass $rights,
    $withactions = true
) {
    global $DB;

    $out = these_render_progress($dossier);

    $status = (int) $dossier->status;
    $actor = these_step_actor($status);
    $statusline = these_render_status_badge($status);
    if ($actor !== null) {
        $statusline .= ' ' . html_writer::span(
            get_string('waitingfor', 'mod_these', these_actor_label($actor)) . ' — '
                . get_string('since', 'mod_these', userdate($dossier->statustime, get_string('strftimedate', 'langconfig'))),
            'text-muted'
        );
    }
    $out .= html_writer::div($statusline, 'mb-3');

    // Renvoi en cours : affiché au personnel et à son destinataire, avec le motif. Un renvoi de la
    // DEVE au directeur ne s'adresse pas à l'étudiant, qui n'en voit que le statut.
    $returnedtostudent = in_array($status, [THESE_STATUS_SUBJECT_RETURNED, THESE_STATUS_DEFENSE_RETURNED], true);
    $showreturn = $rights->viewall || $rights->director || ($rights->owner && $returnedtostudent);
    if ($showreturn && !empty($dossier->returntime) && trim((string) $dossier->returncomment) !== '') {
        $returner = $DB->get_record('user', ['id' => $dossier->returnedby]);
        $a = (object) [
            'by' => $returner ? fullname($returner) : get_string('system', 'mod_these'),
            'date' => userdate($dossier->returntime, get_string('strftimedate', 'langconfig')),
        ];
        $out .= html_writer::div(
            html_writer::tag('strong', get_string('returnedby', 'mod_these', $a)) . html_writer::empty_tag('br')
                . format_text($dossier->returncomment, FORMAT_PLAIN),
            'alert alert-warning'
        );
    }

    $actions = $withactions
        ? these_render_actions(these_get_dossier_actions($cm, $dossier, $rights), 'btn btn-primary mr-1 mb-1') : '';
    if ($actions !== '') {
        $out .= html_writer::div($actions, 'these-actions mb-3');
    }

    $student = $DB->get_record('user', ['id' => $dossier->userid]);
    $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;

    $out .= these_render_detail_section(get_string('subject', 'mod_these'), [
        get_string('student', 'mod_these') => $student ? fullname($student) : '-',
        get_string('subjecttitle', 'mod_these') => format_string($dossier->title) ?: '-',
        get_string('subjecttitleen', 'mod_these') => format_string((string) $dossier->titleen),
        get_string('subjectsummary', 'mod_these') => format_text((string) $dossier->summary, FORMAT_PLAIN),
        get_string('subjectkeywords', 'mod_these') => s((string) $dossier->keywords),
        get_string('director', 'mod_these') => $director ? fullname($director) : '-',
        get_string('codirector', 'mod_these') => s((string) $dossier->codirectorname),
        get_string('subjectdocs', 'mod_these') => these_render_file_links($context, 'subjectdocs', $dossier->id),
    ]);

    $out .= these_render_detail_section(get_string('registration', 'mod_these'), [
        get_string('devenumber', 'mod_these') => s((string) $dossier->devenumber),
        get_string('libraryref', 'mod_these') => s((string) $dossier->libraryref),
    ]);

    if (these_rank_reached($dossier, THESE_STATUS_ACTIVE) || $dossier->proposeddate) {
        $dateformat = get_string('strftimedaydatetime', 'langconfig');
        $rows = [
            get_string('proposeddate', 'mod_these') =>
                $dossier->proposeddate ? userdate($dossier->proposeddate, $dateformat) : null,
            get_string('defensedate', 'mod_these') => $dossier->defensedate ? userdate($dossier->defensedate, $dateformat) : null,
            get_string('defenselocation', 'mod_these') => s((string) $dossier->defenselocation),
            get_string('manuscript', 'mod_these') => these_render_file_links($context, 'manuscript', $dossier->id),
            get_string('defensedocs', 'mod_these') => these_render_file_links($context, 'defensedocs', $dossier->id),
        ];
        if (these_can_view_filearea('directorreport', $rights)) {
            $rows[get_string('directorreport', 'mod_these')] =
                these_render_file_links($context, 'directorreport', $dossier->id);
        }
        $rows[get_string('generateddocuments', 'mod_these')] = these_render_file_links($context, 'generated', $dossier->id);
        if ($status === THESE_STATUS_CLOSED) {
            $rows[get_string('defenseresult', 'mod_these')] = these_result_label($dossier->defenseresult);
            $rows[get_string('mention', 'mod_these')] = s((string) $dossier->mention);
            $rows[get_string('minutes', 'mod_these')] = these_render_file_links($context, 'pv', $dossier->id);
        }
        $out .= these_render_detail_section(get_string('defense', 'mod_these'), $rows);
        $out .= these_render_jury(these_get_jury($dossier->id), $rights->viewall || $rights->director || $rights->owner);
    }

    $out .= these_render_history($dossier->id, $rights->viewall || $rights->director);
    return $out;
}

/**
 * Indique si un dossier a atteint (ou dépassé) un statut donné dans le circuit.
 *
 * @param stdClass $dossier
 * @param int $status
 * @return bool
 */
function these_rank_reached(stdClass $dossier, $status) {
    $current = (int) $dossier->status === THESE_STATUS_CANCELLED ? $dossier->cancelledfromstatus : $dossier->status;
    return these_status_rank($current) >= these_status_rank($status);
}

/**
 * Tableau de dossiers pour les listes du tableau de bord.
 *
 * @param array $dossiers Voir these_get_dossiers().
 * @param stdClass $cm
 * @return string HTML
 */
function these_render_dossier_table(array $dossiers, stdClass $cm) {
    if (empty($dossiers)) {
        return html_writer::div(get_string('nodossiers', 'mod_these'), 'alert alert-info');
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-dossiers';
    $table->head = [
        get_string('student', 'mod_these'),
        get_string('subjecttitle', 'mod_these'),
        get_string('director', 'mod_these'),
        get_string('devenumber', 'mod_these'),
        get_string('status', 'mod_these'),
        get_string('since', 'mod_these', ''),
        '',
    ];
    $dateformat = get_string('strftimedate', 'langconfig');
    foreach ($dossiers as $dossier) {
        $student = (object) [];
        $director = (object) [];
        foreach (\core_user\fields::get_name_fields() as $field) {
            $student->$field = $dossier->{'student' . $field} ?? '';
            $director->$field = $dossier->{'director' . $field} ?? '';
        }
        $url = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
        $table->data[] = [
            fullname($student),
            shorten_text(format_string($dossier->title), 90) ?: '-',
            $dossier->directorid ? fullname($director) : '-',
            s((string) $dossier->devenumber),
            these_render_status_badge($dossier->status),
            userdate($dossier->statustime, $dateformat),
            html_writer::link($url, get_string('open', 'mod_these'), ['class' => 'btn btn-sm btn-secondary']),
        ];
    }
    return html_writer::table($table);
}

// Import.

/**
 * Colonnes reconnues dans un fichier d'import de dossiers. « student » et « title » sont
 * obligatoires ; les autres facultatives.
 *
 * @return string[]
 */
function these_import_columns() {
    return ['student', 'title', 'titleen', 'summary', 'keywords', 'director', 'codirector', 'devenumber', 'libraryref'];
}

/**
 * Retrouve un utilisateur par identifiant de connexion, adresse de courriel ou numéro
 * d'identification, dans cet ordre.
 *
 * @param string $value
 * @return stdClass|null
 */
function these_find_user($value) {
    global $CFG, $DB;

    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $user = $DB->get_record('user', ['username' => core_text::strtolower($value), 'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0]);
    if (!$user) {
        $users = $DB->get_records_select('user', 'LOWER(email) = LOWER(?) AND deleted = 0', [$value], 'id', '*', 0, 2);
        $user = count($users) === 1 ? reset($users) : null;
    }
    if (!$user) {
        $users = $DB->get_records('user', ['idnumber' => $value, 'deleted' => 0], 'id', '*', 0, 2);
        $user = count($users) === 1 ? reset($users) : null;
    }
    return $user ?: null;
}

/**
 * Importe des dossiers existants depuis un fichier CSV : chaque ligne crée un dossier au statut
 * « actif » (sujet déjà validé et enregistré hors du module), avec une entrée d'historique
 * « importé ». Les lignes en erreur sont ignorées et signalées ; aucune ligne n'écrase un dossier
 * existant.
 *
 * @param stdClass $these
 * @param string $content Contenu du fichier.
 * @param string $delimiter Nom du séparateur (voir csv_import_reader::get_delimiter_list()).
 * @param string $encoding
 * @param bool $dryrun Vérifie sans rien créer.
 * @param int $userid Auteur de l'import.
 * @return array Liste de ['line' => int, 'student' => string, 'ok' => bool, 'message' => string].
 */
function these_import_dossiers(stdClass $these, $content, $delimiter, $encoding, $dryrun, $userid) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/csvlib.class.php');

    $iid = csv_import_reader::get_new_iid('mod_these');
    $reader = new csv_import_reader($iid, 'mod_these');
    $count = $reader->load_csv_content($content, $encoding, $delimiter);
    if ($count === false) {
        throw new moodle_exception('importinvalidfile', 'mod_these', '', $reader->get_error());
    }
    $columns = array_map(function ($column) {
        return core_text::strtolower(trim($column));
    }, $reader->get_columns());
    foreach (['student', 'title'] as $required) {
        if (!in_array($required, $columns, true)) {
            $reader->cleanup();
            throw new moodle_exception('importmissingcolumn', 'mod_these', '', $required);
        }
    }

    $report = [];
    $seen = [];
    $numbers = [];
    $line = 1;
    $reader->init();
    while ($row = $reader->next()) {
        $line++;
        $values = [];
        foreach ($columns as $index => $column) {
            if (in_array($column, these_import_columns(), true)) {
                $values[$column] = trim((string) ($row[$index] ?? ''));
            }
        }
        $values += array_fill_keys(these_import_columns(), '');
        $result = ['line' => $line, 'student' => $values['student'], 'ok' => false, 'message' => ''];

        $student = these_find_user($values['student']);
        $director = $values['director'] !== '' ? these_find_user($values['director']) : null;
        if (!$student) {
            $result['message'] = get_string('importusernotfound', 'mod_these', s($values['student']));
        } else if (
            isset($seen[$student->id])
                || $DB->record_exists('these_dossier', ['theseid' => $these->id, 'userid' => $student->id])
        ) {
            $result['message'] = get_string('importdossierexists', 'mod_these');
        } else if ($values['title'] === '') {
            $result['message'] = get_string('importtitlemissing', 'mod_these');
        } else if ($values['director'] !== '' && !$director) {
            $result['message'] = get_string('importusernotfound', 'mod_these', s($values['director']));
        } else if (
            $values['devenumber'] !== ''
                && (isset($numbers[$values['devenumber']]) || these_devenumber_taken($these->id, $values['devenumber']))
        ) {
            $result['message'] = get_string('devenumbertaken', 'mod_these');
        } else {
            $result['ok'] = true;
            $result['student'] = fullname($student);
            $seen[$student->id] = true;
            if ($values['devenumber'] !== '') {
                $numbers[$values['devenumber']] = true;
            }
            if (!$dryrun) {
                $now = time();
                $dossierid = $DB->insert_record('these_dossier', (object) [
                    'theseid' => $these->id,
                    'userid' => $student->id,
                    'status' => THESE_STATUS_ACTIVE,
                    'statustime' => $now,
                    'title' => $values['title'],
                    'titleen' => $values['titleen'],
                    'summary' => $values['summary'],
                    'keywords' => $values['keywords'],
                    'directorid' => $director ? $director->id : 0,
                    'codirectorname' => $values['codirector'],
                    'devenumber' => $values['devenumber'],
                    'libraryref' => $values['libraryref'],
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                these_log($dossierid, $userid, 'imported', THESE_STATUS_ACTIVE, THESE_STATUS_ACTIVE);
            }
            $result['message'] = get_string($dryrun ? 'importwouldcreate' : 'importcreated', 'mod_these');
        }
        $report[] = $result;
    }
    $reader->close();
    $reader->cleanup();
    return $report;
}
