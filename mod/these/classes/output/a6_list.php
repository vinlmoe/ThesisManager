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

use mod_these\local\signatures;
use moodle_url;
use stdClass;
use mod_these\local\bordereau;

/**
 * Page de validation de l'A6 (a6.php) : filtres, étudiants de l'activité à cocher avec leur
 * validation et le lien de saisie, enregistrement groupé pour les étudiants cochés et lien vers
 * l'import d'un fichier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class a6_list implements \renderable, \templatable {
    /** @var stdClass */
    protected $cm;
    /** @var array */
    protected $students;
    /** @var string */
    protected $state;
    /** @var string */
    protected $search;

    /**
     * Constructeur.
     *
     * @param stdClass $cm
     * @param array $students Voir \mod_these\local\a6::students().
     * @param string $state Filtre : '', 'validated' ou 'notvalidated'.
     * @param string $search Texte recherché.
     */
    public function __construct(stdClass $cm, array $students, $state, $search) {
        $this->cm = $cm;
        $this->students = $students;
        $this->state = (string) $state;
        $this->search = (string) $search;
    }

    /**
     * Données du gabarit mod_these/a6_list.
     *
     * @param \renderer_base $output
     * @return array action, cmid, sesskey, statelabel, states, search, placeholder, filterlabel, isempty,
     *               emptymessage, table, bulklabel, notyetlabel, datelabel, schoollabel, schools, submit,
     *               importurl, importlabel
     */
    public function export_for_template(\renderer_base $output): array {
        $url = \mod_these\local\a6::url($this->cm);
        $rows = [];
        foreach ($this->students as $row) {
            $validated = !$row->a6['notyet'] && $row->a6['date'];
            $rows[] = [
                [parts::checkbox('userids[]', (string) $row->user->id, false, 'these-a6-' . $row->user->id)],
                [parts::text(fullname($row->user))],
                [$row->dossier ? parts::status((new status_badge($row->dossier->status))->export_for_template($output))
                    : parts::muted(get_string('a6nodossier', 'mod_these'))],
                [parts::badge($row->defvlabel, !empty($row->a6['defvdate']) ? 'badge-success' : 'badge-secondary')],
                [parts::badge($row->label, $validated ? 'badge-success' : 'badge-secondary')],
                [parts::link(
                    \mod_these\local\a6::url($this->cm, $row->user->id),
                    get_string('edit'),
                    'btn btn-sm btn-secondary'
                )],
            ];
        }
        $head = [get_string('select'), get_string('student', 'mod_these'), get_string('status', 'mod_these'),
            get_string('defvvalidation', 'mod_these'), get_string('a6validation', 'mod_these'), ''];
        $states = [
            '' => get_string('a6stateall', 'mod_these'),
            'notvalidated' => get_string('a6statenotvalidated', 'mod_these'),
            'validated' => get_string('a6statevalidated', 'mod_these'),
        ];
        return [
            'action' => $url->out_omit_querystring(),
            'cmid' => (int) $this->cm->id,
            'sesskey' => sesskey(),
            'statelabel' => get_string('a6validation', 'mod_these'),
            'states' => select_context::export('state', $states, $this->state, 'mr-2', 'these-a6-state'),
            'search' => $this->search,
            'placeholder' => get_string('a6searchstudent', 'mod_these'),
            'filterlabel' => get_string('filter'),
            'isempty' => !$rows,
            'emptymessage' => get_string('a6nostudents', 'mod_these'),
            'table' => $rows ? table_context::export('generaltable these-a6', $head, $rows) : false,
            'bulklabel' => get_string('a6bulk', 'mod_these'),
            'notyetlabel' => get_string('a6bulknotyet', 'mod_these'),
            'datelabel' => get_string('aga6date', 'mod_these'),
            'schoollabel' => get_string('bschool', 'mod_these'),
            'schools' => array_map(function ($value, $label) {
                return ['value' => $value, 'label' => $label];
            }, array_keys(bordereau::school_options()), bordereau::school_options()),
            'submit' => get_string('a6bulksubmit', 'mod_these'),
            'importurl' => (new moodle_url('/mod/these/a6import.php', ['id' => $this->cm->id]))->out(false),
            'importlabel' => get_string('a6import', 'mod_these'),
        ];
    }
}
