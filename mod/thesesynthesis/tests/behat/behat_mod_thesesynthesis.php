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

/**
 * Étapes Behat propres à mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @category  test
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Étapes Behat propres à mod_thesesynthesis : promotions suivies par une activité de suivi.
 *
 * @package   mod_thesesynthesis
 * @category  test
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_thesesynthesis extends behat_base {
    /**
     * Lie une activité de suivi aux activités mod_these des promotions qu'elle suit, comme la page
     * d'administration du suivi (activités désignées par leur numéro d'identification).
     *
     * @Given /^the thesis follow-up "(?P<synthesis_string>[^"]*)" follows the thesis activities "(?P<activities_string>[^"]*)"$/
     * @param string $synthesis Numéro d'identification de l'activité de suivi.
     * @param string $activities Numéros d'identification des activités mod_these, séparés par des virgules.
     * @return void
     */
    public function the_thesis_follow_up_follows_the_thesis_activities(string $synthesis, string $activities): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

        $synthesisid = $DB->get_field_sql(
            "SELECT cm.instance
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE m.name = 'thesesynthesis' AND cm.idnumber = ?",
            [$synthesis],
            MUST_EXIST
        );
        $cmids = [];
        foreach (array_map('trim', explode(',', $activities)) as $idnumber) {
            $cmids[] = (int) $DB->get_field_sql(
                "SELECT cm.id
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module
                  WHERE m.name = 'these' AND cm.idnumber = ?",
                [$idnumber],
                MUST_EXIST
            );
        }
        thesesynthesis_set_links($synthesisid, $cmids);
    }
}
