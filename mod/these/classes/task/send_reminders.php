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

namespace mod_these\task;

/**
 * Tâche planifiée : relance l'acteur attendu (directeur, DEVE, bibliothèque, ou étudiant dont le
 * dossier a été renvoyé) quand un dossier attend à la même étape depuis plus longtemps que le
 * délai de relance de son activité. La relance est répétée à chaque nouveau délai écoulé, tant
 * que l'étape n'a pas avancé. Relance de même les signataires attendus (voir
 * notifications::send_signature_reminders()) et accorde le calendrier aux soutenances.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Nom de la tâche tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskreminders', 'mod_these');
    }

    /**
     * Parcourt les dossiers à relancer.
     *
     * @return void
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/these/locallib.php');

        $dossiers = these_get_dossiers_needing_reminder();
        $instances = [];
        $cms = [];
        $sent = 0;
        foreach ($dossiers as $dossier) {
            if (!array_key_exists($dossier->theseid, $instances)) {
                $instances[$dossier->theseid] = $DB->get_record('these', ['id' => $dossier->theseid]);
                $cms[$dossier->theseid] = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, IGNORE_MISSING);
            }
            $these = $instances[$dossier->theseid];
            $cm = $cms[$dossier->theseid];
            if (!$these || !$cm) {
                continue;
            }
            unset($dossier->reminderdays);
            if (these_send_reminder($these, $cm, $dossier)) {
                $sent++;
                mtrace("mod_these : dossier {$dossier->id} (" . format_string($these->name) . ') relancé, étape '
                    . these_status_label($dossier->status) . '.');
            }
        }
        mtrace("mod_these : $sent relance(s) envoyée(s).");
        // Signatures attendues depuis plus que le délai de relance.
        $signers = \mod_these\local\notifications::send_signature_reminders(null, function ($line) {
            mtrace('mod_these : ' . $line);
        });
        mtrace("mod_these : $signers signataire(s) relancé(s).");
        // Calendrier : soutenances arrêtées (y compris celles fixées avant la synchronisation).
        \mod_these\local\calendar::sync_all();
    }
}
