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
use context;
use context_module;
use html_table;
use html_writer;
use moodle_url;
use paging_bar;
use stdClass;

/**
 * Listes de dossiers et rendu HTML des pages.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rendering {
    /**
     * Dossiers d'une instance, filtrés.
     *
     * @param int $theseid
     * @param array $filters status (int|null), directorid (int|null), search (string), queue (string[]
     *                       acteurs dont on veut les dossiers en attente), queuedirectorid (int),
     *                       confidentialsubjects (bool : la recherche porte aussi sur le titre des thèses
     *                       confidentielles, pour qui connaît leur sujet).
     * @return array Dossiers avec les champs de nom de l'étudiant et du directeur.
     */
    public static function get_dossiers($theseid, array $filters = []) {
        global $DB;

        $studentfields = \core_user\fields::for_name()->get_sql('su', false, 'student', '', false)->selects;
        $directorfields = \core_user\fields::for_name()->get_sql('du', false, 'director', '', false)->selects;

        $where = ['d.theseid = :theseid'];
        $params = ['theseid' => $theseid];

        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $where[] = 'd.status = :status';
            $params['status'] = (int) $filters['status'];
        }
        if (!empty($filters['directorid'])) {
            $where[] = 'd.directorid = :directorid';
            $params['directorid'] = (int) $filters['directorid'];
        }
        if (!empty($filters['search'])) {
            $like = '%' . $DB->sql_like_escape($filters['search']) . '%';
            // Le titre d'une thèse confidentielle n'est cherché que pour qui connaît tous les sujets.
            $titlematch = $DB->sql_like('d.title', ':s1', false, false);
            if (empty($filters['confidentialsubjects'])) {
                $titlematch = "(d.confidential = 0 AND $titlematch)";
            }
            $where[] = '(' . $titlematch
                . ' OR ' . $DB->sql_like($DB->sql_fullname('su.firstname', 'su.lastname'), ':s2', false, false)
                . ' OR ' . $DB->sql_like('d.devenumber', ':s3', false, false) . ')';
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }
        if (isset($filters['queue'])) {
            $queue = [];
            foreach ((array) $filters['queue'] as $actor) {
                $statuses = array_values(array_filter(these_all_statuses(), function ($status) use ($actor) {
                    return these_step_actor($status) === $actor;
                }));
                [$insql, $inparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'q' . $actor);
                $condition = "d.status $insql";
                if ($actor === 'director') {
                    $condition .= ' AND d.directorid = :queuedirectorid';
                    $params['queuedirectorid'] = (int) ($filters['queuedirectorid'] ?? 0);
                }
                $queue[] = "($condition)";
                $params = array_merge($params, $inparams);
            }
            $where[] = $queue ? '(' . implode(' OR ', $queue) . ')' : '1 = 0';
        }

        $sql = "SELECT d.*, $studentfields, $directorfields
                  FROM {these_dossier} d
                  JOIN {user} su ON su.id = d.userid
             LEFT JOIN {user} du ON du.id = d.directorid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY d.statustime ASC, d.id ASC";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Nombre de dossiers par statut dans une instance.
     *
     * @param int $theseid
     * @param int|null $directorid Limite aux dossiers d'un directeur.
     * @return array statut => nombre
     */
    public static function count_by_status($theseid, $directorid = null) {
        global $DB;

        $params = ['theseid' => $theseid];
        $where = 'theseid = :theseid';
        if ($directorid !== null) {
            $where .= ' AND directorid = :directorid';
            $params['directorid'] = $directorid;
        }
        $records = $DB->get_records_sql(
            "SELECT status, COUNT(1) AS total FROM {these_dossier} WHERE $where GROUP BY status",
            $params
        );
        $counts = [];
        foreach ($records as $record) {
            $counts[(int) $record->status] = (int) $record->total;
        }
        return $counts;
    }

    /**
     * Découpe une liste en pages.
     *
     * @param array $items
     * @param int $page
     * @param moodle_url $baseurl
     * @param int $perpage
     * @return array [éléments de la page, barre de pagination HTML]
     */
    public static function paginate(array $items, $page, moodle_url $baseurl, $perpage = THESE_LIST_PERPAGE) {
        global $OUTPUT;

        $items = array_values($items);
        $total = count($items);
        $pageitems = array_slice($items, $page * $perpage, $perpage);
        $pagingbar = $total > $perpage ? $OUTPUT->render(new paging_bar($total, $page, $perpage, $baseurl)) : '';
        return [$pageitems, $pagingbar];
    }

    /**
     * Liens rendus en boutons.
     *
     * @param array $links libellé => moodle_url (null : ignoré)
     * @param string $class
     * @return string HTML
     */
    public static function render_actions(array $links, $class = 'btn btn-sm btn-secondary mr-1 mb-1') {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\actions($links, $class));
    }

    /**
     * Barre de navigation commune aux pages du personnel.
     *
     * @param stdClass $cm
     * @param context $context
     * @return string HTML
     */
    public static function render_navlinks(stdClass $cm, context $context) {
        global $USER;

        $rights = these_get_rights($context, null, $USER->id);
        $links = [];
        if ($rights->viewall || has_capability('mod/these:direct', $context)) {
            $links[get_string('dashboard', 'mod_these')] = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
            $links[get_string('planning', 'mod_these')] = new moodle_url('/mod/these/planning.php', ['id' => $cm->id]);
        }
        if ($rights->deve) {
            $links[get_string('createdossier', 'mod_these')] = new moodle_url('/mod/these/create.php', ['id' => $cm->id]);
            $links[get_string('importdossiers', 'mod_these')] = new moodle_url('/mod/these/import.php', ['id' => $cm->id]);
            $links[get_string('notifications', 'mod_these')] = new moodle_url('/mod/these/notifications.php', ['id' => $cm->id]);
            $links[get_string('doctemplates', 'mod_these')] = new moodle_url('/mod/these/doctemplates.php', ['id' => $cm->id]);
            $links[get_string('griditems', 'mod_these')] = new moodle_url('/mod/these/griditems.php', ['id' => $cm->id]);
            $links[get_string('questions', 'mod_these')] = new moodle_url('/mod/these/questions.php', ['id' => $cm->id]);
            $links[get_string('jurors', 'mod_these')] = new moodle_url('/mod/these/jurors.php', ['id' => $cm->id]);
            $links[get_string('transfersettings', 'mod_these')] =
                new moodle_url('/mod/these/settingstransfer.php', ['id' => $cm->id]);
        }
        if ($rights->viewall) {
            $links[get_string('export', 'mod_these')] = new moodle_url('/mod/these/export.php', ['id' => $cm->id]);
        }
        $tosign = count(these_get_user_pending_signatures($USER));
        if ($tosign) {
            $links[get_string('mysignaturescount', 'mod_these', $tosign)] = new moodle_url('/mod/these/mysignatures.php');
        }
        if (empty($links)) {
            return '';
        }
        return html_writer::div(these_render_actions($links, 'btn btn-secondary mr-1 mb-1'), 'these-navlinks mb-3');
    }

    /**
     * Frise de progression d'un dossier dans le circuit.
     *
     * @param stdClass $dossier
     * @return string HTML
     */
    public static function render_progress(stdClass $dossier) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\progress($dossier));
    }

    /**
     * Section de détail en tableau à deux colonnes ; les lignes vides (null) sont omises.
     *
     * @param string $heading
     * @param array $rows libellé => valeur HTML
     * @return string HTML
     */
    public static function render_detail_section($heading, array $rows) {
        global $PAGE;

        // Valeurs HTML reçues des appelants existants : voir output\detail_section::from_html().
        return $PAGE->get_renderer('mod_these')->render(output\detail_section::from_html($heading, $rows));
    }

    /**
     * Tableau du jury.
     *
     * @param array $members
     * @param bool $showemail
     * @param bool $staff Vue de la DEVE : éligibilité et notification des membres.
     * @return string HTML
     */
    public static function render_jury(array $members, $showemail = true, $staff = false) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\jury($members, $showemail, $staff));
    }

    /**
     * Historique d'un dossier.
     *
     * @param int $dossierid
     * @param bool $staffview Vue du personnel (directeur, DEVE, bibliothèque) : sinon, le motif d'un
     *                        renvoi de la DEVE au directeur, qui ne s'adresse pas à l'étudiant, est
     *                        masqué (voir these_render_dossier()).
     * @param bool $knowssubject L'utilisateur connaît le sujet : sinon, l'ancien titre cité par un
     *                           changement de sujet d'une thèse confidentielle est masqué.
     * @return string HTML
     */
    public static function render_history($dossierid, $staffview = true, $knowssubject = true) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\history($dossierid, $staffview, $knowssubject));
    }

    /**
     * Boutons d'action proposés à l'utilisateur sur un dossier, selon son rôle et l'étape courante.
     *
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     * @return array libellé => moodle_url
     */
    public static function get_dossier_actions(stdClass $cm, stdClass $dossier, stdClass $rights) {
        $params = ['id' => $cm->id, 'dossierid' => $dossier->id];
        $status = (int) $dossier->status;
        $links = [];

        if ($rights->student && these_subject_editable($dossier)) {
            $links[get_string('editsubject', 'mod_these')] = new moodle_url('/mod/these/subject.php', $params);
        }
        // Questionnaire préalable au jury : rempli par l'étudiant avant le dépôt de la soutenance,
        // corrigé au besoin par la DEVE.
        if (($rights->student && these_defense_editable($dossier)) || ($rights->deve && $status >= THESE_STATUS_ACTIVE)) {
            $these = (object) ['id' => $cm->instance, 'name' => ''];
            if (these_get_questions(these_settings_scope($these))) {
                $links[get_string('questionnaire', 'mod_these')] = new moodle_url('/mod/these/questionnaire.php', $params);
            }
        }
        if ($rights->student && these_defense_editable($dossier)) {
            $links[get_string('depositdefense', 'mod_these')] = new moodle_url('/mod/these/defense.php', $params);
        }
        if ($rights->student && these_subject_changeable($dossier)) {
            $links[get_string('subjectchange', 'mod_these')] = new moodle_url('/mod/these/subjectchange.php', ['id' => $cm->id]);
        }
        if ($rights->student && $status === THESE_STATUS_CORRECTIONS) {
            $links[get_string('depositcorrections', 'mod_these')] = new moodle_url('/mod/these/corrections.php', $params);
        }
        if ($rights->director && these_report_editable($dossier)) {
            $links[get_string('writereport', 'mod_these')] = new moodle_url('/mod/these/report.php', $params);
        }
        $steps = these_review_steps();
        if (isset($steps[$status]) && !empty($rights->{$steps[$status]['actor']})) {
            $links[get_string('review' . $steps[$status]['key'], 'mod_these')] = new moodle_url('/mod/these/review.php', $params);
        }
        if ($rights->deve && in_array($status, [THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED], true)) {
            $links[get_string('documents', 'mod_these')] = new moodle_url('/mod/these/documents.php', $params);
        }
        if ($rights->deve && $status === THESE_STATUS_SCHEDULED) {
            $links[get_string('depositminutes', 'mod_these')] = new moodle_url('/mod/these/minutes.php', $params);
        }
        if ($rights->deve) {
            $links[get_string('editdossier', 'mod_these')] = new moodle_url('/mod/these/edit.php', $params);
            if ($status === THESE_STATUS_CANCELLED) {
                $links[get_string('reopendossier', 'mod_these')] = new moodle_url('/mod/these/cancel.php', $params);
            } else if ($status !== THESE_STATUS_CLOSED) {
                $links[get_string('canceldossier', 'mod_these')] = new moodle_url('/mod/these/cancel.php', $params);
            }
        }
        if ($rights->deve && $status !== THESE_STATUS_DRAFT) {
            $links[get_string('managesignatures', 'mod_these')] = new moodle_url('/mod/these/signatures.php', $params);
        }
        return $links;
    }

    /**
     * Rend un dossier complet : progression, renvoi en cours, actions, sujet, enregistrements,
     * soutenance, documents et historique. Utilisé par la page de l'étudiant et par dossier.php.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     * @param bool $withactions Affiche les boutons d'action (pas sur les pages d'action elles-mêmes).
     * @return string HTML
     */
    public static function render_dossier(
        stdClass $these,
        stdClass $cm,
        context $context,
        stdClass $dossier,
        stdClass $rights,
        $withactions = true
    ) {
        global $DB;

        $out = these_render_progress($dossier);

        $status = (int) $dossier->status;
        $actor = these_step_actor($status);
        $statusline = these_render_status_badge($status);
        if ($actor !== null) {
            $statusline .= ' ' . html_writer::span(
                get_string('waitingfor', 'mod_these', these_actor_label($actor)) . ' — '
                    . get_string('since', 'mod_these', userdate($dossier->statustime, get_string('strftimedate', 'langconfig'))),
                'text-muted'
            );
        }
        $out .= html_writer::div($statusline, 'mb-3');

        // Renvoi en cours : affiché au personnel et à son destinataire, avec le motif. Un renvoi de la
        // DEVE au directeur ne s'adresse pas à l'étudiant, qui n'en voit que le statut.
        $returnedtostudent = in_array($status, [THESE_STATUS_SUBJECT_RETURNED, THESE_STATUS_DEFENSE_RETURNED], true);
        $showreturn = $rights->viewall || $rights->director || ($rights->owner && $returnedtostudent);
        if ($showreturn && !empty($dossier->returntime) && trim((string) $dossier->returncomment) !== '') {
            $returner = $DB->get_record('user', ['id' => $dossier->returnedby]);
            $a = (object) [
                'by' => $returner ? fullname($returner) : get_string('system', 'mod_these'),
                'date' => userdate($dossier->returntime, get_string('strftimedate', 'langconfig')),
            ];
            $out .= html_writer::div(
                html_writer::tag('strong', get_string('returnedby', 'mod_these', $a)) . html_writer::empty_tag('br')
                    . format_text($dossier->returncomment, FORMAT_PLAIN),
                'alert alert-warning'
            );
        }
        if (these_is_adjourned($dossier)) {
            $out .= html_writer::div(get_string(
                $rights->student ? 'adjournednoticestudent' : 'adjournednotice',
                'mod_these',
                userdate($dossier->statustime, get_string('strftimedate', 'langconfig'))
            ), 'alert alert-warning');
        }

        $actions = $withactions
            ? these_render_actions(these_get_dossier_actions($cm, $dossier, $rights), 'btn btn-primary mr-1 mb-1') : '';
        if ($actions !== '') {
            $out .= html_writer::div($actions, 'these-actions mb-3');
        }

        $student = $DB->get_record('user', ['id' => $dossier->userid]);
        $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;

        // Thèse confidentielle : sujet masqué pour qui ne doit pas le connaître.
        $knows = $rights->knowssubject;
        $confidential = !empty($dossier->confidential)
            ? html_writer::span(get_string('confidentialbadge', 'mod_these'), 'badge badge-danger') : null;
        $masked = html_writer::tag('em', get_string('confidentialtitle', 'mod_these'));
        $out .= these_render_detail_section(get_string('subject', 'mod_these'), [
            get_string('student', 'mod_these') => $student ? fullname($student) : '-',
            get_string('subjecttitle', 'mod_these') => $knows ? (format_string($dossier->title) ?: '-') : $masked,
            get_string('confidentialitylabel', 'mod_these') => $confidential,
            get_string('subjecttitleen', 'mod_these') => $knows ? format_string((string) $dossier->titleen) : $masked,
            get_string('subjectsummary', 'mod_these') => $knows ? format_text((string) $dossier->summary, FORMAT_PLAIN) : $masked,
            get_string('subjectkeywords', 'mod_these') => $knows ? s((string) $dossier->keywords) : $masked,
            get_string('director', 'mod_these') => $director ? fullname($director) : '-',
            get_string('codirector', 'mod_these') => s((string) $dossier->codirectorname),
            get_string('subjectdocs', 'mod_these') => these_can_view_filearea('subjectdocs', $rights)
                ? these_render_file_links($context, 'subjectdocs', $dossier->id) : null,
        ]);
        $out .= these_render_embargo($dossier, $rights);

        if ($knows && ($rights->viewall || $rights->director || $rights->owner)) {
            $out .= these_render_subject_history($context, $dossier);
        }
        $out .= these_render_detail_section(get_string('registration', 'mod_these'), [
            get_string('devenumber', 'mod_these') => s((string) $dossier->devenumber),
            get_string('libraryref', 'mod_these') => s((string) $dossier->libraryref),
        ]);

        if (these_rank_reached($dossier, THESE_STATUS_ACTIVE) || $dossier->proposeddate) {
            $files = function ($filearea) use ($context, $dossier, $rights) {
                return these_can_view_filearea($filearea, $rights)
                    ? these_render_file_links($context, $filearea, $dossier->id) : null;
            };
            $dateformat = get_string('strftimedaydatetime', 'langconfig');
            $rows = [
                get_string('proposeddate', 'mod_these') =>
                    $dossier->proposeddate ? userdate($dossier->proposeddate, $dateformat) : null,
                get_string('defensedate', 'mod_these') =>
                    $dossier->defensedate ? userdate($dossier->defensedate, $dateformat) : null,
                get_string('defenselocation', 'mod_these') => s((string) $dossier->defenselocation),
                get_string('manuscript', 'mod_these') => $files('manuscript'),
                get_string('defensedocs', 'mod_these') => $files('defensedocs'),
            ];
            $rows[get_string('directorreport', 'mod_these')] = $files('directorreport');
            $rows[get_string('generateddocuments', 'mod_these')] = $files('generated');
            if ($status === THESE_STATUS_CLOSED || these_in_corrections($status)) {
                $rows[get_string('defenseresult', 'mod_these')] = these_result_label($dossier->defenseresult);
                $rows[get_string('mention', 'mod_these')] = s(these_mention_label($dossier->mention));
                $rows[get_string('prizeproposal', 'mod_these')] = $dossier->prizeproposal ? get_string('yes') : null;
                $rows[get_string('minutes', 'mod_these')] = $files('pv');
            }
            $rows[get_string('minutesadjourned', 'mod_these')] = $files('pvadjourned');
            $out .= these_render_detail_section(get_string('defense', 'mod_these'), $rows);
            if (!empty($dossier->correctionsrequested)) {
                $out .= these_render_detail_section(get_string('corrections', 'mod_these'), [
                    get_string('correctionsnote', 'mod_these') => format_text((string) $dossier->correctionsnote, FORMAT_PLAIN),
                    get_string('correctionsdone', 'mod_these') => trim((string) $dossier->correctionsdone) !== ''
                        ? format_text((string) $dossier->correctionsdone, FORMAT_PLAIN) : null,
                    get_string('correctedmanuscript', 'mod_these') => $files('corrections'),
                ]);
            }
            if ($knows && ($rights->viewall || $rights->director || $rights->owner)) {
                $out .= these_render_answers((object) ['id' => $dossier->theseid], $dossier);
            }
            $showemail = $rights->viewall || $rights->director || $rights->owner;
            $out .= these_render_jury(these_get_jury($dossier->id), $showemail, $rights->deve);
            $juryvalidated = (int) $dossier->status >= THESE_STATUS_DEFENSE_VALIDATED
                && (int) $dossier->status < THESE_STATUS_CLOSED;
            if ($rights->deve && $juryvalidated) {
                $out .= html_writer::div(html_writer::link(
                    new moodle_url('/mod/these/jurynotify.php', [
                        'id' => $cm->id,
                        'dossierid' => $dossier->id,
                        'sesskey' => sesskey(),
                    ]),
                    get_string('juryresendaccess', 'mod_these'),
                    ['class' => 'btn btn-secondary btn-sm']
                ), 'mb-3');
            }
        }

        $out .= these_render_signed_documents($cm, $context, $dossier, $rights);
        $out .= these_render_history($dossier->id, $rights->viewall || $rights->director, $rights->knowssubject);
        return $out;
    }

    /**
     * Ligne de l'export du registre pour un dossier, telle que l'utilisateur courant peut la voir : le
     * sujet d'une thèse confidentielle n'y figure que pour qui le connaît (voir these_subject_field()).
     *
     * @param stdClass $dossier
     * @param context $context Contexte de l'activité.
     * @param stdClass|null $student
     * @param stdClass|null $director
     * @return array Colonnes de l'export (voir export.php).
     */
    public static function register_row(stdClass $dossier, context $context, ?stdClass $student, ?stdClass $director) {
        $roles = these_jury_role_options();
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $jury = array_map(function ($member) use ($roles) {
            return ($roles[$member->juryrole] ?? $member->juryrole) . ' : ' . $member->fullname;
        }, these_get_jury($dossier->id));
        $embargo = these_embargo_until($dossier);
        return [
            'devenumber' => $dossier->devenumber,
            'student' => $student ? fullname($student) : '',
            'email' => $student ? $student->email : '',
            'title' => these_subject_field($dossier, 'title', $context),
            'titleen' => these_subject_field($dossier, 'titleen', $context),
            'keywords' => these_subject_field($dossier, 'keywords', $context),
            'director' => $director ? fullname($director) : '',
            'codirector' => $dossier->codirectorname,
            'libraryref' => $dossier->libraryref,
            'status' => these_status_label($dossier->status),
            'statustime' => userdate($dossier->statustime, $dateformat),
            'defensedate' => $dossier->defensedate ? userdate($dossier->defensedate, $dateformat) : '',
            'defenselocation' => $dossier->defenselocation,
            'jury' => implode(' ; ', $jury),
            'result' => (int) $dossier->defenseresult ? these_result_label($dossier->defenseresult) : '',
            'mention' => these_mention_label($dossier->mention),
            'embargo' => $embargo ? userdate($embargo, get_string('strftimedatefullshort', 'langconfig')) : '',
        ];
    }

    /**
     * Diffusion de la thèse choisie au dépôt de la soutenance : mode de diffusion et demande d'embargo,
     * avec sa date de fin (voir these_embargo_until()). Le motif de l'embargo, qui peut révéler le sujet,
     * n'est montré qu'à qui connaît le sujet.
     *
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     * @return string HTML
     */
    public static function render_embargo(stdClass $dossier, stdClass $rights) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\embargo($dossier, $rights));
    }

    /**
     * Indique si un dossier a atteint (ou dépassé) un statut donné dans le circuit.
     *
     * @param stdClass $dossier
     * @param int $status
     * @return bool
     */
    public static function rank_reached(stdClass $dossier, $status) {
        $current = (int) $dossier->status === THESE_STATUS_CANCELLED ? $dossier->cancelledfromstatus : $dossier->status;
        return these_status_rank($current) >= these_status_rank($status);
    }

    /**
     * Tableau de dossiers pour les listes du tableau de bord.
     *
     * @param array $dossiers Voir these_get_dossiers().
     * @param stdClass $cm
     * @return string HTML
     */
    public static function render_dossier_table(array $dossiers, stdClass $cm) {
        global $USER;

        $context = context_module::instance($cm->id);
        if (empty($dossiers)) {
            return html_writer::div(get_string('nodossiers', 'mod_these'), 'alert alert-info');
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable these-dossiers';
        $table->head = [
            get_string('student', 'mod_these'),
            get_string('subjecttitle', 'mod_these'),
            get_string('director', 'mod_these'),
            get_string('devenumber', 'mod_these'),
            get_string('status', 'mod_these'),
            get_string('since', 'mod_these', ''),
            '',
        ];
        $dateformat = get_string('strftimedate', 'langconfig');
        foreach ($dossiers as $dossier) {
            $student = (object) [];
            $director = (object) [];
            foreach (\core_user\fields::get_name_fields() as $field) {
                $student->$field = $dossier->{'student' . $field} ?? '';
                $director->$field = $dossier->{'director' . $field} ?? '';
            }
            $url = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
            $title = these_user_knows_subject($dossier, $context, $USER->id)
                ? (shorten_text(format_string($dossier->title), 90) ?: '-')
                : html_writer::tag('em', get_string('confidentialtitle', 'mod_these'));
            $table->data[] = [
                fullname($student),
                $title,
                $dossier->directorid ? fullname($director) : '-',
                s((string) $dossier->devenumber),
                these_render_status_badge($dossier->status),
                userdate($dossier->statustime, $dateformat),
                html_writer::link($url, get_string('open', 'mod_these'), ['class' => 'btn btn-sm btn-secondary']),
            ];
        }
        return html_writer::table($table);
    }
}
