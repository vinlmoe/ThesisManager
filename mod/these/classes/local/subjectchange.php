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

use mod_these\output;
use coding_exception;
use context;
use context_module;
use core_text;
use stdClass;

/**
 * Changement de sujet de thèse.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subjectchange {
    /**
     * Indique si l'étudiant peut changer de sujet : dossier actif (sujet enregistré, soutenance pas
     * encore déposée, y compris après un ajournement) ou abandonné, que le nouveau sujet relance.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function subject_changeable(stdClass $dossier) {
        return in_array((int) $dossier->status, [THESE_STATUS_ACTIVE, THESE_STATUS_CANCELLED], true);
    }

    /**
     * Sujets précédents d'un dossier, du plus récent au plus ancien.
     *
     * @param int $dossierid
     * @return stdClass[]
     */
    public static function get_subject_history($dossierid) {
        global $DB;
        return $DB->get_records('these_subjecthistory', ['dossierid' => $dossierid], 'timecreated DESC, id DESC');
    }

    /**
     * Change le sujet d'un dossier actif ou abandonné. L'ancien sujet est archivé (historique et PDF du dépôt de
     * sujet signé, zone subjectarchive) ; l'ancien directeur, la DEVE et la bibliothèque sont
     * prévenus ; le nouveau sujet, signé par l'étudiant, repasse toute la phase 1 : directeur
     * (éventuellement nouveau), DEVE, bibliothèque. Un dossier abandonné est ainsi relancé : il ne
     * pourra plus être rouvert dans le statut atteint lors de son abandon.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param stdClass $data Nouveau sujet (champs du formulaire de sujet).
     * @param int $userid
     * @param string $reason Motif du changement.
     * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
     * @return stdClass Le dossier à jour.
     */
    public static function change_subject(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        stdClass $data,
        $userid,
        $reason,
        ?stdClass $signature = null
    ) {
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $data, $userid, $reason, $signature) {
            global $DB;

            if (!these_subject_changeable($dossier)) {
                throw new coding_exception('Changement de sujet impossible au statut ' . $dossier->status);
            }
            $context = context_module::instance($cm->id);
            $now = time();
            $olddirector = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid, 'deleted' => 0]) : null;

            // Trace de l'ancien sujet.
            $history = (object) [
                'dossierid' => $dossier->id,
                'title' => (string) $dossier->title,
                'titleen' => (string) $dossier->titleen,
                'summary' => (string) $dossier->summary,
                'keywords' => (string) $dossier->keywords,
                'studyyear' => (string) ($dossier->studyyear ?? ''),
                'confidential' => (int) ($dossier->confidential ?? 0),
                'directorid' => (int) $dossier->directorid,
                'directorname' => !empty($dossier->externaldirector) ? (string) $dossier->extdirectorname
                    : ($olddirector ? fullname($olddirector) : ''),
                'libraryref' => (string) $dossier->libraryref,
                'reason' => trim((string) $reason),
                'changedby' => (int) $userid,
                'timecreated' => $now,
            ];
            $history->id = $DB->insert_record('these_subjecthistory', $history);
            try {
                get_file_storage()->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_these',
                    'filearea' => 'subjectarchive',
                    'itemid' => $dossier->id,
                    'filepath' => '/',
                    'filename' => 'sujet-' . userdate($now, '%Y-%m-%d') . '-' . $history->id . '.pdf',
                ], these_signed_document_pdf($these, $context, $dossier, 'subject'));
            } catch (\Throwable $e) {
                debugging('Archivage du dépôt de sujet impossible : ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Tous les documents signés (sujet, et soutenance d'un dossier abandonné relancé) sont
            // révoqués : leurs signatures sont redemandées au fil du nouveau circuit.
            signatures::revoke_signatures($context, $dossier, $userid);

            $oldtitle = (string) $dossier->title;
            $fields = [];
            foreach (
                ['title', 'titleen', 'summary', 'keywords', 'studyyear', 'directortitle',
                    'extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field
            ) {
                $fields[$field] = (string) these_submitted_value($data, $dossier, $field);
            }
            foreach (['directorid', 'confidential', 'externaldirector'] as $field) {
                $fields[$field] = (int) these_submitted_value($data, $dossier, $field);
            }
            $fields = dossiers::people_fields($fields, $data);
            // Dossier abandonné relancé par le nouveau sujet : le statut atteint lors de l'abandon ne vaut plus.
            $fields['cancelledfromstatus'] = 0;
            if (property_exists($data, 'commitguide')) {
                $fields['commitments'] = (!empty($data->commitguide) ? signatures::COMMIT_GUIDE : 0)
                    | (!empty($data->commitduplicate) ? signatures::COMMIT_DUPLICATE : 0)
                    | (!empty($data->commitethics) ? signatures::COMMIT_ETHICS : 0);
            }
            if (empty($fields['externaldirector'])) {
                foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
                    $fields[$field] = '';
                }
            }
            $dossier = these_set_status(
                $dossier,
                THESE_STATUS_SUBJECT_SUBMITTED,
                $userid,
                'subjectchanged',
                get_string('subjectchangelog', 'mod_these', (object) ['old' => $oldtitle, 'reason' => trim((string) $reason)]),
                array_merge($fields, these_cleared_return_fields())
            );
            if ($signature !== null) {
                these_sign_document($context, $dossier, 'subject', 'student', $userid, $signature->name, $signature->png);
            }
            // Un directeur extérieur n'est invité à signer qu'à la validation du nouveau sujet par le directeur.

            // Avertissement de l'ancien directeur, de la DEVE et de la bibliothèque.
            $recipients = [];
            if ($olddirector) {
                $recipients[core_text::strtolower($olddirector->email)] = $olddirector;
            }
            foreach (['deve', 'library'] as $service) {
                foreach (these_get_service_recipients($these, $context, $service) as $recipient) {
                    $recipients[core_text::strtolower((string) $recipient->email)] = $recipient;
                }
            }
            $vars = these_email_base_vars($these, $cm, $dossier);
            $vars['oldtitle'] = format_string($oldtitle);
            $vars['reason'] = trim((string) $reason);
            these_send_email($these, 'subjectchanged', array_values($recipients), $vars, '', '', $dossier);
            // Le (nouveau) directeur est attendu pour valider le nouveau sujet.
            these_notify_actor($these, $cm, $dossier);
            return $dossier;
        });
    }

    /**
     * Sujets précédents d'un dossier, pour l'affichage : date, titre, directeur, motif et PDF archivé.
     *
     * @param context $context
     * @param stdClass $dossier
     * @return string HTML ('' sans changement de sujet)
     */
    public static function render_subject_history(context $context, stdClass $dossier) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\subject_history($context, $dossier));
    }
}
