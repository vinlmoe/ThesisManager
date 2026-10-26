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
 * Liste de dossiers à cocher et actions groupées de la DEVE (voir \mod_these\local\bulk), envoyées à
 * bulk.php.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_form implements \renderable, \templatable {
    /** @var stdClass */
    protected $cm;
    /** @var array */
    protected $dossiers;

    /**
     * Constructeur.
     *
     * @param array $dossiers Voir these_get_dossiers().
     * @param stdClass $cm
     */
    public function __construct(array $dossiers, stdClass $cm) {
        $this->dossiers = $dossiers;
        $this->cm = $cm;
    }

    /**
     * Données du gabarit mod_these/bulk_form.
     *
     * @param \renderer_base $output
     * @return array action, cmid, sesskey, table, label, options, submit, a6label, a6datelabel, schoollabel, schools,
     *               importurl, importlabel
     */
    public function export_for_template(\renderer_base $output): array {
        $options = [];
        foreach (\mod_these\local\bulk::actions() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return [
            'action' => (new moodle_url('/mod/these/bulk.php'))->out(false),
            'cmid' => (int) $this->cm->id,
            'sesskey' => sesskey(),
            'table' => $output->render(new dossier_table($this->dossiers, $this->cm, ['selectable' => true])),
            'label' => get_string('bulkfor', 'mod_these'),
            'options' => $options,
            'submit' => get_string('bulkapply', 'mod_these'),
            'a6label' => get_string('bulka6params', 'mod_these'),
            'a6datelabel' => get_string('aga6date', 'mod_these'),
            'schoollabel' => get_string('bschool', 'mod_these'),
            'schools' => array_map(function ($value, $label) {
                return ['value' => $value, 'label' => $label];
            }, array_keys(these_school_options()), these_school_options()),
            'importurl' => (new moodle_url('/mod/these/a6import.php', ['id' => $this->cm->id]))->out(false),
            'importlabel' => get_string('a6import', 'mod_these'),
        ];
    }
}
