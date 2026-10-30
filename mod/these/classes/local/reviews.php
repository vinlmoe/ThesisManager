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

use coding_exception;
use context_module;
use mod_these\event\defense_scheduled;
use stdClass;

/**
 * Circuit de validation : validation de l'étape courante et renvoi du dossier.
 *
 * Les fonctions these_*() correspondantes de locallib/ sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviews {
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
    public static function validate_step(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        $userid,
        array $fields = [],
        $remark = '',
        ?stdClass $signature = null
    ) {
        $operation = function () use ($these, $cm, $dossier, $userid, $fields, $remark, $signature) {
            $steps = workflow::review_steps();
            $step = $steps[(int) $dossier->status] ?? null;
            if ($step === null) {
                throw new coding_exception('Aucune étape de validation pour le statut ' . $dossier->status);
            }
            $context = context_module::instance($cm->id);

            $dossier = dossiers::set_status(
                $dossier,
                $step['next'],
                $userid,
                'validated' . $step['key'],
                $remark,
                array_merge($fields, dossiers::cleared_return_fields())
            );
            if ($step['key'] === 'devedefense') {
                // La DEVE arrête la date de soutenance en validant le dossier.
                defense_scheduled::create_from_dossier($dossier, $context, $userid, [
                    'defensedate' => (int) $dossier->defensedate,
                ])->trigger();
            }
            signatures::refresh_signatures($context, $dossier);
            $agentdoctypes = [];
            foreach ($step['sign'] as $slot => $doctypes) {
                if (!signatureagent::signs_slot_at_validation($slot, $userid, $these)) {
                    // Emplacement de l'agent DEVE : il signe ensuite, depuis « Mes signatures ».
                    $agentdoctypes = array_merge($agentdoctypes, $doctypes);
                    continue;
                }
                foreach ($doctypes as $doctype) {
                    if ($signature !== null && in_array($slot, signatures::document_slots($dossier, $doctype), true)) {
                        signatures::sign_document($context, $dossier, $doctype, $slot, $userid, $signature->name, $signature->png);
                    }
                }
            }
            if ($step['request']) {
                // Direction générale et université par délégation : demandées à leurs agents, s'il y en a,
                // dans le même courriel.
                $agentdoctypes = array_merge($agentdoctypes, $step['request']);
            }
            if ($agentdoctypes) {
                signatureagent::request_agent_signatures($these, $cm, $dossier, array_unique($agentdoctypes), $userid);
            }
            if ($step['request']) {
                // Un seul appel pour tous les documents : chaque signataire reçoit un seul lien.
                signaturetokens::request_external_signatures($these, $cm, $dossier, $step['request']);
            }

            if ((int) $dossier->status === THESE_STATUS_ACTIVE) {
                notifications::notify_subject_active($these, $cm, $dossier);
            } else if ((int) $dossier->status === THESE_STATUS_CLOSED) {
                notifications::notify_closed($these, $cm, $dossier);
            } else {
                notifications::notify_actor($these, $cm, $dossier);
            }
            return $dossier;
        };
        return dossiers::dossier_operation($dossier, $operation);
    }

    /**
     * Renvoie le dossier, depuis l'étape de validation courante, à l'étudiant ou au directeur.
     *
     * Les documents signés de la phase sont révoqués, et leurs signatures redemandées quand le dossier
     * repasse le circuit (voir signatures::revoke_signatures()) : renvoyé à l'étudiant, le dossier
     * passe au statut « renvoyé » de sa phase et, à la nouvelle soumission, refait toute la phase
     * (directeur, DEVE, bibliothèque) ; renvoyé au directeur (par la DEVE), il revient à l'étape de
     * validation du directeur, et seules les signatures de l'étudiant (et du directeur extérieur sur le
     * dépôt de sujet) sont conservées. Pendant les corrections, sans document signé, le dossier
     * revient directement à l'étape qui l'a renvoyé.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid
     * @param string $target 'student' ou 'director'.
     * @param string $comment Motif, obligatoire, transmis au destinataire.
     * @return stdClass Le dossier à jour.
     */
    public static function return_dossier(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $target, $comment) {
        return dossiers::dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $target, $comment) {
            $steps = workflow::review_steps();
            $step = $steps[(int) $dossier->status] ?? null;
            if ($step === null || !in_array($target, $step['returnto'], true)) {
                throw new coding_exception('Renvoi impossible depuis le statut ' . $dossier->status . ' vers ' . $target);
            }

            $phase = workflow::phase($dossier->status);
            $fields = [
                'returncomment' => $comment,
                'returnedby' => $userid,
                'returntime' => time(),
            ];
            $context = context_module::instance($cm->id);
            if ($target === 'student' && workflow::in_corrections($dossier->status)) {
                $tostatus = THESE_STATUS_CORRECTIONS;
                $fields['returnstatus'] = (int) $dossier->status;
            } else if ($target === 'student') {
                $tostatus = $phase === 1 ? THESE_STATUS_SUBJECT_RETURNED : THESE_STATUS_DEFENSE_RETURNED;
                // Toutes les signatures de la phase sont redemandées : la phase est refaite en entier.
                $fields['returnstatus'] = 0;
                signatures::revoke_signatures($context, $dossier, $userid, signatures::phase_documents($phase));
            } else {
                $tostatus = $phase === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
                $fields['returnstatus'] = 0;
                $keep = $phase === 1 ? ['student', 'extdirector'] : ['student'];
                signatures::revoke_signatures($context, $dossier, $userid, signatures::phase_documents($phase), $keep);
            }

            $dossier = dossiers::set_status($dossier, $tostatus, $userid, 'returnedto' . $target, $comment, $fields);
            notifications::notify_returned($these, $cm, $dossier, $target, $comment);
            return $dossier;
        });
    }
}
