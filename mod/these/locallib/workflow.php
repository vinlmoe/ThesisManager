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
        THESE_STATUS_CORRECTIONS, THESE_STATUS_CORRECTIONS_SUBMITTED, THESE_STATUS_CORRECTIONS_APPROVED,
        THESE_STATUS_CORRECTIONS_CONFORM, THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED,
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
        THESE_STATUS_CORRECTIONS => 'statuscorrections',
        THESE_STATUS_CORRECTIONS_SUBMITTED => 'statuscorrectionssubmitted',
        THESE_STATUS_CORRECTIONS_APPROVED => 'statuscorrectionsapproved',
        THESE_STATUS_CORRECTIONS_CONFORM => 'statuscorrectionsconform',
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
        case THESE_STATUS_CORRECTIONS:
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
        case THESE_STATUS_CORRECTIONS:
            return 'student';
        case THESE_STATUS_SUBJECT_SUBMITTED:
        case THESE_STATUS_DEFENSE_SUBMITTED:
        case THESE_STATUS_CORRECTIONS_SUBMITTED:
            return 'director';
        case THESE_STATUS_SUBJECT_APPROVED:
        case THESE_STATUS_DEFENSE_APPROVED:
        case THESE_STATUS_CONFORM:
        case THESE_STATUS_SCHEDULED:
        case THESE_STATUS_CORRECTIONS_CONFORM:
            return 'deve';
        case THESE_STATUS_SUBJECT_REGISTERED:
        case THESE_STATUS_DEFENSE_VALIDATED:
        case THESE_STATUS_CORRECTIONS_APPROVED:
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
 * - returnto : destinataires possibles d'un renvoi ('student', 'director') ;
 * - sign : documents signés à la validation, par emplacement de signature ;
 * - request : documents dont les signataires sans compte sont invités à la validation.
 *
 * @return array
 */
function these_review_steps() {
    return [
        THESE_STATUS_SUBJECT_SUBMITTED => [
            'key' => 'directorsubject', 'actor' => 'director',
            'next' => THESE_STATUS_SUBJECT_APPROVED, 'returnto' => ['student'],
            'sign' => ['director' => ['subject']], 'request' => [],
        ],
        THESE_STATUS_SUBJECT_APPROVED => [
            'key' => 'devesubject', 'actor' => 'deve',
            'next' => THESE_STATUS_SUBJECT_REGISTERED, 'returnto' => ['student', 'director'],
            'sign' => ['deve' => ['subject']], 'request' => [],
        ],
        THESE_STATUS_SUBJECT_REGISTERED => [
            'key' => 'librarysubject', 'actor' => 'library',
            'next' => THESE_STATUS_ACTIVE, 'returnto' => ['student'],
            'sign' => [], 'request' => [],
        ],
        THESE_STATUS_DEFENSE_SUBMITTED => [
            'key' => 'directordefense', 'actor' => 'director',
            'next' => THESE_STATUS_DEFENSE_APPROVED, 'returnto' => ['student'],
            'sign' => ['director' => ['report', 'grid', 'agreement', 'contract', 'conclusions']], 'request' => [],
        ],
        THESE_STATUS_DEFENSE_APPROVED => [
            'key' => 'devedefense', 'actor' => 'deve',
            'next' => THESE_STATUS_DEFENSE_VALIDATED, 'returnto' => ['student', 'director'],
            'sign' => ['deve' => ['agreement'], 'dgrep' => ['agreement']],
            'request' => ['agreement', 'signaletique', 'contract', 'conclusions'],
        ],
        THESE_STATUS_DEFENSE_VALIDATED => [
            'key' => 'libraryconformity', 'actor' => 'library',
            'next' => THESE_STATUS_CONFORM, 'returnto' => ['student'],
            'sign' => ['library' => ['conformity']], 'request' => [],
        ],
        // Corrections demandées par le jury : validées tour à tour avant la clôture du dossier.
        THESE_STATUS_CORRECTIONS_SUBMITTED => [
            'key' => 'directorcorrections', 'actor' => 'director',
            'next' => THESE_STATUS_CORRECTIONS_APPROVED, 'returnto' => ['student'],
            'sign' => [], 'request' => [],
        ],
        THESE_STATUS_CORRECTIONS_APPROVED => [
            'key' => 'librarycorrections', 'actor' => 'library',
            'next' => THESE_STATUS_CORRECTIONS_CONFORM, 'returnto' => ['student'],
            'sign' => [], 'request' => [],
        ],
        THESE_STATUS_CORRECTIONS_CONFORM => [
            'key' => 'devecorrections', 'actor' => 'deve',
            'next' => THESE_STATUS_CLOSED, 'returnto' => ['student'],
            'sign' => [], 'request' => [],
        ],
    ];
}

/**
 * Indique si un statut appartient au circuit des corrections demandées par le jury.
 *
 * @param int $status
 * @return bool
 */
function these_in_corrections($status) {
    return (int) $status >= THESE_STATUS_CORRECTIONS && (int) $status < THESE_STATUS_CLOSED;
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
            'done' => THESE_STATUS_CORRECTIONS],
        ['label' => get_string('stepcorrections', 'mod_these'), 'actor' => 'student',
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
 * Mentions que le jury peut décerner.
 *
 * @return array clé => libellé
 */
function these_mention_options() {
    return [
        'honorable' => get_string('mentionhonorable', 'mod_these'),
        'treshonorable' => get_string('mentiontreshonorable', 'mod_these'),
        'felicitations' => get_string('mentionfelicitations', 'mod_these'),
    ];
}

/**
 * Libellé d'une mention (une mention saisie librement avant la liste fermée est rendue telle quelle).
 *
 * @param string|null $mention
 * @return string
 */
function these_mention_label($mention) {
    return these_mention_options()[(string) $mention] ?? (string) $mention;
}

/**
 * Décisions du jury, lisibles : résultat, mention, proposition pour un prix, corrections.
 *
 * @param stdClass $dossier
 * @return string
 */
function these_decision_label(stdClass $dossier) {
    $parts = [these_result_label($dossier->defenseresult)];
    if ((string) $dossier->mention !== '') {
        $parts[] = get_string('mention', 'mod_these') . ' ' . core_text::strtolower(these_mention_label($dossier->mention));
    }
    if (!empty($dossier->prizeproposal)) {
        $parts[] = get_string('prizeproposal', 'mod_these');
    }
    if (!empty($dossier->correctionsrequested)) {
        $parts[] = get_string('correctionsrequested', 'mod_these');
    }
    return implode(' ; ', $parts);
}

/**
 * Rôles possibles d'un membre du jury.
 *
 * « Membre du jury » vient en premier : c'est le rôle proposé d'office sur une ligne vide du
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
