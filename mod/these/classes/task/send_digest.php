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
 * Tâche planifiée hebdomadaire : récapitulatif de la semaine envoyé à la DEVE de chaque activité
 * (voir \mod_these\local\notifications::send_weekly_digests()).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_digest extends \core\task\scheduled_task {
    /**
     * Nom de la tâche tel qu'affiché dans l'administration des tâches planifiées.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskdigest', 'mod_these');
    }

    /**
     * Envoie les récapitulatifs.
     *
     * @return void
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/these/locallib.php');
        $sent = \mod_these\local\notifications::send_weekly_digests();
        if ($sent > 0) {
            mtrace("mod_these : $sent récapitulatif(s) envoyé(s).");
        }
    }
}
