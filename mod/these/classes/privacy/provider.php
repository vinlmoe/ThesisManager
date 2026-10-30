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

/**
 * Privacy provider for mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_these\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_these\local\dossiers;
use mod_these\local\jury;
use mod_these\local\questionnaire;
use mod_these\local\signatureagent;
use mod_these\local\signatures;
use mod_these\local\subjectchange;
use mod_these\local\university;
use mod_these\local\workflow;
use mod_these\local\bordereau;
use mod_these\local\seals;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/lib.php');

/**
 * Fournisseur de données personnelles de mod_these.
 *
 * Comme dans mod_stage, un utilisateur apparaît sous deux rôles qui n'appellent pas le même
 * traitement :
 *
 * - **l'étudiant**, propriétaire du dossier (these_dossier.userid) : sa suppression efface le
 *   dossier entier (sujet, soutenance, jury, historique, fichiers), sauf si son procès-verbal de
 *   soutenance est enregistré (voir ci-dessous) ;
 * - **le personnel** (directeur, DEVE, bibliothèque), simplement *cité* dans le dossier d'un
 *   étudiant (directeur, auteur d'un renvoi ou d'une action de l'historique) : ses références sont
 *   dissociées et les commentaires qu'il a rédigés effacés, le dossier de l'étudiant restant
 *   intact.
 *
 * Les membres du jury, souvent extérieurs, n'ont pas de compte Moodle : leurs coordonnées font
 * partie du dossier de l'étudiant et suivent son sort.
 *
 * **Conservation des procès-verbaux.** Le procès-verbal de soutenance est un acte administratif :
 * archive publique que l'établissement est tenu de conserver (code du patrimoine, livre II, et
 * règles de gestion des archives des établissements d'enseignement supérieur), ce qui, au sens de
 * l'article 17, paragraphe 3, b) et d) du RGPD, fait exception au droit à l'effacement. Il n'est
 * donc ni effacé ni anonymisé. Le procès-verbal d'un dossier est enregistré dès que la DEVE a
 * déposé le PV scanné (zone pv), qu'un PV d'ajournement a été archivé (zone pvadjourned) ou qu'une
 * décision du jury est reportée (these_dossier.defenseresult). Pour un tel dossier, la suppression
 * de l'étudiant conserve l'enregistrement du dossier réduit aux champs du procès-verbal (étudiant,
 * titre, directeur, date et lieu de soutenance, numéro DEVE, décisions du jury), son jury et les
 * fichiers du PV ; tout le reste est effacé (résumé, mots-clés, bordereau, grille, agrément,
 * conclusions, commentaires, autres fichiers déposés ou générés, documents scellés et signatures,
 * réponses au questionnaire, historique et journaux, liens d'accès du jury). Le PV, document papier
 * signé par le jury puis scanné, ne porte aucune signature ni aucun scellé du module : la
 * suppression d'un membre du personnel ne le modifie donc jamais. Sans procès-verbal enregistré, le
 * dossier est effacé en entier.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] Zones de fichiers du procès-verbal, conservées à la suppression de l'étudiant. */
    const MINUTES_FILEAREAS = ['pv', 'pvadjourned'];

    /**
     * Décrit les données personnelles stockées par le plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('these_dossier', [
            'userid' => 'privacy:metadata:these_dossier:userid',
            'status' => 'privacy:metadata:these_dossier:status',
            'title' => 'privacy:metadata:these_dossier:title',
            'summary' => 'privacy:metadata:these_dossier:summary',
            'keywords' => 'privacy:metadata:these_dossier:keywords',
            'directorid' => 'privacy:metadata:these_dossier:directorid',
            'externaldirector' => 'privacy:metadata:these_dossier:externaldirector',
            'extdirectorname' => 'privacy:metadata:these_dossier:extdirectorname',
            'extdirectorgrade' => 'privacy:metadata:these_dossier:extdirectorgrade',
            'extdirectorinstitution' => 'privacy:metadata:these_dossier:extdirectorinstitution',
            'extdirectoremail' => 'privacy:metadata:these_dossier:extdirectoremail',
            'devenumber' => 'privacy:metadata:these_dossier:devenumber',
            'defensedate' => 'privacy:metadata:these_dossier:defensedate',
            'defenseresult' => 'privacy:metadata:these_dossier:defenseresult',
            'returncomment' => 'privacy:metadata:these_dossier:returncomment',
            'returnedby' => 'privacy:metadata:these_dossier:returnedby',
            'daterequestcomment' => 'privacy:metadata:these_dossier:daterequestcomment',
            'bordereau' => 'privacy:metadata:these_dossier:bordereau',
            'conclusions' => 'privacy:metadata:these_dossier:conclusions',
            'correctionsnote' => 'privacy:metadata:these_dossier:correctionsnote',
            'correctionsdone' => 'privacy:metadata:these_dossier:correctionsdone',
            'timecreated' => 'privacy:metadata:these_dossier:timecreated',
        ], 'privacy:metadata:these_dossier');

        $collection->add_database_table('these_signature', [
            'userid' => 'privacy:metadata:these_signature:userid',
            'signername' => 'privacy:metadata:these_signature:signername',
            'signeremail' => 'privacy:metadata:these_signature:signeremail',
            'token' => 'privacy:metadata:these_signature:token',
            'contenthash' => 'privacy:metadata:these_signature:contenthash',
            'ipaddress' => 'privacy:metadata:these_signature:ipaddress',
            'timesigned' => 'privacy:metadata:these_signature:timesigned',
            'verifiedby' => 'privacy:metadata:these_signature:verifiedby',
            'validatedby' => 'privacy:metadata:these_signature:validatedby',
        ], 'privacy:metadata:these_signature');

        $collection->add_database_table('these_jury', [
            'fullname' => 'privacy:metadata:these_jury:fullname',
            'email' => 'privacy:metadata:these_jury:email',
            'institution' => 'privacy:metadata:these_jury:institution',
            'jurystatus' => 'privacy:metadata:these_jury:jurystatus',
            'eligibleby' => 'privacy:metadata:these_jury:eligibleby',
            'accesstoken' => 'privacy:metadata:these_jury:accesstoken',
            'userid' => 'privacy:metadata:these_jury:userid',
        ], 'privacy:metadata:these_jury');

        // Annuaire du jury : enseignants désignés par leur nom et leur adresse, sans lien avec un compte.
        $collection->add_database_table('these_juror', [
            'title' => 'privacy:metadata:these_juror:title',
            'lastname' => 'privacy:metadata:these_juror:lastname',
            'firstname' => 'privacy:metadata:these_juror:firstname',
            'email' => 'privacy:metadata:these_juror:email',
            'status' => 'privacy:metadata:these_juror:status',
            'institution' => 'privacy:metadata:these_juror:institution',
            'discipline' => 'privacy:metadata:these_juror:discipline',
        ], 'privacy:metadata:these_juror');

        $collection->add_database_table('these_a6', [
            'userid' => 'privacy:metadata:these_a6:userid',
            'a6date' => 'privacy:metadata:these_a6:a6date',
            'a6school' => 'privacy:metadata:these_a6:a6school',
            'defvdate' => 'privacy:metadata:these_a6:defvdate',
            'defvschool' => 'privacy:metadata:these_a6:defvschool',
            'usermodified' => 'privacy:metadata:these_a6:usermodified',
            'timemodified' => 'privacy:metadata:these_a6:timemodified',
        ], 'privacy:metadata:these_a6');

        $collection->add_database_table('these_subjecthistory', [
            'title' => 'privacy:metadata:these_subjecthistory:title',
            'summary' => 'privacy:metadata:these_subjecthistory:summary',
            'directorid' => 'privacy:metadata:these_subjecthistory:directorid',
            'directorname' => 'privacy:metadata:these_subjecthistory:directorname',
            'reason' => 'privacy:metadata:these_subjecthistory:reason',
            'changedby' => 'privacy:metadata:these_subjecthistory:changedby',
            'timecreated' => 'privacy:metadata:these_subjecthistory:timecreated',
        ], 'privacy:metadata:these_subjecthistory');

        $collection->add_database_table('these_answer', [
            'questionid' => 'privacy:metadata:these_answer:questionid',
            'value' => 'privacy:metadata:these_answer:value',
            'timemodified' => 'privacy:metadata:these_answer:timemodified',
        ], 'privacy:metadata:these_answer');

        $collection->add_database_table('these_log', [
            'userid' => 'privacy:metadata:these_log:userid',
            'logaction' => 'privacy:metadata:these_log:logaction',
            'remark' => 'privacy:metadata:these_log:remark',
            'timecreated' => 'privacy:metadata:these_log:timecreated',
        ], 'privacy:metadata:these_log');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');

        $collection->add_external_location_link('jury', [
            'student' => 'privacy:metadata:jury:student',
            'title' => 'privacy:metadata:jury:title',
            'defensedate' => 'privacy:metadata:jury:defensedate',
        ], 'privacy:metadata:jury');

        // Université : les documents de soutenance (fiche signalétique, conclusions, contrat de
        // diffusion, PV) lui sont transmis par courriel après la soutenance.
        $collection->add_external_location_link('university', [
            'student' => 'privacy:metadata:university:student',
            'title' => 'privacy:metadata:university:title',
            'defensedate' => 'privacy:metadata:university:defensedate',
            'documents' => 'privacy:metadata:university:documents',
        ], 'privacy:metadata:university');

        // Signataires extérieurs (directeur extérieur, direction générale, CCEM, université) : lien
        // personnel vers le document à signer.
        $collection->add_external_location_link('externalsigners', [
            'student' => 'privacy:metadata:externalsigners:student',
            'title' => 'privacy:metadata:externalsigners:title',
            'document' => 'privacy:metadata:externalsigners:document',
        ], 'privacy:metadata:externalsigners');

        // Boîtes de service (DEVE, bibliothèque) : notifications du circuit.
        $collection->add_external_location_link('servicemailboxes', [
            'student' => 'privacy:metadata:servicemailboxes:student',
            'title' => 'privacy:metadata:servicemailboxes:title',
            'status' => 'privacy:metadata:servicemailboxes:status',
        ], 'privacy:metadata:servicemailboxes');

        return $collection;
    }

    /**
     * Requête des contextes où l'utilisateur intervient, quel que soit son rôle.
     *
     * @return string
     */
    protected static function contexts_sql() {
        return "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {these_dossier} d ON d.theseid = cm.instance
                 WHERE d.userid = :userid1
                    OR d.directorid = :userid2
                    OR d.returnedby = :userid3
                    OR EXISTS (SELECT 1 FROM {these_log} l WHERE l.dossierid = d.id AND l.userid = :userid4)
                    OR EXISTS (SELECT 1 FROM {these_signature} sg
                                WHERE sg.dossierid = d.id
                                  AND (sg.userid = :userid5 OR sg.verifiedby = :userid6 OR sg.validatedby = :userid7))
                    OR EXISTS (SELECT 1 FROM {these_jury} j
                                WHERE j.dossierid = d.id AND (j.eligibleby = :userid8 OR j.userid = :userid11))
                    OR EXISTS (SELECT 1 FROM {these_subjecthistory} h
                                WHERE h.dossierid = d.id AND (h.directorid = :userid9 OR h.changedby = :userid10))";
    }

    /**
     * Paramètres de contexts_sql() pour un utilisateur.
     *
     * @param int $userid
     * @return array
     */
    protected static function contexts_params(int $userid) {
        $params = ['contextlevel' => CONTEXT_MODULE, 'modname' => 'these'];
        for ($i = 1; $i <= 11; $i++) {
            $params['userid' . $i] = $userid;
        }
        return $params;
    }

    /**
     * Contextes dans lesquels l'utilisateur donné a des données.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(self::contexts_sql(), self::contexts_params($userid));
        // Signature enregistrée et validation de l'A6, dans le contexte de l'utilisateur.
        if (signatureagent::get_saved_signature($userid) !== null || self::has_a6($userid)) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    /**
     * Utilisateurs ayant des données dans le contexte donné.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (
            $context instanceof \context_user
                && (signatureagent::get_saved_signature($context->instanceid) !== null || self::has_a6($context->instanceid))
        ) {
            $userlist->add_user($context->instanceid);
            return;
        }
        if (!$context instanceof context_module) {
            return;
        }
        $params = ['instanceid' => $context->instanceid, 'modname' => 'these'];
        $base = "FROM {course_modules} cm
                 JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 JOIN {these_dossier} d ON d.theseid = cm.instance
                WHERE cm.id = :instanceid";
        foreach (['userid', 'directorid', 'returnedby'] as $field) {
            $userlist->add_from_sql($field, "SELECT d.$field $base", $params);
        }
        $userlist->add_from_sql('userid', "SELECT l.userid
                                             FROM {course_modules} cm
                                             JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                                             JOIN {these_dossier} d ON d.theseid = cm.instance
                                             JOIN {these_log} l ON l.dossierid = d.id
                                            WHERE cm.id = :instanceid", $params);
        $related = [
            'these_signature' => ['userid', 'verifiedby', 'validatedby'],
            'these_jury' => ['eligibleby', 'userid'],
            'these_subjecthistory' => ['directorid', 'changedby'],
        ];
        foreach ($related as $table => $fields) {
            foreach ($fields as $field) {
                $userlist->add_from_sql($field, "SELECT r.$field
                                                   FROM {course_modules} cm
                                                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                                                   JOIN {these_dossier} d ON d.theseid = cm.instance
                                                   JOIN {{$table}} r ON r.dossierid = d.id
                                                  WHERE cm.id = :instanceid AND r.$field > 0", $params);
            }
        }
    }

    /**
     * Exporte les données de l'utilisateur pour les contextes approuvés : son propre dossier s'il
     * est étudiant, et ses interventions dans les dossiers des autres s'il est membre du personnel.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int) $context->instanceid === (int) $user->id) {
                self::export_user_context_data($context, (int) $user->id);
                continue;
            }
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('these', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            self::export_student_dossier($context, $cm, (int) $user->id);
            self::export_staff_data($context, $cm, $user->id);
            self::export_signatures($context, $cm, (int) $user->id);
        }
    }

    /**
     * Exporte les données du contexte utilisateur : validation des études (A6) et signature
     * enregistrée.
     *
     * @param \context_user $context
     * @param int $userid
     * @return void
     */
    protected static function export_user_context_data(\context_user $context, int $userid) {
        global $DB;

        $a6 = $DB->get_record('these_a6', ['userid' => $userid]);
        if ($a6) {
            writer::with_context($context)->export_data([get_string('studiesvalidation', 'mod_these')], (object) [
                'defvdate' => $a6->defvdate ? transform::date($a6->defvdate) : null,
                'defvschool' => (string) $a6->defvschool,
                'a6date' => $a6->a6date ? transform::date($a6->a6date) : get_string('a6notvalidated', 'mod_these'),
                'a6school' => (string) $a6->a6school,
                'timemodified' => transform::datetime($a6->timemodified),
            ]);
        }
        writer::with_context($context)->export_area_files(
            [get_string('signaturesaved', 'mod_these')],
            'mod_these',
            'savedsignature',
            0
        );
    }

    /**
     * Exporte le dossier de l'utilisateur dans l'activité, s'il en a un : fiche, jury, sujets
     * précédents, réponses au questionnaire, historique et fichiers.
     *
     * @param context_module $context
     * @param \stdClass $cm
     * @param int $userid
     * @return void
     */
    protected static function export_student_dossier(context_module $context, $cm, int $userid) {
        global $DB;

        $dossier = $DB->get_record('these_dossier', ['theseid' => $cm->instance, 'userid' => $userid]);
        if (!$dossier) {
            return;
        }
        $subcontext = [get_string('mydossier', 'mod_these')];
        writer::with_context($context)->export_data($subcontext, self::dossier_export_record($dossier));
        self::export_dossier_part($context, $subcontext, 'jury', 'jury', array_map(function ($member) {
            return (object) [
                'role' => $member->juryrole,
                'fullname' => $member->fullname,
                'title' => $member->jurytitle,
                'institution' => $member->institution,
                'email' => $member->email,
            ];
        }, jury::get_jury($dossier->id)));
        self::export_dossier_part($context, $subcontext, 'subjecthistory', 'subjects', array_map(function ($entry) {
            return (object) [
                'title' => $entry->title,
                'summary' => $entry->summary,
                'keywords' => $entry->keywords,
                'director' => $entry->directorname,
                'reason' => $entry->reason,
                'time' => transform::datetime($entry->timecreated),
            ];
        }, subjectchange::get_subject_history($dossier->id)));
        self::export_dossier_part($context, $subcontext, 'questionnaire', 'answers', self::dossier_answers($dossier->id));
        $history = array_map(function ($log) {
            return (object) [
                'action' => $log->logaction,
                'status' => workflow::status_label($log->tostatus),
                'remark' => $log->remark,
                'time' => transform::datetime($log->timecreated),
            ];
        }, array_values($DB->get_records('these_log', ['dossierid' => $dossier->id], 'timecreated ASC, id ASC')));
        // L'historique est exporté même vide, comme avant.
        writer::with_context($context)->export_data(
            array_merge($subcontext, [get_string('history', 'mod_these')]),
            (object) ['history' => $history]
        );
        self::export_dossier_files($context, $subcontext, (int) $dossier->id);
    }

    /**
     * Fiche du dossier telle qu'exportée à l'étudiant.
     *
     * @param \stdClass $dossier
     * @return \stdClass
     */
    protected static function dossier_export_record(\stdClass $dossier) {
        return (object) [
            'status' => workflow::status_label($dossier->status),
            'title' => $dossier->title,
            'titleen' => $dossier->titleen,
            'summary' => $dossier->summary,
            'keywords' => $dossier->keywords,
            'devenumber' => $dossier->devenumber,
            'libraryref' => $dossier->libraryref,
            'proposeddate' => $dossier->proposeddate ? transform::datetime($dossier->proposeddate) : null,
            'defensedate' => $dossier->defensedate ? transform::datetime($dossier->defensedate) : null,
            'defenselocation' => $dossier->defenselocation,
            'defenseresult' => (int) $dossier->defenseresult ? workflow::result_label($dossier->defenseresult) : null,
            'mention' => workflow::mention_label($dossier->mention),
            'prizeproposal' => transform::yesno($dossier->prizeproposal),
            'correctionsnote' => $dossier->correctionsnote,
            'correctionsdone' => $dossier->correctionsdone,
            'returncomment' => $dossier->returncomment,
            'conclusions' => $dossier->conclusions,
            'personaldata' => bordereau::get_bordereau($dossier),
            'timecreated' => transform::datetime($dossier->timecreated),
        ];
    }

    /**
     * Exporte une partie du dossier sous son propre sous-contexte, si elle n'est pas vide.
     *
     * @param context_module $context
     * @param string[] $subcontext Sous-contexte du dossier.
     * @param string $titlekey Chaîne du titre du sous-contexte.
     * @param string $property Nom de la liste exportée.
     * @param array $items
     * @return void
     */
    protected static function export_dossier_part(
        context_module $context,
        array $subcontext,
        string $titlekey,
        string $property,
        array $items
    ) {
        $items = array_values($items);
        if ($items) {
            writer::with_context($context)->export_data(
                array_merge($subcontext, [get_string($titlekey, 'mod_these')]),
                (object) [$property => $items]
            );
        }
    }

    /**
     * Réponses au questionnaire du dossier, avec le libellé de chaque question.
     *
     * @param int $dossierid
     * @return \stdClass[]
     */
    protected static function dossier_answers(int $dossierid) {
        global $DB;

        $answers = [];
        foreach (questionnaire::get_answers($dossierid) as $questionid => $answer) {
            $answers[] = (object) [
                'question' => (string) $DB->get_field('these_question', 'label', ['id' => $questionid]),
                'answer' => questionnaire::answer_text($answer),
            ];
        }
        return $answers;
    }

    /**
     * Exporte les fichiers du dossier et ses documents scellés, sauf ceux réservés au personnel.
     *
     * @param context_module $context
     * @param string[] $subcontext
     * @param int $dossierid
     * @return void
     */
    protected static function export_dossier_files(context_module $context, array $subcontext, int $dossierid) {
        foreach (THESE_FILEAREAS as $filearea) {
            // Le rapport du directeur ne s'adresse pas à l'étudiant (voir
            // these_can_view_filearea()) : il n'est pas exporté avec son dossier.
            if ($filearea !== 'directorreport') {
                writer::with_context($context)->export_area_files($subcontext, 'mod_these', $filearea, $dossierid);
            }
        }
        // Documents scellés, sauf ceux réservés au personnel (rapport, grille).
        $definitions = signatures::document_definitions();
        $sealed = get_file_storage()->get_area_files($context->id, 'mod_these', 'sealed', $dossierid, 'filename', false);
        foreach ($sealed as $file) {
            $doctype = pathinfo($file->get_filename(), PATHINFO_FILENAME);
            if (empty($definitions[$doctype]['staffonly'])) {
                writer::with_context($context)->export_file($subcontext, $file);
            }
        }
    }

    /**
     * Exporte les signatures apposées par l'utilisateur, dans son dossier comme dans ceux des autres.
     *
     * @param context_module $context
     * @param \stdClass $cm
     * @param int $userid
     * @return void
     */
    protected static function export_signatures(context_module $context, $cm, int $userid) {
        global $DB;

        $signatures = $DB->get_records_sql(
            "SELECT sg.*, d.title
               FROM {these_signature} sg
               JOIN {these_dossier} d ON d.id = sg.dossierid
              WHERE d.theseid = :theseid AND sg.userid = :userid AND sg.timesigned > 0",
            ['theseid' => $cm->instance, 'userid' => $userid]
        );
        foreach ($signatures as $signature) {
            $subcontext = [get_string('signatures', 'mod_these'), $signature->id];
            writer::with_context($context)->export_data($subcontext, (object) [
                'dossier' => $signature->title,
                'document' => signatures::document_label($signature->doctype),
                'slot' => signatures::slot_label($signature->slot),
                'signername' => $signature->signername,
                'ipaddress' => $signature->ipaddress,
                'timesigned' => transform::datetime($signature->timesigned),
            ]);
            writer::with_context($context)->export_area_files($subcontext, 'mod_these', 'signature', $signature->id);
        }
    }

    /**
     * Exporte, pour un membre du personnel, ses interventions dans les dossiers des autres : les
     * dossiers qu'il dirige, les jurys où il siège par son compte rattaché et les actions qu'il a
     * menées, avec ses commentaires.
     *
     * @param context_module $context
     * @param \stdClass $cm
     * @param int $userid
     * @return void
     */
    protected static function export_staff_data(context_module $context, $cm, int $userid) {
        global $DB;

        $sql = "SELECT l.*, d.title
                  FROM {these_log} l
                  JOIN {these_dossier} d ON d.id = l.dossierid
                 WHERE d.theseid = :theseid AND d.userid <> :self AND l.userid = :userid
              ORDER BY l.timecreated ASC, l.id ASC";
        $logs = $DB->get_records_sql($sql, ['theseid' => $cm->instance, 'self' => $userid, 'userid' => $userid]);
        $directed = $DB->get_records_select(
            'these_dossier',
            'theseid = ? AND directorid = ? AND userid <> ?',
            [$cm->instance, $userid, $userid],
            'id',
            'id, title'
        );
        $juries = $DB->get_records_sql(
            "SELECT j.id, j.juryrole, d.title
               FROM {these_jury} j
               JOIN {these_dossier} d ON d.id = j.dossierid
              WHERE d.theseid = :theseid AND d.userid <> :self AND j.userid = :userid
           ORDER BY j.id",
            ['theseid' => $cm->instance, 'self' => $userid, 'userid' => $userid]
        );
        if (!$logs && !$directed && !$juries) {
            return;
        }
        writer::with_context($context)->export_data([get_string('staffactions', 'mod_these')], (object) [
            'directed' => array_values(array_map(function ($dossier) {
                return $dossier->title;
            }, $directed)),
            'juries' => array_values(array_map(function ($member) {
                return (object) ['dossier' => $member->title, 'role' => $member->juryrole];
            }, $juries)),
            'actions' => array_values(array_map(function ($log) {
                return (object) [
                    'dossier' => $log->title,
                    'action' => $log->logaction,
                    'remark' => $log->remark,
                    'time' => transform::datetime($log->timecreated),
                ];
            }, $logs)),
        ]);
    }

    /**
     * Supprime toutes les données du contexte.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context instanceof \context_user) {
            signatureagent::delete_saved_signature($context->instanceid);
            $DB->delete_records('these_a6', ['userid' => $context->instanceid]);
            return;
        }
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('these', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        self::delete_student_dossiers($context, $DB->get_fieldset_select('these_dossier', 'id', 'theseid = ?', [$cm->instance]));
    }

    /**
     * Supprime les données d'un utilisateur dans les contextes approuvés.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Supprime les données de plusieurs utilisateurs dans un contexte.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Efface les dossiers dont les utilisateurs sont les étudiants, et dissocie leurs références
     * de personnel dans les dossiers des autres.
     *
     * @param context $context
     * @param int[] $userids
     * @return void
     */
    protected static function delete_users_in_context(context $context, array $userids) {
        global $DB;

        if ($context instanceof \context_user) {
            if (in_array((int) $context->instanceid, array_map('intval', $userids), true)) {
                signatureagent::delete_saved_signature($context->instanceid);
                $DB->delete_records('these_a6', ['userid' => $context->instanceid]);
            }
            return;
        }

        if (!$context instanceof context_module || empty($userids)) {
            return;
        }
        $cm = get_coursemodule_from_id('these', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['theseid' => $cm->instance]);

        // Dossiers propres : supprimés intégralement, sauf le procès-verbal enregistré.
        self::delete_student_dossiers(
            $context,
            $DB->get_fieldset_select('these_dossier', 'id', "theseid = :theseid AND userid $insql", $params)
        );

        // Références de personnel dans les dossiers restants.
        $dossierids = $DB->get_fieldset_select('these_dossier', 'id', 'theseid = ?', [$cm->instance]);
        if (!$dossierids) {
            return;
        }
        [$dsql, $dparams] = $DB->get_in_or_equal($dossierids, SQL_PARAMS_NAMED, 'd');
        $DB->set_field_select('these_dossier', 'directorid', 0, "id $dsql AND directorid $insql", array_merge($dparams, $inparams));
        $DB->execute(
            "UPDATE {these_dossier} SET returnedby = 0, returncomment = '' WHERE id $dsql AND returnedby $insql",
            array_merge($dparams, $inparams)
        );
        $DB->execute(
            "UPDATE {these_log} SET userid = 0, remark = '' WHERE dossierid $dsql AND userid $insql",
            array_merge($dparams, $inparams)
        );
        // Comptes cités sans être signataires : validation d'éligibilité, compte rattaché à un membre
        // du jury (qui garde son nom, son adresse et son lien), ancien directeur ou auteur d'un
        // changement de sujet, vérification d'identité ou validation d'une signature.
        $DB->execute(
            "UPDATE {these_subjecthistory} SET directorid = 0, directorname = '' WHERE dossierid $dsql AND directorid $insql",
            array_merge($dparams, $inparams)
        );
        $cited = [
            'these_jury' => ['eligibleby', 'userid'],
            'these_subjecthistory' => ['changedby'],
            'these_signature' => ['verifiedby', 'validatedby'],
        ];
        foreach ($cited as $table => $fields) {
            foreach ($fields as $field) {
                $DB->set_field_select($table, $field, 0, "dossierid $dsql AND $field $insql", array_merge($dparams, $inparams));
            }
        }
        // Signatures du personnel dans les dossiers d'autrui : l'image et l'identité sont effacées,
        // la date de signature est conservée (le document reste marqué comme signé à cette date).
        $signatures = $DB->get_records_select(
            'these_signature',
            "dossierid $dsql AND userid $insql",
            array_merge($dparams, $inparams)
        );
        foreach ($signatures as $signature) {
            get_file_storage()->delete_area_files($context->id, 'mod_these', 'signature', $signature->id);
            // Le document scellé reproduit la signature : il sera scellé de nouveau sans elle.
            seals::unseal_document($context, $signature->dossierid, $signature->doctype);
            $DB->update_record('these_signature', (object) [
                'id' => $signature->id, 'userid' => 0, 'signername' => '', 'signeremail' => '', 'ipaddress' => '',
            ]);
        }
    }

    /**
     * Efface des dossiers d'étudiants : en entier, ou, si leur procès-verbal est enregistré, tout
     * sauf ce procès-verbal (voir la description de la classe).
     *
     * @param context_module $context
     * @param int[] $dossierids
     * @return void
     */
    protected static function delete_student_dossiers(context_module $context, array $dossierids) {
        $withminutes = array_filter($dossierids, function ($dossierid) use ($context) {
            return self::has_minutes($context, (int) $dossierid);
        });
        dossiers::delete_dossiers(array_values(array_diff($dossierids, $withminutes)), $context);
        foreach ($withminutes as $dossierid) {
            self::keep_minutes_only($context, (int) $dossierid);
        }
    }

    /**
     * Le procès-verbal de soutenance du dossier est-il enregistré : PV scanné, PV d'ajournement
     * archivé ou décision du jury reportée ?
     *
     * @param context_module $context
     * @param int $dossierid
     * @return bool
     */
    protected static function has_minutes(context_module $context, int $dossierid): bool {
        global $DB;

        $fs = get_file_storage();
        return !$fs->is_area_empty($context->id, 'mod_these', 'pv', $dossierid)
            || !$fs->is_area_empty($context->id, 'mod_these', 'pvadjourned', $dossierid)
            || (int) $DB->get_field('these_dossier', 'defenseresult', ['id' => $dossierid]) !== 0;
    }

    /**
     * Réduit le dossier d'un étudiant supprimé à son procès-verbal : enregistrement du dossier limité
     * aux champs du PV, jury et fichiers du PV conservés ; le reste est effacé.
     *
     * @param context_module $context
     * @param int $dossierid
     * @return void
     */
    protected static function keep_minutes_only(context_module $context, int $dossierid) {
        global $DB;

        $fs = get_file_storage();
        $areas = array_diff(array_merge(THESE_FILEAREAS, ['sealed', university::FILEAREA]), self::MINUTES_FILEAREAS);
        foreach ($areas as $filearea) {
            $fs->delete_area_files($context->id, 'mod_these', $filearea, $dossierid);
        }
        foreach ($DB->get_fieldset_select('these_signature', 'id', 'dossierid = ?', [$dossierid]) as $signatureid) {
            $fs->delete_area_files($context->id, 'mod_these', 'signature', $signatureid);
        }
        $DB->delete_records('these_signature', ['dossierid' => $dossierid]);
        $DB->delete_records('these_answer', ['dossierid' => $dossierid]);
        $DB->delete_records('these_subjecthistory', ['dossierid' => $dossierid]);
        $DB->delete_records('these_log', ['dossierid' => $dossierid]);
        $DB->set_field('these_jury', 'accesstoken', null, ['dossierid' => $dossierid]);
        $DB->update_record('these_dossier', (object) [
            'id' => $dossierid, 'titleen' => '', 'summary' => '', 'keywords' => '', 'commitments' => 0,
            'ethicsnumber' => '', 'bordereau' => null, 'conclusions' => null, 'conformitygrid' => null,
            'agreement' => null, 'extdirectoremail' => '', 'libraryref' => '', 'correctionsdone' => '',
            'returnstatus' => 0, 'returncomment' => '', 'returnedby' => 0, 'returntime' => 0, 'remindertime' => 0,
            'daterequested' => 0, 'timemodified' => time(),
        ]);
    }

    /**
     * L'utilisateur a-t-il une validation de l'A6 enregistrée ?
     *
     * @param int $userid
     * @return bool
     */
    protected static function has_a6($userid) {
        global $DB;
        return $DB->record_exists('these_a6', ['userid' => $userid]);
    }
}
