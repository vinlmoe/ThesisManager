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
 * Section de détail : un titre et un tableau à deux colonnes (libellé, valeur). Les lignes sans valeur
 * sont omises, et la section entière quand aucune ligne ne reste.
 *
 * Les valeurs sont des listes de fragments (voir parts) : le choix de ce qui est montré (sujet masqué
 * d'une thèse confidentielle, zones de fichiers autorisées) est fait par l'appelant, avant.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class detail_section implements \renderable, \templatable {
    /** @var string */
    protected $heading;
    /** @var array[] Lignes : label (fragment du libellé), value (fragments de la valeur). */
    protected $rows = [];

    /**
     * Constructeur.
     *
     * @param string $heading Titre (texte brut).
     * @param array $rows libellé (texte brut) => liste de fragments (voir parts) ; null ou liste vide : ligne omise.
     */
    public function __construct($heading, array $rows = []) {
        $this->heading = (string) $heading;
        foreach ($rows as $label => $value) {
            $this->add_row(parts::strong((string) $label), $value);
        }
    }

    /**
     * Section construite à partir de valeurs HTML (appelants de these_render_detail_section()).
     *
     * @param string $heading Titre (texte brut).
     * @param array $rows libellé => valeur HTML déjà échappée (format_text(), format_string(), s()) ;
     *                    null ou '' : ligne omise.
     * @return detail_section
     */
    public static function from_html($heading, array $rows): detail_section {
        $section = new detail_section($heading);
        foreach ($rows as $label => $value) {
            if ($value !== null && $value !== '') {
                $section->add_row(parts::stronghtml((string) $label), [parts::html((string) $value)]);
            }
        }
        return $section;
    }

    /**
     * Ajoute une ligne, sauf si sa valeur est vide.
     *
     * @param array $label Fragment du libellé.
     * @param array|null $value Liste de fragments.
     * @return void
     */
    public function add_row(array $label, ?array $value): void {
        if (!empty($value)) {
            $this->rows[] = ['label' => $label, 'value' => array_values($value)];
        }
    }

    /**
     * Indique si la section n'a aucune ligne (elle n'est alors pas affichée).
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->rows);
    }

    /**
     * Données du gabarit mod_these/detail_section.
     *
     * @param \renderer_base $output
     * @return array hasrows, heading, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = array_map(function ($row) {
            return [[$row['label']], $row['value']];
        }, $this->rows);
        return [
            'hasrows' => !empty($rows),
            'heading' => $this->heading,
            'table' => table_context::export('generaltable these-detail', [], $rows),
        ];
    }
}
