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
 * Frise de progression d'un dossier dans le circuit.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress implements \renderable, \templatable {
    /** @var int Étape après laquelle s'intercale la phase dormante (fin de la phase du sujet). */
    const DORMANT_AFTER = 3;

    /** @var stdClass */
    protected $dossier;

    /**
     * Constructeur.
     *
     * @param stdClass $dossier
     */
    public function __construct(stdClass $dossier) {
        $this->dossier = $dossier;
    }

    /**
     * Données du gabarit mod_these/progress.
     *
     * @param \renderer_base $output
     * @return array steps (label, actor, stateclass, separator : libellé de la phase dormante qui suit, ou
     *               false), current (résumé de l'étape en cours pour les petits écrans, ou false), alllabel
     */
    public function export_for_template(\renderer_base $output): array {
        $status = (int) $this->dossier->status;
        $rank = these_status_rank($status === THESE_STATUS_CANCELLED ? $this->dossier->cancelledfromstatus : $status);
        $steps = [];
        $current = false;
        $currentfound = false;
        $total = count(these_progress_steps());
        foreach (these_progress_steps() as $index => $step) {
            if ($rank >= $step['done']) {
                $class = 'these-step-done';
            } else if (!$currentfound && $status !== THESE_STATUS_CANCELLED) {
                $class = 'these-step-current';
                $currentfound = true;
                $current = [
                    'label' => get_string('progresscurrent', 'mod_these', (object) [
                        'position' => $index + 1,
                        'total' => $total,
                        'label' => $step['label'],
                    ]),
                    'actor' => get_string('waitingfor', 'mod_these', these_actor_label($step['actor'])),
                ];
            } else {
                $class = 'these-step-todo';
            }
            $steps[] = [
                'label' => ($index + 1) . '. ' . $step['label'],
                'actor' => these_actor_label($step['actor']),
                'stateclass' => $class,
                'separator' => $index === self::DORMANT_AFTER ? get_string('dormantphase', 'mod_these') : false,
            ];
        }
        return ['steps' => $steps, 'current' => $current, 'alllabel' => get_string('progressall', 'mod_these')];
    }
}
