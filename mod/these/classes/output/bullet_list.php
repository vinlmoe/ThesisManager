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

/**
 * Liste à puces, au balisage de html_writer::alist().
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bullet_list implements \renderable, \templatable {
    /** @var array[] */
    protected $items;

    /**
     * Constructeur.
     *
     * @param array $items Éléments : texte brut, ou liste de fragments (voir parts).
     */
    public function __construct(array $items) {
        $this->items = array_map(function ($item) {
            return ['parts' => is_array($item) ? array_values($item) : [parts::text((string) $item)]];
        }, array_values($items));
    }

    /**
     * Données du gabarit mod_these/bullet_list.
     *
     * @param \renderer_base $output
     * @return array items (parts)
     */
    public function export_for_template(\renderer_base $output): array {
        return ['items' => $this->items];
    }
}
