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

use mod_these\local\workflow;
use moodle_url;
use stdClass;

/**
 * Décompte des dossiers par statut, en badges cliquables qui filtrent la liste du tableau de bord.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_counts implements \renderable, \templatable {
    /** @var stdClass */
    protected $cm;
    /** @var int[] */
    protected $counts;

    /**
     * Constructeur.
     *
     * @param stdClass $cm
     * @param int[] $counts statut => nombre (voir these_count_by_status()).
     */
    public function __construct(stdClass $cm, array $counts) {
        $this->cm = $cm;
        $this->counts = $counts;
    }

    /**
     * Données du gabarit mod_these/status_counts.
     *
     * @param \renderer_base $output
     * @return array hasbadges, badges (label, url, class, separator)
     */
    public function export_for_template(\renderer_base $output): array {
        $badges = [];
        foreach (workflow::all_statuses() as $status) {
            if (empty($this->counts[$status])) {
                continue;
            }
            $badges[] = [
                'label' => workflow::status_label($status) . ' : ' . $this->counts[$status],
                'url' => (new moodle_url('/mod/these/dashboard.php', ['id' => $this->cm->id, 'status' => $status]))->out(false),
                'class' => 'badge ' . workflow::status_badgeclass($status) . ' mr-1 mb-1 p-2',
                'separator' => !empty($badges),
            ];
        }
        return ['hasbadges' => !empty($badges), 'badges' => $badges];
    }
}
