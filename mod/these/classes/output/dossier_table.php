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

use context_module;
use mod_these\local\rendering;
use mod_these\local\rights;
use moodle_url;
use stdClass;

/**
 * Tableau de dossiers des listes du tableau de bord. Dans la file « À traiter » (option todo), chaque
 * dossier propose directement l'action qui attend l'utilisateur ; avec l'option selectable, une case
 * par dossier sert aux actions groupées de la DEVE.
 *
 * Le titre d'une thèse confidentielle n'entre dans le contexte du gabarit que pour qui connaît son
 * sujet (these_user_knows_subject()) ; les autres reçoivent la mention « Thèse confidentielle ».
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_table implements \renderable, \templatable {
    /** @var array */
    protected $dossiers;
    /** @var stdClass */
    protected $cm;
    /** @var bool */
    protected $todo;
    /** @var bool */
    protected $selectable;

    /**
     * Constructeur.
     *
     * @param array $dossiers Voir these_get_dossiers().
     * @param stdClass $cm
     * @param array $options todo (action directe), selectable (case de sélection, name dossierids[]).
     */
    public function __construct(array $dossiers, stdClass $cm, array $options = []) {
        $this->dossiers = $dossiers;
        $this->cm = $cm;
        $this->todo = !empty($options['todo']);
        $this->selectable = !empty($options['selectable']);
    }

    /**
     * Données du gabarit mod_these/dossier_table.
     *
     * @param \renderer_base $output
     * @return array isempty, emptymessage, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        global $USER;

        if (empty($this->dossiers)) {
            return ['isempty' => true, 'emptymessage' => get_string('nodossiers', 'mod_these'), 'table' => false];
        }
        $context = context_module::instance($this->cm->id);
        $head = [
            get_string('student', 'mod_these'),
            get_string('subjecttitle', 'mod_these'),
            get_string('director', 'mod_these'),
            get_string('devenumber', 'mod_these'),
            get_string('status', 'mod_these'),
            get_string('since', 'mod_these', ''),
            '',
        ];
        $dateformat = get_string('strftimedate', 'langconfig');
        $rows = [];
        foreach ($this->dossiers as $dossier) {
            $student = (object) [];
            $director = (object) [];
            foreach (\core_user\fields::get_name_fields() as $field) {
                $student->$field = $dossier->{'student' . $field} ?? '';
                $director->$field = $dossier->{'director' . $field} ?? '';
            }
            $url = new moodle_url('/mod/these/dossier.php', ['id' => $this->cm->id, 'dossierid' => $dossier->id]);
            if (rights::user_knows_subject($dossier, $context, $USER->id)) {
                $title = shorten_text(format_string($dossier->title), 90);
                $title = $title ? parts::html($title) : parts::text('-');
            } else {
                $title = parts::em(get_string('confidentialtitle', 'mod_these'));
            }
            $open = parts::link($url, get_string('open', 'mod_these'), 'btn btn-sm btn-secondary');
            $actions = [$open];
            $action = $this->todo
                ? rendering::primary_action($this->cm, $dossier, rights::get_rights($context, $dossier, $USER->id)) : null;
            if ($action) {
                $actions = [parts::link($action[1], $action[0], 'btn btn-sm btn-primary mr-1 mb-1'), $open];
            }
            $row = [
                [parts::text(fullname($student))],
                [$title],
                [parts::text($dossier->directorid ? fullname($director) : '-')],
                [parts::text($dossier->devenumber)],
                [parts::status((new status_badge($dossier->status))->export_for_template($output))],
                [parts::text(userdate($dossier->statustime, $dateformat))],
                $actions,
            ];
            if ($this->selectable) {
                $checkbox = parts::checkbox('dossierids[]', (string) $dossier->id, false, 'these-select-' . $dossier->id);
                array_unshift($row, [$checkbox]);
            }
            $rows[] = $row;
        }
        if ($this->selectable) {
            array_unshift($head, get_string('select'));
        }
        return [
            'isempty' => false,
            'emptymessage' => '',
            'table' => table_context::export('generaltable these-dossiers', $head, $rows),
        ];
    }
}
