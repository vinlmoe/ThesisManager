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

// Droits.

/**
 * Rôle Moodle associé à un service dans les réglages du site (Administration du site > Plugins >
 * Modules d'activité > Gestion des thèses) : « deverole » ou « libraryrole ».
 *
 * @param string $actor 'deve' ou 'library'.
 * @return int Identifiant du rôle, 0 si aucun n'est choisi.
 */
function these_get_service_roleid($actor) {
    return (int) get_config('mod_these', $actor === 'deve' ? 'deverole' : 'libraryrole');
}

/**
 * Indique si l'utilisateur détient le rôle d'un service dans le contexte ou l'un de ses parents :
 * le rôle peut être attribué dans le cours, dans sa catégorie ou au niveau du site.
 *
 * @param context $context
 * @param int $userid
 * @param string $actor 'deve' ou 'library'.
 * @return bool
 */
function these_user_has_service_role(context $context, $userid, $actor) {
    $roleid = these_get_service_roleid($actor);
    if (!$roleid || !$userid) {
        return false;
    }
    foreach ($context->get_parent_context_ids(true) as $contextid) {
        if (user_has_role_assignment($userid, $roleid, $contextid)) {
            return true;
        }
    }
    return false;
}

/**
 * Crée, s'il n'existe pas encore, le rôle interne « Bibliothèque (thèses) », et règle les rôles
 * des services par défaut (DEVE : rôle « Enseignant » ; bibliothèque : ce rôle interne) tant que
 * l'administrateur n'en a pas choisi d'autres.
 *
 * Le rôle peut être attribué au niveau du site ou d'une catégorie : il donne alors accès à toutes
 * les activités de gestion des thèses concernées sans inscription au cours.
 *
 * @return int Identifiant du rôle interne.
 */
