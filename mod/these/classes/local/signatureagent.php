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

use context_user;
use core_text;
use moodle_url;
use stdClass;

/**
 * Signature enregistrée et agent DEVE signataire.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signatureagent {
    /**
     * Signature enregistrée d'un utilisateur (image PNG), reprise à chaque signature sans nouveau tracé.
     *
     * @param int $userid
     * @return string|null Contenu PNG, null si l'utilisateur n'en a pas.
     */
    public static function get_saved_signature($userid) {
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
    public static function save_signature_image($userid, $png) {
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
    public static function delete_saved_signature($userid) {
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
    public static function agent_slots() {
        return ['deve', 'dgrep'];
    }

    /**
     * Indique si les agents DEVE signent pour la direction générale : elle ne signe jamais elle-même,
     * ses documents sont toujours signés par délégation, par les agents DEVE quand il y en a de
     * désignés (sinon sur place ou par scan, auprès de la DEVE).
     *
     * @param stdClass $these
     * @return bool
     */
    public static function signs_dg_by_delegation(stdClass $these) {
        return (bool) self::deve_agents($these);
    }

    /**
     * Emplacements signés par des agents d'une activité : ceux des agents DEVE (voir agent_slots()),
     * celui de la direction générale, par délégation, et ceux de l'université qui ont des délégataires
     * internes (voir university::delegated_slots()).
     *
     * @param stdClass $these
     * @return string[]
     */
    public static function slots_for(stdClass $these) {
        return array_merge(
            self::agent_slots(),
            self::signs_dg_by_delegation($these) ? ['dg'] : [],
            university::delegated_slots($these)
        );
    }

    /**
     * Comptes qui signent un emplacement d'agent : les délégataires internes pour un signataire de
     * l'université, les agents DEVE pour les autres.
     *
     * @param stdClass $these
     * @param string $slot
     * @return stdClass[] Comptes, indexés par identifiant.
     */
    public static function agents_for_slot(stdClass $these, $slot) {
        if (in_array($slot, prerequisites::UNIVERSITY_SLOTS, true)) {
            return university::agents($these, $slot);
        }
        return self::deve_agents($these);
    }

    /**
     * Indique si un emplacement d'agent est signé par délégation : direction générale, université.
     *
     * @param string $slot
     * @return bool
     */
    public static function delegated_slot($slot) {
        return $slot === 'dg' || in_array($slot, prerequisites::UNIVERSITY_SLOTS, true);
    }

    /**
     * « validé par … le … » : compte de l'équipe DEVE qui a validé l'étape dont un agent signe les
     * documents, et date de cette validation.
     *
     * @param stdClass $signature Signature (validatedby, validatedtime).
     * @return string Vide sans validateur connu.
     */
    public static function validator_label(stdClass $signature) {
        $validator = !empty($signature->validatedby) ? \core_user::get_user($signature->validatedby) : null;
        if (!$validator) {
            return '';
        }
        if (empty($signature->validatedtime)) {
            return get_string('validatedbyname', 'mod_these', fullname($validator));
        }
        return get_string('validatedbynameon', 'mod_these', (object) [
            'name' => fullname($validator),
            'date' => userdate($signature->validatedtime, get_string('strftimedatetimeshort', 'langconfig')),
        ]);
    }

    /**
     * Libellé d'un document à signer par un agent : document, emplacement, et « par délégation »
     * pour la direction générale et l'université.
     *
     * @param string $doctype
     * @param string $slot
     * @return string
     */
    public static function document_label($doctype, $slot) {
        return signatures::document_label($doctype) . ' (' . signatures::slot_label($slot)
            . (self::delegated_slot($slot) ? ', ' . get_string('signeddelegated', 'mod_these') : '') . ')';
    }

    /**
     * Comptes des agents DEVE signataires, désignés dans les paramètres de l'activité (ceux de
     * l'activité « Suivi des thèses » qui la suit) : identifiants ou adresses de courriel séparés par
     * des virgules, des points-virgules ou des retours à la ligne. N'importe lequel d'entre eux signe
     * les documents qui attendent un agent. Sans agent désigné, la personne de la DEVE qui valide
     * signe elle-même.
     *
     * @param stdClass $these
     * @return stdClass[] Comptes actifs, indexés par identifiant, dans l'ordre du paramètre.
     */
    public static function deve_agents(stdClass $these) {
        return scopesettings::listed_users(scopesettings::for_these($these)['deveagent']);
    }

    /**
     * Indique si une personne appose elle-même une signature à un emplacement lors d'une validation :
     * les emplacements de l'agent DEVE reviennent aux agents quand il y en a de désignés.
     *
     * @param string $slot
     * @param int $userid
     * @param stdClass $these
     * @return bool
     */
    public static function signs_slot_at_validation($slot, $userid, stdClass $these) {
        if (!in_array($slot, self::agent_slots(), true)) {
            return true;
        }
        $agents = self::deve_agents($these);
        return !$agents || isset($agents[(int) $userid]);
    }

    /**
     * Indique si un document attend la signature de l'agent DEVE : une fois validée par l'équipe de la
     * DEVE l'étape qui le remplit (enregistrement du sujet, validation du dossier de soutenance). Les
     * documents signés par la direction générale (par délégation) sont demandés, comme aux autres
     * signataires, à la validation du dossier de soutenance.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return bool
     */
    public static function agent_document_due(stdClass $dossier, $doctype) {
        $after = [
            'subject' => THESE_STATUS_SUBJECT_REGISTERED,
            'agreement' => THESE_STATUS_DEFENSE_VALIDATED,
            'signaletique' => THESE_STATUS_DEFENSE_VALIDATED,
            'contract' => THESE_STATUS_DEFENSE_VALIDATED,
            'conclusions' => THESE_STATUS_DEFENSE_VALIDATED,
        ];
        return isset($after[$doctype]) && signatures::document_available($dossier, $doctype)
            && rendering::rank_reached($dossier, $after[$doctype]);
    }

    /**
     * Demande aux agents de signer les documents d'un dossier qui attendent leur signature : agents
     * DEVE, y compris pour la direction générale quand ils la signent par délégation, et délégataires
     * internes de l'université (voir agents_for_slot()). Chaque agent est prévenu par courriel des
     * documents qu'il signe, avec le lien vers « Mes signatures », et le premier qui signe appose la
     * signature. Avec un seul agent pour un emplacement, les signatures en attente lui sont
     * attribuées ; avec plusieurs, elles restent ouvertes à chacun d'eux. Sert aussi de relance : la date de la demande repart de
     * zéro.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param string[] $doctypes
     * @param int $validatorid Compte de l'équipe DEVE qui vient de valider l'étape (documents validés),
     *                         0 pour une relance : le compte déjà inscrit est gardé.
     * @return int Nombre de signatures demandées.
     */
    public static function request_agent_signatures(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        array $doctypes,
        $validatorid = 0
    ) {
        global $DB;

        $now = time();
        $labels = [];
        $slotagents = [];
        foreach (self::slots_for($these) as $slot) {
            $agents = self::agents_for_slot($these, $slot);
            if (!$agents) {
                continue;
            }
            $single = count($agents) === 1 ? reset($agents) : null;
            foreach (signaturetokens::pending_documents_for_slot($dossier, $slot, $doctypes) as $doctype) {
                if (!self::agent_document_due($dossier, $doctype)) {
                    continue;
                }
                $signature = $DB->get_record(
                    'these_signature',
                    ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]
                );
                $record = (object) [
                    'dossierid' => $dossier->id,
                    'doctype' => $doctype,
                    'slot' => $slot,
                    'userid' => $single ? (int) $single->id : 0,
                    'signername' => $single ? fullname($single) : '',
                    'signeremail' => $single ? $single->email : '',
                    'token' => null,
                    'requesttime' => $now,
                    'timesigned' => 0,
                    'contenthash' => null,
                    'validatedby' => (int) $validatorid ?: (int) ($signature->validatedby ?? 0),
                    'validatedtime' => $validatorid ? $now : (int) ($signature->validatedtime ?? 0),
                ];
                if ($signature) {
                    $record->id = $signature->id;
                    $DB->update_record('these_signature', $record);
                } else {
                    $record->timecreated = $now;
                    $DB->insert_record('these_signature', $record);
                }
                $labels[$slot][] = self::document_label($doctype, $slot);
                $slotagents[$slot] = $agents;
            }
        }
        if (!$labels) {
            return 0;
        }
        // Validateur : compte qui a validé les documents, et quand, donnés aux agents avec la demande.
        $validated = $validatorid
            ? self::validator_label((object) ['validatedby' => $validatorid, 'validatedtime' => $now]) : '';
        // Un courriel par agent, pour les documents des emplacements qu'il signe.
        $recipients = [];
        foreach ($slotagents as $slot => $agents) {
            foreach ($agents as $agent) {
                $recipients[$agent->id]['user'] = $agent;
                $recipients[$agent->id]['labels'] = array_merge($recipients[$agent->id]['labels'] ?? [], $labels[$slot]);
            }
        }
        $vars = notifications::email_base_vars($these, $cm, $dossier);
        $vars['url'] = (new moodle_url('/mod/these/mysignatures.php'))->out(false);
        foreach ($recipients as $recipient) {
            $vars['name'] = fullname($recipient['user']);
            $vars['document'] = implode(', ', $recipient['labels']) . ($validated !== '' ? ' — ' . $validated : '');
            notifications::send_email($these, 'agentsignature', [$recipient['user']], $vars, '', '', $dossier);
        }
        foreach ($slotagents as $slot => $agents) {
            $actor = in_array($slot, prerequisites::UNIVERSITY_SLOTS, true)
                ? get_string('agentuniversity', 'mod_these', signatures::slot_label($slot)) : get_string('agentdeve', 'mod_these');
            dossiers::log(
                $dossier->id,
                0,
                'signaturerequested',
                $dossier->status,
                $dossier->status,
                $actor . ' (' . implode(', ', array_map('fullname', $agents)) . ') — ' . implode(', ', $labels[$slot])
            );
        }
        return count(array_merge(...array_values($labels)));
    }

    /**
     * Signatures qui attendent un utilisateur connecté : celles qui lui sont attribuées par son compte
     * et, pour un agent DEVE désigné, celles qui attendent un agent, quel qu'il soit (direction
     * générale par délégation comprise).
     *
     * Les signatures des signataires invités par courriel (président du jury, directeur extérieur...)
     * ne se signent que depuis le lien à jeton reçu (sign.php) : l'adresse d'un compte n'étant pas
     * vérifiée par le module, l'égalité d'adresse ne suffit pas à les attribuer.
     *
     * @param stdClass $user
     * @return stdClass[] Signatures en attente, avec dossier, activité et module de cours.
     */
    public static function get_user_pending_signatures(stdClass $user) {
        global $DB;

        // Les agents sont désignés par activité : les signatures d'agent sont toutes lues, puis
        // gardées pour les activités dont l'utilisateur est agent.
        $agentslots = array_merge(self::agent_slots(), ['dg'], prerequisites::UNIVERSITY_SLOTS);
        $params = ['userid' => $user->id];
        [$slotsql, $slotparams] = $DB->get_in_or_equal($agentslots, SQL_PARAMS_NAMED, 'agentslot');
        $agentsql = " OR (s.token IS NULL AND s.slot $slotsql)";
        $params += $slotparams;
        $records = $DB->get_records_sql(
            "SELECT s.*
               FROM {these_signature} s
               JOIN {these_dossier} d ON d.id = s.dossierid
              WHERE s.timesigned = 0
                AND d.status <> " . THESE_STATUS_CANCELLED . "
                AND ((s.token IS NULL AND s.userid = :userid)$agentsql)
           ORDER BY s.dossierid, s.id",
            $params
        );
        $pending = [];
        foreach ($records as $signature) {
            $dossier = $DB->get_record('these_dossier', ['id' => $signature->dossierid]);
            $cm = $dossier ? get_coursemodule_from_instance('these', $dossier->theseid, 0, false, IGNORE_MISSING) : null;
            if (!$cm || !signatures::document_available($dossier, $signature->doctype)) {
                continue;
            }
            $these = $DB->get_record('these', ['id' => $dossier->theseid]);
            // Signature d'agent : réservée aux agents désignés de l'activité (sinon à la personne qui l'a
            // validée), une fois l'étape validée par l'équipe. Direction générale : seulement tant que
            // les agents la signent par délégation.
            if ($signature->token === null && in_array($signature->slot, $agentslots, true)) {
                if (!in_array($signature->slot, self::slots_for($these), true)) {
                    continue;
                }
                $agents = self::agents_for_slot($these, $signature->slot);
                $allowed = $agents ? isset($agents[(int) $user->id]) : (int) $signature->userid === (int) $user->id;
                if (!$allowed || !self::agent_document_due($dossier, $signature->doctype)) {
                    continue;
                }
            }
            $signature->dossier = $dossier;
            $signature->cm = $cm;
            $signature->these = $these;
            $pending[$signature->id] = $signature;
        }
        return $pending;
    }
}
