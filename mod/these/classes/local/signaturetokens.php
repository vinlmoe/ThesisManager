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

use moodle_url;
use stdClass;

/**
 * Demandes de signature aux signataires sans compte : documents en attente d'un signataire,
 * invitations par lien à jeton, relances et recherche des signatures par jeton.
 *
 * Les fonctions these_*() correspondantes de locallib/signatures.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signaturetokens {
    /**
     * Documents en attente de la signature d'un signataire sans compte, dans un dossier.
     *
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes Documents à considérer (tous par défaut).
     * @return string[]
     */
    public static function pending_documents_for_slot(stdClass $dossier, $slot, ?array $doctypes = null) {
        $signatures = signatures::get_signatures($dossier->id);
        $pending = [];
        foreach ($doctypes ?? array_keys(signatures::document_definitions()) as $doctype) {
            if (!in_array($slot, signatures::document_slots($dossier, $doctype), true)) {
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
     * Documents en attente d'un signataire sans compte dont la demande de signature a déjà été émise
     * (lien envoyé, ou signature attendue sur place) : ceux qu'une relance peut redemander. Les
     * documents dont la demande n'a pas encore été émise par le circuit (voir these_review_steps())
     * n'en font pas partie : le directeur extérieur, par exemple, n'est pas relancé avant que le
     * directeur interne ait validé le sujet.
     *
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes Documents à considérer (tous par défaut).
     * @return string[]
     */
    public static function requested_documents_for_slot(stdClass $dossier, $slot, ?array $doctypes = null) {
        $signatures = signatures::get_signatures($dossier->id);
        return array_values(array_filter(
            self::pending_documents_for_slot($dossier, $slot, $doctypes),
            function ($doctype) use ($signatures, $slot) {
                return !empty($signatures[$doctype . ':' . $slot]->requesttime);
            }
        ));
    }

    /**
     * Invite un signataire sans compte à signer, en une seule fois, les documents qui l'attendent :
     * chaque emplacement reçoit le même nouveau jeton, et le lien de signature est envoyé au
     * signataire s'il a une adresse. Sans adresse, les signatures restent en attente et peuvent être
     * recueillies sur place par la DEVE (signatures.php). La direction générale n'est jamais invitée :
     * ses documents sont signés par délégation, demandés aux agents DEVE quand il y en a (voir
     * signatureagent::request_agent_signatures()). L'université non plus quand elle signe sur papier
     * ou par ses délégataires internes (voir university::sends_link()).
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes Documents concernés (tous ceux en attente par défaut).
     * @return string|null Jeton, ou null si aucun document n'attend ce signataire.
     */
    public static function request_signature(stdClass $these, stdClass $cm, stdClass $dossier, $slot, ?array $doctypes = null) {
        global $DB;

        if (($slot === 'dg' && signatureagent::signs_dg_by_delegation($these)) || !university::sends_link($these, $slot)) {
            return null;
        }
        $doctypes = self::pending_documents_for_slot($dossier, $slot, $doctypes);
        if (!$doctypes) {
            return null;
        }
        $signer = signatures::external_signers($dossier)[$slot];
        $token = rights::new_token();
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
                'token' => rights::token_hash($token),
                'requesttime' => $now,
                'timesigned' => 0,
                'contenthash' => null,
                'templatehash' => null,
            ];
            if ($signature) {
                $record->id = $signature->id;
                $DB->update_record('these_signature', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('these_signature', $record);
            }
        }

        $labels = implode(', ', array_map([signatures::class, 'document_label'], $doctypes));
        if ($signer['email'] !== '' && validate_email($signer['email'])) {
            $vars = notifications::email_base_vars($these, $cm, $dossier);
            $vars['name'] = $signer['name'];
            $vars['document'] = $labels;
            $vars['url'] = (new moodle_url('/mod/these/sign.php', ['token' => $token]))->out(false);
            notifications::send_email(
                $these,
                'signaturerequest',
                [notifications::make_external_user($signer['email'], $signer['name'])],
                $vars,
                '',
                '',
                $dossier
            );
        }
        dossiers::log(
            $dossier->id,
            0,
            'signaturerequested',
            $dossier->status,
            $dossier->status,
            signatures::slot_label($slot) . ($signer['name'] !== '' ? ' (' . $signer['name'] . ')' : '') . ' — ' . $labels
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
    public static function request_external_signatures(stdClass $these, stdClass $cm, stdClass $dossier, $doctypes) {
        $count = 0;
        foreach (signatures::external_slots() as $slot) {
            if (self::request_signature($these, $cm, $dossier, $slot, (array) $doctypes) !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Signatures en attente correspondant à un jeton d'invitation : tous les documents qu'un même
     * signataire doit signer dans un dossier. Le lien vaut rights::TOKEN_LIFETIME à partir de
     * l'invitation ; la DEVE peut en envoyer un nouveau depuis le suivi des signatures.
     *
     * @param string $token
     * @return stdClass[] Signatures en attente, vide si le jeton est inconnu, déjà utilisé ou expiré.
     */
    public static function get_signatures_by_token($token) {
        global $DB;

        $token = clean_param($token, PARAM_ALPHANUM);
        if (strlen($token) < 32) {
            return [];
        }
        return array_values($DB->get_records_select(
            'these_signature',
            'token = :token AND timesigned = 0 AND requesttime > :since',
            ['token' => rights::token_hash($token), 'since' => time() - rights::TOKEN_LIFETIME],
            'id ASC'
        ));
    }
}
