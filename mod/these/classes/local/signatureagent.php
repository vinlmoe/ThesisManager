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
        if (!in_array($slot, these_agent_slots(), true)) {
            return true;
        }
        $agents = these_deve_agents($these);
        return !$agents || isset($agents[(int) $userid]);
    }

    /**
     * Indique si un document attend la signature de l'agent DEVE : une fois validée par l'équipe de la
     * DEVE l'étape qui le remplit (enregistrement du sujet, validation du dossier de soutenance).
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return bool
     */
    public static function agent_document_due(stdClass $dossier, $doctype) {
        $after = ['subject' => THESE_STATUS_SUBJECT_REGISTERED, 'agreement' => THESE_STATUS_DEFENSE_VALIDATED];
        return isset($after[$doctype]) && these_document_available($dossier, $doctype)
            && these_rank_reached($dossier, $after[$doctype]);
    }

    /**
     * Demande aux agents DEVE de signer les documents d'un dossier qui attendent leur signature :
     * chaque agent est prévenu par courriel, avec le lien vers « Mes signatures », et le premier qui
     * signe appose la signature. Avec un seul agent, les signatures en attente lui sont attribuées ;
     * avec plusieurs, elles restent ouvertes à chacun d'eux.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param string[] $doctypes
     * @return int Nombre de documents en attente de sa signature.
     */
    public static function request_agent_signatures(stdClass $these, stdClass $cm, stdClass $dossier, array $doctypes) {
        global $DB;

        $agents = these_deve_agents($these);
        if (!$agents) {
            return 0;
        }
        $single = count($agents) === 1 ? reset($agents) : null;
        $now = time();
        $labels = [];
        foreach (these_agent_slots() as $slot) {
            foreach (these_pending_documents_for_slot($dossier, $slot, $doctypes) as $doctype) {
                if (!these_agent_document_due($dossier, $doctype)) {
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
        $vars['document'] = implode(', ', $labels);
        $vars['url'] = (new moodle_url('/mod/these/mysignatures.php'))->out(false);
        foreach ($agents as $agent) {
            $vars['name'] = fullname($agent);
            these_send_email($these, 'agentsignature', [$agent], $vars, '', '', $dossier);
        }
        $names = implode(', ', array_map('fullname', $agents));
        these_log(
            $dossier->id,
            0,
            'signaturerequested',
            $dossier->status,
            $dossier->status,
            get_string('agentdeve', 'mod_these') . ' (' . $names . ') — ' . implode(', ', $labels)
        );
        return count($labels);
    }

    /**
     * Signatures qui attendent un utilisateur connecté : pour un agent DEVE désigné, celles qui
     * attendent un agent, quel qu'il soit ; et celles des signataires invités par courriel (président
     * du jury, directeur extérieur...) dont l'adresse est celle de son compte.
     *
     * @param stdClass $user
     * @return stdClass[] Signatures en attente, avec dossier, activité et module de cours.
     */
    public static function get_user_pending_signatures(stdClass $user) {
        global $DB;

        // Les agents sont désignés par activité : les signatures d'agent sont toutes lues, puis
        // gardées pour les activités dont l'utilisateur est agent.
        $agentslots = these_agent_slots();
        $email = core_text::strtolower(trim((string) $user->email));
        $params = ['userid' => $user->id, 'email1' => $email, 'email2' => $email];
        [$slotsql, $slotparams] = $DB->get_in_or_equal($agentslots, SQL_PARAMS_NAMED, 'agentslot');
        $agentsql = " OR (s.token IS NULL AND s.slot $slotsql)";
        $params += $slotparams;
        $records = $DB->get_records_sql(
            "SELECT s.*
               FROM {these_signature} s
               JOIN {these_dossier} d ON d.id = s.dossierid
              WHERE s.timesigned = 0
                AND d.status <> " . THESE_STATUS_CANCELLED . "
                AND ((s.token IS NULL AND s.userid = :userid)
                     OR (s.token IS NOT NULL AND :email1 <> '' AND LOWER(s.signeremail) = :email2)$agentsql)
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
            $these = $DB->get_record('these', ['id' => $dossier->theseid]);
            // Signature d'agent : réservée aux agents désignés de l'activité (sinon à la personne qui l'a
            // validée), une fois l'étape validée par l'équipe.
            if ($signature->token === null && in_array($signature->slot, $agentslots, true)) {
                $agents = these_deve_agents($these);
                $allowed = $agents ? isset($agents[(int) $user->id]) : (int) $signature->userid === (int) $user->id;
                if (!$allowed || !these_agent_document_due($dossier, $signature->doctype)) {
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
