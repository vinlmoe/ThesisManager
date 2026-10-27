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

namespace mod_these\local;

use context;
use core_text;
use stdClass;

/**
 * Droits sur les dossiers, liens à jeton et connaissance du sujet d'une thèse confidentielle.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rights {
    /**
     * Droits de l'utilisateur sur un dossier (ou sur l'activité, si aucun dossier n'est donné).
     *
     * La DEVE est reconnue à sa capacité (rôle « Enseignant » par défaut) ; la bibliothèque à la liste
     * de ses agents (voir scopesettings::library_agents()), ou à sa capacité. Un directeur de
     * thèse n'a de droits que sur les dossiers qu'il dirige ; la DEVE et la bibliothèque, sur tous
     * les dossiers de l'activité.
     *
     * @param context $context
     * @param stdClass|null $dossier
     * @param int $userid
     * @return stdClass {owner, student, director, deve, library, viewall}
     */
    public static function get_rights(context $context, ?stdClass $dossier, $userid) {
        $rights = new stdClass();
        // Un agent de la bibliothèque désigné dans les paramètres n'a que les droits de la bibliothèque,
        // même si son rôle dans le cours lui donnerait ceux de la DEVE.
        $agent = scopesettings::is_library_agent($context, $userid);
        $rights->deve = !$agent && has_capability('mod/these:managedeve', $context, $userid);
        $rights->library = $agent || has_capability('mod/these:managelibrary', $context, $userid);
        $rights->viewall = $rights->deve || $rights->library || has_capability('mod/these:viewall', $context, $userid);
        $rights->owner = $dossier !== null && (int) $dossier->userid === (int) $userid;
        $rights->student = $rights->owner && has_capability('mod/these:submit', $context, $userid);
        $rights->director = $dossier !== null && (int) $dossier->directorid === (int) $userid
            && has_capability('mod/these:direct', $context, $userid);
        // Thèse confidentielle : son sujet n'est connu que de l'étudiant, de la DEVE, de la bibliothèque, du
        // directeur et du jury (voir these_user_knows_subject()).
        $rights->knowssubject = $dossier === null || these_user_knows_subject($dossier, $context, $userid);
        return $rights;
    }

    /**
     * Exige l'accès à l'activité : la capacité de consultation, ou le rôle d'un service.
     *
     * @param context $context
     * @return stdClass Droits de l'utilisateur courant sur l'activité (voir these_get_rights()).
     */
    public static function require_view(context $context) {
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
    public static function require_service(context $context, $actor) {
        global $USER;

        $rights = these_get_rights($context, null, $USER->id);
        if (empty($rights->$actor)) {
            $capability = $actor === 'deve' ? 'mod/these:managedeve' : 'mod/these:managelibrary';
            if (scopesettings::is_library_agent($context, $USER->id)) {
                // Agent de la bibliothèque : pas de page de la DEVE, quel que soit son rôle dans le cours.
                throw new \required_capability_exception($context, $capability, 'nopermissions', '');
            }
            require_capability($capability, $context);
        }
        return $rights;
    }

    /**
     * Capacité de gestion (paramètres, promotions suivies, annuaire...) refusée aux agents de la
     * bibliothèque, quel que soit leur rôle dans le cours : ils ne voient que ce qui concerne la
     * bibliothèque.
     *
     * @param context $context
     * @param string $capability
     * @param int|null $userid Par défaut, l'utilisateur courant.
     * @return bool
     */
    public static function can_manage(context $context, $capability, $userid = null) {
        global $USER;

        $userid = $userid ?? $USER->id;
        return has_capability($capability, $context, $userid) && !scopesettings::is_library_agent($context, $userid);
    }

    /**
     * Exige une capacité de gestion (voir can_manage()).
     *
     * @param context $context
     * @param string $capability
     * @return void
     */
    public static function require_management(context $context, $capability) {
        if (!self::can_manage($context, $capability)) {
            throw new \required_capability_exception($context, $capability, 'nopermissions', '');
        }
    }

    /**
     * Indique si l'utilisateur peut consulter un dossier.
     *
     * @param stdClass $rights Voir these_get_rights().
     * @return bool
     */
    public static function can_view_dossier(stdClass $rights) {
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
    public static function can_view_filearea($filearea, stdClass $rights) {
        if (!these_can_view_dossier($rights)) {
            return false;
        }
        // Thèse confidentielle : sans connaître le sujet, seuls le manuscrit et le manuscrit corrigé
        // restent accessibles ; les autres fichiers portent le titre. Contrôle à faire avant toute autre
        // règle de zone.
        if (isset($rights->knowssubject) && !$rights->knowssubject) {
            return in_array($filearea, ['manuscript', 'corrections'], true);
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
    public static function user_can_act(stdClass $dossier, stdClass $rights) {
        $actor = these_step_actor($dossier->status);
        if ($actor === null) {
            return false;
        }
        return !empty($rights->$actor);
    }

    /**
     * Nouveau jeton pour un lien envoyé par courriel. Seule son empreinte est conservée en base (voir
     * these_token_hash()) : le jeton lui-même ne figure que dans le courriel.
     *
     * @return string
     */
    public static function new_token() {
        return random_string(48);
    }

    /**
     * Empreinte d'un jeton, seule conservée en base : une copie de la base ne donne accès à aucun lien.
     *
     * @param string $token
     * @return string SHA-256 en hexadécimal.
     */
    public static function token_hash($token) {
        return hash('sha256', (string) $token);
    }

    /**
     * Indique si un utilisateur peut connaître le sujet d'une thèse (titre, titre anglais, résumé,
     * mots-clés). Une thèse confidentielle n'est connue que de l'étudiant, de la DEVE, de la
     * bibliothèque, du directeur de thèse et des membres du jury ; pour tous les autres (autres
     * enseignants, détenteurs de mod/these:viewall sans rôle de service), le sujet est masqué partout.
     *
     * @param stdClass $dossier
     * @param context $context Contexte de l'activité du dossier.
     * @param int $userid
     * @return bool
     */
    public static function user_knows_subject(stdClass $dossier, context $context, $userid) {
        if (empty($dossier->confidential)) {
            return true;
        }
        if (!$userid) {
            return false;
        }
        if ((int) $dossier->userid === (int) $userid || (int) $dossier->directorid === (int) $userid) {
            return true;
        }
        return has_capability('mod/these:managedeve', $context, $userid)
            || scopesettings::is_library_agent($context, $userid)
            || has_capability('mod/these:managelibrary', $context, $userid)
            || these_user_in_jury($dossier->id, $userid);
    }

    /**
     * Indique si un utilisateur siège au jury d'un dossier, reconnu à son adresse ou à celle de sa fiche
     * dans l'annuaire du jury.
     *
     * @param int $dossierid
     * @param int $userid
     * @return bool
     */
    public static function user_in_jury($dossierid, $userid) {
        global $DB;

        $email = core_text::strtolower(trim((string) $DB->get_field('user', 'email', ['id' => $userid])));
        if ($email === '') {
            return false;
        }
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {these_jury} j
          LEFT JOIN {these_juror} r ON r.id = j.jurorid
              WHERE j.dossierid = :dossierid
                    AND (LOWER(j.email) = :email1 OR LOWER(r.email) = :email2)",
            ['dossierid' => $dossierid, 'email1' => $email, 'email2' => $email]
        );
    }

    /**
     * Valeur d'un champ du sujet telle qu'un utilisateur peut la voir : le texte brut, ou, pour une
     * thèse confidentielle dont il ne connaît pas le sujet, « Thèse confidentielle » à la place du
     * titre, du titre anglais, du résumé et des mots-clés.
     *
     * @param stdClass $dossier
     * @param string $field title, titleen, summary ou keywords.
     * @param context $context Contexte de l'activité du dossier.
     * @param int|null $userid Utilisateur (l'utilisateur courant par défaut).
     * @return string Texte brut, à échapper par l'appelant.
     */
    public static function subject_field(stdClass $dossier, $field, context $context, $userid = null) {
        global $USER;

        if (these_user_knows_subject($dossier, $context, $userid ?? $USER->id)) {
            return (string) $dossier->$field;
        }
        return get_string('confidentialtitle', 'mod_these');
    }
}
