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

namespace mod_these\output;

/**
 * Historique d'un dossier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history implements \renderable, \templatable {
    /** @var int */
    protected $dossierid;
    /** @var bool */
    protected $staffview;
    /** @var bool */
    protected $knowssubject;

    /**
     * Constructeur.
     *
     * @param int $dossierid
     * @param bool $staffview Vue du personnel (directeur, DEVE, bibliothèque) : sinon, le motif d'un
     *                        renvoi de la DEVE au directeur, qui ne s'adresse pas à l'étudiant, est masqué.
     * @param bool $knowssubject L'utilisateur connaît le sujet : sinon, l'ancien titre cité par un
     *                           changement de sujet d'une thèse confidentielle est masqué.
     */
    public function __construct($dossierid, $staffview = true, $knowssubject = true) {
        $this->dossierid = (int) $dossierid;
        $this->staffview = (bool) $staffview;
        $this->knowssubject = (bool) $knowssubject;
    }

    /**
     * Données du gabarit mod_these/history.
     *
     * @param \renderer_base $output
     * @return array haslogs, heading, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $logs = $DB->get_records('these_log', ['dossierid' => $this->dossierid], 'timecreated DESC, id DESC');
        if (empty($logs)) {
            return ['haslogs' => false, 'heading' => '', 'table' => false];
        }
        $userids = array_filter(array_unique(array_map(function ($log) {
            return (int) $log->userid;
        }, $logs)));
        $users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

        $head = [get_string('date'), get_string('historyaction', 'mod_these'), get_string('historyby', 'mod_these'),
            get_string('status', 'mod_these'), get_string('historyremark', 'mod_these')];
        $strman = get_string_manager();
        $rows = [];
        foreach ($logs as $log) {
            $actionkey = 'log' . $log->logaction;
            $by = $log->userid && isset($users[$log->userid])
                ? fullname($users[$log->userid]) : get_string('system', 'mod_these');
            // Motifs masqués : renvoi au directeur pour l'étudiant, ancien titre pour qui ne connaît pas le sujet.
            $showremark = ($this->staffview || $log->logaction !== 'returnedtodirector')
                && ($this->knowssubject || $log->logaction !== 'subjectchanged');
            $rows[] = [
                [parts::text(userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')))],
                [parts::text($strman->string_exists($actionkey, 'mod_these')
                    ? get_string($actionkey, 'mod_these') : $log->logaction)],
                [parts::text($by)],
                [parts::text(these_status_label($log->tostatus))],
                $showremark ? [parts::html(format_text((string) $log->remark, FORMAT_PLAIN))] : [],
            ];
        }
        return [
            'haslogs' => true,
            'heading' => get_string('history', 'mod_these'),
            'table' => table_context::export('generaltable these-history', $head, $rows),
        ];
    }
}
