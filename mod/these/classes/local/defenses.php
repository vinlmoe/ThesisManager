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

use context_module;
use stdClass;

/**
 * Dossier de soutenance (phase 2) : dépôt par l'étudiant, bordereau, rapport du directeur.
 *
 * Les fonctions these_*() correspondantes de locallib/ sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defenses {
    /**
     * Indique si le dossier de soutenance peut être déposé ou modifié par l'étudiant.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function defense_editable(stdClass $dossier) {
        return in_array((int) $dossier->status, [THESE_STATUS_ACTIVE, THESE_STATUS_DEFENSE_RETURNED], true);
    }

    /**
     * Indique si le rapport du directeur peut être déposé : tant que le directeur n'a pas validé la
     * soutenance.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function report_editable(stdClass $dossier) {
        return (int) $dossier->status === THESE_STATUS_DEFENSE_SUBMITTED;
    }

    /**
     * Enregistre l'avis du directeur sur son rapport (sans changer le statut du dossier) ; le rapport
     * lui-même est un fichier, déposé par l'appelant (zone directorreport).
     *
     * @param stdClass $dossier
     * @param int $userid
     * @param bool $favorable
     * @return stdClass Le dossier à jour.
     */
    public static function save_report(stdClass $dossier, $userid, $favorable) {
        return dossiers::dossier_operation($dossier, function () use ($dossier, $userid, $favorable) {
            return dossiers::set_status($dossier, $dossier->status, $userid, 'reportsaved', '', [
                'reportfavorable' => $favorable ? 1 : 0,
            ]);
        });
    }

    /**
     * Données de l'étudiant soumises avec le dépôt de soutenance (voir these_bordereau_fields()),
     * fusionnées avec celles déjà enregistrées.
     *
     * @param stdClass $data
     * @param array $existing
     * @return array
     */
    public static function extract_bordereau(stdClass $data, array $existing) {
        $bordereau = $existing;
        foreach (bordereau::bordereau_fields() as $field) {
            if ($field === 'keywords') {
                if (property_exists($data, 'bkeyword')) {
                    $bordereau['keywords'] = array_values(array_filter(array_map('trim', (array) $data->bkeyword), 'strlen'));
                }
            } else if (property_exists($data, 'b' . $field)) {
                $value = $data->{'b' . $field};
                $bordereau[$field] = is_string($value) ? trim($value) : $value;
            }
        }
        // L'embargo dure désormais la durée demandée après la soutenance (voir these_embargo_until()) :
        // une date saisie avec l'ancien formulaire ne vaut plus dès que l'étudiant enregistre le nouveau.
        if (property_exists($data, 'bembargo') && !property_exists($data, 'bembargountil')) {
            $bordereau['embargountil'] = 0;
        }
        if (empty($bordereau['embargo'])) {
            unset($bordereau['embargoreason'], $bordereau['embargomonths']);
        }
        return $bordereau;
    }

    /**
     * Enregistre le dossier de soutenance (phase 2, étape 1) : date proposée, jury, données du
     * bordereau, de la fiche signalétique et du contrat de diffusion, conclusions, et, si demandé, le
     * soumet signé (bordereau et contrat de diffusion). Le manuscrit est enregistré par l'appelant ;
     * la salle est attribuée par la DEVE à la validation du dossier.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier Dossier actif ou renvoyé en phase 2.
     * @param stdClass $data {proposeddate}, les champs de jury (voir
     *                       these_extract_submitted_jury()) et, facultativement, titleen,
     *                       conclusions et les champs du bordereau (préfixés « b »).
     * @param bool $submit
     * @param int $userid
     * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
     * @return stdClass Le dossier à jour.
     */
    public static function save_defense(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        stdClass $data,
        $submit,
        $userid,
        ?stdClass $signature = null
    ) {
        return dossiers::dossier_operation($dossier, function () use ($these, $cm, $dossier, $data, $submit, $userid, $signature) {
            $context = context_module::instance($cm->id);
            jury::save_jury($dossier->id, juryform::extract_submitted_jury($data));

            $fields = [
                'proposeddate' => (int) $data->proposeddate,
                'titleen' => (string) dossiers::submitted_value($data, $dossier, 'titleen'),
                'conclusions' => (string) dossiers::submitted_value($data, $dossier, 'conclusions'),
                'bordereau' => json_encode(
                    self::extract_bordereau($data, bordereau::get_bordereau($dossier)),
                    JSON_UNESCAPED_UNICODE
                ),
            ];
            // Grille de conformité remplie par l'étudiant ; le directeur la vérifie et la signe ensuite.
            if (property_exists($data, 'ethicsnumber')) {
                $grid = [];
                foreach (grid::get_grid_items(doctemplates::settings_scope($these)) as $items) {
                    foreach (array_keys($items) as $item) {
                        $grid[$item] = (int) !empty($data->{'grid_' . $item});
                    }
                }
                $fields['conformitygrid'] = json_encode($grid);
                $fields['ethicsnumber'] = trim((string) $data->ethicsnumber);
            }
            // Tant que la DEVE n'a pas arrêté la date, la date retenue suit la proposition de l'étudiant.
            if ((int) $dossier->status === THESE_STATUS_ACTIVE || empty($dossier->defensedate)) {
                $fields['defensedate'] = (int) $data->proposeddate;
            }

            if (!$submit) {
                $dossier = dossiers::set_status($dossier, $dossier->status, $userid, 'defensesaved', '', $fields);
                signatures::refresh_signatures($context, $dossier);
                return $dossier;
            }

            // Le dossier déposé porte une date : une autre date demandée auparavant par la DEVE (avant un
            // renvoi, par exemple) n'est plus attendue.
            $fields['daterequested'] = 0;
            $fields['daterequestcomment'] = '';
            $previous = clone $dossier;
            foreach ($fields as $field => $value) {
                $dossier->$field = $value;
            }
            $invalidated = signatures::refresh_signatures($context, $dossier);
            $target = dossiers::resubmission_target($previous, $invalidated);
            $resubmitted = (int) $previous->status === THESE_STATUS_DEFENSE_RETURNED;
            $dossier = dossiers::set_status(
                $previous,
                $target,
                $userid,
                $resubmitted ? 'defenseresubmitted' : 'defensesubmitted',
                '',
                array_merge($fields, dossiers::cleared_return_fields())
            );
            if ($signature !== null) {
                foreach (['bordereau', 'contract'] as $doctype) {
                    signatures::sign_document($context, $dossier, $doctype, 'student', $userid, $signature->name, $signature->png);
                }
            }
            notifications::notify_actor($these, $cm, $dossier);
            return $dossier;
        });
    }
}
