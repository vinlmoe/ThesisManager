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

use mod_these\local\doctemplates;
use moodle_url;
use stdClass;

/**
 * Liste des modèles de documents d'une portée de paramètres : état (d'origine ou modifié) et actions.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doctemplate_list implements \renderable, \templatable {
    /** @var stdClass */
    protected $scope;
    /** @var moodle_url */
    protected $listurl;

    /**
     * Constructeur.
     *
     * @param stdClass $scope Portée (voir these_settings_scope()).
     * @param moodle_url $listurl Adresse de la liste.
     */
    public function __construct(stdClass $scope, moodle_url $listurl) {
        $this->scope = $scope;
        $this->listurl = $listurl;
    }

    /**
     * Données du gabarit mod_these/doctemplate_list.
     *
     * @param \renderer_base $output
     * @return array Voir table_context::export().
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        foreach (array_keys(doctemplates::doctemplate_types()) as $type) {
            $customised = doctemplates::doctemplate_customised($this->scope, $type);
            $actions = [
                parts::link(new moodle_url($this->listurl, ['type' => $type]), get_string('edit'), 'btn btn-sm btn-primary'),
                parts::text(' '),
                parts::link(
                    new moodle_url($this->listurl, ['type' => $type, 'action' => 'preview']),
                    get_string('doctplpreview', 'mod_these'),
                    'btn btn-sm btn-secondary',
                    '_blank'
                ),
            ];
            if ($customised) {
                $actions[] = parts::text(' ');
                $actions[] = parts::link(
                    new moodle_url($this->listurl, ['type' => $type, 'action' => 'reset']),
                    get_string('doctplreset', 'mod_these'),
                    'btn btn-sm btn-outline-danger'
                );
            }
            $rows[] = [
                [parts::text(doctemplates::doctemplate_label($type))],
                [$customised
                    ? parts::span(get_string('doctplcustomised', 'mod_these'), 'badge badge-warning bg-warning text-dark')
                    : parts::span(get_string('doctploriginal', 'mod_these'), 'badge badge-secondary bg-secondary')],
                $actions,
            ];
        }
        $head = [get_string('doctpldocument', 'mod_these'), get_string('status'), get_string('actions')];
        return table_context::export('generaltable', $head, $rows);
    }
}
