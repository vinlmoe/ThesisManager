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
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Refus de signer d'un agent DEVE : au lieu de signer les documents que l'équipe DEVE a validés, il
 * les refuse avec un motif, et le dossier revient à l'étape où ces documents ont été créés.
 *
 * - dépôt de sujet : à l'étudiant (sujet renvoyé), qui le soumet de nouveau à l'enregistrement de
 *   la DEVE ;
 * - fiche signalétique, contrat de diffusion, conclusions : à l'étudiant (dossier de soutenance
 *   renvoyé), qui le soumet de nouveau à la validation de la DEVE ;
 * - fiche d'agréments, dont l'agrément administratif est rempli par la DEVE : à la validation du
 *   dossier par la DEVE.
 *
 * Plusieurs documents refusés ensemble : le dossier revient à la première de ces étapes. Le refus
 * n'est possible que tant que l'étape suivante n'a pas avancé (elle attend ces signatures, voir
 * prerequisites). Les demandes de signature aux agents sont retirées ; elles repartent à la
 * nouvelle validation de l'équipe. Le motif est inscrit à l'historique, montré au destinataire du
 * renvoi et envoyé à l'équipe DEVE (validateur compris).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refusal {
    /**
     * Étape de création de chaque document signé par les agents : statut d'où le refus est possible
     * (celui qu'atteint le dossier à la validation de l'équipe), statut de retour, statut repris à la
     * nouvelle soumission de l'étudiant, destinataire.
     *
     * @return array doctype => [from, to, returnstatus, target]
     */
    public static function creation_steps() {
        $defense = [
            'from' => THESE_STATUS_DEFENSE_VALIDATED,
            'to' => THESE_STATUS_DEFENSE_RETURNED,
            'returnstatus' => THESE_STATUS_DEFENSE_APPROVED,
            'target' => 'student',
        ];
        return [
            'subject' => [
                'from' => THESE_STATUS_SUBJECT_REGISTERED,
                'to' => THESE_STATUS_SUBJECT_RETURNED,
                'returnstatus' => THESE_STATUS_SUBJECT_APPROVED,
                'target' => 'student',
            ],
            'agreement' => [
                'from' => THESE_STATUS_DEFENSE_VALIDATED,
                'to' => THESE_STATUS_DEFENSE_APPROVED,
                'returnstatus' => 0,
                'target' => 'deve',
            ],
            'signaletique' => $defense,
            'contract' => $defense,
            'conclusions' => $defense,
        ];
    }

    /**
     * Indique si une signature attendue d'un agent peut être refusée : demande d'agent encore
     * ouverte, document au retour connu, dossier toujours à l'étape qui suit sa validation.
     *
     * @param stdClass $signature
     * @param stdClass $dossier
     * @return bool
     */
    public static function refusable(stdClass $signature, stdClass $dossier) {
        $step = self::creation_steps()[$signature->doctype] ?? null;
        return $step !== null && $signature->token === null && empty($signature->timesigned)
            && (int) $signature->dossierid === (int) $dossier->id
            && (int) $dossier->status === $step['from'];
    }

    /**
     * Étape de retour pour un ensemble de documents refusés : la première dans le circuit.
     *
     * @param string[] $doctypes
     * @return array|null Voir creation_steps().
     */
    public static function return_step(array $doctypes) {
        $chosen = null;
        foreach ($doctypes as $doctype) {
            $step = self::creation_steps()[$doctype] ?? null;
            if ($step && ($chosen === null || these_status_rank($step['to']) < these_status_rank($chosen['to']))) {
                $chosen = $step;
            }
        }
        return $chosen;
    }

    /**
     * Refuse de signer des documents d'un dossier et le renvoie à l'étape de leur création.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param stdClass[] $signatures Signatures attendues refusées (voir refusable()).
     * @param int $userid Agent qui refuse.
     * @param string $reason Motif, obligatoire.
     * @return stdClass Le dossier à jour.
     */
    public static function refuse(stdClass $these, stdClass $cm, stdClass $dossier, array $signatures, $userid, $reason) {
        global $DB;

        $reason = trim((string) $reason);
        $signatures = array_values(array_filter($signatures, function ($signature) use ($dossier) {
            return self::refusable($signature, $dossier);
        }));
        if ($reason === '' || !$signatures) {
            throw new moodle_exception('refusenothing', 'mod_these');
        }
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $signatures, $userid, $reason, $DB) {
            $step = self::return_step(array_column($signatures, 'doctype'));
            $labels = array_map(function ($signature) {
                return signatureagent::document_label($signature->doctype, $signature->slot);
            }, $signatures);
            $validators = array_unique(array_filter(array_map('intval', array_column($signatures, 'validatedby'))));

            // Demandes aux agents issues de la même validation retirées : elles repartent à la
            // nouvelle validation de l'équipe. Celles d'une étape antérieure restent ouvertes.
            $samestep = array_keys(array_filter(self::creation_steps(), function ($creation) use ($dossier) {
                return $creation['from'] === (int) $dossier->status;
            }));
            [$slotsql, $params] = $DB->get_in_or_equal(signatureagent::slots_for($these), SQL_PARAMS_NAMED, 'slot');
            [$docsql, $docparams] = $DB->get_in_or_equal($samestep, SQL_PARAMS_NAMED, 'doc');
            $DB->delete_records_select(
                'these_signature',
                "dossierid = :dossierid AND token IS NULL AND timesigned = 0 AND slot $slotsql AND doctype $docsql",
                $params + $docparams + ['dossierid' => $dossier->id]
            );

            $remark = get_string('refusalremark', 'mod_these', (object) [
                'documents' => implode(', ', $labels),
                'reason' => $reason,
            ]);
            $dossier = these_set_status($dossier, $step['to'], $userid, 'agentrefused', $remark, [
                'returncomment' => $reason,
                'returnedby' => (int) $userid,
                'returntime' => time(),
                'returnstatus' => $step['returnstatus'],
            ]);

            // Prévenus : le destinataire du renvoi, l'équipe DEVE et les comptes qui avaient validé.
            if ($step['target'] === 'student') {
                these_notify_returned($these, $cm, $dossier, 'student', $reason);
            }
            $recipients = these_get_service_recipients($these, context_module::instance($cm->id), 'deve');
            foreach ($validators as $validatorid) {
                $validator = \core_user::get_user($validatorid);
                if ($validator && !$validator->deleted) {
                    $recipients[] = $validator;
                }
            }
            $agent = \core_user::get_user($userid);
            $vars = these_email_base_vars($these, $cm, $dossier);
            $vars['name'] = $agent ? fullname($agent) : '';
            $vars['document'] = implode(', ', $labels);
            $vars['comment'] = $reason;
            $vars['target'] = $step['target'] === 'student' ? these_actor_label('student') : these_actor_label('deve');
            $vars['url'] = (new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]))->out(false);
            these_send_email($these, 'agentrefused', self::unique_users($recipients), $vars, '', '', $dossier);
            return $dossier;
        });
    }

    /**
     * Destinataires sans doublon (même compte, ou même adresse pour une boîte de service).
     *
     * @param stdClass[] $users
     * @return stdClass[]
     */
    protected static function unique_users(array $users) {
        $unique = [];
        foreach ($users as $user) {
            $key = !empty($user->id) && $user->id > 0 ? 'id' . $user->id : 'mail' . \core_text::strtolower((string) $user->email);
            $unique[$key] = $user;
        }
        return array_values($unique);
    }
}
