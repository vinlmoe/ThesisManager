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

/** Durée de validité d'un lien à jeton (signature sans compte, accès du jury), en secondes. */
define('THESE_TOKEN_LIFETIME', 60 * DAYSECS);

/**
 * Nouveau jeton pour un lien envoyé par courriel. Seule son empreinte est conservée en base (voir
 * these_token_hash()) : le jeton lui-même ne figure que dans le courriel.
 *
 * @return string
 */
function these_new_token() {
    return random_string(48);
}

/**
 * Empreinte d'un jeton, seule conservée en base : une copie de la base ne donne accès à aucun lien.
 *
 * @param string $token
 * @return string SHA-256 en hexadécimal.
 */
function these_token_hash($token) {
    return hash('sha256', (string) $token);
}
