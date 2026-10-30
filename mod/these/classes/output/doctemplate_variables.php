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

/**
 * Variables disponibles dans un modèle de document, avec une valeur d'exemple tirée de données
 * fictives (voir these_doctemplate_sample_context()).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doctemplate_variables implements \renderable, \templatable {
    /** @var array */
    protected $sample;

    /**
     * Constructeur.
     *
     * @param array $sample Données d'exemple du modèle.
     */
    public function __construct(array $sample) {
        $this->sample = $sample;
    }

    /**
     * Données du gabarit mod_these/doctemplate_variables.
     *
     * @param \renderer_base $output
     * @return array Voir table_context::export().
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        foreach (doctemplates::doctemplate_variables($this->sample) as $name => $example) {
            $rows[] = [[parts::code('{{' . $name . '}}')], [parts::text((string) $example)]];
        }
        $head = [get_string('doctplvariable', 'mod_these'), get_string('doctplexample', 'mod_these')];
        return table_context::export('generaltable table-sm', $head, $rows);
    }
}
