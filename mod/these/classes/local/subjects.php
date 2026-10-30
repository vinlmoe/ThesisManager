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
use mod_these\event\subject_submitted;
use stdClass;

/**
 * Fiche de sujet (phase 1) : modification, enregistrement et soumission par l'étudiant.
 *
 * Les fonctions these_*() correspondantes de locallib/ sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subjects {
    /**
     * Indique si la fiche de sujet peut être modifiée par l'étudiant.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function subject_editable(stdClass $dossier) {
        return in_array((int) $dossier->status, [THESE_STATUS_DRAFT, THESE_STATUS_SUBJECT_RETURNED], true);
    }

    /**
     * Découpe une liste de mots-clés séparés par des virgules ou des points-virgules.
     *
     * @param string $keywords
     * @return string[]
     */
    public static function split_keywords($keywords) {
        return array_values(array_filter(array_map('trim', preg_split('/[;,]/', (string) $keywords)), 'strlen'));
    }

    /**
     * Champs de sujet dépendant des personnes : titre du directeur, repris de l'annuaire quand il y
     * figure (l'étudiant ne le saisit pas).
     *
     * @param array $fields Champs du sujet déjà relevés (directorid, directortitle...).
     * @param stdClass $data Formulaire soumis.
     * @return array Les champs, complétés.
     */
    public static function people_fields(array $fields, stdClass $data) {
        $title = jurors::titles_for_users([(int) ($fields['directorid'] ?? 0)]);
        if ($title) {
            $fields['directortitle'] = reset($title);
        }
        return $fields;
    }

    /**
     * Directeur extérieur déclaré par l'étudiant, en une ligne : nom, grade, établissement et adresse
     * (celle à laquelle son lien de signature sera envoyé), pour que le validateur la vérifie avant de
     * valider le sujet. Chaîne vide si le directeur n'est pas extérieur.
     *
     * @param stdClass $dossier
     * @return string Texte brut.
     */
    public static function external_director_summary(stdClass $dossier) {
        if (empty($dossier->externaldirector)) {
            return '';
        }
        $details = array_values(array_filter(array_map('trim', [
            (string) ($dossier->extdirectorgrade ?? ''),
            (string) ($dossier->extdirectorinstitution ?? ''),
        ]), 'strlen'));
        $summary = trim((string) ($dossier->extdirectorname ?? ''));
        if ($details) {
            $summary .= ' (' . implode(', ', $details) . ')';
        }
        $email = trim((string) ($dossier->extdirectoremail ?? ''));
        if ($email !== '') {
            $summary .= ' — ' . $email;
        }
        return trim($summary);
    }

    /**
     * Enregistre la fiche de sujet (phase 1, étape 1) et, si demandé, la soumet signée.
     *
     * Toute modification du contenu invalide les signatures déjà apposées sur le formulaire de dépôt
     * de sujet (voir these_refresh_signatures()). À la soumission, la signature de l'étudiant est
     * apposée ; si le directeur est extérieur, sa signature ne lui est demandée par courriel qu'une
     * fois le sujet validé par le directeur interne (voir these_review_steps()), l'adresse saisie par
     * l'étudiant n'étant pas vérifiée avant.
     *
     * @param stdClass $these Instance.
     * @param stdClass $cm Course module.
     * @param stdClass $dossier Dossier en brouillon ou renvoyé.
     * @param stdClass $data Champs du formulaire de sujet ; les champs absents sont conservés.
     * @param bool $submit True pour soumettre, false pour un simple enregistrement.
     * @param int $userid Auteur.
     * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
     * @return stdClass Le dossier à jour.
     */
    public static function save_subject(
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
            $fields = [];
            foreach (
                ['title', 'titleen', 'summary', 'keywords', 'studyyear', 'directortitle',
                    'extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field
            ) {
                $fields[$field] = (string) dossiers::submitted_value($data, $dossier, $field);
            }
            foreach (['directorid', 'confidential', 'externaldirector'] as $field) {
                $fields[$field] = (int) dossiers::submitted_value($data, $dossier, $field);
            }
            $fields = self::people_fields($fields, $data);
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

            if (!$submit) {
                $dossier = dossiers::set_status($dossier, $dossier->status, $userid, 'subjectsaved', '', $fields);
                signatures::refresh_signatures($context, $dossier);
                return $dossier;
            }

            $previous = clone $dossier;
            foreach ($fields as $field => $value) {
                $dossier->$field = $value;
            }
            $invalidated = signatures::refresh_signatures($context, $dossier);
            $target = dossiers::resubmission_target($previous, $invalidated);
            $resubmitted = (int) $previous->status === THESE_STATUS_SUBJECT_RETURNED;
            $dossier = dossiers::set_status(
                $previous,
                $target,
                $userid,
                $resubmitted ? 'subjectresubmitted' : 'subjectsubmitted',
                '',
                array_merge($fields, dossiers::cleared_return_fields())
            );
            if ($signature !== null) {
                signatures::sign_document($context, $dossier, 'subject', 'student', $userid, $signature->name, $signature->png);
            }
            subject_submitted::create_from_dossier($dossier, $context, $userid, ['resubmitted' => $resubmitted])->trigger();
            notifications::notify_actor($these, $cm, $dossier);
            return $dossier;
        });
    }
}
