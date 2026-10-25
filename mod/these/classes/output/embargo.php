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

use stdClass;

/**
 * Diffusion de la thèse choisie au dépôt de la soutenance : mode de diffusion et demande d'embargo,
 * avec sa date de fin (voir these_embargo_until()).
 *
 * Le motif de l'embargo, qui peut révéler le sujet, n'entre dans le contexte du gabarit que pour qui
 * connaît le sujet.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embargo implements \renderable, \templatable {
    /** @var stdClass */
    protected $dossier;
    /** @var stdClass */
    protected $rights;

    /**
     * Constructeur.
     *
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     */
    public function __construct(stdClass $dossier, stdClass $rights) {
        $this->dossier = $dossier;
        $this->rights = $rights;
    }

    /**
     * Section de détail de la diffusion.
     *
     * @return detail_section
     */
    public function get_section(): detail_section {
        $section = new detail_section(get_string('diffusion', 'mod_these'));
        $bordereau = these_get_bordereau($this->dossier);
        if (!$bordereau) {
            return $section;
        }
        $diffusion = (string) ($bordereau['diffusion'] ?? '');
        $section->add_row(
            parts::strong(get_string('diffusion', 'mod_these')),
            in_array($diffusion, ['internet', 'restricted'], true)
                ? [parts::text(get_string('diffusion' . $diffusion, 'mod_these'))] : null
        );
        if (!empty($bordereau['embargo'])) {
            $until = these_embargo_until($this->dossier);
            $label = $until
                ? get_string('embargorequested', 'mod_these', (object) [
                    'years' => THESE_EMBARGO_YEARS,
                    'until' => userdate($until, get_string('strftimedate', 'langconfig')),
                ])
                : get_string('embargorequestednodate', 'mod_these', THESE_EMBARGO_YEARS);
            $section->add_row(parts::strong(get_string('embargo', 'mod_these')), [parts::badge($label, 'badge badge-warning')]);
            if ($this->rights->knowssubject && trim((string) ($bordereau['embargoreason'] ?? '')) !== '') {
                $section->add_row(
                    parts::strong(get_string('embargoreason', 'mod_these')),
                    [parts::html(format_text((string) $bordereau['embargoreason'], FORMAT_PLAIN))]
                );
            }
        }
        return $section;
    }

    /**
     * Données du gabarit mod_these/embargo (celles de mod_these/detail_section).
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        return $this->get_section()->export_for_template($output);
    }
}
