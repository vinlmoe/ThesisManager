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

use moodle_url;
use stdClass;

/**
 * Soutenances d'une période (semaine ou mois), regroupées par jour : un tableau par jour, une ligne
 * par soutenance.
 *
 * Les textes de chaque ligne viennent de these_defense_row(), qui masque le titre d'une thèse
 * confidentielle pour qui ne connaît pas son sujet (these_subject_field()) : le vrai titre n'entre
 * alors pas dans le contexte du gabarit.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planning implements \renderable, \templatable {
    /** @var stdClass[] */
    protected $defenses;
    /** @var bool */
    protected $showpromotion;

    /**
     * Constructeur.
     *
     * @param stdClass[] $defenses Voir these_get_defenses().
     * @param bool $showpromotion Afficher la promotion (planning de plusieurs activités).
     */
    public function __construct(array $defenses, $showpromotion) {
        $this->defenses = $defenses;
        $this->showpromotion = (bool) $showpromotion;
    }

    /**
     * Cellules de la ligne d'une soutenance : heure et statut de la date, étudiant (lien vers le
     * dossier), promotion, titre, directeur, jury (président en tête), lieu.
     *
     * @param stdClass $defense Voir these_get_defenses().
     * @return array[] Une liste de fragments (voir parts) par cellule.
     */
    public function defense_cells(stdClass $defense): array {
        $row = \mod_these\local\planning::defense_row($defense);
        $url = new moodle_url('/mod/these/dossier.php', [
            'id' => $defense->source->cm->id,
            'dossierid' => $defense->dossier->id,
        ]);
        $jury = [];
        if ($row['president'] !== '') {
            $jury = [
                parts::strong($row['president']),
                parts::text(' (' . get_string('juryrolepresident', 'mod_these') . ')'),
                parts::br(),
            ];
        }
        $jury[] = parts::text($row['jury']);
        // Membre déjà engagé dans un autre jury sur le même créneau (personnel qui voit tous les dossiers).
        if (!empty($defense->source->rights->viewall)) {
            foreach (\mod_these\local\jury::jury_conflicts($defense->dossier, $defense->jury) as $conflict) {
                $jury[] = parts::br();
                $jury[] = parts::badge(get_string('juryconflictshort', 'mod_these', (object) [
                    'member' => $conflict->member,
                    'student' => $conflict->student,
                    'time' => userdate($conflict->time, get_string('strftimedatetimeshort', 'langconfig')),
                ]), 'badge badge-danger');
            }
        }
        return array_merge(
            [
                [parts::text($row['time']), parts::br(),
                    parts::badge($row['status'], 'badge ' . ($row['confirmed'] ? 'badge-success' : 'badge-warning'))],
                [parts::link($url, $row['student'], '')],
            ],
            $this->showpromotion ? [[parts::text($row['promotion'])]] : [],
            [
                [parts::text($row['title'])],
                [parts::text($row['director'])],
                $jury,
                $this->location_cell($defense, $row['location']),
            ]
        );
    }

    /**
     * Cellule du lieu : salle, et badge si une autre soutenance occupe la même salle sur le créneau
     * (personnel qui voit tous les dossiers).
     *
     * @param stdClass $defense Voir these_get_defenses().
     * @param string $location
     * @return array Fragments (voir parts).
     */
    protected function location_cell(stdClass $defense, string $location): array {
        $cell = [parts::text($location)];
        if (!empty($defense->source->rights->viewall)) {
            foreach (\mod_these\local\jury::room_conflicts($defense->dossier) as $conflict) {
                $cell[] = parts::br();
                $cell[] = parts::badge(get_string('roomconflictshort', 'mod_these', (object) [
                    'student' => $conflict->student,
                    'time' => userdate($conflict->time, get_string('strftimedatetimeshort', 'langconfig')),
                ]), 'badge badge-danger');
            }
        }
        return $cell;
    }

    /**
     * Données du gabarit mod_these/planning.
     *
     * @param \renderer_base $output
     * @return array isempty, emptymessage, days (heading, table : voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        if (!$this->defenses) {
            return ['isempty' => true, 'emptymessage' => get_string('planningnone', 'mod_these'), 'days' => []];
        }
        $bydate = [];
        foreach ($this->defenses as $defense) {
            $bydate[usergetmidnight($defense->dossier->defensedate)][] = $defense;
        }
        $head = array_merge(
            [get_string('planningtime', 'mod_these'), get_string('student', 'mod_these')],
            $this->showpromotion ? [get_string('planningpromotion', 'mod_these')] : [],
            [
                get_string('subjecttitle', 'mod_these'),
                get_string('director', 'mod_these'),
                get_string('jury', 'mod_these'),
                get_string('defenselocation', 'mod_these'),
            ]
        );
        $days = [];
        foreach ($bydate as $defenses) {
            $rows = array_map([$this, 'defense_cells'], $defenses);
            $days[] = [
                'heading' => \mod_these\local\planning::defense_row($defenses[0])['day'],
                'table' => table_context::export('generaltable these-planning', $head, $rows),
            ];
        }
        return ['isempty' => false, 'emptymessage' => '', 'days' => $days];
    }
}
