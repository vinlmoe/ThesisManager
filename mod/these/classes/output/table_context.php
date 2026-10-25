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
 * Contexte du partiel mod_these/table : le même balisage que html_writer::table() (enveloppe
 * table-responsive, classes header/cell cN, lastcol, lastrow), sur lequel s'appuient le thème et Behat.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_context {
    /**
     * Contexte d'un tableau.
     *
     * @param string $class Classes CSS du tableau.
     * @param string[] $head En-têtes de colonnes (texte brut) ; tableau vide : pas de ligne d'en-tête.
     * @param array[] $rows Lignes : une liste de cellules, chacune une liste de fragments (voir parts).
     * @return array
     */
    public static function export(string $class, array $head, array $rows): array {
        $headcells = [];
        $last = count($head) - 1;
        foreach (array_values($head) as $index => $text) {
            // Comme html_writer::table() : un en-tête vide est une cellule td, sans scope.
            $headcells[] = [
                'text' => (string) $text,
                'class' => 'header c' . $index . ($index === $last ? ' lastcol' : ''),
                'isth' => (string) $text !== '',
            ];
        }
        $rowcontexts = [];
        $lastrow = count($rows) - 1;
        foreach (array_values($rows) as $rowindex => $cells) {
            $cellcontexts = [];
            $lastcell = count($cells) - 1;
            foreach (array_values($cells) as $index => $cellparts) {
                $cellcontexts[] = [
                    'class' => 'cell c' . $index . ($index === $lastcell ? ' lastcol' : ''),
                    'parts' => array_values($cellparts),
                ];
            }
            $rowcontexts[] = ['class' => $rowindex === $lastrow ? 'lastrow' : '', 'cells' => $cellcontexts];
        }
        return [
            'tableclass' => $class,
            'hashead' => !empty($headcells),
            'head' => $headcells,
            'rows' => $rowcontexts,
        ];
    }
}
