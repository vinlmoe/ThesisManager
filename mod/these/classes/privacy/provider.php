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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Fournisseur de données personnelles de mod_these.
 *
 * Comme dans mod_stage, un utilisateur apparaît sous deux rôles qui n'appellent pas le même
 * traitement :
 *
 * - **l'étudiant**, propriétaire du dossier (these_dossier.userid) : sa suppression efface le
 *   dossier entier (sujet, soutenance, jury, historique, fichiers) ;
 * - **le personnel** (directeur, DEVE, bibliothèque), simplement *cité* dans le dossier d'un
 *   étudiant (directeur, auteur d'un renvoi ou d'une action de l'historique) : ses références sont
 *   dissociées et les commentaires qu'il a rédigés effacés, le dossier de l'étudiant restant
 *   intact.
 *
 * Les membres du jury, souvent extérieurs, n'ont pas de compte Moodle : leurs coordonnées font
 * partie du dossier de l'étudiant et suivent son sort.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
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
            'devenumber' => 'privacy:metadata:these_dossier:devenumber',
            'defensedate' => 'privacy:metadata:these_dossier:defensedate',
            'defenseresult' => 'privacy:metadata:these_dossier:defenseresult',
            'returncomment' => 'privacy:metadata:these_dossier:returncomment',
            'returnedby' => 'privacy:metadata:these_dossier:returnedby',
            'bordereau' => 'privacy:metadata:these_dossier:bordereau',
            'conclusions' => 'privacy:metadata:these_dossier:conclusions',
            'reporttext' => 'privacy:metadata:these_dossier:reporttext',
            'correctionsdone' => 'privacy:metadata:these_dossier:correctionsdone',
            'timecreated' => 'privacy:metadata:these_dossier:timecreated',
        ], 'privacy:metadata:these_dossier');

        $collection->add_database_table('these_signature', [
            'userid' => 'privacy:metadata:these_signature:userid',
            'signername' => 'privacy:metadata:these_signature:signername',
            'signeremail' => 'privacy:metadata:these_signature:signeremail',
            'ipaddress' => 'privacy:metadata:these_signature:ipaddress',
            'timesigned' => 'privacy:metadata:these_signature:timesigned',
        ], 'privacy:metadata:these_signature');

        $collection->add_database_table('these_jury', [
            'fullname' => 'privacy:metadata:these_jury:fullname',
            'email' => 'privacy:metadata:these_jury:email',
            'institution' => 'privacy:metadata:these_jury:institution',
            'jurystatus' => 'privacy:metadata:these_jury:jurystatus',
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
            'usermodified' => 'privacy:metadata:these_a6:usermodified',
            'timemodified' => 'privacy:metadata:these_a6:timemodified',
        ], 'privacy:metadata:these_a6');

        $collection->add_database_table('these_subjecthistory', [
            'title' => 'privacy:metadata:these_subjecthistory:title',
            'summary' => 'privacy:metadata:these_subjecthistory:summary',
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

        $collection->add_external_location_link('jury', [
            'student' => 'privacy:metadata:jury:student',
            'title' => 'privacy:metadata:jury:title',
            'defensedate' => 'privacy:metadata:jury:defensedate',
        ], 'privacy:metadata:jury');

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
                    OR EXISTS (SELECT 1 FROM {these_signature} sg WHERE sg.dossierid = d.id AND sg.userid = :userid5)";
    }

    /**
     * Contextes dans lesquels l'utilisateur donné a des données.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(self::contexts_sql(), [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'these',
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
        ]);
        // Signature enregistrée et validation de l'A6, dans le contexte de l'utilisateur.
        if (these_get_saved_signature($userid) !== null || self::has_a6($userid)) {
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
                && (these_get_saved_signature($context->instanceid) !== null || self::has_a6($context->instanceid))
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
        $userlist->add_from_sql('userid', "SELECT sg.userid
                                             FROM {course_modules} cm
                                             JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                                             JOIN {these_dossier} d ON d.theseid = cm.instance
                                             JOIN {these_signature} sg ON sg.dossierid = d.id
                                            WHERE cm.id = :instanceid", $params);
    }

    /**
     * Exporte les données de l'utilisateur pour les contextes approuvés : son propre dossier s'il
     * est étudiant, et ses interventions dans les dossiers des autres s'il est membre du personnel.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && (int) $context->instanceid === (int) $user->id) {
                $a6 = $DB->get_record('these_a6', ['userid' => $user->id]);
                if ($a6) {
                    writer::with_context($context)->export_data([get_string('a6validation', 'mod_these')], (object) [
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
                continue;
            }
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('these', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $dossier = $DB->get_record('these_dossier', ['theseid' => $cm->instance, 'userid' => $user->id]);
            if ($dossier) {
                $subcontext = [get_string('mydossier', 'mod_these')];
                writer::with_context($context)->export_data($subcontext, (object) [
                    'status' => these_status_label($dossier->status),
                    'title' => $dossier->title,
                    'titleen' => $dossier->titleen,
                    'summary' => $dossier->summary,
                    'keywords' => $dossier->keywords,
                    'codirector' => $dossier->codirectorname,
                    'devenumber' => $dossier->devenumber,
                    'libraryref' => $dossier->libraryref,
                    'proposeddate' => $dossier->proposeddate ? transform::datetime($dossier->proposeddate) : null,
                    'defensedate' => $dossier->defensedate ? transform::datetime($dossier->defensedate) : null,
                    'defenselocation' => $dossier->defenselocation,
                    'defenseresult' => (int) $dossier->defenseresult ? these_result_label($dossier->defenseresult) : null,
                    'mention' => these_mention_label($dossier->mention),
                    'prizeproposal' => transform::yesno($dossier->prizeproposal),
                    'correctionsnote' => $dossier->correctionsnote,
                    'correctionsdone' => $dossier->correctionsdone,
                    'returncomment' => $dossier->returncomment,
                    'conclusions' => $dossier->conclusions,
                    'personaldata' => these_get_bordereau($dossier),
                    'timecreated' => transform::datetime($dossier->timecreated),
                ]);
                $jury = array_map(function ($member) {
                    return (object) [
                        'role' => $member->juryrole,
                        'fullname' => $member->fullname,
                        'title' => $member->jurytitle,
                        'institution' => $member->institution,
                        'email' => $member->email,
                    ];
                }, these_get_jury($dossier->id));
                if ($jury) {
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('jury', 'mod_these')]),
                        (object) ['jury' => $jury]
                    );
                }
                $previous = array_values(array_map(function ($entry) {
                    return (object) [
                        'title' => $entry->title,
                        'summary' => $entry->summary,
                        'keywords' => $entry->keywords,
                        'director' => $entry->directorname,
                        'reason' => $entry->reason,
                        'time' => transform::datetime($entry->timecreated),
                    ];
                }, these_get_subject_history($dossier->id)));
                if ($previous) {
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('subjecthistory', 'mod_these')]),
                        (object) ['subjects' => $previous]
                    );
                }
                $answers = [];
                foreach (these_get_answers($dossier->id) as $questionid => $answer) {
                    $answers[] = (object) [
                        'question' => (string) $DB->get_field('these_question', 'label', ['id' => $questionid]),
                        'answer' => these_answer_text($answer),
                    ];
                }
                if ($answers) {
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('questionnaire', 'mod_these')]),
                        (object) ['answers' => $answers]
                    );
                }
                $history = array_map(function ($log) {
                    return (object) [
                        'action' => $log->logaction,
                        'status' => these_status_label($log->tostatus),
                        'remark' => $log->remark,
                        'time' => transform::datetime($log->timecreated),
                    ];
                }, array_values($DB->get_records('these_log', ['dossierid' => $dossier->id], 'timecreated ASC, id ASC')));
                writer::with_context($context)->export_data(
                    array_merge($subcontext, [get_string('history', 'mod_these')]),
                    (object) ['history' => $history]
                );
                foreach (THESE_FILEAREAS as $filearea) {
                    // Le rapport du directeur ne s'adresse pas à l'étudiant (voir
                    // these_can_view_filearea()) : il n'est pas exporté avec son dossier.
                    if ($filearea !== 'directorreport') {
                        writer::with_context($context)->export_area_files($subcontext, 'mod_these', $filearea, $dossier->id);
                    }
                }
                // Documents scellés, sauf ceux réservés au personnel (rapport, grille).
                $definitions = these_document_definitions();
                $sealed = get_file_storage()->get_area_files($context->id, 'mod_these', 'sealed', $dossier->id, 'filename', false);
                foreach ($sealed as $file) {
                    $doctype = pathinfo($file->get_filename(), PATHINFO_FILENAME);
                    if (empty($definitions[$doctype]['staffonly'])) {
                        writer::with_context($context)->export_file($subcontext, $file);
                    }
                }
            }

            self::export_staff_data($context, $cm, $user->id);

            // Signatures apposées par l'utilisateur, dans son dossier comme dans ceux des autres.
            $signatures = $DB->get_records_sql(
                "SELECT sg.*, d.title
                   FROM {these_signature} sg
                   JOIN {these_dossier} d ON d.id = sg.dossierid
                  WHERE d.theseid = :theseid AND sg.userid = :userid AND sg.timesigned > 0",
                ['theseid' => $cm->instance, 'userid' => $user->id]
            );
            foreach ($signatures as $signature) {
                $subcontext = [get_string('signatures', 'mod_these'), $signature->id];
                writer::with_context($context)->export_data($subcontext, (object) [
                    'dossier' => $signature->title,
                    'document' => these_document_label($signature->doctype),
                    'slot' => these_slot_label($signature->slot),
                    'signername' => $signature->signername,
                    'ipaddress' => $signature->ipaddress,
                    'timesigned' => transform::datetime($signature->timesigned),
                ]);
                writer::with_context($context)->export_area_files($subcontext, 'mod_these', 'signature', $signature->id);
            }
        }
    }

    /**
     * Exporte, pour un membre du personnel, ses interventions dans les dossiers des autres : les
     * dossiers qu'il dirige et les actions qu'il a menées, avec ses commentaires.
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
        if (!$logs && !$directed) {
            return;
        }
        writer::with_context($context)->export_data([get_string('staffactions', 'mod_these')], (object) [
            'directed' => array_values(array_map(function ($dossier) {
                return $dossier->title;
            }, $directed)),
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
            these_delete_saved_signature($context->instanceid);
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
        these_delete_dossiers($DB->get_fieldset_select('these_dossier', 'id', 'theseid = ?', [$cm->instance]), $context);
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
                these_delete_saved_signature($context->instanceid);
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

        // Dossiers propres : supprimés intégralement.
        these_delete_dossiers(
            $DB->get_fieldset_select('these_dossier', 'id', "theseid = :theseid AND userid $insql", $params),
            $context
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
            these_unseal_document($context, $signature->dossierid, $signature->doctype);
            $DB->update_record('these_signature', (object) [
                'id' => $signature->id, 'userid' => 0, 'signername' => '', 'signeremail' => '', 'ipaddress' => '',
            ]);
        }
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
