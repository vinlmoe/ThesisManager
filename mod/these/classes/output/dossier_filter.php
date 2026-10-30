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

use mod_these\local\workflow;
use moodle_url;

/**
 * Filtres de la liste des dossiers du tableau de bord : statut et texte.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_filter implements \renderable, \templatable {
    /** @var moodle_url */
    protected $url;
    /** @var int */
    protected $cmid;
    /** @var string */
    protected $status;
    /** @var string */
    protected $search;

    /**
     * Constructeur.
     *
     * @param moodle_url $url Adresse de la page.
     * @param int $cmid
     * @param string $status Statut choisi ('' : tous).
     * @param string $search Texte recherché.
     */
    public function __construct(moodle_url $url, $cmid, $status, $search) {
        $this->url = $url;
        $this->cmid = (int) $cmid;
        $this->status = (string) $status;
        $this->search = (string) $search;
    }

    /**
     * Données du gabarit mod_these/dossier_filter.
     *
     * @param \renderer_base $output
     * @return array action, cmid, statuslabel, statuses (voir select_context), search, placeholder, filterlabel
     */
    public function export_for_template(\renderer_base $output): array {
        $options = ['' => get_string('allstatuses', 'mod_these')];
        foreach (workflow::all_statuses() as $status) {
            $options[$status] = workflow::status_label($status);
        }
        return [
            'action' => $this->url->out_omit_querystring(),
            'cmid' => $this->cmid,
            'statuslabel' => get_string('status', 'mod_these'),
            'statuses' => select_context::export('status', $options, $this->status, 'mr-2', 'these-status'),
            'search' => $this->search,
            'placeholder' => get_string('searchdossiers', 'mod_these'),
            'filterlabel' => get_string('filter'),
        ];
    }
}
