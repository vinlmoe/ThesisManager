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
use mod_these\event\dossier_created;
use mod_these\event\dossier_event;
use mod_these\event\dossier_status_changed;
use moodle_exception;
use stdClass;

/**
 * Cycle de vie des dossiers : création, changements de statut, opérations atomiques, abandon et
 * réouverture, suppression, numéro DEVE. Les étapes du circuit sont dans subjects (sujet), defenses
 * (soutenance), reviews (validations et renvois) et minutes (procès-verbal et corrections).
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
        self::log($dossier->id, $actorid, 'created', THESE_STATUS_DRAFT, THESE_STATUS_DRAFT);

        $dossier = $DB->get_record('these_dossier', ['id' => $dossier->id], '*', MUST_EXIST);
        if ($context = dossier_event::context_for($dossier)) {
            dossier_created::create_from_dossier($dossier, $context, $actorid)->trigger();
        }
        return $dossier;
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
        if (!workflow::transition_allowed($fromstatus, $tostatus)) {
            throw new coding_exception('Transition de statut interdite : ' . $fromstatus . ' vers ' . (int) $tostatus);
        }
        $update = array_merge($fields, ['id' => $dossier->id, 'status' => (int) $tostatus, 'timemodified' => $now]);
        if ((int) $tostatus !== $fromstatus) {
            $update['statustime'] = $now;
            $update['remindertime'] = 0;
        }
        $DB->update_record('these_dossier', (object) $update);
        self::log($dossier->id, $userid, $action, $fromstatus, $tostatus, $remark);
        if (in_array((int) $tostatus, [THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED], true)) {
            // Dossier clos ou abandonné : les liens d'accès du jury sont révoqués.
            $DB->set_field('these_jury', 'accesstoken', null, ['dossierid' => $dossier->id]);
        }

        foreach ($update as $field => $value) {
            $dossier->$field = $value;
        }
        // Soutenance au calendrier : date arrêtée, déplacée, ajournée ou dossier abandonné.
        calendar::sync($dossier);
        if ((int) $tostatus !== $fromstatus && ($context = dossier_event::context_for($dossier))) {
            dossier_status_changed::create_from_dossier($dossier, $context, $userid, [
                'from' => $fromstatus, 'to' => (int) $tostatus, 'action' => $action,
            ])->trigger();
        }
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
     * Signature transmise par un formulaire (voir these_add_signature_fields()).
     *
     * @param stdClass $data
     * @return stdClass|null {name, png}, ou null si aucune signature n'a été tracée.
     */
    public static function signature_from_form(stdClass $data) {
        global $USER;

        $png = !empty($data->usesavedsignature) ? signatureagent::get_saved_signature($USER->id) : null;
        if ($png === null) {
            $png = signatures::decode_signature_image($data->signaturedata ?? '');
            if ($png !== null && !empty($data->savesignature) && isloggedin() && !isguestuser()) {
                signatureagent::save_signature_image($USER->id, $png);
            }
        }
        if ($png === null) {
            return null;
        }
        return (object) ['name' => trim((string) ($data->signername ?? '')), 'png' => $png];
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
        $target = self::submission_target($dossier);
        $directorstep = workflow::phase($dossier->status) === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
        foreach ($invalidated as $key) {
            if (substr($key, -strlen(':director')) === ':director' && $target > $directorstep) {
                return $directorstep;
            }
        }
        return $target;
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
        return self::dossier_operation($dossier, function () use ($dossier, $userid, $comment) {
            return self::set_status($dossier, THESE_STATUS_CANCELLED, $userid, 'cancelled', $comment, [
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
        return self::dossier_operation($dossier, function () use ($dossier, $userid, $comment) {
            return self::set_status($dossier, (int) $dossier->cancelledfromstatus, $userid, 'reopened', $comment, [
                'cancelledfromstatus' => 0,
            ]);
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
