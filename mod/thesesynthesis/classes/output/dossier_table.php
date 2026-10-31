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

namespace mod_thesesynthesis\output;

use mod_these\output\parts;
use mod_these\output\status_badge;
use mod_these\output\table_context;
use moodle_url;

/**
 * Tableau de dossiers suivis, toutes promotions confondues (dossiers à traiter, dossiers suivis).
 * Dans la file « À traiter » (option todo), chaque dossier propose directement l'action qui attend
 * l'utilisateur (voir these_primary_action()).
 *
 * Le titre d'une thèse confidentielle n'entre dans le contexte du gabarit que si l'utilisateur
 * connaît son sujet (knowssubject, évalué par thesesynthesis_get_dossiers()).
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_table implements \renderable, \templatable {
    /** @var array */
    protected $rows;
    /** @var string */
    protected $emptymessage;
    /** @var bool */
    protected $todo;

    /**
     * Constructeur.
     *
     * @param array $rows Voir thesesynthesis_get_dossiers().
     * @param string $emptymessage Message affiché sans dossier.
     * @param bool $todo Proposer l'action attendue de l'utilisateur.
     */
    public function __construct(array $rows, $emptymessage, $todo = false) {
        $this->rows = $rows;
        $this->emptymessage = (string) $emptymessage;
        $this->todo = (bool) $todo;
    }

    /**
     * Données du gabarit mod_thesesynthesis/dossier_table.
     *
     * @param \renderer_base $output
     * @return array isempty, emptymessage, table (voir \mod_these\output\table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        global $USER;

        if (!$this->rows) {
            return ['isempty' => true, 'emptymessage' => $this->emptymessage, 'table' => false];
        }
        $head = [
            get_string('promotion', 'mod_thesesynthesis'),
            get_string('student', 'mod_these'),
            get_string('subjecttitle', 'mod_these'),
            get_string('director', 'mod_these'),
            get_string('status'),
            get_string('waitingfor', 'mod_thesesynthesis'),
            get_string('defensedate', 'mod_these'),
            '',
        ];
        // Activités et signatures des soutenances programmées lues d'avance pour toute la file.
        $preloaded = $this->todo ? \mod_these\local\rendering::primary_action_preload(array_column($this->rows, 'dossier')) : null;
        $rows = [];
        foreach ($this->rows as $row) {
            $date = (int) ($row->dossier->defensedate ?: $row->dossier->proposeddate);
            $url = new moodle_url('/mod/these/dossier.php', ['id' => $row->link->cm->id, 'dossierid' => $row->dossier->id]);
            $actions = [parts::link($url, get_string('open', 'mod_thesesynthesis'), 'btn btn-sm btn-secondary')];
            $action = $this->todo ? these_primary_action(
                $row->link->cm,
                $row->dossier,
                these_get_rights($row->link->context, $row->dossier, $USER->id),
                $preloaded
            ) : null;
            if ($action) {
                array_unshift($actions, parts::link($action[1], $action[0], 'btn btn-sm btn-primary mr-1 mb-1'));
            }
            $rows[] = [
                [parts::html(format_string($row->link->course->shortname ?: $row->link->course->fullname))],
                [parts::text($row->student ? fullname($row->student) : '-')],
                [$row->knowssubject ? parts::html(format_string($row->dossier->title))
                    : parts::em(get_string('confidentialtitle', 'mod_these'))],
                [parts::text($row->director ? fullname($row->director) : '-')],
                [parts::status((new status_badge($row->dossier->status))->export_for_template($output))],
                [parts::text($row->actor ? these_actor_label($row->actor) : '-')],
                [parts::text($date ? userdate($date, get_string('strftimedatefullshort', 'langconfig')) : '-')],
                $actions,
            ];
        }
        return [
            'isempty' => false,
            'emptymessage' => '',
            'table' => table_context::export('generaltable these-dossiers', $head, $rows),
        ];
    }
}
