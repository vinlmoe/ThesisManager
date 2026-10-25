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

use mod_these\output\select_context;
use moodle_url;

/**
 * Filtres des dossiers suivis : promotion, statut, texte.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_filters implements \renderable, \templatable {
    /** @var moodle_url */
    protected $baseurl;
    /** @var int */
    protected $cmid;
    /** @var array */
    protected $links;
    /** @var array */
    protected $values;

    /**
     * Constructeur.
     *
     * @param moodle_url $baseurl Adresse de la page.
     * @param int $cmid Module de l'activité de suivi.
     * @param array $links Voir thesesynthesis_get_active_links().
     * @param int $filtercmid Promotion choisie (0 : toutes).
     * @param int $status Statut choisi (-999 : tous).
     * @param string $search Texte recherché.
     */
    public function __construct(moodle_url $baseurl, $cmid, array $links, $filtercmid, $status, $search) {
        $this->baseurl = $baseurl;
        $this->cmid = (int) $cmid;
        $this->links = $links;
        $this->values = ['cmid' => (int) $filtercmid, 'status' => (int) $status, 'search' => (string) $search];
    }

    /**
     * Données du gabarit mod_thesesynthesis/dossier_filters.
     *
     * @param \renderer_base $output
     * @return array action, cmid, promotions, statuses (voir select_context), search, placeholder, filterlabel
     */
    public function export_for_template(\renderer_base $output): array {
        $class = 'custom-select mr-2 mb-1';
        $promotions = search_form::promotion_options($this->links);
        return [
            'action' => $this->baseurl->out_omit_querystring(),
            'cmid' => $this->cmid,
            'promotions' => select_context::export('cmid', $promotions, $this->values['cmid'], $class),
            'statuses' => select_context::export('status', search_form::status_options(), $this->values['status'], $class),
            'search' => $this->values['search'],
            'placeholder' => get_string('search'),
            'filterlabel' => get_string('filter'),
        ];
    }
}
