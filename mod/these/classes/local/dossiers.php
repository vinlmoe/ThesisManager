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
use context;
use context_module;
use moodle_exception;
use résultat;
use stdClass;

/**
 * Cycle de vie des dossiers : création, soumissions, validations, renvois, procès-verbal, numéro DEVE.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossiers {
    /**
     * Dossier d'un étudiant dans une instance, s'il existe.
     *
     * @param int $theseid
     * @param int $userid
     * @return stdClass|null
     */
    public static function get_user_dossier($theseid, $userid) {
        global $DB;

        return $DB->get_record('these_dossier', ['theseid' => $theseid, 'userid' => $userid]) ?: null;
    }

    /**
     * Crée un dossier vide (brouillon) pour un étudiant.
     *
     * @param int $theseid
     * @param int $userid
     * @param int $actorid Auteur de la création (l'étudiant, ou la DEVE pour son compte).
     * @return stdClass Le dossier créé.
     */
    public static function create_dossier($theseid, $userid, $actorid) {
        global $DB;

        $now = time();
        $dossier = (object) [
            'theseid' => $theseid,
            'userid' => $userid,
            'status' => THESE_STATUS_DRAFT,
            'statustime' => $now,
            'title' => '',
            'titleen' => '',
            'summary' => '',
            'keywords' => '',
            'directorid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $dossier->id = $DB->insert_record('these_dossier', $dossier);
        these_log($dossier->id, $actorid, 'created', THESE_STATUS_DRAFT, THESE_STATUS_DRAFT);

        return $DB->get_record('these_dossier', ['id' => $dossier->id], '*', MUST_EXIST);
    }

    /**
     * Inscrit une action dans l'historique d'un dossier.
     *
     * @param int $dossierid
     * @param int $userid Auteur, 0 pour le système.
     * @param string $action Clé d'action (libellé : chaîne de langue « log<action> »).
     * @param int $fromstatus
     * @param int $tostatus
     * @param string $remark Commentaire éventuel (motif de renvoi, avis...).
     * @return void
     */
    public static function log($dossierid, $userid, $action, $fromstatus, $tostatus, $remark = '') {
        global $DB;

        $DB->insert_record('these_log', (object) [
            'dossierid' => $dossierid,
            'userid' => (int) $userid,
            'logaction' => $action,
            'fromstatus' => (int) $fromstatus,
            'tostatus' => (int) $tostatus,
            'remark' => (string) $remark,
            'timecreated' => time(),
        ]);
    }

    /**
     * Fait passer un dossier dans un nouveau statut, avec les champs à mettre à jour en même temps,
     * et l'inscrit à l'historique. Un changement de statut remet à zéro la relance (une nouvelle
     * étape commence), un enregistrement sans changement de statut n'y touche pas. Un dossier clos ou
     * abandonné perd les liens d'accès de son jury.
     *
     * @param stdClass $dossier Mis à jour en place.
     * @param int $tostatus
     * @param int $userid Auteur de l'action.
     * @param string $action Voir these_log().
     * @param string $remark
     * @param array $fields Autres champs du dossier à enregistrer.
     * @return stdClass Le dossier à jour.
     */
    public static function set_status(stdClass $dossier, $tostatus, $userid, $action, $remark = '', array $fields = []) {
        global $DB;

        $now = time();
        $fromstatus = (int) $dossier->status;
        if (!these_transition_allowed($fromstatus, $tostatus)) {
            throw new coding_exception('Transition de statut interdite : ' . $fromstatus . ' vers ' . (int) $tostatus);
        }
        $update = array_merge($fields, ['id' => $dossier->id, 'status' => (int) $tostatus, 'timemodified' => $now]);
        if ((int) $tostatus !== $fromstatus) {
            $update['statustime'] = $now;
            $update['remindertime'] = 0;
        }
        $DB->update_record('these_dossier', (object) $update);
        these_log($dossier->id, $userid, $action, $fromstatus, $tostatus, $remark);
        if (in_array((int) $tostatus, [THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED], true)) {
            // Dossier clos ou abandonné : les liens d'accès du jury sont révoqués.
            $DB->set_field('these_jury', 'accesstoken', null, ['dossierid' => $dossier->id]);
        }

        foreach ($update as $field => $value) {
            $dossier->$field = $value;
        }
        // Soutenance au calendrier : date arrêtée, déplacée, ajournée ou dossier abandonné.
        calendar::sync($dossier);
        return $dossier;
    }

    /**
     * Exécute une opération sur un dossier (soumission, validation, renvoi, décision du jury...) de
     * façon atomique : un seul traitement à la fois par dossier (verrou), dans une transaction, et
     * seulement si le dossier est encore dans le statut où l'utilisateur l'a vu. Une double soumission
     * ou deux validations simultanées ne peuvent donc pas s'appliquer deux fois.
     *
     * Les opérations imbriquées (une décision d'ajournement depuis le procès-verbal, par exemple)
     * s'exécutent dans le verrou et la transaction de l'opération qui les appelle.
     *
     * @param stdClass $dossier Dossier tel que l'utilisateur l'a vu.
     * @param callable $operation
     * @return mixed Résultat de l'opération.
     * @throws moodle_exception dossierchanged si le dossier a changé de statut entre-temps, dossierbusy
     *                          si un autre traitement le retient.
     */
    public static function dossier_operation(stdClass $dossier, callable $operation) {
        global $DB;
        static $held = [];

        if (empty($dossier->id) || !empty($held[$dossier->id])) {
            return $operation();
        }
        $lock = \core\lock\lock_config::get_lock_factory('mod_these')->get_lock('dossier' . $dossier->id, 10);
        if (!$lock) {
            throw new moodle_exception('dossierbusy', 'mod_these');
        }
        $held[$dossier->id] = true;
        try {
            $status = $DB->get_field('these_dossier', 'status', ['id' => $dossier->id]);
            if ($status === false || (int) $status !== (int) $dossier->status) {
                throw new moodle_exception('dossierchanged', 'mod_these');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $result = $operation();
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
            return $result;
        } finally {
            unset($held[$dossier->id]);
            $lock->release();
        }
    }

    /**
     * Champs d'un renvoi remis à zéro dès que le dossier repart vers l'avant.
     *
     * @return array
     */
    public static function cleared_return_fields() {
        return ['returnstatus' => 0, 'returncomment' => '', 'returnedby' => 0, 'returntime' => 0];
    }

    /**
     * Statut atteint quand l'étudiant soumet son dossier : l'étape suivante du circuit, ou l'étape
     * qui l'avait renvoyé s'il s'agit d'une nouvelle soumission.
     *
     * @param stdClass $dossier
     * @return int|null Null si le dossier n'est pas dans un statut où l'étudiant peut soumettre.
     */
    public static function submission_target(stdClass $dossier) {
        switch ((int) $dossier->status) {
            case THESE_STATUS_DRAFT:
                return THESE_STATUS_SUBJECT_SUBMITTED;
            case THESE_STATUS_ACTIVE:
                return THESE_STATUS_DEFENSE_SUBMITTED;
            case THESE_STATUS_SUBJECT_RETURNED:
                return (int) $dossier->returnstatus ?: THESE_STATUS_SUBJECT_SUBMITTED;
            case THESE_STATUS_DEFENSE_RETURNED:
                return (int) $dossier->returnstatus ?: THESE_STATUS_DEFENSE_SUBMITTED;
            case THESE_STATUS_CORRECTIONS:
                return (int) $dossier->returnstatus ?: THESE_STATUS_CORRECTIONS_SUBMITTED;
            default:
                return null;
        }
    }

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
        return these_dossier_operation($dossier, function () use ($dossier, $userid, $favorable) {
            return these_set_status($dossier, $dossier->status, $userid, 'reportsaved', '', [
                'reportfavorable' => $favorable ? 1 : 0,
            ]);
        });
    }

    /**
     * Signature transmise par un formulaire (voir these_add_signature_fields()).
     *
     * @param stdClass $data
     * @return stdClass|null {name, png}, ou null si aucune signature n'a été tracée.
     */
    public static function signature_from_form(stdClass $data) {
        global $USER;

        $png = !empty($data->usesavedsignature) ? these_get_saved_signature($USER->id) : null;
        if ($png === null) {
            $png = these_decode_signature_image($data->signaturedata ?? '');
            if ($png !== null && !empty($data->savesignature) && isloggedin() && !isguestuser()) {
                these_save_signature_image($USER->id, $png);
            }
        }
        if ($png === null) {
            return null;
        }
        return (object) ['name' => trim((string) ($data->signername ?? '')), 'png' => $png];
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
     * Valeur d'un champ soumis, ou valeur actuelle du dossier si le formulaire ne le contient pas.
     *
     * @param stdClass $data
     * @param stdClass $dossier
     * @param string $field
     * @return mixed
     */
    public static function submitted_value(stdClass $data, stdClass $dossier, $field) {
        if (property_exists($data, $field)) {
            return is_string($data->$field) ? trim($data->$field) : $data->$field;
        }
        return $dossier->$field ?? '';
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
     * Statut atteint à la soumission, compte tenu des signatures que les modifications viennent
     * d'invalider : un dossier renvoyé revient normalement à l'étape qui l'a renvoyé, mais si un
     * document signé par le directeur a changé, il repart chez le directeur pour qu'il le signe de
     * nouveau.
     *
     * @param stdClass $dossier Dossier avant changement de statut.
     * @param string[] $invalidated Signatures invalidées (« doctype:slot »).
     * @return int
     */
    public static function resubmission_target(stdClass $dossier, array $invalidated) {
        $target = these_submission_target($dossier);
        $directorstep = these_phase($dossier->status) === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
        foreach ($invalidated as $key) {
            if (substr($key, -strlen(':director')) === ':director' && $target > $directorstep) {
                return $directorstep;
            }
        }
        return $target;
    }

    /**
     * Enregistre la fiche de sujet (phase 1, étape 1) et, si demandé, la soumet signée.
     *
     * Toute modification du contenu invalide les signatures déjà apposées sur le formulaire de dépôt
     * de sujet (voir these_refresh_signatures()). À la soumission, la signature de l'étudiant est
     * apposée et, si le directeur est extérieur, sa signature lui est demandée par courriel.
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
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $data, $submit, $userid, $signature) {
            $context = context_module::instance($cm->id);
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
            $fields = self::people_fields($fields, $data);
            if (property_exists($data, 'commitguide')) {
                $fields['commitments'] = (!empty($data->commitguide) ? THESE_COMMIT_GUIDE : 0)
                    | (!empty($data->commitduplicate) ? THESE_COMMIT_DUPLICATE : 0)
                    | (!empty($data->commitethics) ? THESE_COMMIT_ETHICS : 0);
            }
            if (empty($fields['externaldirector'])) {
                foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
                    $fields[$field] = '';
                }
            }

            if (!$submit) {
                $dossier = these_set_status($dossier, $dossier->status, $userid, 'subjectsaved', '', $fields);
                these_refresh_signatures($context, $dossier);
                return $dossier;
            }

            $previous = clone $dossier;
            foreach ($fields as $field => $value) {
                $dossier->$field = $value;
            }
            $invalidated = these_refresh_signatures($context, $dossier);
            $target = these_resubmission_target($previous, $invalidated);
            $resubmitted = (int) $previous->status === THESE_STATUS_SUBJECT_RETURNED;
            $dossier = these_set_status(
                $previous,
                $target,
                $userid,
                $resubmitted ? 'subjectresubmitted' : 'subjectsubmitted',
                '',
                array_merge($fields, these_cleared_return_fields())
            );
            if ($signature !== null) {
                these_sign_document($context, $dossier, 'subject', 'student', $userid, $signature->name, $signature->png);
            }
            if (!empty($dossier->externaldirector)) {
                these_request_external_signatures($these, $cm, $dossier, 'subject');
            }
            these_notify_actor($these, $cm, $dossier);
            return $dossier;
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
        foreach (these_bordereau_fields() as $field) {
            if ($field === 'keywords') {
                if (property_exists($data, 'bkeyword')) {
                    $bordereau['keywords'] = array_values(array_filter(array_map('trim', (array) $data->bkeyword), 'strlen'));
                }
            } else if (property_exists($data, 'b' . $field)) {
                $value = $data->{'b' . $field};
                $bordereau[$field] = is_string($value) ? trim($value) : $value;
            }
        }
        // L'embargo dure désormais THESE_EMBARGO_YEARS ans après la soutenance (voir these_embargo_until()) :
        // une date saisie avec l'ancien formulaire ne vaut plus dès que l'étudiant enregistre le nouveau.
        if (property_exists($data, 'bembargo') && !property_exists($data, 'bembargountil')) {
            $bordereau['embargountil'] = 0;
        }
        if (empty($bordereau['embargo'])) {
            unset($bordereau['embargoreason']);
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
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $data, $submit, $userid, $signature) {
            $context = context_module::instance($cm->id);
            these_save_jury($dossier->id, these_extract_submitted_jury($data));

            $fields = [
                'proposeddate' => (int) $data->proposeddate,
                'titleen' => (string) these_submitted_value($data, $dossier, 'titleen'),
                'conclusions' => (string) these_submitted_value($data, $dossier, 'conclusions'),
                'bordereau' => json_encode(these_extract_bordereau($data, these_get_bordereau($dossier)), JSON_UNESCAPED_UNICODE),
            ];
            // Grille de conformité remplie par l'étudiant ; le directeur la vérifie et la signe ensuite.
            if (property_exists($data, 'ethicsnumber')) {
                $grid = [];
                foreach (these_get_grid_items(these_settings_scope($these)) as $items) {
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
                $dossier = these_set_status($dossier, $dossier->status, $userid, 'defensesaved', '', $fields);
                these_refresh_signatures($context, $dossier);
                return $dossier;
            }

            $previous = clone $dossier;
            foreach ($fields as $field => $value) {
                $dossier->$field = $value;
            }
            $invalidated = these_refresh_signatures($context, $dossier);
            $target = these_resubmission_target($previous, $invalidated);
            $resubmitted = (int) $previous->status === THESE_STATUS_DEFENSE_RETURNED;
            $dossier = these_set_status(
                $previous,
                $target,
                $userid,
                $resubmitted ? 'defenseresubmitted' : 'defensesubmitted',
                '',
                array_merge($fields, these_cleared_return_fields())
            );
            if ($signature !== null) {
                foreach (['bordereau', 'contract'] as $doctype) {
                    these_sign_document($context, $dossier, $doctype, 'student', $userid, $signature->name, $signature->png);
                }
            }
            these_notify_actor($these, $cm, $dossier);
            return $dossier;
        });
    }

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
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $fields, $remark, $signature) {
            $steps = these_review_steps();
            $step = $steps[(int) $dossier->status] ?? null;
            if ($step === null) {
                throw new coding_exception('Aucune étape de validation pour le statut ' . $dossier->status);
            }
            $context = context_module::instance($cm->id);

            $dossier = these_set_status(
                $dossier,
                $step['next'],
                $userid,
                'validated' . $step['key'],
                $remark,
                array_merge($fields, these_cleared_return_fields())
            );
            these_refresh_signatures($context, $dossier);
            $agentdoctypes = [];
            foreach ($step['sign'] as $slot => $doctypes) {
                if (!these_signs_slot_at_validation($slot, $userid, $these)) {
                    // Emplacement de l'agent DEVE : il signe ensuite, depuis « Mes signatures ».
                    $agentdoctypes = array_merge($agentdoctypes, $doctypes);
                    continue;
                }
                foreach ($doctypes as $doctype) {
                    if ($signature !== null && in_array($slot, these_document_slots($dossier, $doctype), true)) {
                        these_sign_document($context, $dossier, $doctype, $slot, $userid, $signature->name, $signature->png);
                    }
                }
            }
            if ($step['request']) {
                // Direction générale et université par délégation : demandées à leurs agents, s'il y en a,
                // dans le même courriel.
                $agentdoctypes = array_merge($agentdoctypes, $step['request']);
            }
            if ($agentdoctypes) {
                these_request_agent_signatures($these, $cm, $dossier, array_unique($agentdoctypes), $userid);
            }
            if ($step['request']) {
                // Un seul appel pour tous les documents : chaque signataire reçoit un seul lien.
                these_request_external_signatures($these, $cm, $dossier, $step['request']);
            }

            if ((int) $dossier->status === THESE_STATUS_ACTIVE) {
                these_notify_subject_active($these, $cm, $dossier);
            } else if ((int) $dossier->status === THESE_STATUS_CLOSED) {
                these_notify_closed($these, $cm, $dossier);
            } else {
                these_notify_actor($these, $cm, $dossier);
            }
            return $dossier;
        });
    }

    /**
     * Renvoie le dossier, depuis l'étape de validation courante, à l'étudiant ou au directeur.
     *
     * Renvoyé à l'étudiant, le dossier passe au statut « renvoyé » de sa phase et reviendra, à la
     * nouvelle soumission, directement à l'étape qui l'a renvoyé. Renvoyé au directeur (par la DEVE),
     * il revient à l'étape de validation du directeur de la même phase.
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
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $target, $comment) {
            $steps = these_review_steps();
            $step = $steps[(int) $dossier->status] ?? null;
            if ($step === null || !in_array($target, $step['returnto'], true)) {
                throw new coding_exception('Renvoi impossible depuis le statut ' . $dossier->status . ' vers ' . $target);
            }

            $phase = these_phase($dossier->status);
            $fields = [
                'returncomment' => $comment,
                'returnedby' => $userid,
                'returntime' => time(),
            ];
            if ($target === 'student' && these_in_corrections($dossier->status)) {
                $tostatus = THESE_STATUS_CORRECTIONS;
                $fields['returnstatus'] = (int) $dossier->status;
            } else if ($target === 'student') {
                $tostatus = $phase === 1 ? THESE_STATUS_SUBJECT_RETURNED : THESE_STATUS_DEFENSE_RETURNED;
                $fields['returnstatus'] = (int) $dossier->status;
            } else {
                $tostatus = $phase === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
                $fields['returnstatus'] = 0;
            }

            $dossier = these_set_status($dossier, $tostatus, $userid, 'returnedto' . $target, $comment, $fields);
            these_notify_returned($these, $cm, $dossier, $target, $comment);
            return $dossier;
        });
    }

    /**
     * Abandonne un dossier (DEVE). Le statut atteint est mémorisé pour une éventuelle réouverture.
     *
     * @param stdClass $dossier
     * @param int $userid
     * @param string $comment
     * @return stdClass
     */
    public static function cancel_dossier(stdClass $dossier, $userid, $comment) {
        return these_dossier_operation($dossier, function () use ($dossier, $userid, $comment) {
            return these_set_status($dossier, THESE_STATUS_CANCELLED, $userid, 'cancelled', $comment, [
                'cancelledfromstatus' => (int) $dossier->status,
            ]);
        });
    }

    /**
     * Rouvre un dossier abandonné dans le statut qu'il avait atteint.
     *
     * @param stdClass $dossier
     * @param int $userid
     * @param string $comment
     * @return stdClass
     */
    public static function reopen_dossier(stdClass $dossier, $userid, $comment = '') {
        return these_dossier_operation($dossier, function () use ($dossier, $userid, $comment) {
            return these_set_status($dossier, (int) $dossier->cancelledfromstatus, $userid, 'reopened', $comment, [
                'cancelledfromstatus' => 0,
            ]);
        });
    }

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
     * @return stdClass Le dossier à jour.
     */
    public static function record_minutes(
        stdClass $these,
        stdClass $cm,
        stdClass $dossier,
        $userid,
        stdClass $decision,
        $remark = ''
    ) {
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $decision, $remark) {
            $admitted = (int) $decision->result === THESE_RESULT_ADMITTED;
            $mention = (string) ($decision->mention ?? '');
            if ((int) $decision->result === THESE_RESULT_ADJOURNED) {
                return these_adjourn_dossier($these, $cm, $dossier, $userid, $remark);
            }
            $corrections = $admitted && !empty($decision->correctionsrequested);
            $fields = [
                'defenseresult' => (int) $decision->result,
                'mention' => $admitted && array_key_exists($mention, these_mention_options()) ? $mention : '',
                'prizeproposal' => $admitted && !empty($decision->prizeproposal) ? 1 : 0,
                'correctionsrequested' => $corrections ? 1 : 0,
                'correctionsnote' => $corrections ? trim((string) ($decision->correctionsnote ?? '')) : '',
            ];
            if ($corrections) {
                $dossier = these_set_status(
                    $dossier,
                    THESE_STATUS_CORRECTIONS,
                    $userid,
                    'minutescorrections',
                    $remark,
                    array_merge($fields, these_cleared_return_fields())
                );
                these_notify_actor($these, $cm, $dossier);
                return $dossier;
            }
            $dossier = these_set_status($dossier, THESE_STATUS_CLOSED, $userid, 'closed', $remark, $fields);
            these_notify_closed($these, $cm, $dossier);
            return $dossier;
        });
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
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $remark) {
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
            foreach (these_get_signatures($dossier->id) as $signature) {
                if ($signature->doctype !== 'subject') {
                    these_delete_signature($context, $signature);
                }
            }
            $DB->set_field('these_jury', 'notifiedtime', 0, ['dossierid' => $dossier->id]);
            $DB->set_field('these_jury', 'accesstoken', null, ['dossierid' => $dossier->id]);
            $DB->set_field('these_jury', 'eligible', 0, ['dossierid' => $dossier->id]);

            $dossier = these_set_status($dossier, THESE_STATUS_ACTIVE, $userid, 'adjourned', $remark, array_merge([
                'defenseresult' => THESE_RESULT_ADJOURNED,
                'mention' => '',
                'prizeproposal' => 0,
                'correctionsrequested' => 0,
                'correctionsnote' => '',
                'reportfavorable' => 0,
                'documentstime' => 0,
                'senttime' => 0,
            ], these_cleared_return_fields()));
            $vars = these_email_base_vars($these, $cm, $dossier);
            these_send_email($these, 'adjourned', these_get_student_and_director($dossier), $vars, '', '', $dossier);
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
     * Dépôt du manuscrit corrigé par l'étudiant (les fichiers sont enregistrés par l'appelant) : le
     * dossier passe au directeur, ou revient à l'étape qui l'avait renvoyé.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid
     * @param string $done Corrections apportées.
     * @return stdClass Le dossier à jour.
     */
    public static function submit_corrections(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $done) {
        return these_dossier_operation($dossier, function () use ($these, $cm, $dossier, $userid, $done) {
            if ((int) $dossier->status !== THESE_STATUS_CORRECTIONS) {
                throw new coding_exception('Dépôt des corrections impossible au statut ' . $dossier->status);
            }
            $resubmitted = !empty($dossier->returnstatus);
            $dossier = these_set_status(
                $dossier,
                these_submission_target($dossier),
                $userid,
                $resubmitted ? 'correctionsresubmitted' : 'correctionssubmitted',
                '',
                array_merge(['correctionsdone' => trim((string) $done)], these_cleared_return_fields())
            );
            these_notify_actor($these, $cm, $dossier);
            return $dossier;
        });
    }

    /**
     * Supprime des dossiers et tout ce qui leur est rattaché (jury, historique, fichiers).
     *
     * @param int[] $dossierids
     * @param context|null $context Contexte du module, pour supprimer aussi les fichiers. Sans
     *                              contexte (suppression de l'instance), Moodle s'en charge.
     * @return void
     */
    public static function delete_dossiers(array $dossierids, ?context $context = null) {
        global $DB;

        if (empty($dossierids)) {
            return;
        }
        if ($context !== null) {
            $fs = get_file_storage();
            foreach ($dossierids as $dossierid) {
                foreach (array_merge(THESE_FILEAREAS, ['sealed', university::FILEAREA]) as $filearea) {
                    $fs->delete_area_files($context->id, 'mod_these', $filearea, $dossierid);
                }
            }
        }
        [$insql, $inparams] = $DB->get_in_or_equal($dossierids);
        $signatureids = $DB->get_fieldset_select('these_signature', 'id', "dossierid $insql", $inparams);
        if ($context !== null) {
            foreach ($signatureids as $signatureid) {
                get_file_storage()->delete_area_files($context->id, 'mod_these', 'signature', $signatureid);
            }
        }
        $DB->delete_records_select('these_signature', "dossierid $insql", $inparams);
        $DB->delete_records_select('these_jury', "dossierid $insql", $inparams);
        $DB->delete_records_select('these_answer', "dossierid $insql", $inparams);
        $DB->delete_records_select('these_subjecthistory', "dossierid $insql", $inparams);
        $DB->delete_records_select('these_log', "dossierid $insql", $inparams);
        $DB->delete_records_select('these_dossier', "id $insql", $inparams);
    }

    /**
     * Propose le numéro d'enregistrement DEVE suivant, au format AAAA-NNN : l'année en cours et le
     * premier numéro d'ordre libre de cette année dans l'activité.
     *
     * @param int $theseid
     * @param int|null $time Date de référence (année), maintenant par défaut.
     * @return string
     */
    public static function suggest_devenumber($theseid, $time = null) {
        global $DB;

        $year = userdate($time ?? time(), '%Y');
        $numbers = $DB->get_fieldset_select(
            'these_dossier',
            'devenumber',
            'theseid = ? AND ' . $DB->sql_like('devenumber', '?'),
            [$theseid, $year . '-%']
        );
        $max = 0;
        foreach ($numbers as $number) {
            if (preg_match('/^' . $year . '-(\d+)$/', (string) $number, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }
        return $year . '-' . sprintf('%03d', $max + 1);
    }

    /**
     * Indique si un numéro DEVE est déjà attribué à un autre dossier de l'activité.
     *
     * @param int $theseid
     * @param string $number
     * @param int $excludeid Dossier à ignorer (celui qu'on enregistre).
     * @return bool
     */
    public static function devenumber_taken($theseid, $number, $excludeid = 0) {
        global $DB;

        return $DB->record_exists_select(
            'these_dossier',
            'theseid = ? AND devenumber = ? AND id <> ?',
            [$theseid, trim($number), $excludeid]
        );
    }
}
