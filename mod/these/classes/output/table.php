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
 * Tableau simple : en-têtes et cellules faites de fragments (voir parts), au balisage de
 * html_writer::table(). Rendu par le gabarit mod_these/table, aussi utilisé comme partiel.
 *
 * Les cellules sont préparées par l'appelant, qui décide de ce qu'elles montrent.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table implements \renderable, \templatable {
    /** @var string */
    protected $class;
    /** @var string[] */
    protected $head;
    /** @var array[] */
    protected $rows;

    /**
     * Constructeur.
     *
     * @param string[] $head En-têtes (texte brut) ; vide : pas de ligne d'en-tête.
     * @param array[] $rows Lignes : une liste de cellules, chacune une liste de fragments.
     * @param string $class Classes CSS du tableau.
     */
    public function __construct(array $head, array $rows, $class = 'generaltable') {
        $this->head = $head;
        $this->rows = $rows;
        $this->class = (string) $class;
    }

    /**
     * Indique si le tableau n'a aucune ligne.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->rows);
    }

    /**
     * Données du gabarit mod_these/table.
     *
     * @param \renderer_base $output
     * @return array Voir table_context::export().
     */
    public function export_for_template(\renderer_base $output): array {
        return table_context::export($this->class, $this->head, $this->rows);
    }
}
