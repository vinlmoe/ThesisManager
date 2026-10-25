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
 * Bouton du planning imprimable (PDF) de la période affichée.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planning_print implements \renderable, \templatable {
    /** @var moodle_url */
    protected $baseurl;
    /** @var string */
    protected $view;
    /** @var int */
    protected $from;

    /**
     * Constructeur.
     *
     * @param moodle_url $baseurl Adresse de la page (avec ses paramètres d'accès).
     * @param string $view week ou month.
     * @param int $from Début de la période affichée.
     */
    public function __construct(moodle_url $baseurl, $view, $from) {
        $this->baseurl = $baseurl;
        $this->view = $view === 'month' ? 'month' : 'week';
        $this->from = (int) $from;
    }

    /**
     * Données du gabarit mod_these/planning_print.
     *
     * @param \renderer_base $output
     * @return array url, label
     */
    public function export_for_template(\renderer_base $output): array {
        $url = new moodle_url($this->baseurl, ['view' => $this->view, 'date' => $this->from, 'format' => 'pdf']);
        return [
            'url' => $url->out(false),
            'label' => get_string($this->view === 'month' ? 'planningpdfmonth' : 'planningpdfweek', 'mod_these'),
        ];
    }
}
