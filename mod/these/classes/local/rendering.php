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

use context;
use mod_these\output;
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
                $statuses = array_values(array_filter(workflow::all_statuses(), function ($status) use ($actor) {
                    return workflow::step_actor($status) === $actor;
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
     * Barre de navigation commune aux pages du personnel, précédée du lien de retour (voir
     * \mod_these\local\backlinks).
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass|null $back Cible du retour (voir backlinks::target()) ; null : la page d'origine,
     *     à défaut l'accueil de l'activité (voir backlinks::from_activity()).
     * @return string HTML
     */
    public static function render_navlinks(stdClass $cm, context $context, ?stdClass $back = null) {
        global $PAGE;

        return backlinks::render($back ?? backlinks::from_activity($cm, $context))
            . $PAGE->get_renderer('mod_these')->render(new output\navlinks($cm, $context));
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
        if (dossiers::is_erased($dossier)) {
            // Dossier réduit à son procès-verbal : en lecture seule.
            return $links;
        }

        if ($rights->student && subjects::subject_editable($dossier)) {
            $links[get_string('editsubject', 'mod_these')] = new moodle_url('/mod/these/subject.php', $params);
        }
        // Questionnaire préalable au jury : rempli par l'étudiant avant le dépôt de la soutenance,
        // corrigé au besoin par la DEVE.
        if (($rights->student && defenses::defense_editable($dossier)) || ($rights->deve && $status >= THESE_STATUS_ACTIVE)) {
            $these = (object) ['id' => $cm->instance, 'name' => ''];
            if (questionnaire::get_questions(doctemplates::settings_scope($these))) {
                $links[get_string('questionnaire', 'mod_these')] = new moodle_url('/mod/these/questionnaire.php', $params);
            }
        }
        if ($rights->student && defenses::defense_editable($dossier)) {
            $links[get_string('depositdefense', 'mod_these')] = new moodle_url('/mod/these/defense.php', $params);
        }
        if ($rights->student && daterequest::pending($dossier)) {
            $links[get_string('newdate', 'mod_these')] = daterequest::url($cm);
        }
        if ($rights->student && subjectchange::subject_changeable($dossier)) {
            $links[get_string('subjectchange', 'mod_these')] = new moodle_url('/mod/these/subjectchange.php', ['id' => $cm->id]);
        }
        if ($rights->student && $status === THESE_STATUS_CORRECTIONS) {
            $links[get_string('depositcorrections', 'mod_these')] = new moodle_url('/mod/these/corrections.php', $params);
        }
        if ($rights->director && defenses::report_editable($dossier)) {
            $links[get_string('depositreport', 'mod_these')] = new moodle_url('/mod/these/report.php', $params);
        }
        $steps = workflow::review_steps();
        if (isset($steps[$status]) && !empty($rights->{$steps[$status]['actor']})) {
            $links[get_string('review' . $steps[$status]['key'], 'mod_these')] = new moodle_url('/mod/these/review.php', $params);
        }
        if ($rights->deve && daterequest::allowed($dossier)) {
            $links[get_string('daterequest', 'mod_these')] = new moodle_url('/mod/these/daterequest.php', $params);
        }
        if ($rights->deve && in_array($status, [THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED], true)) {
            $links[get_string('documents', 'mod_these')] = new moodle_url('/mod/these/documents.php', $params);
        }
        if ($rights->deve && $status === THESE_STATUS_SCHEDULED) {
            $links[get_string('depositminutes', 'mod_these')] = new moodle_url('/mod/these/minutes.php', $params);
        }
        if ($rights->deve && \mod_these\local\scans::allowed($dossier)) {
            $links[get_string('addsignedscan', 'mod_these')] = new moodle_url('/mod/these/scan.php', $params);
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
     * Action qui attend l'utilisateur sur un dossier, pour l'ouvrir directement depuis la file « À
     * traiter » : la validation de l'étape en cours, ou pour la DEVE les documents puis le
     * procès-verbal.
     *
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     * @param array|null $preloaded Données lues d'avance pour toute une liste (voir
     *                              primary_action_preload()) ; sans elles, lues pour ce dossier.
     * @return array|null [libellé, moodle_url], ou null sans action attendue.
     */
    public static function primary_action(stdClass $cm, stdClass $dossier, stdClass $rights, ?array $preloaded = null) {
        global $DB;

        $params = ['id' => $cm->id, 'dossierid' => $dossier->id];
        $status = (int) $dossier->status;
        if (dossiers::is_erased($dossier)) {
            return null;
        }
        $steps = workflow::review_steps();
        if (isset($steps[$status]) && !empty($rights->{$steps[$status]['actor']})) {
            return [get_string('review' . $steps[$status]['key'], 'mod_these'), new moodle_url('/mod/these/review.php', $params)];
        }
        if ($rights->deve && $status === THESE_STATUS_CONFORM) {
            return [get_string('documents', 'mod_these'), new moodle_url('/mod/these/documents.php', $params)];
        }
        if ($rights->deve && $status === THESE_STATUS_SCHEDULED) {
            // Pièces de l'université pas encore revenues signées : leurs scans d'abord.
            $these = $preloaded['these'][(int) $dossier->theseid]
                ?? $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
            $signatures = $preloaded['signatures'][(int) $dossier->id] ?? null;
            $missing = prerequisites::missing($dossier, $signatures);
            if (prerequisites::only_university($missing, university::paper_slots($these))) {
                return [get_string('universityaddscan', 'mod_these'), new moodle_url('/mod/these/scan.php', $params)];
            }
            return [get_string('depositminutes', 'mod_these'), new moodle_url('/mod/these/minutes.php', $params)];
        }
        return null;
    }

    /**
     * Données de primary_action() lues d'avance pour une liste de dossiers : activités et signatures
     * des soutenances programmées, seules à en avoir besoin.
     *
     * @param stdClass[] $dossiers
     * @return array these (par activité), signatures (par dossier).
     */
    public static function primary_action_preload(array $dossiers) {
        global $DB;

        $scheduled = array_filter($dossiers, function ($dossier) {
            return (int) $dossier->status === THESE_STATUS_SCHEDULED;
        });
        if (!$scheduled) {
            return ['these' => [], 'signatures' => []];
        }
        return [
            'these' => $DB->get_records_list('these', 'id', array_unique(array_column($scheduled, 'theseid'))),
            'signatures' => signatures::get_signatures_for(array_column($scheduled, 'id')),
        ];
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
        global $PAGE;

        $dossierview = new output\dossier($these, $cm, $context, $dossier, $rights, $withactions);
        return $PAGE->get_renderer('mod_these')->render($dossierview);
    }

    /**
     * Ligne de l'export du registre pour un dossier, telle que l'utilisateur courant peut la voir : le
     * sujet d'une thèse confidentielle n'y figure que pour qui le connaît (voir these_subject_field()).
     *
     * @param stdClass $dossier
     * @param context $context Contexte de l'activité.
     * @param stdClass|null $student
     * @param stdClass|null $director
     * @param array|null $preloaded Données de tous les dossiers exportés, lues d'avance (voir
     *                              register_preload()) ; sans elles, lues pour ce dossier.
     * @return array Colonnes de l'export (voir export.php).
     */
    public static function register_row(
        stdClass $dossier,
        context $context,
        ?stdClass $student,
        ?stdClass $director,
        ?array $preloaded = null
    ) {
        $preloaded = $preloaded ?? self::register_preload([$dossier]);
        $id = (int) $dossier->id;
        $signatures = $preloaded['signatures'][$id] ?? [];
        $roles = workflow::jury_role_options();
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $jury = array_map(function ($member) use ($roles) {
            return ($roles[$member->juryrole] ?? $member->juryrole) . ' : ' . $member->fullname;
        }, $preloaded['juries'][$id] ?? []);
        $embargo = bordereau::embargo_until($dossier);
        return [
            'devenumber' => $dossier->devenumber,
            'student' => $student ? fullname($student) : '',
            'email' => $student ? $student->email : '',
            'title' => rights::subject_field($dossier, 'title', $context),
            'titleen' => rights::subject_field($dossier, 'titleen', $context),
            'keywords' => rights::subject_field($dossier, 'keywords', $context),
            'director' => $director ? fullname($director) : '',
            'extdirector' => !empty($dossier->externaldirector) ? (string) $dossier->extdirectorname : '',
            'libraryref' => $dossier->libraryref,
            'status' => workflow::status_label($dossier->status),
            'statustime' => userdate($dossier->statustime, $dateformat),
            'defensedate' => $dossier->defensedate ? userdate($dossier->defensedate, $dateformat) : '',
            'defenselocation' => $dossier->defenselocation,
            'jury' => implode(' ; ', $jury),
            'result' => (int) $dossier->defenseresult ? workflow::result_label($dossier->defenseresult) : '',
            'mention' => workflow::mention_label($dossier->mention),
            'embargo' => $embargo ? userdate($embargo, get_string('strftimedatefullshort', 'langconfig')) : '',
            'a6' => a6::label($dossier, $preloaded['a6']),
            'directortitle' => (string) $dossier->directortitle,
            'missingsignatures' => implode(' ; ', array_map(function ($item) {
                return signatures::document_label($item['doctype']) . ' — ' . signatures::slot_label($item['slot']);
            }, prerequisites::missing($dossier, $signatures))),
            'universitypieces' => self::university_pieces_label(
                $context,
                $dossier,
                $preloaded['these'][(int) $dossier->theseid] ?? null,
                $signatures
            ),
            'lastrefusal' => self::last_refusal_label($preloaded['refusals'][$id] ?? null),
        ];
    }

    /**
     * Données de l'export du registre lues d'avance pour tous les dossiers exportés, en une requête par
     * table : jurys, signatures, validations de l'A6, activités et derniers refus de signer.
     *
     * @param stdClass[] $dossiers
     * @return array juries, signatures (par dossier), a6 (par étudiant), these (par activité), refusals
     *               (dernier refus, par dossier).
     */
    public static function register_preload(array $dossiers) {
        global $DB;

        $ids = array_map('intval', array_column($dossiers, 'id'));
        $preloaded = ['juries' => jury::get_juries($ids), 'signatures' => signatures::get_signatures_for($ids),
            'a6' => [], 'these' => [], 'refusals' => []];
        $userids = array_filter(array_unique(array_map('intval', array_column($dossiers, 'userid'))));
        if ($userids) {
            foreach ($DB->get_records_list('these_a6', 'userid', $userids) as $record) {
                $preloaded['a6'][(int) $record->userid] = $record;
            }
        }
        $theseids = array_unique(array_map('intval', array_column($dossiers, 'theseid')));
        $preloaded['these'] = $theseids ? $DB->get_records_list('these', 'id', $theseids) : [];
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $params['action'] = 'agentrefused';
            $logs = $DB->get_records_select(
                'these_log',
                "dossierid $insql AND logaction = :action",
                $params,
                'timecreated ASC, id ASC',
                'id, dossierid, timecreated, remark'
            );
            foreach ($logs as $log) {
                $preloaded['refusals'][(int) $log->dossierid] = $log;
            }
        }
        return $preloaded;
    }

    /**
     * Pièces pour l'université, pour l'export : documents qui n'attendent plus que l'université,
     * scellés pour l'envoi (avec la date) ou encore à sceller.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass|null $these Activité du dossier, déjà lue.
     * @param array|null $signatures Signatures du dossier, déjà lues.
     * @return string
     */
    protected static function university_pieces_label(
        context $context,
        stdClass $dossier,
        ?stdClass $these = null,
        ?array $signatures = null
    ) {
        $dateformat = get_string('strftimedatefullshort', 'langconfig');
        $items = [];
        foreach (array_keys(university::pending($dossier, $these, $signatures)) as $doctype) {
            $file = university::get_sealed($context, $dossier->id, $doctype);
            $items[] = signatures::document_label($doctype) . ' — ' . ($file
                ? get_string('universitysealedon', 'mod_these', userdate($file->get_timecreated(), $dateformat))
                : get_string('exportuniversitytoseal', 'mod_these'));
        }
        return implode(' ; ', $items);
    }

    /**
     * Dernier refus de signer d'un agent DEVE, pour l'export : date et motif.
     *
     * @param stdClass|null $log Dernier refus (these_log), voir register_preload().
     * @return string
     */
    protected static function last_refusal_label(?stdClass $log) {
        if (!$log) {
            return '';
        }
        return userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')) . ' — ' . $log->remark;
    }

    /**
     * Neutralise les cellules d'une ligne d'export tableur qui seraient interprétées comme une formule
     * par Excel ou LibreOffice (valeur commençant par =, +, - ou @) : un titre ou un nom saisi par un
     * utilisateur ne doit pas exécuter de formule au poste de qui ouvre l'export. La valeur est
     * précédée d'une apostrophe, que les tableurs affichent comme marque de texte.
     *
     * @param array $row Colonne => valeur.
     * @return array La ligne, cellules neutralisées.
     */
    public static function spreadsheet_safe(array $row) {
        foreach ($row as $key => $value) {
            if (is_string($value) && $value !== '' && strpbrk($value[0], '=+-@') !== false) {
                $row[$key] = "'" . $value;
            }
        }
        return $row;
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
        return workflow::status_rank($current) >= workflow::status_rank($status);
    }

    /**
     * Tableau de dossiers pour les listes du tableau de bord.
     *
     * @param array $dossiers Voir these_get_dossiers().
     * @param stdClass $cm
     * @param array $options Voir output\dossier_table.
     * @return string HTML
     */
    public static function render_dossier_table(array $dossiers, stdClass $cm, array $options = []) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\dossier_table($dossiers, $cm, $options));
    }
}
