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

/**
 * Semaines à venir qui ont des soutenances, avec leur nombre (vue par semaine).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planning_upcoming implements \renderable, \templatable {
    /** @var moodle_url */
    protected $baseurl;
    /** @var int[] */
    protected $counts;

    /**
     * Constructeur.
     *
     * @param moodle_url $baseurl Adresse de la page (avec ses paramètres d'accès).
     * @param int[] $counts début de semaine => nombre de soutenances (voir these_count_defenses_by_week()).
     */
    public function __construct(moodle_url $baseurl, array $counts) {
        $this->baseurl = $baseurl;
        $this->counts = $counts;
    }

    /**
     * Données du gabarit mod_these/planning_upcoming.
     *
     * @param \renderer_base $output
     * @return array weeks (url, label, count)
     */
    public function export_for_template(\renderer_base $output): array {
        $dateformat = get_string('strftimedate', 'langconfig');
        $weeks = [];
        foreach ($this->counts as $week => $count) {
            $weeks[] = [
                'url' => (new moodle_url($this->baseurl, ['view' => 'week', 'date' => $week]))->out(false),
                'label' => get_string('planningweekof', 'mod_these', (object) [
                    'start' => userdate($week, $dateformat),
                    'end' => userdate(these_planning_shift_week($week, 1) - DAYSECS, $dateformat),
                ]),
                'count' => get_string('planningcount', 'mod_these', $count),
            ];
        }
        return ['weeks' => $weeks];
    }
}
