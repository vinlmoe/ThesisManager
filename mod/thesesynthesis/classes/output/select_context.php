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

/**
 * Contexte du partiel mod_thesesynthesis/select : le balisage de html_writer::select() (classes
 * « select custom-select », identifiant « menu<nom> » par défaut, option choisie).
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_context {
    /**
     * Contexte d'une liste déroulante.
     *
     * @param string $name
     * @param array $options valeur => libellé (texte brut)
     * @param string|int $selected Valeur choisie.
     * @param string $class Classes CSS ajoutées à « select custom-select ».
     * @param string|null $id Identifiant ; null : « menu<nom> ».
     * @return array
     */
    public static function export(string $name, array $options, $selected, string $class, ?string $id = null): array {
        $choices = [];
        foreach ($options as $value => $label) {
            $choices[] = ['value' => (string) $value, 'label' => (string) $label,
                'selected' => (string) $value === (string) $selected];
        }
        return [
            'name' => $name,
            'id' => $id ?? 'menu' . $name,
            'class' => 'select custom-select ' . $class,
            'options' => $choices,
        ];
    }
}