function these_setup_default_roles() {
    global $DB;

    // À l'installation, xmldb_these_install() s'exécute avant l'enregistrement des capacités du
    // module : elles sont enregistrées ici d'abord, pour pouvoir les accorder au rôle.
    update_capabilities('mod_these');

    $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'theselibrary']);
    if (!$roleid) {
        $roleid = create_role(
            get_string('libraryrolename', 'mod_these'),
            'theselibrary',
            get_string('libraryroledescription', 'mod_these'),
            ''
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE]);
        $systemcontext = context_system::instance();
        foreach (['mod/these:view', 'mod/these:managelibrary', 'mod/these:viewall', 'moodle/course:view'] as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
        }
    }
    if (get_config('mod_these', 'libraryrole') === false) {
        set_config('libraryrole', $roleid, 'mod_these');
    }
    if (get_config('mod_these', 'deverole') === false) {
        set_config('deverole', (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']), 'mod_these');
    }
    return $roleid;
}

/**
 * Droits de l'utilisateur sur un dossier (ou sur l'activité, si aucun dossier n'est donné).
 *
 * La DEVE et la bibliothèque sont reconnues soit par leur capacité, soit par le rôle choisi pour
 * leur service dans les réglages du site (voir these_user_has_service_role()). Un directeur de
 * thèse n'a de droits que sur les dossiers qu'il dirige ; la DEVE et la bibliothèque, sur tous
 * les dossiers de l'activité.
 *
 * @param context $context
 * @param stdClass|null $dossier
 * @param int $userid
 * @return stdClass {owner, student, director, deve, library, viewall}
 */
function these_get_rights(context $context, ?stdClass $dossier, $userid) {
    $rights = new stdClass();
    $rights->deve = has_capability('mod/these:managedeve', $context, $userid)
        || these_user_has_service_role($context, $userid, 'deve');
    $rights->library = has_capability('mod/these:managelibrary', $context, $userid)
        || these_user_has_service_role($context, $userid, 'library');
    $rights->viewall = $rights->deve || $rights->library || has_capability('mod/these:viewall', $context, $userid);
    $rights->owner = $dossier !== null && (int) $dossier->userid === (int) $userid;
    $rights->student = $rights->owner && has_capability('mod/these:submit', $context, $userid);
    $rights->director = $dossier !== null && (int) $dossier->directorid === (int) $userid
        && has_capability('mod/these:direct', $context, $userid);
    return $rights;
}

/**
 * Exige l'accès à l'activité : la capacité de consultation, ou le rôle d'un service.
 *
 * @param context $context
 * @return stdClass Droits de l'utilisateur courant sur l'activité (voir these_get_rights()).
 */
function these_require_view(context $context) {
    global $USER;

    $rights = these_get_rights($context, null, $USER->id);
    if (!$rights->deve && !$rights->library) {
        require_capability('mod/these:view', $context);
    }
    return $rights;
}

/**
 * Exige d'être la DEVE (ou la bibliothèque) dans l'activité.
 *
 * @param context $context
 * @param string $actor 'deve' ou 'library'.
 * @return stdClass Droits de l'utilisateur courant (voir these_get_rights()).
 */
function these_require_service(context $context, $actor) {
    global $USER;

    $rights = these_get_rights($context, null, $USER->id);
    if (empty($rights->$actor)) {
        require_capability($actor === 'deve' ? 'mod/these:managedeve' : 'mod/these:managelibrary', $context);
    }
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
        case THESE_STATUS_CORRECTIONS:
            return (int) $dossier->returnstatus ?: THESE_STATUS_CORRECTIONS_SUBMITTED;
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
 * Indique si le rapport du directeur peut être rédigé : tant que le directeur n'a pas validé la
 * soutenance.
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_report_editable(stdClass $dossier) {
    return (int) $dossier->status === THESE_STATUS_DEFENSE_SUBMITTED;
}

/**
 * Enregistre le rapport du directeur (sans changer le statut du dossier). Les signatures déjà
 * apposées sur un rapport modifié sont invalidées.
 *
 * @param stdClass $dossier
 * @param int $userid
 * @param string $text
 * @param bool $favorable
 * @return stdClass Le dossier à jour.
 */
function these_save_report(stdClass $dossier, $userid, $text, $favorable) {
    $dossier = these_set_status($dossier, $dossier->status, $userid, 'reportsaved', '', [
        'reporttext' => trim((string) $text),
        'reportfavorable' => $favorable ? 1 : 0,
    ]);
    $cm = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, MUST_EXIST);
    these_refresh_signatures(context_module::instance($cm->id), $dossier);
    return $dossier;
}

/**
 * Signature transmise par un formulaire (voir these_add_signature_fields()).
 *
 * @param stdClass $data
 * @return stdClass|null {name, png}, ou null si aucune signature n'a été tracée.
 */
function these_signature_from_form(stdClass $data) {
    global $USER;

    $png = !empty($data->usesavedsignature) ? these_get_saved_signature($USER->id) : null;
    if ($png === null) {
        $png = these_decode_signature_image($data->signaturedata ?? '');
        if ($png !== null && !empty($data->savesignature) && isloggedin() && !isguestuser()) {
            these_save_signature_image($USER->id, $png);
        }
    }
    if ($png === null) {
        return null;
    }
    return (object) ['name' => trim((string) ($data->signername ?? '')), 'png' => $png];
}

/**
 * Découpe une liste de mots-clés séparés par des virgules ou des points-virgules.
 *
 * @param string $keywords
 * @return string[]
 */
function these_split_keywords($keywords) {
    return array_values(array_filter(array_map('trim', preg_split('/[;,]/', (string) $keywords)), 'strlen'));
}

/**
 * Valeur d'un champ soumis, ou valeur actuelle du dossier si le formulaire ne le contient pas.
 *
 * @param stdClass $data
 * @param stdClass $dossier
 * @param string $field
 * @return mixed
 */
function these_submitted_value(stdClass $data, stdClass $dossier, $field) {
    if (property_exists($data, $field)) {
        return is_string($data->$field) ? trim($data->$field) : $data->$field;
    }
    return $dossier->$field ?? '';
}

/**
 * Statut atteint à la soumission, compte tenu des signatures que les modifications viennent
 * d'invalider : un dossier renvoyé revient normalement à l'étape qui l'a renvoyé, mais si un
 * document signé par le directeur a changé, il repart chez le directeur pour qu'il le signe de
 * nouveau.
 *
 * @param stdClass $dossier Dossier avant changement de statut.
 * @param string[] $invalidated Signatures invalidées (« doctype:slot »).
 * @return int
 */
function these_resubmission_target(stdClass $dossier, array $invalidated) {
    $target = these_submission_target($dossier);
    $directorstep = these_phase($dossier->status) === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
    foreach ($invalidated as $key) {
        if (substr($key, -strlen(':director')) === ':director' && $target > $directorstep) {
            return $directorstep;
        }
    }
    return $target;
}

/**
 * Enregistre la fiche de sujet (phase 1, étape 1) et, si demandé, la soumet signée.
 *
 * Toute modification du contenu invalide les signatures déjà apposées sur le formulaire de dépôt
 * de sujet (voir these_refresh_signatures()). À la soumission, la signature de l'étudiant est
 * apposée et, si le directeur est extérieur, sa signature lui est demandée par courriel.
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
    $context = context_module::instance($cm->id);
    $fields = [];
    foreach (
        ['title', 'titleen', 'summary', 'keywords', 'codirectorname', 'studyyear', 'directortitle',
            'extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field
    ) {
        $fields[$field] = (string) these_submitted_value($data, $dossier, $field);
    }
    foreach (['directorid', 'confidential', 'externaldirector'] as $field) {
        $fields[$field] = (int) these_submitted_value($data, $dossier, $field);
    }
    if (property_exists($data, 'commitguide')) {
        $fields['commitments'] = (!empty($data->commitguide) ? THESE_COMMIT_GUIDE : 0)
            | (!empty($data->commitduplicate) ? THESE_COMMIT_DUPLICATE : 0)
            | (!empty($data->commitethics) ? THESE_COMMIT_ETHICS : 0);
    }
    if (empty($fields['externaldirector'])) {
        foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
            $fields[$field] = '';
        }
    }

    if (!$submit) {
        $dossier = these_set_status($dossier, $dossier->status, $userid, 'subjectsaved', '', $fields);
        these_refresh_signatures($context, $dossier);
        return $dossier;
    }

    $previous = clone $dossier;
    foreach ($fields as $field => $value) {
        $dossier->$field = $value;
    }
    $invalidated = these_refresh_signatures($context, $dossier);
    $target = these_resubmission_target($previous, $invalidated);
    $resubmitted = (int) $previous->status === THESE_STATUS_SUBJECT_RETURNED;
    $dossier = these_set_status(
        $previous,
        $target,
        $userid,
        $resubmitted ? 'subjectresubmitted' : 'subjectsubmitted',
        '',
        array_merge($fields, these_cleared_return_fields())
    );
    if ($signature !== null) {
        these_sign_document($context, $dossier, 'subject', 'student', $userid, $signature->name, $signature->png);
    }
    if (!empty($dossier->externaldirector)) {
        these_request_external_signatures($these, $cm, $dossier, 'subject');
    }
    these_notify_actor($these, $cm, $dossier);
    return $dossier;
}

/**
 * Données de l'étudiant soumises avec le dépôt de soutenance (voir these_bordereau_fields()),
 * fusionnées avec celles déjà enregistrées.
 *
 * @param stdClass $data
 * @param array $existing
 * @return array
 */
function these_extract_bordereau(stdClass $data, array $existing) {
    $bordereau = $existing;
    foreach (these_bordereau_fields() as $field) {
        if ($field === 'keywords') {
            if (property_exists($data, 'bkeyword')) {
                $bordereau['keywords'] = array_values(array_filter(array_map('trim', (array) $data->bkeyword), 'strlen'));
            }
        } else if (property_exists($data, 'b' . $field)) {
            $value = $data->{'b' . $field};
            $bordereau[$field] = is_string($value) ? trim($value) : $value;
        }
    }
    return $bordereau;
}

/**
 * Enregistre le dossier de soutenance (phase 2, étape 1) : date et lieu proposés, jury, données
 * du bordereau, de la fiche signalétique et du contrat de diffusion, conclusions, et, si demandé,
 * le soumet signé (bordereau et contrat de diffusion). Les fichiers (manuscrit, autres
 * documents) sont enregistrés par l'appelant.
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
    $context = context_module::instance($cm->id);
    these_save_jury($dossier->id, these_extract_submitted_jury($data));

    $fields = [
        'proposeddate' => (int) $data->proposeddate,
        'defenselocation' => trim((string) $data->defenselocation),
        'titleen' => (string) these_submitted_value($data, $dossier, 'titleen'),
        'conclusions' => (string) these_submitted_value($data, $dossier, 'conclusions'),
        'bordereau' => json_encode(these_extract_bordereau($data, these_get_bordereau($dossier)), JSON_UNESCAPED_UNICODE),
    ];
    // Tant que la DEVE n'a pas arrêté la date, la date retenue suit la proposition de l'étudiant.
    if ((int) $dossier->status === THESE_STATUS_ACTIVE || empty($dossier->defensedate)) {
        $fields['defensedate'] = (int) $data->proposeddate;
    }

    if (!$submit) {
        $dossier = these_set_status($dossier, $dossier->status, $userid, 'defensesaved', '', $fields);
        these_refresh_signatures($context, $dossier);
        return $dossier;
    }

    $previous = clone $dossier;
    foreach ($fields as $field => $value) {
        $dossier->$field = $value;
    }
    $invalidated = these_refresh_signatures($context, $dossier);
    $target = these_resubmission_target($previous, $invalidated);
    $resubmitted = (int) $previous->status === THESE_STATUS_DEFENSE_RETURNED;
    $dossier = these_set_status(
        $previous,
        $target,
        $userid,
        $resubmitted ? 'defenseresubmitted' : 'defensesubmitted',
        '',
        array_merge($fields, these_cleared_return_fields())
    );
    if ($signature !== null) {
        foreach (['bordereau', 'contract'] as $doctype) {
            these_sign_document($context, $dossier, $doctype, 'student', $userid, $signature->name, $signature->png);
        }
    }
    these_notify_actor($these, $cm, $dossier);
    return $dossier;
}

/**
 * Valide l'étape de validation courante du dossier (voir these_review_steps()) : enregistre les
 * champs propres à l'étape, appose la signature de l'acteur sur les documents de l'étape, invite
 * les signataires sans compte attendus, et prévient l'acteur suivant.
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
    $steps = these_review_steps();
    $step = $steps[(int) $dossier->status] ?? null;
    if ($step === null) {
        throw new coding_exception('Aucune étape de validation pour le statut ' . $dossier->status);
    }
    $context = context_module::instance($cm->id);

    $dossier = these_set_status(
        $dossier,
        $step['next'],
        $userid,
        'validated' . $step['key'],
        $remark,
        array_merge($fields, these_cleared_return_fields())
    );
    these_refresh_signatures($context, $dossier);
    $agentdoctypes = [];
    foreach ($step['sign'] as $slot => $doctypes) {
        if (!these_signs_slot_at_validation($slot, $userid)) {
            // Emplacement de l'agent DEVE : il signe ensuite, depuis « Mes signatures ».
            $agentdoctypes = array_merge($agentdoctypes, $doctypes);
            continue;
        }
        foreach ($doctypes as $doctype) {
            if ($signature !== null && in_array($slot, these_document_slots($dossier, $doctype), true)) {
                these_sign_document($context, $dossier, $doctype, $slot, $userid, $signature->name, $signature->png);
            }
        }
    }
    if ($agentdoctypes) {
        these_request_agent_signatures($these, $cm, $dossier, array_unique($agentdoctypes));
    }
    if ($step['request']) {
        // Un seul appel pour tous les documents : chaque signataire reçoit un seul lien.
        these_request_external_signatures($these, $cm, $dossier, $step['request']);
    }

    if ((int) $dossier->status === THESE_STATUS_ACTIVE) {
        these_notify_subject_active($these, $cm, $dossier);
    } else if ((int) $dossier->status === THESE_STATUS_CLOSED) {
        these_notify_closed($these, $cm, $dossier);
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
    if ($target === 'student' && these_in_corrections($dossier->status)) {
        $tostatus = THESE_STATUS_CORRECTIONS;
        $fields['returnstatus'] = (int) $dossier->status;
    } else if ($target === 'student') {
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
 * Enregistre les décisions du jury reportées par la DEVE depuis le procès-verbal scanné (le fichier
 * est enregistré par l'appelant) : résultat, mention, proposition pour un prix de thèse,
 * corrections demandées. Sans corrections, le dossier est clos ; avec corrections, l'étudiant doit
 * déposer son manuscrit corrigé, que le directeur, la bibliothèque et la DEVE valident avant la
 * clôture.
 *
 * Une thèse ajournée ne reçoit ni mention, ni proposition de prix, ni corrections : le dossier
 * revient en phase de soutenance (voir these_adjourn_dossier()).
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
    $admitted = (int) $decision->result === THESE_RESULT_ADMITTED;
    $mention = (string) ($decision->mention ?? '');
    if ((int) $decision->result === THESE_RESULT_ADJOURNED) {
        return these_adjourn_dossier($these, $cm, $dossier, $userid, $remark);
    }
    $corrections = $admitted && !empty($decision->correctionsrequested);
    $fields = [
        'defenseresult' => (int) $decision->result,
        'mention' => $admitted && array_key_exists($mention, these_mention_options()) ? $mention : '',
        'prizeproposal' => $admitted && !empty($decision->prizeproposal) ? 1 : 0,
        'correctionsrequested' => $corrections ? 1 : 0,
        'correctionsnote' => $corrections ? trim((string) ($decision->correctionsnote ?? '')) : '',
    ];
    if ($corrections) {
        $dossier = these_set_status(
            $dossier,
            THESE_STATUS_CORRECTIONS,
            $userid,
            'minutescorrections',
            $remark,
            array_merge($fields, these_cleared_return_fields())
        );
        these_notify_actor($these, $cm, $dossier);
        return $dossier;
    }
    $dossier = these_set_status($dossier, THESE_STATUS_CLOSED, $userid, 'closed', $remark, $fields);
    these_notify_closed($these, $cm, $dossier);
    return $dossier;
}

/**
 * Ajournement de la soutenance : le dossier revient en phase de soutenance (dossier actif), sujet,
 * enregistrements et numéro conservés. L'étudiant dépose un nouveau dossier de soutenance, qui
 * suit de nouveau tout le circuit.
 *
 * Le procès-verbal d'ajournement est archivé (zone pvadjourned, nom préfixé par la date) ; les
 * documents générés et les signatures des documents de soutenance sont supprimés, pour être
 * produits et signés de nouveau ; les accès du jury sont révoqués et son éligibilité sera revue.
 * Le texte du rapport et les données déjà saisies restent proposés.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param string $remark
 * @return stdClass Le dossier à jour.
 */
function these_adjourn_dossier(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $remark = '') {
    global $DB;

    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $prefix = 'ajournement-' . userdate(time(), '%Y-%m-%d') . '-';
    foreach ($fs->get_area_files($context->id, 'mod_these', 'pv', $dossier->id, 'filename', false) as $file) {
        $filename = $prefix . $file->get_filename();
        if (!$fs->file_exists($context->id, 'mod_these', 'pvadjourned', $dossier->id, '/', $filename)) {
            $fs->create_file_from_storedfile(['filearea' => 'pvadjourned', 'filename' => $filename], $file);
        }
    }
    $fs->delete_area_files($context->id, 'mod_these', 'pv', $dossier->id);
    $fs->delete_area_files($context->id, 'mod_these', 'generated', $dossier->id);
    foreach (these_get_signatures($dossier->id) as $signature) {
        if ($signature->doctype !== 'subject') {
            these_delete_signature($context, $signature);
        }
    }
    $DB->set_field('these_jury', 'notifiedtime', 0, ['dossierid' => $dossier->id]);
    $DB->set_field('these_jury', 'accesstoken', null, ['dossierid' => $dossier->id]);
    $DB->set_field('these_jury', 'eligible', 0, ['dossierid' => $dossier->id]);

    $dossier = these_set_status($dossier, THESE_STATUS_ACTIVE, $userid, 'adjourned', $remark, array_merge([
        'defenseresult' => THESE_RESULT_ADJOURNED,
        'mention' => '',
        'prizeproposal' => 0,
        'correctionsrequested' => 0,
        'correctionsnote' => '',
        'reportfavorable' => 0,
        'documentstime' => 0,
        'senttime' => 0,
    ], these_cleared_return_fields()));
    these_send_email($these, 'adjourned', these_get_student_and_director($dossier), these_email_base_vars($these, $cm, $dossier));
    return $dossier;
}

/**
 * Indique si le dossier est en attente d'une nouvelle soutenance après un ajournement.
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_is_adjourned(stdClass $dossier) {
    return (int) $dossier->status === THESE_STATUS_ACTIVE && (int) $dossier->defenseresult === THESE_RESULT_ADJOURNED;
}

/**
 * Dépôt du manuscrit corrigé par l'étudiant (les fichiers sont enregistrés par l'appelant) : le
 * dossier passe au directeur, ou revient à l'étape qui l'avait renvoyé.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param int $userid
 * @param string $done Corrections apportées.
 * @return stdClass Le dossier à jour.
 */
function these_submit_corrections(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $done) {
    if ((int) $dossier->status !== THESE_STATUS_CORRECTIONS) {
        throw new coding_exception('Dépôt des corrections impossible au statut ' . $dossier->status);
    }
    $resubmitted = !empty($dossier->returnstatus);
    $dossier = these_set_status(
        $dossier,
        these_submission_target($dossier),
        $userid,
        $resubmitted ? 'correctionsresubmitted' : 'correctionssubmitted',
        '',
        array_merge(['correctionsdone' => trim((string) $done)], these_cleared_return_fields())
    );
    these_notify_actor($these, $cm, $dossier);
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
    $signatureids = $DB->get_fieldset_select('these_signature', 'id', "dossierid $insql", $inparams);
    if ($context !== null) {
        foreach ($signatureids as $signatureid) {
            get_file_storage()->delete_area_files($context->id, 'mod_these', 'signature', $signatureid);
        }
    }
    $DB->delete_records_select('these_signature', "dossierid $insql", $inparams);
    $DB->delete_records_select('these_jury', "dossierid $insql", $inparams);
    $DB->delete_records_select('these_answer', "dossierid $insql", $inparams);
    $DB->delete_records_select('these_subjecthistory', "dossierid $insql", $inparams);
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
 * Clé d'identité d'un membre du jury, pour reconnaître le même membre d'une saisie à l'autre.
 *
 * @param stdClass $member
 * @return string
 */
function these_jury_member_key(stdClass $member) {
    if (!empty($member->jurorid)) {
        return 'j' . (int) $member->jurorid;
    }
    return 'e' . core_text::strtolower(trim((string) $member->email)) . '|'
        . core_text::strtolower(trim((string) $member->fullname));
}

/**
 * Remplace le jury d'un dossier. Un membre déjà présent (même enseignant de l'annuaire, ou mêmes
 * nom et adresse) garde son accès aux documents et la trace de sa notification ; son éligibilité
 * validée est conservée s'il garde le même rôle et le même statut, sauf si $member->eligible est
 * fourni (saisie de la DEVE).
 *
 * @param int $dossierid
 * @param array $members Liste d'objets {juryrole, fullname, jurytitle, institution, email, jurorid?,
 *                       jurystatus?, eligible?}.
 * @param int $userid Auteur de la validation d'éligibilité, s'il y en a une.
 * @return void
 */
function these_save_jury($dossierid, array $members, $userid = 0) {
    global $DB;

    $previous = [];
    foreach ($DB->get_records('these_jury', ['dossierid' => $dossierid]) as $record) {
        $previous[these_jury_member_key($record)] = $record;
    }
    $DB->delete_records('these_jury', ['dossierid' => $dossierid]);
    $sortorder = 0;
    foreach ($members as $member) {
        $old = $previous[these_jury_member_key($member)] ?? null;
        $record = (object) [
            'dossierid' => $dossierid,
            'juryrole' => $member->juryrole,
            'fullname' => $member->fullname,
            'jurytitle' => $member->jurytitle,
            'institution' => $member->institution,
            'email' => $member->email,
            'sortorder' => $sortorder++,
            'timecreated' => $old->timecreated ?? time(),
            'jurorid' => (int) ($member->jurorid ?? 0),
            'jurystatus' => (string) ($member->jurystatus ?? ''),
            'eligible' => 0,
            'eligibleby' => 0,
            'eligibletime' => 0,
            'accesstoken' => $old->accesstoken ?? null,
            'notifiedtime' => (int) ($old->notifiedtime ?? 0),
        ];
        if (isset($member->eligible)) {
            $record->eligible = $member->eligible ? 1 : 0;
            $record->eligibleby = $member->eligible ? ($old && $old->eligible ? $old->eligibleby : (int) $userid) : 0;
            $record->eligibletime = $member->eligible ? ($old && $old->eligible ? $old->eligibletime : time()) : 0;
        } else if ($old && $old->eligible && $old->juryrole === $record->juryrole && $old->jurystatus === $record->jurystatus) {
            $record->eligible = 1;
            $record->eligibleby = $old->eligibleby;
            $record->eligibletime = $old->eligibletime;
        }
        $DB->insert_record('these_jury', $record);
    }
}

/**
 * Ajoute à un formulaire les lignes répétables de saisie du jury : chaque membre est choisi dans
 * l'annuaire des enseignants (titre, établissement, adresse et statut repris de l'annuaire) ou, pour
 * un membre extérieur ou un invité, saisi.
 *
 * @param moodleform $form
 * @param MoodleQuickForm $mform
 * @param int $count Nombre de lignes déjà renseignées.
 * @param bool $eligibility Ajouter la validation d'éligibilité par la DEVE.
 * @param int[] $keep Enseignants de l'annuaire à proposer même s'ils ne sont plus actifs.
 * @return void
 */
function these_add_jury_fields(moodleform $form, MoodleQuickForm $mform, $count, $eligibility = false, array $keep = []) {
    $mform->addElement('header', 'juryheader', get_string('jury', 'mod_these'));
    $mform->setExpanded('juryheader');
    $mform->addElement('static', 'juryhelp', '', get_string('juryhelp', 'mod_these'));

    $jurors = [0 => get_string('juryexternal', 'mod_these')] + \mod_these\local\jurors::options(false, $keep);
    $repeat = [];
    $repeat[] = $mform->createElement('static', 'jurymemberlabel', '', html_writer::tag('hr', ''));
    $repeat[] = $mform->createElement('select', 'juryrole', get_string('juryrole', 'mod_these'), these_jury_role_options());
    $repeat[] = $mform->createElement('select', 'jurorid', get_string('juryjuror', 'mod_these'), $jurors);
    $repeat[] = $mform->createElement('text', 'juryname', get_string('juryname', 'mod_these'), ['size' => 48]);
    $repeat[] = $mform->createElement('text', 'jurytitle', get_string('jurytitle', 'mod_these'), ['size' => 48]);
    $repeat[] = $mform->createElement(
        'select',
        'jurystatus',
        get_string('jurystatus', 'mod_these'),
        ['' => get_string('choosedots')] + \mod_these\local\jurors::external_statuses()
    );
    $repeat[] = $mform->createElement('text', 'juryinstitution', get_string('juryinstitution', 'mod_these'), ['size' => 48]);
    $repeat[] = $mform->createElement('text', 'juryemail', get_string('juryemail', 'mod_these'), ['size' => 48]);
    if ($eligibility) {
        $repeat[] = $mform->createElement('advcheckbox', 'juryeligible', '', get_string('juryeligiblecheck', 'mod_these'));
    }

    $external = ['jurorid', 'neq', 0];
    $options = [
        'jurorid' => ['type' => PARAM_INT, 'helpbutton' => ['juryjuror', 'mod_these']],
        'juryname' => ['type' => PARAM_TEXT, 'hideif' => $external],
        'jurytitle' => ['type' => PARAM_TEXT, 'hideif' => $external],
        'jurystatus' => ['type' => PARAM_ALPHA, 'hideif' => $external],
        'juryinstitution' => ['type' => PARAM_TEXT, 'hideif' => $external],
        'juryemail' => ['type' => PARAM_RAW_TRIMMED, 'hideif' => $external],
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
    $data = ['juryrole' => [], 'jurorid' => [], 'juryname' => [], 'jurytitle' => [], 'jurystatus' => [],
        'juryinstitution' => [], 'juryemail' => [], 'juryeligible' => []];
    foreach (array_values($members) as $i => $member) {
        $data['juryrole'][$i] = $member->juryrole;
        $data['jurorid'][$i] = (int) ($member->jurorid ?? 0);
        $data['juryname'][$i] = $member->fullname;
        $data['jurytitle'][$i] = $member->jurytitle;
        $data['jurystatus'][$i] = (string) ($member->jurystatus ?? '');
        $data['juryinstitution'][$i] = $member->institution;
        $data['juryemail'][$i] = $member->email;
        // Pour la DEVE : éligibilité déjà validée, ou évidente (enseignant de l'annuaire au statut admis pour son rôle).
        $data['juryeligible'][$i] = (int) (!empty($member->eligible) || these_jury_eligibility($member) === 'ok');
    }
    return $data;
}

/**
 * Extrait des données soumises les membres du jury renseignés : enseignant choisi dans l'annuaire
 * (ses titre, nom, établissement, adresse et statut en sont repris), ou ligne au nom non vide.
 *
 * @param stdClass|array $data
 * @param bool $witheligibility Reprendre la validation d'éligibilité de la DEVE.
 * @return array Liste d'objets {juryrole, fullname, jurytitle, institution, email, jurorid, jurystatus, eligible?}.
 */
function these_extract_submitted_jury($data, $witheligibility = false) {
    $data = (array) $data;
    $members = [];
    $rows = array_unique(array_merge(array_keys((array) ($data['juryname'] ?? [])), array_keys((array) ($data['jurorid'] ?? []))));
    sort($rows);
    foreach ($rows as $i) {
        $role = $data['juryrole'][$i] ?? 'member';
        $role = array_key_exists($role, these_jury_role_options()) ? $role : 'member';
        $juror = \mod_these\local\jurors::get((int) ($data['jurorid'][$i] ?? 0));
        if ($juror) {
            $member = (object) [
                'juryrole' => $role,
                'fullname' => \mod_these\local\jurors::fullname($juror),
                'jurytitle' => $juror->title,
                'institution' => (string) $juror->institution,
                'email' => (string) $juror->email,
                'jurorid' => (int) $juror->id,
                'jurystatus' => $juror->status,
            ];
        } else {
            $name = trim((string) ($data['juryname'][$i] ?? ''));
            if ($name === '') {
                continue;
            }
            $status = (string) ($data['jurystatus'][$i] ?? '');
            $member = (object) [
                'juryrole' => $role,
                'fullname' => $name,
                'jurytitle' => trim((string) ($data['jurytitle'][$i] ?? '')),
                'institution' => trim((string) ($data['juryinstitution'][$i] ?? '')),
                'email' => trim((string) ($data['juryemail'][$i] ?? '')),
                'jurorid' => 0,
                'jurystatus' => array_key_exists($status, \mod_these\local\jurors::external_statuses()) ? $status : '',
            ];
        }
        if ($witheligibility) {
            $member->eligible = !empty($data['juryeligible'][$i]);
        }
        $members[] = $member;
    }
    return $members;
}

/**
 * Éligibilité d'un membre du jury à son rôle : un invité ne vote pas ; seuls les maîtres de
 * conférences HDR, les professeurs et les émérites peuvent présider ; un enseignant de l'annuaire
 * est éligible d'office aux autres rôles ; un membre extérieur l'est sous réserve de validation par
 * la DEVE.
 *
 * @param stdClass $member
 * @return string 'ok', 'tocheck' (à valider par la DEVE) ou 'ko'
 */
function these_jury_eligibility(stdClass $member) {
    if ($member->juryrole === 'guest') {
        return 'ok';
    }
    $status = (string) ($member->jurystatus ?? '');
    if ($member->juryrole === 'president' && !\mod_these\local\jurors::can_preside($status)) {
        return 'ko';
    }
    return !empty($member->jurorid) ? 'ok' : 'tocheck';
}

/**
 * Vérifie la composition d'un jury : un et un seul président, éligible à la présidence, au moins
 * $minjury membres votants (les invités ne votent pas), un statut déclaré pour chaque membre
 * extérieur votant, pas deux fois le même enseignant, et des adresses de courriel valides lorsqu'elles
 * sont données.
 *
 * @param array $members Voir these_extract_submitted_jury().
 * @param int $minjury
 * @return string|null Message d'erreur, ou null si le jury est valide.
 */
function these_validate_jury(array $members, $minjury) {
    $presidents = 0;
    $voting = 0;
    $seen = [];
    foreach ($members as $member) {
        if ($member->juryrole === 'president') {
            $presidents++;
        }
        if ($member->juryrole !== 'guest') {
            $voting++;
            if (empty($member->jurorid) && (string) ($member->jurystatus ?? '') === '') {
                return get_string('jurystatusrequired', 'mod_these', s($member->fullname));
            }
        }
        if (!empty($member->jurorid)) {
            if (isset($seen[$member->jurorid])) {
                return get_string('juryduplicate', 'mod_these', s($member->fullname));
            }
            $seen[$member->jurorid] = true;
        }
        if ($member->juryrole === 'president' && these_jury_eligibility($member) === 'ko') {
            return get_string('jurypresidentnoteligible', 'mod_these', s($member->fullname));
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

/**
 * Validation de l'éligibilité par la DEVE : chaque membre votant doit être coché, et aucun ne peut
 * être inéligible à son rôle.
 *
 * @param array $members Voir these_extract_submitted_jury() (avec éligibilité).
 * @return string|null Message d'erreur, ou null.
 */
function these_validate_jury_eligibility(array $members) {
    foreach ($members as $member) {
        if ($member->juryrole === 'guest') {
            continue;
        }
        if (these_jury_eligibility($member) === 'ko') {
            return get_string('jurypresidentnoteligible', 'mod_these', s($member->fullname));
        }
        if (empty($member->eligible)) {
            return get_string('juryeligiblerequired', 'mod_these', s($member->fullname));
        }
    }
    return null;
}

/**
 * Prévient les membres du jury de leur désignation et leur donne accès aux documents du dossier
 * (manuscrit, rapport, grille, conclusions...) par un lien personnel, sans compte Moodle. Seuls les
 * membres pas encore prévenus le sont, sauf $force.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param bool $force Prévenir de nouveau tous les membres.
 * @return stdClass {sent, failed, missing (noms des membres sans adresse)}
 */
function these_notify_jury(stdClass $these, stdClass $cm, stdClass $dossier, $force = false) {
    global $DB;

    $result = (object) ['sent' => 0, 'failed' => 0, 'missing' => []];
    $student = $DB->get_record('user', ['id' => $dossier->userid]);
    $date = (int) ($dossier->defensedate ?: $dossier->proposeddate);
    $roles = these_jury_role_options();
    foreach (these_get_jury($dossier->id) as $member) {
        if (!$force && !empty($member->notifiedtime)) {
            continue;
        }
        if (trim((string) $member->email) === '') {
            $result->missing[] = $member->fullname;
            continue;
        }
        if (empty($member->accesstoken)) {
            $member->accesstoken = random_string(48);
            $DB->set_field('these_jury', 'accesstoken', $member->accesstoken, ['id' => $member->id]);
        }
        $sent = these_send_email($these, 'jurydesignation', [these_make_external_user($member->email, $member->fullname)], [
            'name' => trim($member->jurytitle . ' ' . $member->fullname),
            'role' => $roles[$member->juryrole] ?? $member->juryrole,
            'student' => $student ? fullname($student) : '',
            'title' => format_string($dossier->title),
            'date' => $date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-',
            'location' => (string) $dossier->defenselocation,
            'url' => (new moodle_url('/mod/these/jury.php', ['token' => $member->accesstoken]))->out(false),
        ]);
        if ($sent) {
            $DB->set_field('these_jury', 'notifiedtime', time(), ['id' => $member->id]);
            $result->sent++;
        } else {
            $result->failed++;
        }
    }
    return $result;
}

/**
 * Membre du jury correspondant à un lien d'accès aux documents.
 *
 * @param string $token
 * @return stdClass|null Membre, avec dossier, these et cm.
 */
function these_get_jury_access($token) {
    global $DB;

    $token = clean_param($token, PARAM_ALPHANUM);
    if (strlen($token) < 32) {
        return null;
    }
    $member = $DB->get_record('these_jury', ['accesstoken' => $token]);
    if (!$member) {
        return null;
    }
    $dossier = $DB->get_record('these_dossier', ['id' => $member->dossierid]);
    if (!$dossier || (int) $dossier->status === THESE_STATUS_CANCELLED) {
        return null;
    }
    $member->dossier = $dossier;
    $member->these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
    $member->cm = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, MUST_EXIST);
    return $member;
}

/**
 * Zones de fichiers et documents signés auxquels le jury a accès.
 *
 * @return array {areas => string[], documents => string[]}
 */
function these_jury_documents() {
    return [
        'areas' => ['manuscript', 'defensedocs', 'directorreport'],
        'documents' => ['report', 'grid', 'conclusions', 'signaletique'],
    ];
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
        case 'pv':
            // Procès-verbal signé par le jury, scanné en un PDF ou en images.
            $options['maxfiles'] = 5;
            $options['accepted_types'] = ['.pdf', '.jpg', '.jpeg', '.png'];
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
            'vars' => ['student', 'activity', 'title', 'url'],
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
        'agentsignature' => [
            'subjectstring' => 'emailagentsignaturesubject',
            'bodystring' => 'emailagentsignaturebody',
            'vars' => ['name', 'student', 'activity', 'title', 'document', 'url'],
        ],
        'subjectchanged' => [
            'subjectstring' => 'emailsubjectchangedsubject',
            'bodystring' => 'emailsubjectchangedbody',
            'vars' => ['student', 'activity', 'oldtitle', 'title', 'reason', 'url'],
        ],
        'adjourned' => [
            'subjectstring' => 'emailadjournedsubject',
            'bodystring' => 'emailadjournedbody',
            'vars' => ['student', 'activity', 'title', 'url'],
        ],
        'jurydesignation' => [
            'subjectstring' => 'emailjurydesignationsubject',
            'bodystring' => 'emailjurydesignationbody',
            'vars' => ['name', 'role', 'student', 'title', 'date', 'location', 'url'],
        ],
        'signaturerequest' => [
            'subjectstring' => 'emailsignaturerequestsubject',
            'bodystring' => 'emailsignaturerequestbody',
            'vars' => ['name', 'student', 'title', 'document', 'url'],
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
    global $DB;

    $email = $actor === 'deve' ? trim((string) $these->deveemail) : trim((string) $these->libraryemail);
    if ($email !== '') {
        return [these_make_external_user($email, these_actor_label($actor))];
    }
    $capability = $actor === 'deve' ? 'mod/these:managedeve' : 'mod/these:managelibrary';
    $users = get_enrolled_users($context, $capability, 0, 'u.*', null, 0, 0, true);
    // Détenteurs du rôle du service, attribué dans le cours, sa catégorie ou au niveau du site.
    $roleid = these_get_service_roleid($actor);
    if ($roleid) {
        $userids = [];
        foreach ($context->get_parent_context_ids(true) as $contextid) {
            $userids = array_merge($userids, $DB->get_fieldset_select(
                'role_assignments',
                'userid',
                'roleid = ? AND contextid = ?',
                [$roleid, $contextid]
            ));
        }
        $userids = array_diff(array_unique($userids), array_keys($users));
        if ($userids) {
            $users += $DB->get_records_list('user', 'id', $userids);
        }
    }
    return array_values(array_filter($users, function ($user) {
        return empty($user->deleted) && empty($user->suspended);
    }));
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
    $vars['result'] = these_decision_label($dossier);
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
        'mention' => these_mention_label($dossier->mention),
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

    $data = these_document_context($these, $context, $dossier, null);
    $documents = [];
    $documents[these_document_filename('convocation-etudiant')] = these_render_doctemplate($these, 'convocation', $data);
    foreach (these_get_jury($dossier->id) as $index => $member) {
        $documents[these_document_filename('convocation-jury-' . ($index + 1), $member->fullname)] =
            these_render_doctemplate($these, 'convocation', these_document_context($these, $context, $dossier, null, $member));
    }
    $documents[these_document_filename('feuille-de-jury')] = these_render_doctemplate($these, 'jurysheet', $data);
    $documents[these_document_filename('proces-verbal')] = these_render_doctemplate($these, 'minutes', $data);
    // Documents de l'établissement, dans leur état de signature à la génération.
    foreach (array_keys(these_document_definitions()) as $doctype) {
        if (these_document_available($dossier, $doctype)) {
            $documents[these_document_filename($doctype)] = these_signed_document_pdf($these, $context, $dossier, $doctype);
        }
    }

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
    global $USER;

    $rights = these_get_rights($context, null, $USER->id);
    $links = [];
    if ($rights->viewall || has_capability('mod/these:direct', $context)) {
        $links[get_string('dashboard', 'mod_these')] = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
    }
    if ($rights->deve) {
        $links[get_string('createdossier', 'mod_these')] = new moodle_url('/mod/these/create.php', ['id' => $cm->id]);
        $links[get_string('importdossiers', 'mod_these')] = new moodle_url('/mod/these/import.php', ['id' => $cm->id]);
        $links[get_string('notifications', 'mod_these')] = new moodle_url('/mod/these/notifications.php', ['id' => $cm->id]);
        $links[get_string('doctemplates', 'mod_these')] = new moodle_url('/mod/these/doctemplates.php', ['id' => $cm->id]);
        $links[get_string('griditems', 'mod_these')] = new moodle_url('/mod/these/griditems.php', ['id' => $cm->id]);
        $links[get_string('questions', 'mod_these')] = new moodle_url('/mod/these/questions.php', ['id' => $cm->id]);
        $links[get_string('jurors', 'mod_these')] = new moodle_url('/mod/these/jurors.php', ['id' => $cm->id]);
        $links[get_string('transfersettings', 'mod_these')] = new moodle_url('/mod/these/settingstransfer.php', ['id' => $cm->id]);
    }
    if ($rights->viewall) {
        $links[get_string('export', 'mod_these')] = new moodle_url('/mod/these/export.php', ['id' => $cm->id]);
    }
    $tosign = count(these_get_user_pending_signatures($USER));
    if ($tosign) {
        $links[get_string('mysignaturescount', 'mod_these', $tosign)] = new moodle_url('/mod/these/mysignatures.php');
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
 * @param bool $staff Vue de la DEVE : éligibilité et notification des membres.
 * @return string HTML
 */
function these_render_jury(array $members, $showemail = true, $staff = false) {
    if (empty($members)) {
        return '';
    }
    $roles = these_jury_role_options();
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('juryrole', 'mod_these'), get_string('juryname', 'mod_these'),
        get_string('jurytitle', 'mod_these'), get_string('jurystatus', 'mod_these'), get_string('juryinstitution', 'mod_these')];
    if ($showemail) {
        $table->head[] = get_string('juryemail', 'mod_these');
    }
    if ($staff) {
        $table->head[] = get_string('juryeligiblecheck', 'mod_these');
    }
    foreach ($members as $member) {
        $row = [$roles[$member->juryrole] ?? s($member->juryrole), s($member->fullname), s($member->jurytitle),
            s(\mod_these\local\jurors::status_label($member->jurystatus ?? '')), s($member->institution)];
        if ($showemail) {
            $row[] = s($member->email);
        }
        if ($staff) {
            if ($member->juryrole === 'guest') {
                $eligibility = '-';
            } else if (!empty($member->eligible)) {
                $eligibility = html_writer::span(
                    get_string('juryeligibilityvalidated', 'mod_these'),
                    'badge badge-success bg-success'
                );
            } else {
                $state = these_jury_eligibility($member);
                $eligibility = html_writer::span(
                    get_string('juryeligibility' . $state, 'mod_these'),
                    'badge ' . ($state === 'ko' ? 'badge-danger bg-danger' : 'badge-warning bg-warning text-dark')
                );
            }
            if (!empty($member->notifiedtime)) {
                $eligibility .= html_writer::div(
                    get_string('jurynotifiedon', 'mod_these', userdate(
                        $member->notifiedtime,
                        get_string('strftimedatetimeshort', 'langconfig')
                    )),
                    'small text-muted'
                );
            }
            $row[] = $eligibility;
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
    // Questionnaire préalable au jury : rempli par l'étudiant avant le dépôt de la soutenance,
    // corrigé au besoin par la DEVE.
    if (($rights->student && these_defense_editable($dossier)) || ($rights->deve && $status >= THESE_STATUS_ACTIVE)) {
        $these = (object) ['id' => $cm->instance, 'name' => ''];
        if (these_get_questions(these_settings_scope($these))) {
            $links[get_string('questionnaire', 'mod_these')] = new moodle_url('/mod/these/questionnaire.php', $params);
        }
    }
    if ($rights->student && these_defense_editable($dossier)) {
        $links[get_string('depositdefense', 'mod_these')] = new moodle_url('/mod/these/defense.php', $params);
    }
    if ($rights->student && these_subject_changeable($dossier)) {
        $links[get_string('subjectchange', 'mod_these')] = new moodle_url('/mod/these/subjectchange.php', ['id' => $cm->id]);
    }
    if ($rights->student && $status === THESE_STATUS_CORRECTIONS) {
        $links[get_string('depositcorrections', 'mod_these')] = new moodle_url('/mod/these/corrections.php', $params);
    }
    if ($rights->director && these_report_editable($dossier)) {
        $links[get_string('writereport', 'mod_these')] = new moodle_url('/mod/these/report.php', $params);
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
    if ($rights->deve && $status !== THESE_STATUS_DRAFT) {
        $links[get_string('managesignatures', 'mod_these')] = new moodle_url('/mod/these/signatures.php', $params);
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
    if (these_is_adjourned($dossier)) {
        $out .= html_writer::div(get_string(
            $rights->student ? 'adjournednoticestudent' : 'adjournednotice',
            'mod_these',
            userdate($dossier->statustime, get_string('strftimedate', 'langconfig'))
        ), 'alert alert-warning');
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

    if ($rights->viewall || $rights->director || $rights->owner) {
        $out .= these_render_subject_history($context, $dossier);
    }
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
        if ($status === THESE_STATUS_CLOSED || these_in_corrections($status)) {
            $rows[get_string('defenseresult', 'mod_these')] = these_result_label($dossier->defenseresult);
            $rows[get_string('mention', 'mod_these')] = s(these_mention_label($dossier->mention));
            $rows[get_string('prizeproposal', 'mod_these')] = $dossier->prizeproposal ? get_string('yes') : null;
            $rows[get_string('minutes', 'mod_these')] = these_render_file_links($context, 'pv', $dossier->id);
        }
        $rows[get_string('minutesadjourned', 'mod_these')] = these_render_file_links($context, 'pvadjourned', $dossier->id);
        $out .= these_render_detail_section(get_string('defense', 'mod_these'), $rows);
        if (!empty($dossier->correctionsrequested)) {
            $out .= these_render_detail_section(get_string('corrections', 'mod_these'), [
                get_string('correctionsnote', 'mod_these') => format_text((string) $dossier->correctionsnote, FORMAT_PLAIN),
                get_string('correctionsdone', 'mod_these') => trim((string) $dossier->correctionsdone) !== ''
                    ? format_text((string) $dossier->correctionsdone, FORMAT_PLAIN) : null,
                get_string('correctedmanuscript', 'mod_these') => these_render_file_links($context, 'corrections', $dossier->id),
            ]);
        }
        if ($rights->viewall || $rights->director || $rights->owner) {
            $out .= these_render_answers((object) ['id' => $dossier->theseid], $dossier);
        }
        $showemail = $rights->viewall || $rights->director || $rights->owner;
        $out .= these_render_jury(these_get_jury($dossier->id), $showemail, $rights->deve);
        $juryvalidated = (int) $dossier->status >= THESE_STATUS_DEFENSE_VALIDATED && (int) $dossier->status < THESE_STATUS_CLOSED;
        if ($rights->deve && $juryvalidated) {
            $out .= html_writer::div(html_writer::link(
                new moodle_url('/mod/these/jurynotify.php', ['id' => $cm->id, 'dossierid' => $dossier->id, 'sesskey' => sesskey()]),
                get_string('juryresendaccess', 'mod_these'),
                ['class' => 'btn btn-secondary btn-sm']
            ), 'mb-3');
        }
    }

    $out .= these_render_signed_documents($cm, $context, $dossier, $rights);
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

// Documents signés.

/** Engagement de l'étudiant : avoir pris connaissance du guide des thèses. */
define('THESE_COMMIT_GUIDE', 1);
/** Engagement de l'étudiant : avoir vérifié que le sujet n'a pas déjà été traité. */
define('THESE_COMMIT_DUPLICATE', 2);
/** Engagement de l'étudiant : soumettre le protocole au comité d'éthique (si nécessaire). */
define('THESE_COMMIT_ETHICS', 4);

/**
 * Documents de l'établissement produits par le module, et qui les signe.
 *
 * - subject : formulaire de dépôt de sujet (étudiant, directeur, directeur extérieur éventuel,
 *   visa de la DEVE) ;
 * - bordereau : bordereau de thèse d'exercice vétérinaire (ABES), signé par l'étudiant ;
 * - report : rapport du directeur ; grid : grille de conformité, renseignée par le directeur ;
 * - agreement : fiche d'agréments administratif (DEVE) et scientifique (directeur, et directeur
 *   extérieur éventuel), visée par la direction générale (représentée par la DEVE) et le CCEM ;
 * - signaletique : fiche signalétique de l'étudiant, signée par le président du jury, la
 *   direction générale et le CCEM ;
 * - contract : contrat de diffusion électronique, signé par l'auteur, visé par le directeur, et
 *   signé par la direction générale et la présidence de l'université ;
 * - conformity : attestation de conformité du manuscrit, délivrée par la bibliothèque ;
 * - conclusions : conclusions de la thèse, signées par le directeur, le président du jury, la
 *   direction générale et le CCEM.
 *
 * « staffonly » : document non communiqué à l'étudiant (voir these_can_view_document()).
 *
 * @return array doctype => ['slots' => string[], 'staffonly' => bool]
 */
function these_document_definitions() {
    return [
        'subject' => ['slots' => ['student', 'director', 'extdirector', 'deve'], 'staffonly' => false],
        'bordereau' => ['slots' => ['student'], 'staffonly' => false],
        'report' => ['slots' => ['director'], 'staffonly' => true],
        'grid' => ['slots' => ['director'], 'staffonly' => true],
        'agreement' => ['slots' => ['deve', 'director', 'extdirector', 'dgrep', 'ccem'], 'staffonly' => false],
        'signaletique' => ['slots' => ['president', 'dg', 'ccem'], 'staffonly' => false],
        'contract' => ['slots' => ['student', 'director', 'dg', 'ucbl'], 'staffonly' => false],
        'conformity' => ['slots' => ['library'], 'staffonly' => false],
        'conclusions' => ['slots' => ['director', 'president', 'dg', 'ccem'], 'staffonly' => false],
    ];
}

/**
 * Emplacements de signature effectifs d'un document pour un dossier : le directeur extérieur
 * n'est attendu que si le dossier en déclare un.
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return string[]
 */
function these_document_slots(stdClass $dossier, $doctype) {
    $slots = these_document_definitions()[$doctype]['slots'];
    if (empty($dossier->externaldirector)) {
        $slots = array_values(array_diff($slots, ['extdirector']));
    }
    return $slots;
}

/**
 * Emplacements signés par un signataire sans compte, invité par un lien à jeton.
 *
 * @return string[]
 */
function these_external_slots() {
    return ['extdirector', 'president', 'dg', 'ccem', 'ucbl'];
}

/**
 * Libellé d'un document.
 *
 * @param string $doctype
 * @return string
 */
function these_document_label($doctype) {
    return get_string('doc' . $doctype, 'mod_these');
}

/**
 * Libellé d'un emplacement de signature.
 *
 * @param string $slot
 * @return string
 */
function these_slot_label($slot) {
    return get_string('slot' . $slot, 'mod_these');
}

/**
 * Contenu d'un document couvert par les signatures : toute modification de ces données après
 * signature invalide les signatures du document (voir these_refresh_signatures()). Les données
 * purement administratives (date de soutenance, numéro DEVE pour le formulaire de sujet) n'en
 * font pas partie, pour qu'un report de date n'oblige pas à tout faire signer de nouveau.
 *
 * Un document rempli en plusieurs temps n'engage chaque signataire que sur sa partie : sur la
 * fiche d'agréments, le directeur signe l'agrément scientifique avant que la DEVE ne renseigne
 * l'agrément administratif, qui ne doit donc pas invalider sa signature.
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @param string $slot Emplacement de signature concerné, '' pour le document entier.
 * @return array
 */
function these_document_content(stdClass $dossier, $doctype, $slot = '') {
    $common = ['userid' => (int) $dossier->userid, 'title' => (string) $dossier->title];
    switch ($doctype) {
        case 'subject':
            return $common + [
                'confidential' => (int) ($dossier->confidential ?? 0),
                'studyyear' => (string) ($dossier->studyyear ?? ''),
                'keywords' => (string) $dossier->keywords,
                'directorid' => (int) $dossier->directorid,
                'directortitle' => (string) ($dossier->directortitle ?? ''),
                'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
                'extdirectorgrade' => (string) ($dossier->extdirectorgrade ?? ''),
                'extdirectorinstitution' => (string) ($dossier->extdirectorinstitution ?? ''),
                'extdirectoremail' => (string) ($dossier->extdirectoremail ?? ''),
                'commitments' => (int) ($dossier->commitments ?? 0),
            ];
        case 'bordereau':
            return $common + ['titleen' => (string) $dossier->titleen, 'bordereau' => these_get_bordereau($dossier)];
        case 'report':
            return $common + ['reporttext' => (string) ($dossier->reporttext ?? ''),
                'reportfavorable' => (int) ($dossier->reportfavorable ?? 0)];
        case 'grid':
            return $common + ['grid' => these_get_grid($dossier), 'ethicsnumber' => (string) ($dossier->ethicsnumber ?? '')];
        case 'agreement':
            $scientific = [
                'directorid' => (int) $dossier->directorid,
                'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
            ];
            if (in_array($slot, ['director', 'extdirector'], true)) {
                return $common + $scientific + ['function' => (string) (these_get_agreement($dossier)['directorfunction'] ?? '')];
            }
            return $common + [
                'agreement' => these_get_agreement($dossier),
                'directorid' => (int) $dossier->directorid,
                'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
            ];
        case 'signaletique':
            $student = these_get_bordereau($dossier);
            $keys = ['birthname', 'firstnames', 'personalemail', 'emailconsent', 'birthdate', 'birthplace', 'birthdept',
                'street', 'postcode', 'city', 'country', 'phone', 'defvdate', 'defvschool', 'a6date', 'a6school', 'a6notyet'];
            return $common + [
                'student' => array_intersect_key($student, array_flip($keys)),
                'confidential' => (int) ($dossier->confidential ?? 0),
                'jury' => array_map(function ($member) {
                    return [$member->juryrole, $member->fullname, $member->jurytitle, $member->institution];
                }, these_get_jury($dossier->id)),
            ];
        case 'contract':
            $student = these_get_bordereau($dossier);
            $keys = ['birthname', 'firstnames', 'birthdate', 'street', 'postcode', 'city', 'country', 'personalemail',
                'confidentialuntil', 'embargo', 'embargountil', 'diffusion'];
            return $common + [
                'student' => array_intersect_key($student, array_flip($keys)),
                'confidential' => (int) ($dossier->confidential ?? 0),
            ];
        case 'conformity':
            return $common + ['devenumber' => (string) $dossier->devenumber];
        case 'conclusions':
            return $common + ['conclusions' => (string) ($dossier->conclusions ?? '')];
    }
    throw new coding_exception('Document inconnu : ' . $doctype);
}

/**
 * Empreinte SHA-256 du contenu d'un document couvert par un emplacement de signature.
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @param string $slot Voir these_document_content().
 * @return string
 */
function these_document_hash(stdClass $dossier, $doctype, $slot = '') {
    return hash('sha256', json_encode(these_document_content($dossier, $doctype, $slot), JSON_UNESCAPED_UNICODE));
}

/**
 * Indique si un document peut être produit pour un dossier (ses données ont été saisies).
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return bool
 */
function these_document_available(stdClass $dossier, $doctype) {
    switch ($doctype) {
        case 'subject':
            return trim((string) $dossier->title) !== '';
        case 'bordereau':
            return !empty(these_get_bordereau($dossier));
        case 'report':
            return trim((string) ($dossier->reporttext ?? '')) !== '';
        case 'grid':
            return !empty(these_get_grid($dossier));
        case 'agreement':
            return !empty(these_get_agreement($dossier)) || these_has_signature($dossier->id, 'agreement');
        case 'signaletique':
        case 'contract':
            return !empty(these_get_bordereau($dossier));
        case 'conformity':
            return these_rank_reached($dossier, THESE_STATUS_CONFORM);
        case 'conclusions':
            return trim((string) ($dossier->conclusions ?? '')) !== '';
    }
    return false;
}

/**
 * Indique si l'utilisateur peut consulter un document d'un dossier.
 *
 * @param string $doctype
 * @param stdClass $rights Voir these_get_rights().
 * @return bool
 */
function these_can_view_document($doctype, stdClass $rights) {
    if (!these_can_view_dossier($rights)) {
        return false;
    }
    if (these_document_definitions()[$doctype]['staffonly']) {
        return $rights->director || $rights->viewall;
    }
    return true;
}

/**
 * Indique si au moins une signature a été apposée sur un document d'un dossier.
 *
 * @param int $dossierid
 * @param string $doctype
 * @return bool
 */
function these_has_signature($dossierid, $doctype) {
    global $DB;

    return $DB->record_exists_select(
        'these_signature',
        'dossierid = ? AND doctype = ? AND timesigned > 0',
        [$dossierid, $doctype]
    );
}

/**
 * Agrément administratif renseigné par la DEVE (diplôme d'études fondamentales, mobilité
 * internationale, année d'approfondissement).
 *
 * @param stdClass $dossier
 * @return array {defvdate, mobility, a6date, a6notyet} ; tableau vide s'il n'a pas été renseigné.
 */
function these_get_agreement(stdClass $dossier) {
    $data = json_decode((string) ($dossier->agreement ?? ''), true);
    return is_array($data) ? $data : [];
}

/**
 * Écoles nationales vétérinaires, pour le cursus déclaré dans la fiche signalétique.
 *
 * @return array
 */
function these_school_options() {
    return [
        '' => get_string('choosedots'),
        'alfort' => 'EnvA (Maisons-Alfort)',
        'lyon' => 'VetAgro Sup (Lyon)',
        'nantes' => 'Oniris VetAgroBio (Nantes)',
        'toulouse' => 'ENVT (Toulouse)',
        'rouen' => 'UniLaSalle (Rouen)',
        'other' => get_string('schoolother', 'mod_these'),
    ];
}

/**
 * Bordereau de thèse d'un dossier.
 *
 * @param stdClass $dossier
 * @return array Voir these_bordereau_fields() ; tableau vide s'il n'a pas été renseigné.
 */
function these_get_bordereau(stdClass $dossier) {
    $data = json_decode((string) ($dossier->bordereau ?? ''), true);
    return is_array($data) ? $data : [];
}

/**
 * Données saisies par l'étudiant au dépôt de sa soutenance, communes au bordereau ABES, à la
 * fiche signalétique et au contrat de diffusion (stockées ensemble dans these_dossier.bordereau).
 *
 * @return string[]
 */
function these_bordereau_fields() {
    return [
        // État civil et coordonnées.
        'birthname', 'firstnames', 'usagename', 'publicationname', 'birthdate', 'birthplace', 'birthdept',
        'nationality', 'street', 'postcode', 'city', 'country', 'phone', 'personalemail', 'emailconsent',
        // Cursus.
        'defvdate', 'defvschool', 'a6date', 'a6school', 'a6notyet',
        // Bordereau ABES.
        'nbvolumes', 'nbpages', 'nbrefs', 'summaryfr', 'summaryen', 'keywords',
        // Contrat de diffusion.
        'confidentialuntil', 'embargo', 'embargountil', 'diffusion',
        // Soutenance.
        'visio',
    ];
}

/** Longueur maximale d'un résumé du bordereau, espaces compris. */
define('THESE_BORDEREAU_SUMMARY_MAX', 1700);

/** Nombre de mots-clés du bordereau. */
define('THESE_BORDEREAU_KEYWORDS', 8);

/**
 * Éléments de la grille de conformité du directeur, par rubrique. Les éléments « conditionnels »
 * ne s'appliquent qu'à certaines thèses : ils peuvent rester non cochés.
 *
 * @return array rubrique => [clé => conditionnel (bool)]
 */
function these_grid_items() {
    return [
        'fond' => [
            'question' => false, 'answer' => false, 'biblio' => false, 'critique' => false, 'ownwork' => false,
            'tool' => true,
        ],
        'forme' => [
            'french' => false, 'language' => false, 'typing' => false, 'thanks' => false, 'toc' => false,
            'staff' => false, 'lists' => false, 'covers' => false, 'citations' => false, 'bibliography' => false,
            'figures' => false, 'introduction' => false, 'conclusion' => false, 'abstract' => false,
            'structure' => true,
        ],
    ];
}

/**
 * Rubriques de la grille de conformité, dans l'ordre.
 *
 * @return string[]
 */
function these_grid_sections() {
    return ['fond', 'forme'];
}

/**
 * Éléments de la grille de conformité d'une portée de paramètres (voir these_settings_scope()),
 * modifiables par la DEVE. Les éléments d'origine (these_grid_items()) sont enregistrés à la
 * première consultation.
 *
 * @param stdClass|null $scope null : éléments d'origine, sans enregistrement (aperçus).
 * @return array rubrique => [clé => {itemkey, section, label, conditional, sortorder}]
 */
function these_get_grid_items(?stdClass $scope) {
    global $DB;

    $records = $scope ? $DB->get_records('these_griditem', these_scope_conditions($scope), 'sortorder ASC, id ASC') : [];
    if (!$records) {
        $records = [];
        $sortorder = 0;
        foreach (these_grid_items() as $section => $items) {
            foreach ($items as $key => $conditional) {
                $records[] = (object) array_merge($scope ? these_scope_conditions($scope) : [], [
                    'itemkey' => $key,
                    'section' => $section,
                    'label' => get_string('grid' . $key, 'mod_these'),
                    'conditional' => (int) $conditional,
                    'sortorder' => $sortorder++,
                    'timemodified' => time(),
                ]);
            }
        }
        if ($scope) {
            foreach ($records as $record) {
                $record->id = $DB->insert_record('these_griditem', $record);
            }
        }
    }
    $sections = array_fill_keys(these_grid_sections(), []);
    foreach ($records as $record) {
        $section = array_key_exists($record->section, $sections) ? $record->section : 'forme';
        $sections[$section][$record->itemkey] = $record;
    }
    return $sections;
}

/**
 * Remplace les éléments de la grille de conformité d'une portée. Un élément gardé conserve sa clé,
 * et donc les réponses déjà données ; un élément supprimé disparaît des grilles et de leurs PDF.
 *
 * @param stdClass $scope Voir these_settings_scope().
 * @param array $items Liste d'objets {itemkey ('' pour un nouvel élément), section, label, conditional}, dans l'ordre ;
 *                     une clé inconnue de la portée (transfert depuis une autre) est reprise telle quelle.
 * @return void
 */
function these_save_grid_items(stdClass $scope, array $items) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $existing = $DB->get_records_menu('these_griditem', these_scope_conditions($scope), '', 'itemkey, id');
    $kept = [];
    $sortorder = 0;
    foreach ($items as $item) {
        $label = trim((string) $item->label);
        if ($label === '') {
            continue;
        }
        $key = (string) ($item->itemkey ?? '');
        if (!preg_match('/^[a-zA-Z0-9]{1,40}$/', $key) || isset($kept[$key])) {
            $key = 'item' . random_string(10);
        }
        $record = (object) array_merge(these_scope_conditions($scope), [
            'itemkey' => $key,
            'section' => in_array($item->section, these_grid_sections(), true) ? $item->section : 'forme',
            'label' => $label,
            'conditional' => empty($item->conditional) ? 0 : 1,
            'sortorder' => $sortorder++,
            'timemodified' => time(),
        ]);
        if (isset($existing[$key])) {
            $record->id = $existing[$key];
            $DB->update_record('these_griditem', $record);
        } else {
            $DB->insert_record('these_griditem', $record);
        }
        $kept[$key] = true;
    }
    foreach ($existing as $key => $id) {
        if (!isset($kept[$key])) {
            $DB->delete_records('these_griditem', ['id' => $id]);
        }
    }
    $transaction->allow_commit();
}

/**
 * Grille de conformité d'un dossier.
 *
 * @param stdClass $dossier
 * @return array clé => 0|1 ; tableau vide si elle n'a pas été renseignée.
 */
function these_get_grid(stdClass $dossier) {
    $data = json_decode((string) ($dossier->conformitygrid ?? ''), true);
    return is_array($data) ? $data : [];
}

/**
 * Signatures d'un dossier.
 *
 * @param int $dossierid
 * @return array « doctype:slot » => enregistrement these_signature
 */
function these_get_signatures($dossierid) {
    global $DB;

    $signatures = [];
    foreach ($DB->get_records('these_signature', ['dossierid' => $dossierid], 'id ASC') as $signature) {
        $signatures[$signature->doctype . ':' . $signature->slot] = $signature;
    }
    return $signatures;
}

/**
 * Décode l'image de signature tracée dans le formulaire (URL de données PNG).
 *
 * @param string $dataurl
 * @return string|null Contenu PNG, ou null si la donnée n'est pas une image PNG plausible.
 */
function these_decode_signature_image($dataurl) {
    $prefix = 'data:image/png;base64,';
    if (!is_string($dataurl) || strpos($dataurl, $prefix) !== 0 || strlen($dataurl) > 600000) {
        return null;
    }
    $png = base64_decode(substr($dataurl, strlen($prefix)), true);
    if ($png === false || strlen($png) < 100) {
        return null;
    }
    $info = @getimagesizefromstring($png);
    if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 2000 || $info[1] > 1000) {
        return null;
    }
    return $png;
}

/**
 * Contenu de l'image d'une signature.
 *
 * @param int $contextid
 * @param int $signatureid
 * @return string|null
 */
function these_get_signature_image($contextid, $signatureid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_these', 'signature', $signatureid, 'id', false);
    $file = reset($files);
    return $file ? $file->get_content() : null;
}

/**
 * Appose une signature sur un document : l'emplacement est créé ou complété, l'image enregistrée
 * et l'empreinte du contenu signé conservée. Le jeton d'une invitation éventuelle est effacé.
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
    global $DB;

    $now = time();
    $signature = $DB->get_record('these_signature', ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]);
    $record = (object) [
        'dossierid' => $dossier->id,
        'doctype' => $doctype,
        'slot' => $slot,
        'userid' => (int) $userid,
        'signername' => trim($name),
        'signeremail' => $email !== '' ? $email : ($signature->signeremail ?? ''),
        'token' => null,
        'timesigned' => $now,
        'contenthash' => these_document_hash($dossier, $doctype, $slot),
        'ipaddress' => getremoteaddr(),
    ];
    if ($signature) {
        $record->id = $signature->id;
        $DB->update_record('these_signature', $record);
    } else {
        $record->requesttime = 0;
        $record->timecreated = $now;
        $record->id = $DB->insert_record('these_signature', $record);
    }

    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_these', 'signature', $record->id);
    $fs->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'mod_these',
        'filearea' => 'signature',
        'itemid' => $record->id,
        'filepath' => '/',
        'filename' => 'signature.png',
        'userid' => $userid ?: null,
    ], $png);

    these_log(
        $dossier->id,
        $userid,
        'signed',
        $dossier->status,
        $dossier->status,
        these_document_label($doctype) . ' — ' . these_slot_label($slot) . ' — ' . trim($name)
    );
    return $DB->get_record('these_signature', ['id' => $record->id]);
}

/**
 * Supprime une signature et son image.
 *
 * @param context $context
 * @param stdClass $signature
 * @return void
 */
function these_delete_signature(context $context, stdClass $signature) {
    global $DB;

    get_file_storage()->delete_area_files($context->id, 'mod_these', 'signature', $signature->id);
    $DB->delete_records('these_signature', ['id' => $signature->id]);
}

/**
 * Invalide les signatures dont le document a changé depuis qu'elles ont été apposées : un
 * document signé ne peut pas être modifié en silence. Les signatures concernées sont supprimées
 * et la suppression est inscrite à l'historique.
 *
 * @param context $context
 * @param stdClass $dossier
 * @return string[] Emplacements invalidés (« doctype:slot »).
 */
function these_refresh_signatures(context $context, stdClass $dossier) {
    $invalidated = [];
    foreach (these_get_signatures($dossier->id) as $key => $signature) {
        if (empty($signature->timesigned)) {
            continue;
        }
        if ($signature->contenthash !== these_document_hash($dossier, $signature->doctype, $signature->slot)) {
            these_delete_signature($context, $signature);
            $invalidated[] = $key;
        }
    }
    if ($invalidated) {
        these_log($dossier->id, 0, 'signaturesinvalidated', $dossier->status, $dossier->status, implode(', ', array_map(
            function ($key) {
                [$doctype, $slot] = explode(':', $key);
                return these_document_label($doctype) . ' — ' . these_slot_label($slot);
            },
            $invalidated
        )));
    }
    return $invalidated;
}

/**
 * Signataires sans compte attendus pour un dossier, avec leur nom et leur adresse : le
 * directeur extérieur (formulaire de sujet), le président du jury, le directeur général et le
 * président du CCEM (conclusions), ces deux derniers étant définis dans les réglages du site.
 *
 * @param stdClass $dossier
 * @return array slot => ['name' => string, 'email' => string]
 */
function these_external_signers(stdClass $dossier) {
    $signers = [
        'extdirector' => [
            'name' => trim(($dossier->extdirectorname ?? '')),
            'email' => trim((string) ($dossier->extdirectoremail ?? '')),
        ],
        'president' => ['name' => '', 'email' => ''],
        'dg' => ['name' => (string) get_config('mod_these', 'dgname'), 'email' => (string) get_config('mod_these', 'dgemail')],
        'ccem' => ['name' => (string) get_config('mod_these', 'ccemname'),
            'email' => (string) get_config('mod_these', 'ccememail')],
        'ucbl' => ['name' => (string) get_config('mod_these', 'ucblname'),
            'email' => (string) get_config('mod_these', 'ucblemail')],
    ];
    foreach (these_get_jury($dossier->id) as $member) {
        if ($member->juryrole === 'president') {
            $signers['president'] = ['name' => trim($member->jurytitle . ' ' . $member->fullname), 'email' => $member->email];
        }
    }
    return $signers;
}

/**
 * Documents en attente de la signature d'un signataire sans compte, dans un dossier.
 *
 * @param stdClass $dossier
 * @param string $slot
 * @param string[]|null $doctypes Documents à considérer (tous par défaut).
 * @return string[]
 */
function these_pending_documents_for_slot(stdClass $dossier, $slot, ?array $doctypes = null) {
    $signatures = these_get_signatures($dossier->id);
    $pending = [];
    foreach ($doctypes ?? array_keys(these_document_definitions()) as $doctype) {
        if (!in_array($slot, these_document_slots($dossier, $doctype), true)) {
            continue;
        }
        $existing = $signatures[$doctype . ':' . $slot] ?? null;
        if (!$existing || empty($existing->timesigned)) {
            $pending[] = $doctype;
        }
    }
    return $pending;
}

/**
 * Invite un signataire sans compte à signer, en une seule fois, les documents qui l'attendent :
 * chaque emplacement reçoit le même nouveau jeton, et le lien de signature est envoyé au
 * signataire s'il a une adresse. Sans adresse, les signatures restent en attente et peuvent être
 * recueillies sur place par la DEVE (signatures.php).
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string $slot
 * @param string[]|null $doctypes Documents concernés (tous ceux en attente par défaut).
 * @return string|null Jeton, ou null si aucun document n'attend ce signataire.
 */
function these_request_signature(stdClass $these, stdClass $cm, stdClass $dossier, $slot, ?array $doctypes = null) {
    global $DB;

    $doctypes = these_pending_documents_for_slot($dossier, $slot, $doctypes);
    if (!$doctypes) {
        return null;
    }
    $signer = these_external_signers($dossier)[$slot];
    $token = random_string(48);
    $now = time();
    foreach ($doctypes as $doctype) {
        $signature = $DB->get_record('these_signature', ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]);
        $record = (object) [
            'dossierid' => $dossier->id,
            'doctype' => $doctype,
            'slot' => $slot,
            'userid' => 0,
            'signername' => $signer['name'],
            'signeremail' => $signer['email'],
            'token' => $token,
            'requesttime' => $now,
            'timesigned' => 0,
            'contenthash' => null,
        ];
        if ($signature) {
            $record->id = $signature->id;
            $DB->update_record('these_signature', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('these_signature', $record);
        }
    }

    $labels = implode(', ', array_map('these_document_label', $doctypes));
    if ($signer['email'] !== '' && validate_email($signer['email'])) {
        $vars = these_email_base_vars($these, $cm, $dossier);
        $vars['name'] = $signer['name'];
        $vars['document'] = $labels;
        $vars['url'] = (new moodle_url('/mod/these/sign.php', ['token' => $token]))->out(false);
        these_send_email($these, 'signaturerequest', [these_make_external_user($signer['email'], $signer['name'])], $vars);
    }
    these_log(
        $dossier->id,
        0,
        'signaturerequested',
        $dossier->status,
        $dossier->status,
        these_slot_label($slot) . ($signer['name'] !== '' ? ' (' . $signer['name'] . ')' : '') . ' — ' . $labels
    );
    return $token;
}

/**
 * Invite tous les signataires sans compte attendus sur des documents et qui n'ont pas encore
 * signé (un seul courriel par signataire, pour tous ses documents).
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string|string[] $doctypes
 * @return int Nombre de signataires invités.
 */
function these_request_external_signatures(stdClass $these, stdClass $cm, stdClass $dossier, $doctypes) {
    $count = 0;
    foreach (these_external_slots() as $slot) {
        if (these_request_signature($these, $cm, $dossier, $slot, (array) $doctypes) !== null) {
            $count++;
        }
    }
    return $count;
}

/**
 * Signatures en attente correspondant à un jeton d'invitation : tous les documents qu'un même
 * signataire doit signer dans un dossier.
 *
 * @param string $token
 * @return stdClass[] Signatures en attente, vide si le jeton est inconnu ou déjà utilisé.
 */
function these_get_signatures_by_token($token) {
    global $DB;

    $token = clean_param($token, PARAM_ALPHANUM);
    if (strlen($token) < 32) {
        return [];
    }
    return array_values($DB->get_records('these_signature', ['token' => $token, 'timesigned' => 0], 'id ASC'));
}

/**
 * Ajoute à un formulaire le bloc de signature : zone de tracé, nom du signataire et
 * certification. La zone de tracé alimente un champ caché « signaturedata » (module AMD
 * mod_these/signaturepad).
 *
 * @param MoodleQuickForm $mform
 * @param string $defaultname Nom proposé.
 * @param string $intro Texte d'introduction (ce qui est signé).
 * @return void
 */
function these_add_signature_fields(MoodleQuickForm $mform, $defaultname, $intro = '', $allowsaved = true) {
    global $PAGE, $USER;

    $mform->addElement('header', 'signatureheader', get_string('signature', 'mod_these'));
    $mform->setExpanded('signatureheader');
    if ($intro !== '') {
        $mform->addElement('static', 'signatureintro', '', $intro);
    }
    // Signature enregistrée de l'utilisateur connecté : reprise d'office, sans nouveau tracé.
    $cansave = $allowsaved && isloggedin() && !isguestuser();
    $saved = $cansave ? these_get_saved_signature($USER->id) : null;
    if ($saved !== null) {
        $mform->addElement('advcheckbox', 'usesavedsignature', '', get_string('signatureusesaved', 'mod_these')
            . html_writer::empty_tag('br') . html_writer::empty_tag('img', [
                'src' => 'data:image/png;base64,' . base64_encode($saved), 'alt' => get_string('signaturesaved', 'mod_these'),
                'class' => 'these-saved-signature border mt-1', 'style' => 'max-height:80px;background:#fff;',
            ]));
        $mform->setDefault('usesavedsignature', 1);
    }
    $pad = html_writer::div(
        html_writer::tag('canvas', '', ['width' => 520, 'height' => 170, 'class' => 'these-signature-canvas',
            'aria-label' => get_string('signaturedraw', 'mod_these')])
            . html_writer::div(
                html_writer::tag('button', get_string('signatureclear', 'mod_these'), [
                    'type' => 'button', 'class' => 'btn btn-sm btn-secondary', 'data-action' => 'these-signature-clear',
                ]),
                'mt-1'
            ),
        'these-signature-pad'
    );
    $mform->addElement('static', 'signaturepad', get_string('signaturedraw', 'mod_these'), $pad);
    $mform->addElement('hidden', 'signaturedata', '');
    $mform->setType('signaturedata', PARAM_RAW);
    if ($cansave) {
        $mform->addElement('advcheckbox', 'savesignature', '', get_string('signaturesave', 'mod_these'));
        $mform->setDefault('savesignature', 1);
    }
    if ($saved !== null) {
        $mform->hideIf('signaturepad', 'usesavedsignature', 'checked');
        $mform->hideIf('savesignature', 'usesavedsignature', 'checked');
    }
    $mform->addElement('text', 'signername', get_string('signername', 'mod_these'), ['size' => 48]);
    $mform->setType('signername', PARAM_TEXT);
    $mform->setDefault('signername', $defaultname);
    $mform->addElement('advcheckbox', 'signatureconsent', '', get_string('signatureconsent', 'mod_these'));
    $PAGE->requires->js_call_amd('mod_these/signaturepad', 'init');
}

/**
 * Contrôle du bloc de signature (voir these_add_signature_fields()).
 *
 * @param array $data
 * @return array Erreurs, indexées par champ.
 */
function these_signature_errors(array $data) {
    global $USER;

    $errors = [];
    $saved = !empty($data['usesavedsignature']) && these_get_saved_signature($USER->id) !== null;
    if (!$saved && these_decode_signature_image($data['signaturedata'] ?? '') === null) {
        $errors['signername'] = get_string('signaturemissing', 'mod_these');
    } else if (trim((string) ($data['signername'] ?? '')) === '') {
        $errors['signername'] = get_string('required');
    }
    if (empty($data['signatureconsent'])) {
        $errors['signatureconsent'] = get_string('signatureconsentrequired', 'mod_these');
    }
    return $errors;
}

/**
 * Appose la signature saisie dans un formulaire sur un ou plusieurs documents.
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
    $signature = these_signature_from_form($data);
    if ($signature === null) {
        throw new coding_exception('Signature absente');
    }
    foreach ($doctypes as $doctype) {
        these_sign_document($context, $dossier, $doctype, $slot, $userid, $signature->name, $signature->png);
    }
}

/**
 * Tableau des documents signés d'un dossier : état de chaque signature et lien de
 * téléchargement du PDF.
 *
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $dossier
 * @param stdClass $rights
 * @return string HTML
 */
function these_render_signed_documents(stdClass $cm, context $context, stdClass $dossier, stdClass $rights) {
    $signatures = these_get_signatures($dossier->id);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-signatures';
    $table->head = [get_string('document', 'mod_these'), get_string('signatures', 'mod_these'), ''];
    $dateformat = get_string('strftimedatetimeshort', 'langconfig');
    foreach (array_keys(these_document_definitions()) as $doctype) {
        if (!these_document_available($dossier, $doctype) || !these_can_view_document($doctype, $rights)) {
            continue;
        }
        $states = [];
        foreach (these_document_slots($dossier, $doctype) as $slot) {
            $signature = $signatures[$doctype . ':' . $slot] ?? null;
            if ($signature && $signature->timesigned) {
                $state = html_writer::span(get_string('signedby', 'mod_these', (object) [
                    'name' => s($signature->signername),
                    'date' => userdate($signature->timesigned, $dateformat),
                ]), 'badge badge-success');
            } else if ($signature && $signature->requesttime) {
                $state = html_writer::span(get_string(
                    'signaturerequestedon',
                    'mod_these',
                    userdate($signature->requesttime, $dateformat)
                ), 'badge badge-warning');
            } else {
                $state = html_writer::span(get_string('signaturepending', 'mod_these'), 'badge badge-light');
            }
            $states[] = html_writer::tag('strong', these_slot_label($slot)) . ' : ' . $state;
        }
        $url = new moodle_url('/mod/these/document.php', ['id' => $cm->id, 'dossierid' => $dossier->id, 'type' => $doctype]);
        $table->data[] = [
            these_document_label($doctype),
            implode(html_writer::empty_tag('br'), $states),
            html_writer::link($url, get_string('downloadpdf', 'mod_these'), ['class' => 'btn btn-sm btn-secondary']),
        ];
    }
    if (empty($table->data)) {
        return '';
    }
    $out = html_writer::tag('h4', get_string('signeddocuments', 'mod_these'), ['class' => 'mt-4']) . html_writer::table($table);
    return $out;
}


// Modèles des documents.

/**
 * Documents produits à partir d'un modèle modifiable (doctemplates/, page d'administration
 * « Modèles des documents »), dans l'ordre de présentation.
 *
 * @return array type de modèle => document signé dont il reprend les signatures (null : aucun).
 */
function these_doctemplate_types() {
    return [
        'subject' => 'subject',
        'agreement' => 'agreement',
        'signaletique' => 'signaletique',
        'bordereau' => 'bordereau',
        'report' => 'report',
        'grid' => 'grid',
        'conclusions' => 'conclusions',
        'contractdata' => 'contract',
        'contractsign' => 'contract',
        'conformity' => 'conformity',
        'convocation' => null,
        'jurysheet' => null,
        'minutes' => null,
    ];
}

/**
 * Libellé d'un modèle de document.
 *
 * @param string $type
 * @return string
 */
function these_doctemplate_label($type) {
    return get_string('doctpl' . $type, 'mod_these');
}

/**
 * Modèle d'origine d'un document, fourni avec le module (doctemplates/<type>.html).
 *
 * @param string $type
 * @return string
 */
function these_doctemplate_default($type) {
    global $CFG;

    if (!array_key_exists($type, these_doctemplate_types())) {
        throw new coding_exception('Modèle de document inconnu : ' . $type);
    }
    return (string) file_get_contents($CFG->dirroot . '/mod/these/doctemplates/' . $type . '.html');
}

/**
 * Portée propre à une activité « Gestion des thèses » : ses propres modèles, images, grille et
 * questionnaire.
 *
 * @param stdClass $these
 * @return stdClass {theseid, synthesisid, shared, component, contextid, cmid, name}
 */
function these_local_scope(stdClass $these) {
    $cm = !empty($these->id) ? get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING) : null;
    return (object) [
        'theseid' => (int) ($these->id ?? 0),
        'synthesisid' => 0,
        'shared' => false,
        'component' => 'mod_these',
        'contextid' => $cm ? context_module::instance($cm->id)->id : 0,
        'cmid' => $cm ? (int) $cm->id : 0,
        'name' => (string) ($these->name ?? ''),
    ];
}

/**
 * Portée d'une activité « Suivi des thèses » : ses modèles, images, grille et questionnaire valent
 * pour toutes les activités « Gestion des thèses » qu'elle suit.
 *
 * @param int $synthesisid
 * @return stdClass|null Voir these_local_scope().
 */
function these_synthesis_scope($synthesisid) {
    global $DB;

    $synthesis = $DB->get_record('thesesynthesis', ['id' => $synthesisid]);
    $cm = $synthesis ? get_coursemodule_from_instance('thesesynthesis', $synthesis->id, 0, false, IGNORE_MISSING) : null;
    if (!$cm) {
        return null;
    }
    return (object) [
        'theseid' => 0,
        'synthesisid' => (int) $synthesis->id,
        'shared' => true,
        'component' => 'mod_thesesynthesis',
        'contextid' => context_module::instance($cm->id)->id,
        'cmid' => (int) $cm->id,
        'name' => (string) $synthesis->name,
    ];
}

/**
 * Portée des paramètres en vigueur pour une activité : l'activité « Suivi des thèses » qui la suit
 * (la plus ancienne si plusieurs la suivent), sinon l'activité elle-même. Les modèles des documents,
 * leurs images, la grille de conformité et le questionnaire préalable au jury sont ainsi définis une
 * fois pour toutes les promotions suivies.
 *
 * @param stdClass $these
 * @return stdClass Voir these_local_scope().
 */
function these_settings_scope(stdClass $these) {
    global $DB;

    if (!empty($these->id) && get_config('mod_thesesynthesis', 'version')) {
        $cm = get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING);
        if ($cm) {
            $synthesisids = $DB->get_fieldset_sql(
                "SELECT l.synthesisid
                   FROM {thesesynthesis_link} l
                   JOIN {thesesynthesis} t ON t.id = l.synthesisid
                  WHERE l.thesecmid = :cmid
               ORDER BY l.synthesisid",
                ['cmid' => $cm->id]
            );
            foreach ($synthesisids as $synthesisid) {
                if ($scope = these_synthesis_scope($synthesisid)) {
                    return $scope;
                }
            }
        }
    }
    return these_local_scope($these);
}

/**
 * Conditions de base de données désignant une portée.
 *
 * @param stdClass $scope
 * @return array
 */
function these_scope_conditions(stdClass $scope) {
    return ['theseid' => (int) $scope->theseid, 'synthesisid' => (int) $scope->synthesisid];
}

/**
 * Modèle en vigueur d'un document dans une portée : celui modifié par la DEVE, sinon celui
 * d'origine.
 *
 * @param stdClass $scope Voir these_settings_scope().
 * @param string $type
 * @return string
 */
function these_get_doctemplate(stdClass $scope, $type) {
    global $DB;

    $custom = $DB->get_field('these_doctemplate', 'content', these_scope_conditions($scope) + ['doctype' => $type]);
    return is_string($custom) && trim($custom) !== '' ? $custom : these_doctemplate_default($type);
}

/**
 * Le modèle d'un document a-t-il été modifié dans la portée ?
 *
 * @param stdClass $scope
 * @param string $type
 * @return bool
 */
function these_doctemplate_customised(stdClass $scope, $type) {
    global $DB;

    return $DB->record_exists('these_doctemplate', these_scope_conditions($scope) + ['doctype' => $type]);
}

/**
 * Enregistre le modèle d'un document d'une portée ; null, ou le modèle d'origine, rétablit celui-ci.
 *
 * @param stdClass $scope
 * @param string $type
 * @param string|null $content
 * @return void
 */
function these_set_doctemplate(stdClass $scope, $type, $content) {
    global $DB;

    $normalise = function ($text) {
        return trim(str_replace("\r\n", "\n", (string) $text));
    };
    $conditions = these_scope_conditions($scope) + ['doctype' => $type];
    $existing = $DB->get_record('these_doctemplate', $conditions);
    if ($content === null || $normalise($content) === $normalise(these_doctemplate_default($type))) {
        if ($existing) {
            $DB->delete_records('these_doctemplate', ['id' => $existing->id]);
        }
        return;
    }
    $record = (object) ($conditions + ['content' => str_replace("\r\n", "\n", $content), 'timemodified' => time()]);
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('these_doctemplate', $record);
    } else {
        $DB->insert_record('these_doctemplate', $record);
    }
}

/**
 * Nom sous lequel une image est appelée dans les modèles ({{img.nom}}).
 *
 * @param string $filename
 * @return string
 */
function these_doctemplate_image_name($filename) {
    return preg_replace('/[^a-z0-9]/', '', core_text::strtolower(pathinfo($filename, PATHINFO_FILENAME)));
}

/**
 * Images utilisables dans les modèles : celles fournies avec le module (doctemplates/images/),
 * remplacées ou complétées par celles déposées par la DEVE dans la portée.
 *
 * @param stdClass|null $scope null : images du module seules.
 * @return array nom => source pour TCPDF ('@' suivi du contenu en base64)
 */
function these_doctemplate_images(?stdClass $scope) {
    global $CFG;

    $images = [];
    foreach (glob($CFG->dirroot . '/mod/these/doctemplates/images/*.{png,jpg,jpeg}', GLOB_BRACE) ?: [] as $path) {
        $images[these_doctemplate_image_name($path)] = '@' . base64_encode(file_get_contents($path));
    }
    if ($scope && $scope->contextid) {
        $fs = get_file_storage();
        foreach ($fs->get_area_files($scope->contextid, $scope->component, 'docimages', 0, 'filename', false) as $file) {
            if (in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                $images[these_doctemplate_image_name($file->get_filename())] = '@' . base64_encode($file->get_content());
            }
        }
    }
    return $images;
}

/**
 * Données d'un dossier nécessaires à ses documents, lues en base.
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string|null $doctype Document signé dont reprendre les signatures (null : aucun).
 * @return array Voir these_build_document_context().
 */
function these_document_sources(stdClass $these, context $context, stdClass $dossier, $doctype) {
    global $DB;

    $signatures = [];
    if ($doctype !== null) {
        foreach (these_get_signatures($dossier->id) as $signature) {
            if ($signature->doctype === $doctype && !empty($signature->timesigned)) {
                $signatures[$signature->slot] = [
                    'name' => $signature->signername,
                    'time' => (int) $signature->timesigned,
                    'hash' => substr((string) $signature->contenthash, 0, 12),
                    'image' => these_get_signature_image($context->id, $signature->id),
                ];
            }
        }
    }
    return [
        'these' => $these,
        'dossier' => $dossier,
        'student' => $DB->get_record('user', ['id' => $dossier->userid]) ?: null,
        'director' => $dossier->directorid ? ($DB->get_record('user', ['id' => $dossier->directorid]) ?: null) : null,
        'jury' => these_get_jury($dossier->id),
        'signatures' => $signatures,
        'recipient' => null,
        'scope' => these_settings_scope($these),
    ];
}

/**
 * Données d'un document, prêtes à être insérées dans son modèle.
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string|null $doctype Document signé dont reprendre les signatures (null : aucun).
 * @param stdClass|null $recipient Destinataire d'une convocation : membre du jury, ou null pour
 *                                 l'étudiant.
 * @return array
 */
function these_document_context(stdClass $these, context $context, stdClass $dossier, $doctype, ?stdClass $recipient = null) {
    $sources = these_document_sources($these, $context, $dossier, $doctype);
    $sources['recipient'] = $recipient;
    return these_build_document_context($sources);
}

/**
 * Case à cocher des modèles (glyphes de FreeSerif : FreeSans ne les contient pas).
 *
 * @param bool $checked
 * @return string HTML
 */
function these_doctemplate_box($checked) {
    return '<span style="font-family:freeserif;">' . ($checked ? '&#9746;' : '&#9744;') . '</span>';
}

/**
 * Texte multiligne pour un modèle : échappé, retours à la ligne conservés.
 *
 * @param string|null $text
 * @return string HTML
 */
function these_doctemplate_text($text) {
    return nl2br(htmlspecialchars(trim((string) $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
}

/**
 * Compose les données des modèles de documents. Toutes les valeurs sont du texte brut (échappé
 * par {{...}}), sauf celles dont le nom finit par « html » et les tableaux box, mark, sig, qui
 * contiennent du HTML à insérer par {{{...}}}. La liste complète, avec un exemple de valeur, est
 * présentée dans la page de modification d'un modèle.
 *
 * @param array $src {these, dossier, student, director, jury, signatures, recipient, scope}, voir
 *                   these_document_sources().
 * @return array
 */
function these_build_document_context(array $src) {
    $these = $src['these'];
    $d = $src['dossier'];
    $b = these_get_bordereau($d);
    $agreementdata = these_get_agreement($d);
    $griddata = these_get_grid($d);
    $config = (array) get_config('mod_these');

    $day = function ($time) {
        return (int) $time ? userdate((int) $time, '%d/%m/%Y') : '';
    };
    $up = function ($text) {
        return core_text::strtoupper(trim((string) $text));
    };
    $schools = these_school_options();
    $school = function ($key) use ($schools) {
        return (string) $key !== '' ? ($schools[$key] ?? '') : '';
    };

    // Étudiant : noms de l'état civil déclaré au bordereau, à défaut ceux du compte.
    $user = $src['student'];
    $lastname = $up(($b['birthname'] ?? '') ?: ($user->lastname ?? ''));
    $firstname = trim((string) (($b['firstnames'] ?? '') ?: ($user->firstname ?? '')));
    $address = implode(', ', array_filter([
        trim((string) ($b['street'] ?? '')),
        trim(($b['postcode'] ?? '') . ' ' . ($b['city'] ?? '')),
        trim((string) ($b['country'] ?? '')),
    ], 'strlen'));
    $student = [
        'lastname' => $lastname,
        'firstname' => $firstname,
        'fullname' => trim($lastname . ' ' . $firstname),
        'email' => (string) ($user->email ?? ''),
        'usagename' => $up($b['usagename'] ?? ''),
        'publicationname' => $up($b['publicationname'] ?? ''),
        'birthdate' => $day($b['birthdate'] ?? 0),
        'birthdatecompact' => !empty($b['birthdate']) ? userdate((int) $b['birthdate'], '%d%m%Y') : '',
        'birthplace' => (string) ($b['birthplace'] ?? ''),
        'birthdept' => (string) ($b['birthdept'] ?? ''),
        'nationality' => (string) ($b['nationality'] ?? ''),
        'street' => (string) ($b['street'] ?? ''),
        'postcode' => (string) ($b['postcode'] ?? ''),
        'city' => (string) ($b['city'] ?? ''),
        'country' => (string) ($b['country'] ?? ''),
        'address' => $address,
        'phone' => (string) ($b['phone'] ?? ''),
        'personalemail' => (string) ($b['personalemail'] ?? ''),
        'emailconsent' => !empty($b['emailconsent']),
    ];

    // Directeur (membre de l'établissement) et directeur extérieur éventuel.
    $duser = $src['director'];
    $directorlast = $up($duser->lastname ?? '');
    $directorfirst = (string) ($duser->firstname ?? '');
    // Titre du directeur : celui de l'annuaire du jury s'il y figure, sinon celui saisi au dépôt du sujet.
    $directorjuror = $duser ? \mod_these\local\jurors::find_by_email($duser->email) : null;
    $directortitle = $directorjuror ? $directorjuror->title : (string) $d->directortitle;
    $director = [
        'title' => $directortitle,
        'lastname' => $directorlast,
        'firstname' => $directorfirst,
        'fullname' => trim($directortitle . ' ' . $directorlast . ' ' . $directorfirst),
        'email' => (string) ($duser->email ?? ''),
        'function' => (string) ($agreementdata['directorfunction'] ?? ''),
    ];
    $external = !empty($d->externaldirector);
    $extdirector = [
        'present' => $external,
        'name' => $external ? trim((string) $d->extdirectorname) : '',
        'grade' => $external ? trim((string) $d->extdirectorgrade) : '',
        'institution' => $external ? trim((string) $d->extdirectorinstitution) : '',
        'email' => $external ? trim((string) $d->extdirectoremail) : '',
    ];
    // Directeur de thèse au sens des formulaires : l'extérieur s'il y en a un.
    $thesisdirector = [
        'name' => $external ? $extdirector['name'] : $director['fullname'],
        'function' => $external ? $extdirector['grade'] : $director['function'],
        'external' => $external,
    ];

    // Thèse.
    $keywords = these_split_keywords((string) $d->keywords);
    $abeskeywords = array_slice(array_values((array) ($b['keywords'] ?? [])), 0, THESE_BORDEREAU_KEYWORDS);
    $thesis = [
        'title' => (string) $d->title,
        'titleupper' => $up($d->title),
        'titleen' => (string) $d->titleen,
        'summary' => (string) $d->summary,
        'summaryhtml' => these_doctemplate_text($d->summary),
        'keywords' => implode(' ; ', $keywords),
        'confidential' => !empty($d->confidential),
        'studyyear' => (string) $d->studyyear,
        'devenumber' => (string) $d->devenumber,
        'libraryref' => (string) $d->libraryref,
        'ethicsnumber' => trim((string) $d->ethicsnumber),
        'codirector' => (string) $d->codirectorname,
    ];
    $summary = function ($field) use ($b) {
        return core_text::substr((string) ($b[$field] ?? ''), 0, THESE_BORDEREAU_SUMMARY_MAX);
    };
    $abes = [
        'nbvolumes' => (string) (int) ($b['nbvolumes'] ?? 1 ?: 1),
        'nbpages' => !empty($b['nbpages']) ? (string) (int) $b['nbpages'] : '',
        'nbrefs' => !empty($b['nbrefs']) ? (string) (int) $b['nbrefs'] : '',
        // Résumés coupés à 1700 caractères, comme l'annonce le bordereau.
        'summaryfrhtml' => these_doctemplate_text($summary('summaryfr')),
        'summaryenhtml' => these_doctemplate_text($summary('summaryen')),
    ];
    for ($i = 1; $i <= THESE_BORDEREAU_KEYWORDS; $i++) {
        $abes['kw' . $i] = (string) ($abeskeywords[$i - 1] ?? '');
    }

    // Soutenance.
    $date = (int) ($d->defensedate ?: $d->proposeddate);
    $defense = [
        'date' => $day($date),
        'longdate' => $date ? userdate($date, get_string('strftimedaydate', 'langconfig')) : '',
        'time' => $date ? userdate($date, '%H:%M') : '',
        'year' => $date ? userdate($date, '%Y') : '',
        'month' => $date ? userdate($date, '%m') : '',
        'day' => $date ? userdate($date, '%d') : '',
        'location' => (string) $d->defenselocation,
        'visio' => !empty($b['visio']),
        'result' => these_result_label($d->defenseresult),
        'mention' => these_mention_label($d->mention),
        'prizeproposal' => !empty($d->prizeproposal),
        'corrections' => !empty($d->correctionsrequested),
        'correctionsnote' => (string) ($d->correctionsnote ?? ''),
    ];

    // Cursus déclaré par l'étudiant (fiche signalétique) et agrément administratif de la DEVE.
    $cursus = [
        'defvdate' => $day($b['defvdate'] ?? 0),
        'defvschool' => $school($b['defvschool'] ?? ''),
        'a6notyet' => !empty($b['a6notyet']),
        'a6date' => empty($b['a6notyet']) ? $day($b['a6date'] ?? 0) : '',
        'a6school' => empty($b['a6notyet']) ? $school($b['a6school'] ?? '') : '',
    ];
    $agreement = [
        'defv' => !empty($agreementdata['defvdate']),
        'defvdate' => $day($agreementdata['defvdate'] ?? 0),
        'mobility' => !empty($agreementdata['mobility']),
        'a6notyet' => !empty($agreementdata['a6notyet']),
        'a6' => empty($agreementdata['a6notyet']) && !empty($agreementdata['a6date']),
        'a6date' => empty($agreementdata['a6notyet']) ? $day($agreementdata['a6date'] ?? 0) : '',
    ];

    // Jury : liste complète, et places fixes des formulaires (président, membre de
    // l'établissement, deux autres membres, directeur, deux invités).
    $roles = these_jury_role_options();
    $empty = ['role' => '', 'rolelabel' => '', 'title' => '', 'fullname' => '', 'name' => '', 'institution' => '', 'email' => ''];
    $jury = [];
    $president = $empty;
    $jurydirector = $empty;
    $members = [];
    $guests = [];
    foreach ($src['jury'] as $member) {
        $item = [
            'role' => $member->juryrole,
            'rolelabel' => $roles[$member->juryrole] ?? $member->juryrole,
            'title' => (string) $member->jurytitle,
            'fullname' => (string) $member->fullname,
            'name' => trim($member->jurytitle . ' ' . $member->fullname),
            'institution' => (string) $member->institution,
            'email' => (string) $member->email,
        ];
        $jury[] = $item;
        if ($member->juryrole === 'president') {
            $president = $item;
        } else if ($member->juryrole === 'director') {
            $jurydirector = $item;
        } else if ($member->juryrole === 'guest') {
            $guests[] = $item;
        } else {
            $members[] = $item;
        }
    }
    $institution = core_text::strtolower(trim((string) ($config['institution'] ?? '')));
    $local = $empty;
    foreach ($members as $index => $member) {
        if ($institution !== '' && strpos(core_text::strtolower($member['institution']), $institution) !== false) {
            $local = $member;
            unset($members[$index]);
            break;
        }
    }
    $members = array_values($members);
    if ($jurydirector['name'] === '') {
        $jurydirector = ['name' => $thesisdirector['name'], 'fullname' => $thesisdirector['name'],
            'institution' => $external ? $extdirector['institution'] : (string) ($config['institution'] ?? '')] + $empty;
    }
    $voting = array_values(array_filter($jury, function ($item) {
        return $item['role'] !== 'guest';
    }));

    // Rapport, grille et conclusions du directeur.
    $report = these_report_context($d->reporttext, !empty($d->reportfavorable));
    $grid = [];
    $mark = [];
    $gridsections = [];
    $checkmark = '<span style="font-family:freeserif;">&#10003;</span>';
    foreach (these_get_grid_items($src['scope'] ?? null) as $section => $items) {
        $rows = [];
        foreach ($items as $key => $item) {
            $checked = !empty($griddata[$key]);
            $grid[$key] = $checked;
            $mark[$key] = $checked ? $checkmark : '';
            $rows[] = ['key' => $key, 'label' => $item->label, 'checked' => $checked, 'mark' => $checked ? $checkmark : ''];
        }
        $gridsections[] = [
            'key' => $section,
            'first' => !$gridsections,
            'label' => get_string('gridsection' . $section, 'mod_these'),
            'pdflabel' => get_string('gridsectionpdf' . $section, 'mod_these'),
            'items' => $rows,
        ];
    }
    $conclusions = these_conclusions_context((string) $d->conclusions);

    // Contrat de diffusion.
    $embargo = !empty($b['embargo']);
    $contract = [
        'confidential' => !empty($d->confidential),
        'confidentialuntil' => !empty($d->confidential) ? $day($b['confidentialuntil'] ?? 0) : '',
        'embargo' => $embargo,
        'embargountil' => $embargo ? $day($b['embargountil'] ?? 0) : '',
        'internet' => ($b['diffusion'] ?? '') === 'internet',
        'restricted' => ($b['diffusion'] ?? '') === 'restricted',
        'templatemissing' => false,
    ];

    // Signatures : image et mention par emplacement, plus l'alias « thesisdirector ».
    $sig = [];
    $sigsrc = [];
    $sigcaption = [];
    $sigdate = [];
    $signer = [];
    $signed = [];
    $slots = ['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl', 'president'];
    foreach ($slots as $slot) {
        $signature = $src['signatures'][$slot] ?? null;
        $signed[$slot] = (bool) $signature;
        $sigdate[$slot] = $signature ? $day($signature['time']) : '';
        $signer[$slot] = $signature ? (string) $signature['name'] : '';
        $sigcaption[$slot] = $signature ? get_string('pdfsignedby', 'mod_these', (object) [
            'name' => $signature['name'],
            'date' => userdate($signature['time'], get_string('strftimedatetimeshort', 'langconfig')),
            'hash' => $signature['hash'],
        ]) : '';
        $sigsrc[$slot] = $signature && !empty($signature['image']) ? '@' . base64_encode($signature['image']) : '';
        $sig[$slot] = $signature ? ($sigsrc[$slot] !== '' ? '<img src="' . $sigsrc[$slot] . '" height="12mm" /><br />' : '')
            . '<span style="font-size:6pt;color:#555555;letter-spacing:0;">'
            . htmlspecialchars($sigcaption[$slot], ENT_QUOTES, 'UTF-8')
            . '</span>' : '';
    }
    $alias = $external ? 'extdirector' : 'director';
    $sig['thesisdirector'] = $sig[$alias];
    $sigsrc['thesisdirector'] = $sigsrc[$alias];
    $sigcaption['thesisdirector'] = $sigcaption[$alias];
    $sigdate['thesisdirector'] = $sigdate[$alias];
    $signer['thesisdirector'] = $signer[$alias];
    $signed['thesisdirector'] = $signed[$alias];

    // Destinataire d'une convocation.
    $recipient = $src['recipient'];
    $recipientdata = $recipient ? [
        'jury' => true,
        'name' => trim($recipient->jurytitle . ' ' . $recipient->fullname),
        'institution' => (string) $recipient->institution,
        'rolelabel' => $roles[$recipient->juryrole] ?? $recipient->juryrole,
    ] : ['jury' => false, 'name' => $student['fullname'], 'institution' => '', 'rolelabel' => ''];

    $years = [];
    foreach (['A2', 'A3', 'A4', 'A5', 'A6'] as $year) {
        $years[$year] = $thesis['studyyear'] === $year;
    }
    $commitments = (int) $d->commitments;
    $box = [
        'confidential' => $thesis['confidential'],
        'notconfidential' => !$thesis['confidential'],
        'visio' => $defense['visio'],
        'defv' => $agreement['defv'],
        'mobility' => $agreement['mobility'],
        'a6' => $agreement['a6'],
        'a6notyet' => $agreement['a6notyet'],
        'cursusa6notyet' => $cursus['a6notyet'],
        'embargo' => $embargo,
        'noembargo' => !$embargo,
        'internet' => $contract['internet'],
        'restricted' => $contract['restricted'],
        'favorable' => $report['favorable'],
        'emailconsent' => $student['emailconsent'],
        'commitguide' => (bool) ($commitments & THESE_COMMIT_GUIDE),
        'commitduplicate' => (bool) ($commitments & THESE_COMMIT_DUPLICATE),
        'commitethics' => (bool) ($commitments & THESE_COMMIT_ETHICS),
        'admitted' => (int) $d->defenseresult === THESE_RESULT_ADMITTED,
        'adjourned' => (int) $d->defenseresult === THESE_RESULT_ADJOURNED,
        'honorable' => (string) $d->mention === 'honorable',
        'treshonorable' => (string) $d->mention === 'treshonorable',
        'felicitations' => (string) $d->mention === 'felicitations',
        'prize' => !empty($d->prizeproposal),
        'corrections' => !empty($d->correctionsrequested),
    ] + $years;

    return [
        'today' => userdate(time(), '%d/%m/%Y'),
        'settings' => [
            'institution' => (string) ($config['institution'] ?? ''),
            'visaplace' => (string) ($config['visaplace'] ?? ''),
            'hetopurl' => (string) ($config['hetopurl'] ?? ''),
            'devename' => (string) ($config['devename'] ?? ''),
            'devetitle' => (string) ($config['devetitle'] ?? ''),
            'dgtitle' => (string) ($config['dgtitle'] ?? ''),
            'dgname' => (string) ($config['dgname'] ?? ''),
            'ccemname' => (string) ($config['ccemname'] ?? ''),
            'ucbltitle' => (string) ($config['ucbltitle'] ?? ''),
            'ucblname' => (string) ($config['ucblname'] ?? ''),
        ],
        'establishment' => [
            'name' => (string) $these->establishmentname,
            'addresshtml' => these_doctemplate_text($these->establishmentaddress),
            'city' => (string) $these->establishmentcity,
            'signatoryname' => (string) $these->signatoryname,
            'signatorytitle' => (string) $these->signatorytitle,
        ],
        'student' => $student,
        'director' => $director,
        'extdirector' => $extdirector,
        'thesisdirector' => $thesisdirector,
        'thesis' => $thesis,
        'abes' => $abes,
        'defense' => $defense,
        'cursus' => $cursus,
        'agreement' => $agreement,
        'year' => $years,
        'jury' => $jury,
        'juryvoting' => $voting,
        'jurypresident' => $president,
        'jurydirector' => $jurydirector,
        'jurylocal' => $local,
        'jurymember1' => $members[0] ?? $empty,
        'jurymember2' => $members[1] ?? $empty,
        'juryothers' => $members,
        'juryguest1' => $guests[0] ?? $empty,
        'juryguest2' => $guests[1] ?? $empty,
        'juryguests' => $guests,
        'report' => $report,
        'grid' => $grid,
        'mark' => $mark,
        'gridsections' => $gridsections,
        'conclusions' => $conclusions,
        'contract' => $contract,
        'recipient' => $recipientdata,
        'box' => array_map('these_doctemplate_box', $box),
        'checked' => these_doctemplate_box(true),
        'unchecked' => these_doctemplate_box(false),
        'check' => '<span style="font-family:freeserif;">&#10003;</span>',
        'sig' => $sig,
        'sigsrc' => $sigsrc,
        'sigcaption' => $sigcaption,
        'sigdate' => $sigdate,
        'signer' => $signer,
        'signed' => $signed,
        'img' => these_doctemplate_images($src['scope'] ?? null),
    ];
}

/** Longueur maximale des conclusions, espaces compris : elles doivent tenir sur une page avec les signatures. */
define('THESE_CONCLUSIONS_MAX', 2200);

/**
 * Conclusions prêtes pour le modèle : paragraphes (séparés par une ligne vide), le dernier à part.
 *
 * @param string $text
 * @return array {html, bodyhtml, lasthtml}
 */
function these_conclusions_context($text) {
    $paragraphs = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", trim($text))), 'strlen'));
    $paragraphs = array_map('these_doctemplate_text', $paragraphs);
    $last = $paragraphs ? array_pop($paragraphs) : '';
    return [
        'html' => implode('<br /><br />', array_merge($paragraphs, $last !== '' ? [$last] : [])),
        'bodyhtml' => implode('<br /><br />', $paragraphs),
        'lasthtml' => $last,
    ];
}

/**
 * Vérifie que des conclusions tiennent sur une page, signatures comprises : longueur maximale,
 * puis mise en page réelle avec le modèle de l'activité.
 *
 * @param stdClass $these
 * @param context|null $context
 * @param string $text
 * @return string|null Message d'erreur, ou null si elles tiennent.
 */
function these_conclusions_error(stdClass $these, ?context $context, $text) {
    $length = core_text::strlen(trim((string) $text));
    if ($length > THESE_CONCLUSIONS_MAX) {
        return get_string('conclusionstoolong', 'mod_these', (object) ['max' => THESE_CONCLUSIONS_MAX, 'length' => $length]);
    }
    $data = these_sample_document_context(these_settings_scope($these), $these);
    $data['conclusions'] = these_conclusions_context((string) $text);
    try {
        $pages = \mod_these\pdf\html_document::page_count(these_render_doctemplate($these, 'conclusions', $data));
    } catch (\Throwable $e) {
        return null;
    }
    return $pages > 1 ? get_string('conclusionsonepage', 'mod_these') : null;
}

/** Longueur maximale du rapport du directeur, qui doit tenir sur une page avec la signature. */
define('THESE_REPORT_MAX', 4000);

/**
 * Vérifie que le rapport du directeur tient sur une page, signature comprise : longueur maximale,
 * puis mise en page réelle avec le modèle en vigueur.
 *
 * @param stdClass $these
 * @param string $text
 * @param bool $favorable Avis favorable (imprimé sous le rapport).
 * @return string|null Message d'erreur, ou null s'il tient.
 */
function these_report_error(stdClass $these, $text, $favorable = true) {
    $length = core_text::strlen(trim((string) $text));
    if ($length > THESE_REPORT_MAX) {
        return get_string('reporttoolong', 'mod_these', (object) ['max' => THESE_REPORT_MAX, 'length' => $length]);
    }
    $data = these_sample_document_context(these_settings_scope($these), $these);
    $data['report'] = these_report_context($text, $favorable);
    try {
        $pages = \mod_these\pdf\html_document::page_count(these_render_doctemplate($these, 'report', $data));
    } catch (\Throwable $e) {
        return null;
    }
    return $pages > 1 ? get_string('reportonepage', 'mod_these') : null;
}

/**
 * Données du rapport du directeur pour les modèles de documents.
 *
 * @param string|null $text
 * @param bool $favorable
 * @return array {text, html, favorable, favorabletext}
 */
function these_report_context($text, $favorable) {
    return [
        'text' => trim((string) $text),
        'html' => these_doctemplate_text($text),
        'favorable' => (bool) $favorable,
        'favorabletext' => get_string('pdfreportfavorable', 'mod_these'),
    ];
}

/**
 * Produit le PDF d'un modèle de document, avec le modèle en vigueur pour l'activité (voir
 * these_settings_scope()).
 *
 * @param stdClass $these Activité.
 * @param string $type Voir these_doctemplate_types().
 * @param array $data Voir these_build_document_context().
 * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
 * @return string Contenu PDF.
 */
function these_render_doctemplate(stdClass $these, $type, array $data, $template = null) {
    return these_render_scope_doctemplate(these_settings_scope($these), $type, $data, $template);
}

/**
 * Produit le PDF d'un modèle de document d'une portée.
 *
 * @param stdClass|null $scope Voir these_settings_scope() ; null : modèle d'origine.
 * @param string $type
 * @param array $data
 * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
 * @return string Contenu PDF.
 */
function these_render_scope_doctemplate(?stdClass $scope, $type, array $data, $template = null) {
    return \mod_these\pdf\html_document::render(
        $template ?? ($scope ? these_get_doctemplate($scope, $type) : these_doctemplate_default($type)),
        $data,
        these_doctemplate_label($type)
    );
}

/**
 * Produit le PDF d'un document signé.
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string $doctype
 * @return string Contenu PDF.
 */
function these_signed_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
    if (!array_key_exists($doctype, these_document_definitions())) {
        throw new coding_exception('Document inconnu : ' . $doctype);
    }
    $data = these_document_context($these, $context, $dossier, $doctype);
    if ($doctype !== 'contract') {
        return these_render_doctemplate($these, $doctype, $data);
    }

    // Contrat de diffusion, comme les conventions de mod_stage : page de données et de choix,
    // contrat type officiel déposé dans les réglages du site, page de signatures.
    $template = these_get_contract_template();
    if ($template !== null) {
        try {
            return these_merge_pdfs([
                these_render_doctemplate($these, 'contractdata', $data),
                $template,
                these_render_doctemplate($these, 'contractsign', $data),
            ]);
        } catch (\Throwable $e) {
            // Contrat type illisible par l'analyseur libre de FPDI (certaines compressions récentes) :
            // les pages du module restent produites, avec un avertissement à la place du contrat.
            debugging('Contrat type de diffusion illisible : ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    $data['contract']['templatemissing'] = true;
    return these_merge_pdfs([
        these_render_doctemplate($these, 'contractdata', $data),
        these_render_doctemplate($these, 'contractsign', $data),
    ]);
}

/**
 * Vérifie qu'un modèle produit un document avec les données d'exemple.
 *
 * @param stdClass $scope Voir these_settings_scope().
 * @param string $type
 * @param string $content
 * @param stdClass|null $activity Activité dont reprendre l'établissement dans l'exemple.
 * @return string|null Message d'erreur, ou null si le modèle est utilisable.
 */
function these_doctemplate_error(stdClass $scope, $type, $content, ?stdClass $activity = null) {
    try {
        these_render_scope_doctemplate($scope, $type, these_sample_document_context($scope, $activity), $content);
    } catch (\Throwable $e) {
        return get_string('doctplerror', 'mod_these', $e->getMessage());
    }
    return null;
}

/**
 * Données fictives pour l'aperçu d'un modèle : un dossier complet, signé à tous les emplacements.
 *
 * @param stdClass|null $scope Portée des paramètres (images, grille) ; null : ceux d'origine.
 * @param stdClass|null $activity Activité dont reprendre l'établissement (sinon des valeurs fictives).
 * @return array Voir these_build_document_context().
 */
function these_sample_document_context(?stdClass $scope = null, ?stdClass $activity = null) {
    $now = time();
    $these = $activity ?? (object) [
        'establishmentname' => 'VetAgro Sup', 'establishmentaddress' => "1 avenue Bourgelat\n69280 Marcy-l'Étoile",
        'establishmentcity' => "Marcy-l'Étoile", 'signatoryname' => 'Hélène AGUESSE',
        'signatorytitle' => 'Directrice des Études et de la Vie Étudiante',
    ];
    $bordereau = [
        'birthname' => 'Martin', 'firstnames' => 'Camille', 'usagename' => '', 'publicationname' => '',
        'birthdate' => make_timestamp(1999, 4, 12), 'birthplace' => 'Lyon', 'birthdept' => '69', 'nationality' => 'Française',
        'street' => '12 rue des Écoles', 'postcode' => '69007', 'city' => 'Lyon', 'country' => 'France',
        'phone' => '06 12 34 56 78', 'personalemail' => 'camille.martin@example.org', 'emailconsent' => 1,
        'defvdate' => make_timestamp(2023, 7, 3), 'defvschool' => 'lyon', 'a6date' => make_timestamp(2025, 7, 1),
        'a6school' => 'lyon', 'a6notyet' => 0, 'nbvolumes' => 1, 'nbpages' => 112, 'nbrefs' => 86,
        'summaryfr' => str_repeat("Ce travail étudie la prise en charge de la douleur chronique chez le chien âgé. ", 12),
        'summaryen' => str_repeat("This work studies the management of chronic pain in elderly dogs. ", 12),
        'keywords' => ['Chien', 'Douleur chronique', 'Gériatrie', 'Analgésie', 'Arthrose'],
        'confidentialuntil' => 0, 'embargo' => 0, 'embargountil' => 0, 'diffusion' => 'internet', 'visio' => 0,
    ];
    $dossier = (object) [
        'id' => 0, 'userid' => 0, 'directorid' => 0,
        'title' => 'Prise en charge de la douleur chronique chez le chien âgé : enquête auprès des vétérinaires praticiens',
        'titleen' => 'Management of chronic pain in elderly dogs: a survey of veterinary practitioners',
        'summary' => 'Résumé du sujet.', 'keywords' => 'Chien ; Douleur chronique ; Gériatrie ; Analgésie',
        'codirectorname' => '', 'studyyear' => 'A5', 'confidential' => 0, 'directortitle' => 'Pr',
        'externaldirector' => 0, 'extdirectorname' => '', 'extdirectorgrade' => '', 'extdirectorinstitution' => '',
        'extdirectoremail' => '', 'commitments' => THESE_COMMIT_GUIDE | THESE_COMMIT_DUPLICATE, 'ethicsnumber' => '2526',
        'bordereau' => json_encode($bordereau),
        'conclusions' => "Premier paragraphe des conclusions.\n\n"
            . "Dernier paragraphe des conclusions, qui reste avec les signatures.",
        'reporttext' => "Camille Martin a conduit une enquête rigoureuse auprès de 250 praticiens.\n"
            . "Le manuscrit est clair et bien construit.",
        'reportfavorable' => 1,
        'conformitygrid' => json_encode(array_fill_keys(
            array_merge(...array_map('array_keys', array_values(these_get_grid_items($scope)))),
            1
        )),
        'agreement' => json_encode(['defvdate' => make_timestamp(2023, 7, 3), 'mobility' => 1,
            'a6date' => make_timestamp(2025, 7, 1), 'a6notyet' => 0, 'directorfunction' => 'Professeur']),
        'devenumber' => '2026-042', 'libraryref' => 'THV-2026-042', 'proposeddate' => $now + 30 * DAYSECS,
        'defensedate' => $now + 30 * DAYSECS, 'defenselocation' => 'Amphithéâtre Bourgelat', 'defenseresult' => 0,
        'mention' => '',
    ];
    $jury = [];
    foreach (
        [
        ['president', 'Pr', 'Jean DUPONT', 'Université Claude Bernard Lyon 1'],
        ['member', 'Dr', 'Claire BERNARD', 'VetAgro Sup Campus vétérinaire de Lyon'],
        ['member', 'Dr', 'Paul LEROY', 'VetAgro Sup Campus vétérinaire de Lyon'],
        ['director', 'Pr', 'Anne DURAND', 'VetAgro Sup Campus vétérinaire de Lyon'],
        ['guest', 'Dr', 'Luc PETIT', 'Clinique vétérinaire des Monts'],
        ] as $i => [$role, $title, $name, $institution]
    ) {
        $jury[] = (object) ['juryrole' => $role, 'jurytitle' => $title, 'fullname' => $name, 'institution' => $institution,
            'email' => 'jury' . $i . '@example.org'];
    }
    $image = these_sample_signature_image();
    $signatures = [];
    foreach (['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl', 'president'] as $slot) {
        $signatures[$slot] = ['name' => these_slot_label($slot), 'time' => $now, 'hash' => '3f2a9c51d0e4',
            'image' => $image];
    }
    return these_build_document_context([
        'these' => $these,
        'dossier' => $dossier,
        'student' => (object) ['firstname' => 'Camille', 'lastname' => 'Martin', 'email' => 'camille.martin@example.org'],
        'director' => (object) ['firstname' => 'Anne', 'lastname' => 'Durand', 'email' => 'anne.durand@example.org'],
        'jury' => $jury,
        'signatures' => $signatures,
        'recipient' => null,
        'scope' => $scope,
    ]);
}

/**
 * Tracé de signature fictif (PNG) pour les aperçus.
 *
 * @return string
 */
function these_sample_signature_image() {
    $image = imagecreatetruecolor(300, 100);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
    $ink = imagecolorallocate($image, 20, 40, 120);
    imagesetthickness($image, 3);
    $previous = null;
    for ($x = 20; $x <= 280; $x += 4) {
        $point = [$x, (int) (50 + 28 * sin($x / 18) * cos($x / 47))];
        if ($previous) {
            imageline($image, $previous[0], $previous[1], $point[0], $point[1], $ink);
        }
        $previous = $point;
    }
    ob_start();
    imagepng($image);
    imagedestroy($image);
    return (string) ob_get_clean();
}

/**
 * Variables d'un contexte de modèle, à plat, avec un exemple de valeur : aide de la page de
 * modification d'un modèle.
 *
 * @param array $data
 * @param string $prefix
 * @return array nom => exemple (texte)
 */
function these_doctemplate_variables(array $data, $prefix = '') {
    $variables = [];
    foreach ($data as $key => $value) {
        $name = $prefix . $key;
        if ($key === 'img') {
            foreach (array_keys($value) as $image) {
                $variables[$name . '.' . $image] = get_string('doctplvarimage', 'mod_these');
            }
        } else if (is_array($value) && isset($value[0]) && is_array($value[0])) {
            $variables[$name] = get_string('doctplvarlist', 'mod_these', implode(', ', array_keys($value[0])));
        } else if (is_array($value) && !isset($value[0])) {
            $variables += these_doctemplate_variables($value, $name . '.');
        } else if (is_bool($value)) {
            $variables[$name] = get_string('doctplvarbool', 'mod_these', $value ? 'true' : 'false');
        } else if (is_scalar($value)) {
            $value = (string) $value;
            $variables[$name] = strpos($value, '<') !== false ? get_string('doctplvarhtml', 'mod_these')
                : core_text::substr($value, 0, 60) . (core_text::strlen($value) > 60 ? '…' : '');
        }
    }
    return $variables;
}

/**
 * Contrat type de diffusion électronique (PDF officiel), déposé dans les réglages du site.
 *
 * @return string|null Contenu du PDF.
 */
function these_get_contract_template() {
    $fs = get_file_storage();
    $files = $fs->get_area_files(context_system::instance()->id, 'mod_these', 'contracttemplate', 0, 'id', false);
    $file = reset($files);
    return $file ? $file->get_content() : null;
}

/**
 * Assemble plusieurs PDF en un seul, page à page et au format de chaque page (FPDI, fournie dans
 * thirdparty/, comme dans mod_stage).
 *
 * @param string[] $sources Contenus PDF, dans l'ordre.
 * @return string Contenu PDF assemblé.
 */
function these_merge_pdfs(array $sources) {
    global $CFG;

    require_once($CFG->libdir . '/pdflib.php');
    require_once($CFG->dirroot . '/mod/these/thirdparty/vendor/autoload.php');

    $merger = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
    $merger->setPrintHeader(false);
    $merger->setPrintFooter(false);
    foreach ($sources as $source) {
        $pagecount = $merger->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($source));
        for ($pageno = 1; $pageno <= $pagecount; $pageno++) {
            $tplidx = $merger->importPage($pageno);
            $size = $merger->getTemplateSize($tplidx);
            $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $merger->useTemplate($tplidx);
        }
    }
    return $merger->Output('', 'S');
}

// Transfert des paramètres.

/** Format du fichier de paramètres exporté. */
define('THESE_SETTINGS_FORMAT', 'mod_these_settings');

/**
 * Paramètres de l'activité transférables (formulaire de l'activité), avec leur type.
 *
 * @return array champ => PARAM_*
 */
function these_settings_activity_fields() {
    return [
        'establishmentname' => PARAM_TEXT,
        'establishmentaddress' => PARAM_TEXT,
        'establishmentcity' => PARAM_TEXT,
        'signatoryname' => PARAM_TEXT,
        'signatorytitle' => PARAM_TEXT,
        'defaultlocation' => PARAM_TEXT,
        'minjury' => PARAM_INT,
        'deveemail' => PARAM_EMAIL,
        'libraryemail' => PARAM_EMAIL,
        'universityemail' => PARAM_EMAIL,
        'reminderdays' => PARAM_INT,
    ];
}

/**
 * Groupes de paramètres transférables, dans l'ordre de présentation.
 *
 * @return string[]
 */
function these_settings_groups() {
    return ['activity', 'emails', 'griditems', 'questions', 'doctemplates', 'docimages'];
}

/**
 * Paramètres d'une activité, sous une forme transférable (et enregistrable en JSON).
 *
 * @param stdClass $these
 * @param context $context
 * @param string[] $groups Voir these_settings_groups().
 * @return array
 */
function these_export_settings(stdClass $these, context $context, array $groups) {
    global $DB;

    $data = ['format' => THESE_SETTINGS_FORMAT, 'version' => 1, 'name' => (string) $these->name, 'groups' => []];
    if (in_array('activity', $groups, true)) {
        $data['groups']['activity'] = [];
        foreach (array_keys(these_settings_activity_fields()) as $field) {
            $data['groups']['activity'][$field] = $these->$field;
        }
    }
    if (in_array('emails', $groups, true)) {
        $data['groups']['emails'] = [];
        foreach ($DB->get_records('these_email_template', ['theseid' => $these->id], 'emailkey') as $record) {
            $data['groups']['emails'][] = ['emailkey' => $record->emailkey, 'subject' => $record->subject, 'body' => $record->body];
        }
    }
    if (in_array('griditems', $groups, true)) {
        $data['groups']['griditems'] = [];
        foreach (these_get_grid_items(these_local_scope($these)) as $items) {
            foreach ($items as $item) {
                $data['groups']['griditems'][] = ['itemkey' => $item->itemkey, 'section' => $item->section,
                    'label' => $item->label, 'conditional' => (int) $item->conditional];
            }
        }
    }
    if (in_array('questions', $groups, true)) {
        $data['groups']['questions'] = [];
        foreach (these_get_questions(these_local_scope($these)) as $question) {
            $data['groups']['questions'][] = ['qtype' => $question->qtype, 'label' => $question->label,
                'options' => $question->options, 'required' => (int) $question->required];
        }
    }
    if (in_array('doctemplates', $groups, true)) {
        $data['groups']['doctemplates'] = [];
        foreach ($DB->get_records('these_doctemplate', ['theseid' => $these->id], 'doctype') as $record) {
            $data['groups']['doctemplates'][] = ['doctype' => $record->doctype, 'content' => $record->content];
        }
    }
    if (in_array('docimages', $groups, true)) {
        $data['groups']['docimages'] = [];
        $fs = get_file_storage();
        foreach ($fs->get_area_files($context->id, 'mod_these', 'docimages', 0, 'filename', false) as $file) {
            $data['groups']['docimages'][] = [
                'filename' => $file->get_filename(),
                'content' => base64_encode($file->get_content()),
            ];
        }
    }
    return $data;
}

/**
 * Lit un fichier de paramètres exporté.
 *
 * @param string $json
 * @return array|null Données (voir these_export_settings()), null si le fichier n'en est pas un.
 */
function these_parse_settings_file($json) {
    $data = json_decode((string) $json, true);
    if (!is_array($data) || ($data['format'] ?? '') !== THESE_SETTINGS_FORMAT || !is_array($data['groups'] ?? null)) {
        return null;
    }
    return $data;
}

/**
 * Remplace les paramètres choisis d'une activité par ceux transférés. Pour chaque groupe choisi
 * et présent dans les données, les paramètres de l'activité sont remplacés en totalité : un
 * courriel ou un modèle personnalisé dans l'activité mais pas dans la source revient à son texte
 * d'origine. Les éléments inconnus (courriel, document) et les modèles qui ne produisent pas de
 * document sont ignorés et signalés.
 *
 * @param stdClass $these Activité de destination (mise à jour).
 * @param context $context
 * @param array $data Voir these_export_settings().
 * @param string[] $groups Groupes à transférer.
 * @return stdClass {activity (bool), emails, griditems, questions, doctemplates, docimages (nombres),
 *     skipped (string[])}
 */
function these_import_settings(stdClass $these, context $context, array $data, array $groups) {
    global $DB;

    $result = (object) ['activity' => false, 'emails' => 0, 'griditems' => 0, 'questions' => 0, 'doctemplates' => 0,
        'docimages' => 0, 'skipped' => []];
    $source = $data['groups'];
    $transaction = $DB->start_delegated_transaction();

    if (in_array('activity', $groups, true) && is_array($source['activity'] ?? null)) {
        $update = (object) ['id' => $these->id, 'timemodified' => time()];
        foreach (these_settings_activity_fields() as $field => $type) {
            if (array_key_exists($field, $source['activity'])) {
                $update->$field = clean_param($source['activity'][$field] ?? '', $type);
            }
        }
        if (isset($update->minjury)) {
            $update->minjury = max(1, (int) $update->minjury);
        }
        if (isset($update->reminderdays)) {
            $update->reminderdays = max(0, (int) $update->reminderdays);
        }
        $DB->update_record('these', $update);
        foreach ((array) $update as $field => $value) {
            $these->$field = $value;
        }
        $result->activity = true;
    }

    if (in_array('emails', $groups, true) && is_array($source['emails'] ?? null)) {
        $DB->delete_records('these_email_template', ['theseid' => $these->id]);
        $definitions = these_get_email_definitions();
        foreach ($source['emails'] as $email) {
            $key = (string) ($email['emailkey'] ?? '');
            if (!isset($definitions[$key])) {
                $result->skipped[] = get_string('transferskippedemail', 'mod_these', s($key));
                continue;
            }
            these_save_email_template($these->id, $key, (string) ($email['subject'] ?? ''), (string) ($email['body'] ?? ''));
            $result->emails++;
        }
    }

    if (in_array('griditems', $groups, true) && is_array($source['griditems'] ?? null) && $source['griditems']) {
        $items = [];
        foreach ($source['griditems'] as $item) {
            $items[] = (object) [
                'itemkey' => clean_param((string) ($item['itemkey'] ?? ''), PARAM_ALPHANUM),
                'section' => (string) ($item['section'] ?? ''),
                'label' => clean_param((string) ($item['label'] ?? ''), PARAM_TEXT),
                'conditional' => !empty($item['conditional']),
            ];
        }
        $scope = these_local_scope($these);
        these_save_grid_items($scope, $items);
        $result->griditems = count(these_get_grid_items($scope), COUNT_RECURSIVE) - count(these_grid_sections());
    }

    // Questionnaire : les réponses déjà données aux questions remplacées sont supprimées avec elles.
    if (in_array('questions', $groups, true) && is_array($source['questions'] ?? null)) {
        $scope = these_local_scope($these);
        foreach (array_keys(these_get_questions($scope)) as $questionid) {
            these_delete_question($scope, $questionid);
        }
        foreach ($source['questions'] as $question) {
            $label = clean_param((string) ($question['label'] ?? ''), PARAM_TEXT);
            if (trim($label) === '') {
                continue;
            }
            these_save_question($scope, (object) [
                'qtype' => (string) ($question['qtype'] ?? 'text'),
                'label' => $label,
                'options' => clean_param((string) ($question['options'] ?? ''), PARAM_TEXT),
                'required' => !empty($question['required']),
            ]);
            $result->questions++;
        }
    }

    if (in_array('doctemplates', $groups, true) && is_array($source['doctemplates'] ?? null)) {
        $DB->delete_records('these_doctemplate', ['theseid' => $these->id]);
        $types = these_doctemplate_types();
        foreach ($source['doctemplates'] as $template) {
            $type = (string) ($template['doctype'] ?? '');
            $content = (string) ($template['content'] ?? '');
            if (!array_key_exists($type, $types)) {
                $result->skipped[] = get_string('transferskippeddoctemplate', 'mod_these', s($type));
                continue;
            }
            if (($error = these_doctemplate_error(these_local_scope($these), $type, $content, $these)) !== null) {
                $result->skipped[] = these_doctemplate_label($type) . ' : ' . $error;
                continue;
            }
            these_set_doctemplate(these_local_scope($these), $type, $content);
            $result->doctemplates++;
        }
    }

    if (in_array('docimages', $groups, true) && is_array($source['docimages'] ?? null)) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_these', 'docimages', 0);
        foreach ($source['docimages'] as $image) {
            $filename = clean_param((string) ($image['filename'] ?? ''), PARAM_FILE);
            $content = base64_decode((string) ($image['content'] ?? ''), true);
            $info = $content !== false ? @getimagesizefromstring($content) : false;
            if (
                $filename === '' || !$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
                    || $fs->file_exists($context->id, 'mod_these', 'docimages', 0, '/', $filename)
            ) {
                $result->skipped[] = get_string('transferskippedimage', 'mod_these', s($filename));
                continue;
            }
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_these',
                'filearea' => 'docimages',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
            $result->docimages++;
        }
    }

    $transaction->allow_commit();
    return $result;
}

/**
 * Autres activités « Gestion des thèses » dont un utilisateur gère les paramètres (DEVE) :
 * sources possibles d'un transfert.
 *
 * @param stdClass $these Activité courante (exclue).
 * @param int $userid
 * @return array id de l'activité => libellé « cours : activité »
 */
function these_settings_transfer_sources(stdClass $these, $userid) {
    global $DB;

    $sources = [];
    $sql = "SELECT t.id, t.name, c.fullname AS coursename
              FROM {these} t
              JOIN {course} c ON c.id = t.course
             WHERE t.id <> :id
          ORDER BY c.fullname, t.name";
    foreach ($DB->get_records_sql($sql, ['id' => $these->id]) as $record) {
        $cm = get_coursemodule_from_instance('these', $record->id, 0, false, IGNORE_MISSING);
        if (!$cm) {
            continue;
        }
        $rights = these_get_rights(context_module::instance($cm->id), null, $userid);
        if ($rights->deve) {
            $sources[$record->id] = format_string($record->coursename) . ' : ' . format_string($record->name);
        }
    }
    return $sources;
}

// Questionnaire préalable au jury.

/**
 * Types de questions.
 *
 * @return array type => libellé
 */
function these_question_types() {
    $types = [];
    foreach (['text', 'textarea', 'list', 'multi'] as $type) {
        $types[$type] = get_string('qtype' . $type, 'mod_these');
    }
    return $types;
}

/**
 * Questions du questionnaire préalable au jury d'une portée (voir these_settings_scope()), dans
 * l'ordre.
 *
 * @param stdClass $scope
 * @return array id => question
 */
function these_get_questions(stdClass $scope) {
    global $DB;
    return $DB->get_records('these_question', these_scope_conditions($scope), 'sortorder ASC, id ASC');
}

/**
 * Choix proposés par une question à liste ou à choix multiples.
 *
 * @param stdClass $question
 * @return string[]
 */
function these_question_options(stdClass $question) {
    return array_values(array_unique(array_filter(
        array_map('trim', preg_split('/\r\n|\r|\n/', (string) $question->options)),
        'strlen'
    )));
}

/**
 * Enregistre une question d'une portée (ajout en fin de questionnaire, ou modification).
 *
 * @param stdClass $scope
 * @param stdClass $data {id?, qtype, label, options, required}
 * @return int id
 */
function these_save_question(stdClass $scope, stdClass $data) {
    global $DB;

    $record = (object) array_merge(these_scope_conditions($scope), [
        'qtype' => array_key_exists($data->qtype, these_question_types()) ? $data->qtype : 'text',
        'label' => trim((string) $data->label),
        'options' => in_array($data->qtype, ['list', 'multi'], true) ? trim((string) $data->options) : '',
        'required' => empty($data->required) ? 0 : 1,
        'timemodified' => time(),
    ]);
    $existing = !empty($data->id) ? $DB->get_record('these_question', ['id' => $data->id] + these_scope_conditions($scope)) : null;
    if ($existing) {
        $record->id = $existing->id;
        $record->sortorder = $existing->sortorder;
        $DB->update_record('these_question', $record);
        return (int) $existing->id;
    }
    $record->sortorder = (int) $DB->get_field_sql(
        'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {these_question} WHERE theseid = :theseid AND synthesisid = :synthesisid',
        these_scope_conditions($scope)
    );
    return (int) $DB->insert_record('these_question', $record);
}

/**
 * Supprime une question d'une portée et les réponses qui lui ont été données.
 *
 * @param stdClass $scope
 * @param int $id
 * @return void
 */
function these_delete_question(stdClass $scope, $id) {
    global $DB;
    if ($DB->record_exists('these_question', ['id' => $id] + these_scope_conditions($scope))) {
        $DB->delete_records('these_answer', ['questionid' => $id]);
        $DB->delete_records('these_question', ['id' => $id]);
    }
}

/**
 * Déplace une question d'un rang vers le haut ou vers le bas.
 *
 * @param stdClass $scope
 * @param int $id
 * @param int $direction -1 ou 1
 * @return void
 */
function these_move_question(stdClass $scope, $id, $direction) {
    global $DB;

    $ids = array_keys(these_get_questions($scope));
    $index = array_search((int) $id, array_map('intval', $ids), true);
    $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
    if ($target === false || $target < 0 || $target >= count($ids)) {
        return;
    }
    [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
    foreach (array_values($ids) as $sortorder => $questionid) {
        $DB->set_field('these_question', 'sortorder', $sortorder, ['id' => $questionid]);
    }
}

/**
 * Réponses d'un dossier au questionnaire.
 *
 * @param int $dossierid
 * @return array questionid => valeur (texte, ou liste de choix pour un QCM)
 */
function these_get_answers($dossierid) {
    global $DB;

    $answers = [];
    foreach ($DB->get_records('these_answer', ['dossierid' => $dossierid]) as $answer) {
        $decoded = json_decode((string) $answer->value, true);
        $answers[(int) $answer->questionid] = is_array($decoded) ? $decoded : (string) $answer->value;
    }
    return $answers;
}

/**
 * Enregistre les réponses d'un dossier (formulaire du questionnaire : champs q<id>, ou q<id>_<n>
 * pour les choix d'un QCM).
 *
 * @param stdClass $dossier
 * @param array $questions Voir these_get_questions().
 * @param stdClass|array $data
 * @return void
 */
function these_save_answers(stdClass $dossier, array $questions, $data) {
    global $DB;

    $data = (array) $data;
    foreach ($questions as $question) {
        $options = these_question_options($question);
        if ($question->qtype === 'multi') {
            $chosen = [];
            foreach ($options as $n => $option) {
                if (!empty($data['q' . $question->id . '_' . $n])) {
                    $chosen[] = $option;
                }
            }
            $value = $chosen ? json_encode($chosen, JSON_UNESCAPED_UNICODE) : '';
        } else {
            $value = trim((string) ($data['q' . $question->id] ?? ''));
            if ($question->qtype === 'list' && !in_array($value, $options, true)) {
                $value = '';
            }
        }
        $existing = $DB->get_record('these_answer', ['dossierid' => $dossier->id, 'questionid' => $question->id]);
        if ($value === '') {
            if ($existing) {
                $DB->delete_records('these_answer', ['id' => $existing->id]);
            }
            continue;
        }
        $record = (object) ['dossierid' => $dossier->id, 'questionid' => $question->id, 'value' => $value,
            'timemodified' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('these_answer', $record);
        } else {
            $DB->insert_record('these_answer', $record);
        }
    }
}

/**
 * Questions obligatoires sans réponse.
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @return string[] Libellés.
 */
function these_questionnaire_missing(stdClass $these, stdClass $dossier) {
    $answers = these_get_answers($dossier->id);
    $missing = [];
    foreach (these_get_questions(these_settings_scope($these)) as $question) {
        $answer = $answers[(int) $question->id] ?? '';
        if ($question->required && ($answer === '' || $answer === [])) {
            $missing[] = $question->label;
        }
    }
    return $missing;
}

/**
 * Réponse lisible.
 *
 * @param mixed $answer Voir these_get_answers().
 * @return string
 */
function these_answer_text($answer) {
    return is_array($answer) ? implode(' ; ', $answer) : (string) $answer;
}

/**
 * Réponses d'un dossier au questionnaire, pour l'affichage (questions en vigueur, puis réponses à
 * des questions d'une autre portée, si l'activité a changé de suivi).
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @return string HTML ('' si le questionnaire est vide et sans réponse)
 */
function these_render_answers(stdClass $these, stdClass $dossier) {
    global $DB;

    $answers = these_get_answers($dossier->id);
    $questions = these_get_questions(these_settings_scope($these));
    foreach (array_diff(array_keys($answers), array_keys($questions)) as $questionid) {
        if ($question = $DB->get_record('these_question', ['id' => $questionid])) {
            $questions[$questionid] = $question;
        }
    }
    if (!$questions) {
        return '';
    }
    $rows = [];
    foreach ($questions as $question) {
        $answer = $answers[(int) $question->id] ?? '';
        $rows[format_string($question->label)] = $answer === '' || $answer === []
            ? html_writer::span(get_string('questionnaireunanswered', 'mod_these'), 'text-muted')
            : format_text(these_answer_text($answer), FORMAT_PLAIN);
    }
    return these_render_detail_section(get_string('questionnaire', 'mod_these'), $rows);
}

// Signature enregistrée et agent DEVE signataire.

/**
 * Signature enregistrée d'un utilisateur (image PNG), reprise à chaque signature sans nouveau tracé.
 *
 * @param int $userid
 * @return string|null Contenu PNG, null si l'utilisateur n'en a pas.
 */
function these_get_saved_signature($userid) {
    if (empty($userid)) {
        return null;
    }
    $context = context_user::instance($userid, IGNORE_MISSING);
    if (!$context) {
        return null;
    }
    $file = get_file_storage()->get_file($context->id, 'mod_these', 'savedsignature', 0, '/', 'signature.png');
    return $file ? $file->get_content() : null;
}

/**
 * Enregistre (ou remplace) la signature d'un utilisateur.
 *
 * @param int $userid
 * @param string $png
 * @return void
 */
function these_save_signature_image($userid, $png) {
    $context = context_user::instance($userid);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_these', 'savedsignature', 0);
    $fs->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'mod_these',
        'filearea' => 'savedsignature',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => 'signature.png',
        'userid' => $userid,
    ], $png);
}

/**
 * Supprime la signature enregistrée d'un utilisateur.
 *
 * @param int $userid
 * @return void
 */
function these_delete_saved_signature($userid) {
    $context = context_user::instance($userid, IGNORE_MISSING);
    if ($context) {
        get_file_storage()->delete_area_files($context->id, 'mod_these', 'savedsignature', 0);
    }
}

/**
 * Emplacements signés par l'agent responsable de la DEVE (visa du sujet, agrément administratif,
 * représentation de la direction générale).
 *
 * @return string[]
 */
function these_agent_slots() {
    return ['deve', 'dgrep'];
}

/**
 * Compte de l'agent responsable de la DEVE, désigné dans les réglages du site (identifiant ou
 * adresse de courriel). Sans agent désigné, la personne de la DEVE qui valide signe elle-même.
 *
 * @return stdClass|null
 */
function these_deve_agent() {
    global $DB;

    $value = trim((string) get_config('mod_these', 'deveagent'));
    if ($value === '') {
        return null;
    }
    $field = strpos($value, '@') !== false ? 'email' : 'username';
    $users = $DB->get_records_select(
        'user',
        "LOWER($field) = LOWER(?) AND deleted = 0 AND suspended = 0",
        [$value],
        'id ASC',
        '*',
        0,
        1
    );
    return $users ? reset($users) : null;
}

/**
 * Indique si une personne appose elle-même une signature à un emplacement lors d'une validation :
 * les emplacements de l'agent DEVE reviennent à l'agent quand il est désigné.
 *
 * @param string $slot
 * @param int $userid
 * @return bool
 */
function these_signs_slot_at_validation($slot, $userid) {
    if (!in_array($slot, these_agent_slots(), true)) {
        return true;
    }
    $agent = these_deve_agent();
    return $agent === null || (int) $agent->id === (int) $userid;
}

/**
 * Indique si un document attend la signature de l'agent DEVE : une fois validée par l'équipe de la
 * DEVE l'étape qui le remplit (enregistrement du sujet, validation du dossier de soutenance).
 *
 * @param stdClass $dossier
 * @param string $doctype
 * @return bool
 */
function these_agent_document_due(stdClass $dossier, $doctype) {
    $after = ['subject' => THESE_STATUS_SUBJECT_REGISTERED, 'agreement' => THESE_STATUS_DEFENSE_VALIDATED];
    return isset($after[$doctype]) && these_document_available($dossier, $doctype)
        && these_rank_reached($dossier, $after[$doctype]);
}

/**
 * Demande à l'agent DEVE de signer les documents d'un dossier qui attendent sa signature : les
 * signatures en attente lui sont attribuées et il est prévenu par courriel, avec le lien vers
 * « Mes signatures ».
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string[] $doctypes
 * @return int Nombre de documents en attente de sa signature.
 */
function these_request_agent_signatures(stdClass $these, stdClass $cm, stdClass $dossier, array $doctypes) {
    global $DB;

    $agent = these_deve_agent();
    if ($agent === null) {
        return 0;
    }
    $now = time();
    $labels = [];
    foreach (these_agent_slots() as $slot) {
        foreach (these_pending_documents_for_slot($dossier, $slot, $doctypes) as $doctype) {
            if (!these_agent_document_due($dossier, $doctype)) {
                continue;
            }
            $signature = $DB->get_record('these_signature', ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]);
            $record = (object) [
                'dossierid' => $dossier->id,
                'doctype' => $doctype,
                'slot' => $slot,
                'userid' => (int) $agent->id,
                'signername' => fullname($agent),
                'signeremail' => $agent->email,
                'token' => null,
                'requesttime' => $now,
                'timesigned' => 0,
                'contenthash' => null,
            ];
            if ($signature) {
                $record->id = $signature->id;
                $DB->update_record('these_signature', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('these_signature', $record);
            }
            $labels[] = these_document_label($doctype) . ' (' . these_slot_label($slot) . ')';
        }
    }
    if (!$labels) {
        return 0;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['name'] = fullname($agent);
    $vars['document'] = implode(', ', $labels);
    $vars['url'] = (new moodle_url('/mod/these/mysignatures.php'))->out(false);
    these_send_email($these, 'agentsignature', [$agent], $vars);
    these_log(
        $dossier->id,
        0,
        'signaturerequested',
        $dossier->status,
        $dossier->status,
        get_string('agentdeve', 'mod_these') . ' (' . fullname($agent) . ') — ' . implode(', ', $labels)
    );
    return count($labels);
}

/**
 * Signatures qui attendent un utilisateur connecté : celles demandées à l'agent DEVE, et celles
 * des signataires invités par courriel (président du jury, directeur extérieur...) dont l'adresse
 * est celle de son compte.
 *
 * @param stdClass $user
 * @return stdClass[] Signatures en attente, avec dossier, activité et module de cours.
 */
function these_get_user_pending_signatures(stdClass $user) {
    global $DB;

    $email = core_text::strtolower(trim((string) $user->email));
    $params = ['userid' => $user->id, 'email1' => $email, 'email2' => $email];
    $records = $DB->get_records_sql(
        "SELECT s.*
           FROM {these_signature} s
           JOIN {these_dossier} d ON d.id = s.dossierid
          WHERE s.timesigned = 0
            AND d.status <> " . THESE_STATUS_CANCELLED . "
            AND ((s.token IS NULL AND s.userid = :userid)
                 OR (s.token IS NOT NULL AND :email1 <> '' AND LOWER(s.signeremail) = :email2))
       ORDER BY s.dossierid, s.id",
        $params
    );
    $pending = [];
    foreach ($records as $signature) {
        $dossier = $DB->get_record('these_dossier', ['id' => $signature->dossierid]);
        $cm = $dossier ? get_coursemodule_from_instance('these', $dossier->theseid, 0, false, IGNORE_MISSING) : null;
        if (!$cm || !these_document_available($dossier, $signature->doctype)) {
            continue;
        }
        $signature->dossier = $dossier;
        $signature->cm = $cm;
        $signature->these = $DB->get_record('these', ['id' => $dossier->theseid]);
        $pending[$signature->id] = $signature;
    }
    return $pending;
}

// Changement de sujet.

/**
 * Indique si l'étudiant peut changer de sujet : dossier actif (sujet enregistré, soutenance pas
 * encore déposée, y compris après un ajournement).
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_subject_changeable(stdClass $dossier) {
    return (int) $dossier->status === THESE_STATUS_ACTIVE;
}

/**
 * Sujets précédents d'un dossier, du plus récent au plus ancien.
 *
 * @param int $dossierid
 * @return stdClass[]
 */
function these_get_subject_history($dossierid) {
    global $DB;
    return $DB->get_records('these_subjecthistory', ['dossierid' => $dossierid], 'timecreated DESC, id DESC');
}

/**
 * Change le sujet d'un dossier actif. L'ancien sujet est archivé (historique et PDF du dépôt de
 * sujet signé, zone subjectarchive) ; l'ancien directeur, la DEVE et la bibliothèque sont
 * prévenus ; le nouveau sujet, signé par l'étudiant, repasse toute la phase 1 : directeur
 * (éventuellement nouveau), DEVE, bibliothèque.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param stdClass $data Nouveau sujet (champs du formulaire de sujet).
 * @param int $userid
 * @param string $reason Motif du changement.
 * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
 * @return stdClass Le dossier à jour.
 */
function these_change_subject(
    stdClass $these,
    stdClass $cm,
    stdClass $dossier,
    stdClass $data,
    $userid,
    $reason,
    ?stdClass $signature = null
) {
    global $DB;

    if (!these_subject_changeable($dossier)) {
        throw new coding_exception('Changement de sujet impossible au statut ' . $dossier->status);
    }
    $context = context_module::instance($cm->id);
    $now = time();
    $olddirector = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid, 'deleted' => 0]) : null;

    // Trace de l'ancien sujet.
    $history = (object) [
        'dossierid' => $dossier->id,
        'title' => (string) $dossier->title,
        'titleen' => (string) $dossier->titleen,
        'summary' => (string) $dossier->summary,
        'keywords' => (string) $dossier->keywords,
        'studyyear' => (string) ($dossier->studyyear ?? ''),
        'confidential' => (int) ($dossier->confidential ?? 0),
        'directorid' => (int) $dossier->directorid,
        'directorname' => !empty($dossier->externaldirector) ? (string) $dossier->extdirectorname
            : ($olddirector ? fullname($olddirector) : ''),
        'codirectorname' => (string) $dossier->codirectorname,
        'libraryref' => (string) $dossier->libraryref,
        'reason' => trim((string) $reason),
        'changedby' => (int) $userid,
        'timecreated' => $now,
    ];
    $history->id = $DB->insert_record('these_subjecthistory', $history);
    try {
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => 'subjectarchive',
            'itemid' => $dossier->id,
            'filepath' => '/',
            'filename' => 'sujet-' . userdate($now, '%Y-%m-%d') . '-' . $history->id . '.pdf',
        ], these_signed_document_pdf($these, $context, $dossier, 'subject'));
    } catch (\Throwable $e) {
        debugging('Archivage du dépôt de sujet impossible : ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // Le dépôt de sujet sera signé de nouveau par tous.
    foreach (these_get_signatures($dossier->id) as $existing) {
        if ($existing->doctype === 'subject') {
            these_delete_signature($context, $existing);
        }
    }

    $oldtitle = (string) $dossier->title;
    $fields = [];
    foreach (
        ['title', 'titleen', 'summary', 'keywords', 'codirectorname', 'studyyear', 'directortitle',
            'extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field
    ) {
        $fields[$field] = (string) these_submitted_value($data, $dossier, $field);
    }
    foreach (['directorid', 'confidential', 'externaldirector'] as $field) {
        $fields[$field] = (int) these_submitted_value($data, $dossier, $field);
    }
    if (property_exists($data, 'commitguide')) {
        $fields['commitments'] = (!empty($data->commitguide) ? THESE_COMMIT_GUIDE : 0)
            | (!empty($data->commitduplicate) ? THESE_COMMIT_DUPLICATE : 0)
            | (!empty($data->commitethics) ? THESE_COMMIT_ETHICS : 0);
    }
    if (empty($fields['externaldirector'])) {
        foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
            $fields[$field] = '';
        }
    }
    $dossier = these_set_status(
        $dossier,
        THESE_STATUS_SUBJECT_SUBMITTED,
        $userid,
        'subjectchanged',
        get_string('subjectchangelog', 'mod_these', (object) ['old' => $oldtitle, 'reason' => trim((string) $reason)]),
        array_merge($fields, these_cleared_return_fields())
    );
    if ($signature !== null) {
        these_sign_document($context, $dossier, 'subject', 'student', $userid, $signature->name, $signature->png);
    }
    if (!empty($dossier->externaldirector)) {
        these_request_external_signatures($these, $cm, $dossier, 'subject');
    }

    // Avertissement de l'ancien directeur, de la DEVE et de la bibliothèque.
    $recipients = [];
    if ($olddirector) {
        $recipients[core_text::strtolower($olddirector->email)] = $olddirector;
    }
    foreach (['deve', 'library'] as $service) {
        foreach (these_get_service_recipients($these, $context, $service) as $recipient) {
            $recipients[core_text::strtolower((string) $recipient->email)] = $recipient;
        }
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['oldtitle'] = format_string($oldtitle);
    $vars['reason'] = trim((string) $reason);
    these_send_email($these, 'subjectchanged', array_values($recipients), $vars);
    // Le (nouveau) directeur est attendu pour valider le nouveau sujet.
    these_notify_actor($these, $cm, $dossier);
    return $dossier;
}

/**
 * Sujets précédents d'un dossier, pour l'affichage : date, titre, directeur, motif et PDF archivé.
 *
 * @param context $context
 * @param stdClass $dossier
 * @return string HTML ('' sans changement de sujet)
 */
function these_render_subject_history(context $context, stdClass $dossier) {
    $history = these_get_subject_history($dossier->id);
    if (!$history) {
        return '';
    }
    $files = [];
    foreach (these_get_files($context, 'subjectarchive', $dossier->id) as $file) {
        if (preg_match('/-(\d+)\.pdf$/', $file->get_filename(), $match)) {
            $files[(int) $match[1]] = $file;
        }
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-subjecthistory';
    $table->head = [get_string('date'), get_string('subjecttitle', 'mod_these'), get_string('director', 'mod_these'),
        get_string('subjectchangereason', 'mod_these'), get_string('subjectarchive', 'mod_these')];
    foreach ($history as $entry) {
        $file = $files[(int) $entry->id] ?? null;
        $table->data[] = [
            userdate($entry->timecreated, get_string('strftimedate', 'langconfig')),
            format_string($entry->title),
            s((string) $entry->directorname),
            format_text((string) $entry->reason, FORMAT_PLAIN),
            $file ? html_writer::link(moodle_url::make_pluginfile_url(
                $context->id,
                'mod_these',
                'subjectarchive',
                $dossier->id,
                '/',
                $file->get_filename(),
                true
            ), $file->get_filename()) : '-',
        ];
    }
    return html_writer::tag('h4', get_string('subjecthistory', 'mod_these'), ['class' => 'mt-4']) . html_writer::table($table);
}
