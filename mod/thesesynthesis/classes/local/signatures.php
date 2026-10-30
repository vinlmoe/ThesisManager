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

namespace mod_thesesynthesis\local;

use mod_these\local\rights;
use mod_these\local\signatureagent;
use mod_these\local\signatures as thesesignatures;
use moodle_url;
use stdClass;

/**
 * Signatures en attente dans les activités liées : celles que l'utilisateur appose lui-même et,
 * pour la DEVE, celles demandées à d'autres.
 *
 * Les fonctions thesesynthesis_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signatures {
    /**
     * Signatures en attente dans les activités liées, groupées par dossier, chacune avec la page où agir :
     * - mine : documents que l'utilisateur signe lui-même (agent DEVE, bibliothèque, directeur, juré
     *   ayant un compte...), à signer depuis « Mes signatures » ;
     * - awaited : pour la DEVE, signatures demandées à d'autres et pas encore apposées, à relancer ou à
     *   recueillir sur place depuis le suivi des signatures du dossier.
     *
     * @param array $links Voir links::get_active_links().
     * @param stdClass $user
     * @return array mine, awaited : listes d'objets {dossier, link, student, knowssubject, documents
     *               (libellés), since (première demande), url, label}
     */
    public static function get_signatures(array $links, stdClass $user) {
        global $DB;

        $bycmid = [];
        foreach ($links as $link) {
            $bycmid[(int) $link->cm->id] = $link;
        }
        $mine = [];
        $mineids = [];
        $mysignatures = new moodle_url('/mod/these/mysignatures.php');
        foreach (signatureagent::get_user_pending_signatures($user) as $signature) {
            $link = $bycmid[(int) $signature->cm->id] ?? null;
            if (!$link) {
                continue;
            }
            $mineids[(int) $signature->id] = true;
            // Signature d'agent DEVE (sans jeton) : « par délégation » à l'emplacement de la direction générale.
            $label = $signature->token === null
                ? signatureagent::document_label($signature->doctype, $signature->slot)
                : thesesignatures::document_label($signature->doctype)
                    . ' (' . thesesignatures::slot_label($signature->slot) . ')';
            $validated = signatureagent::validator_label($signature);
            if ($validated !== '') {
                $label .= ' — ' . $validated;
            }
            $row = self::signature_row($mine, $signature->dossier, $link, $user->id, $signature->requesttime);
            $row->documents[] = $label;
            $row->url = $mysignatures;
            $row->label = get_string('sign', 'mod_thesesynthesis');
        }

        $awaited = [];
        foreach ($links as $link) {
            if (empty($link->rights->deve)) {
                continue;
            }
            $signatures = $DB->get_records_sql(
                "SELECT s.*
                   FROM {these_signature} s
                   JOIN {these_dossier} d ON d.id = s.dossierid
                  WHERE d.theseid = :theseid AND s.timesigned = 0
                    AND d.status <> :cancelled AND d.status <> :closed
               ORDER BY d.id, s.id",
                ['theseid' => $link->these->id, 'cancelled' => THESE_STATUS_CANCELLED, 'closed' => THESE_STATUS_CLOSED]
            );
            // Les dossiers concernés sont lus en une requête par activité.
            $dossiers = $DB->get_records_list('these_dossier', 'id', array_unique(array_column($signatures, 'dossierid')));
            foreach ($signatures as $signature) {
                if (isset($mineids[(int) $signature->id])) {
                    continue;
                }
                $dossier = $dossiers[(int) $signature->dossierid];
                if (!thesesignatures::document_available($dossier, $signature->doctype)) {
                    continue;
                }
                $row = self::signature_row($awaited, $dossier, $link, $user->id, $signature->requesttime);
                $row->documents[] = thesesignatures::document_label($signature->doctype)
                    . ' (' . thesesignatures::slot_label($signature->slot) . ')';
                $row->url = new moodle_url('/mod/these/signatures.php', ['id' => $link->cm->id, 'dossierid' => $dossier->id]);
                $row->label = get_string('managesignatures', 'mod_these');
            }
        }
        self::fill_signature_rows($mine, $user->id);
        self::fill_signature_rows($awaited, $user->id);
        return ['mine' => array_values($mine), 'awaited' => array_values($awaited)];
    }

    /**
     * Ligne d'un dossier dans une liste de signatures (voir get_signatures()), créée au premier
     * document ; la date retenue est celle de la plus ancienne demande. L'étudiant et la
     * connaissance du sujet sont renseignés ensuite, pour toutes les lignes à la fois (voir
     * fill_signature_rows()).
     *
     * @param array $groups Lignes, indexées par dossier.
     * @param stdClass $dossier
     * @param stdClass $link
     * @param int $userid
     * @param int $requesttime
     * @return stdClass La ligne du dossier.
     */
    public static function signature_row(array &$groups, stdClass $dossier, stdClass $link, $userid, $requesttime) {
        if (!isset($groups[$dossier->id])) {
            $groups[$dossier->id] = (object) [
                'dossier' => $dossier,
                'link' => $link,
                'student' => null,
                'knowssubject' => false,
                'documents' => [],
                'since' => 0,
                'url' => null,
                'label' => '',
            ];
        }
        $row = $groups[$dossier->id];
        $requesttime = (int) $requesttime;
        if ($requesttime && (!$row->since || $requesttime < $row->since)) {
            $row->since = $requesttime;
        }
        return $row;
    }

    /**
     * Renseigne l'étudiant et la connaissance du sujet des lignes d'une liste de signatures : les
     * étudiants sont lus en une requête, la connaissance du sujet calculée une fois par activité (voir
     * rights::user_knows_subjects()).
     *
     * @param array $rows Lignes (voir signature_row()), modifiées en place.
     * @param int $userid
     * @return void
     */
    public static function fill_signature_rows(array $rows, $userid) {
        if (!$rows) {
            return;
        }
        $dossiers = array_map(function ($row) {
            return $row->dossier;
        }, $rows);
        $users = dossiers::load_users($dossiers);
        $bylink = [];
        foreach ($rows as $row) {
            $bylink[(int) $row->link->cm->id][] = $row;
        }
        foreach ($bylink as $linkrows) {
            $link = $linkrows[0]->link;
            $knows = rights::user_knows_subjects(array_map(function ($row) {
                return $row->dossier;
            }, $linkrows), $link->context, $userid);
            foreach ($linkrows as $row) {
                $row->student = $users[(int) $row->dossier->userid] ?? null;
                $row->knowssubject = $knows[(int) $row->dossier->id];
            }
        }
    }
}
