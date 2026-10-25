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

use mod_these\local\jurors;
use moodle_url;

/**
 * Annuaire des enseignants qui peuvent siéger dans un jury, avec leurs actions.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class juror_list implements \renderable, \templatable {
    /** @var array */
    protected $jurors;
    /** @var moodle_url */
    protected $baseurl;

    /**
     * Constructeur.
     *
     * @param array $jurors Voir jurors::get_all().
     * @param moodle_url $baseurl Adresse de la page.
     */
    public function __construct(array $jurors, moodle_url $baseurl) {
        $this->jurors = $jurors;
        $this->baseurl = $baseurl;
    }

    /**
     * Indique si l'annuaire est vide.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->jurors);
    }

    /**
     * Données du gabarit mod_these/juror_list.
     *
     * @param \renderer_base $output
     * @return array Voir table_context::export().
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        foreach ($this->jurors as $juror) {
            $active = get_string($juror->active ? 'juroractiveyes' : 'juroractiveno', 'mod_these');
            $edit = new moodle_url($this->baseurl, ['action' => 'edit', 'jurorid' => $juror->id]);
            $delete = new moodle_url($this->baseurl, ['action' => 'delete', 'jurorid' => $juror->id]);
            $rows[] = [
                [parts::text(jurors::name($juror))],
                [parts::text(jurors::status_label($juror->status))],
                [parts::text(jurors::can_preside($juror->status) ? get_string('yes') : get_string('no'))],
                [parts::text($juror->institution)],
                [parts::text($juror->email)],
                [parts::text($active)],
                [parts::link($edit, get_string('edit'), ''), parts::text(' | '), parts::link($delete, get_string('delete'), '')],
            ];
        }
        $head = [
            get_string('juryname', 'mod_these'),
            get_string('jurystatus', 'mod_these'),
            get_string('jurorpresident', 'mod_these'),
            get_string('juryinstitution', 'mod_these'),
            get_string('email'),
            get_string('status'),
            get_string('actions'),
        ];
        return table_context::export('generaltable these-jurors', $head, $rows);
    }
}
