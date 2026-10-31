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
use mod_these\event\minutes_recorded;
use stdClass;

/**
 * Procès-verbal de soutenance : décisions du jury, ajournement et dépôt des corrections.
 *
 * Les fonctions these_*() correspondantes de locallib/ sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class minutes {
    /**
     * Enregistre les décisions du jury reportées par la DEVE depuis le procès-verbal scanné (le fichier
     * est enregistré par l'appelant) : résultat, mention, proposition pour un prix de thèse,
     * corrections demandées. Sans corrections, le dossier est clos ; avec corrections, l'étudiant doit
     * déposer son manuscrit corrigé, que le directeur, la bibliothèque et la DEVE valident avant la
     * clôture.
     *
     * Une thèse ajournée ne reçoit ni mention, ni proposition de prix, ni corrections : le dossier
     * revient en phase de soutenance (voir these_adjourn_dossier()).
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid
     * @param stdClass $decision {result, mention, prizeproposal, correctionsrequested, correctionsnote}
     * @param string $remark
     * @param array $drafts Brouillons des gestionnaires de fichiers du formulaire (zone => draftitemid),
     *                      enregistrés dans l'opération (voir files::save_drafts()).
     * @return stdClass Le dossier à jour.
     */
    public static function record_minutes(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        $userid,
        stdClass $decision,
        $remark = '',
        array $drafts = []
    ) {
        $operation = function () use ($these, $cm, $dossier, $userid, $decision, $remark, $drafts) {
            files::save_drafts($dossier, $drafts);
            $admitted = (int) $decision->result === THESE_RESULT_ADMITTED;
            $mention = (string) ($decision->mention ?? '');
            $corrections = $admitted && !empty($decision->correctionsrequested);
            if ((int) $decision->result === THESE_RESULT_ADJOURNED) {
                $dossier = self::adjourn_dossier($these, $cm, $dossier, $userid, $remark);
            } else {
                $fields = [
                    'defenseresult' => (int) $decision->result,
                    'mention' => $admitted && array_key_exists($mention, workflow::mention_options()) ? $mention : '',
                    'prizeproposal' => $admitted && !empty($decision->prizeproposal) ? 1 : 0,
                    'correctionsrequested' => $corrections ? 1 : 0,
                    'correctionsnote' => $corrections ? trim((string) ($decision->correctionsnote ?? '')) : '',
                ];
                if ($corrections) {
                    $dossier = dossiers::set_status(
                        $dossier,
                        THESE_STATUS_CORRECTIONS,
                        $userid,
                        'minutescorrections',
                        $remark,
                        array_merge($fields, dossiers::cleared_return_fields())
                    );
                    notifications::notify_actor($these, $cm, $dossier);
                } else {
                    $dossier = dossiers::set_status($dossier, THESE_STATUS_CLOSED, $userid, 'closed', $remark, $fields);
                    notifications::notify_closed($these, $cm, $dossier);
                }
            }
            minutes_recorded::create_from_dossier($dossier, context_module::instance($cm->id), $userid, [
                'result' => (int) $decision->result, 'corrections' => $corrections,
            ])->trigger();
            return $dossier;
        };
        return dossiers::dossier_operation($dossier, $operation);
    }

    /**
     * Ajournement de la soutenance : le dossier revient en phase de soutenance (dossier actif), sujet,
     * enregistrements et numéro conservés. L'étudiant dépose un nouveau dossier de soutenance, qui
     * suit de nouveau tout le circuit.
     *
     * Le procès-verbal d'ajournement est archivé (zone pvadjourned, nom préfixé par la date) ; les
     * documents générés et les signatures des documents de soutenance sont supprimés, pour être
     * produits et signés de nouveau ; les accès du jury sont révoqués et son éligibilité sera revue.
     * Le texte du rapport et les données déjà saisies restent proposés.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid
     * @param string $remark
     * @return stdClass Le dossier à jour.
     */
    public static function adjourn_dossier(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $remark = '') {
        return dossiers::dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $remark) {
            global $DB;

            $context = context_module::instance($cm->id);
            $fs = get_file_storage();
            $prefix = 'ajournement-' . userdate(time(), '%Y-%m-%d') . '-';
            foreach ($fs->get_area_files($context->id, 'mod_these', 'pv', $dossier->id, 'filename', false) as $file) {
                $filename = $prefix . $file->get_filename();
                if (!$fs->file_exists($context->id, 'mod_these', 'pvadjourned', $dossier->id, '/', $filename)) {
                    $fs->create_file_from_storedfile(['filearea' => 'pvadjourned', 'filename' => $filename], $file);
                }
            }
            $fs->delete_area_files($context->id, 'mod_these', 'pv', $dossier->id);
            $fs->delete_area_files($context->id, 'mod_these', 'generated', $dossier->id);
            foreach (signatures::get_signatures($dossier->id) as $signature) {
                if ($signature->doctype !== 'subject') {
                    signatures::delete_signature($context, $signature);
                }
            }
            $DB->set_field('these_jury', 'notifiedtime', 0, ['dossierid' => $dossier->id]);
            $DB->set_field('these_jury', 'accesstoken', null, ['dossierid' => $dossier->id]);
            $DB->set_field('these_jury', 'eligible', 0, ['dossierid' => $dossier->id]);

            $dossier = dossiers::set_status($dossier, THESE_STATUS_ACTIVE, $userid, 'adjourned', $remark, array_merge([
                'defenseresult' => THESE_RESULT_ADJOURNED,
                'mention' => '',
                'prizeproposal' => 0,
                'correctionsrequested' => 0,
                'correctionsnote' => '',
                'reportfavorable' => 0,
                'documentstime' => 0,
                'senttime' => 0,
            ], dossiers::cleared_return_fields()));
            $vars = notifications::email_base_vars($these, $cm, $dossier);
            $recipients = notifications::get_student_and_director($dossier);
            notifications::send_email($these, 'adjourned', $recipients, $vars, '', '', $dossier);
            return $dossier;
        });
    }

    /**
     * Indique si le dossier est en attente d'une nouvelle soutenance après un ajournement.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function is_adjourned(stdClass $dossier) {
        return (int) $dossier->status === THESE_STATUS_ACTIVE && (int) $dossier->defenseresult === THESE_RESULT_ADJOURNED;
    }

    /**
     * Dépôt du manuscrit corrigé par l'étudiant (brouillon du formulaire enregistré dans la même
     * opération) : le dossier passe au directeur, ou revient à l'étape qui l'avait renvoyé.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid
     * @param string $done Corrections apportées.
     * @param array $drafts Brouillons des gestionnaires de fichiers du formulaire (zone => draftitemid),
     *                      enregistrés dans l'opération (voir files::save_drafts()).
     * @return stdClass Le dossier à jour.
     */
    public static function submit_corrections(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        $userid,
        $done,
        array $drafts = []
    ) {
        return dossiers::dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $done, $drafts) {
            files::save_drafts($dossier, $drafts);
            if ((int) $dossier->status !== THESE_STATUS_CORRECTIONS) {
                throw new coding_exception('Dépôt des corrections impossible au statut ' . $dossier->status);
            }
            $resubmitted = !empty($dossier->returnstatus);
            $dossier = dossiers::set_status(
                $dossier,
                dossiers::submission_target($dossier),
                $userid,
                $resubmitted ? 'correctionsresubmitted' : 'correctionssubmitted',
                '',
                array_merge(['correctionsdone' => trim((string) $done)], dossiers::cleared_return_fields())
            );
            notifications::notify_actor($these, $cm, $dossier);
            return $dossier;
        });
    }
}
