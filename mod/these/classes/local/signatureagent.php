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
     * Compte de l'agent responsable de la DEVE, désigné dans les réglages du site (identifiant ou
     * adresse de courriel). Sans agent désigné, la personne de la DEVE qui valide signe elle-même.
     *
     * @return stdClass|null
     */
    public static function deve_agent() {
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
    public static function signs_slot_at_validation($slot, $userid) {
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
    public static function agent_document_due(stdClass $dossier, $doctype) {
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
    public static function request_agent_signatures(stdClass $these, stdClass $cm, stdClass $dossier, array $doctypes) {
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
                $signature = $DB->get_record(
                    'these_signature',
                    ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]
                );
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
        these_send_email($these, 'agentsignature', [$agent], $vars, '', '', $dossier);
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
    public static function get_user_pending_signatures(stdClass $user) {
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
}
