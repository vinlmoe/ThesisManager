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

namespace mod_thesesynthesis\local;

use context_module;
use mod_these\local\rights;
use mod_these\local\scopesettings;

/**
 * Activités « Gestion des thèses » suivies par une activité de suivi : liens enregistrés,
 * activités qu'un utilisateur peut lier, activités liées encore présentes avec ses droits.
 *
 * Les fonctions thesesynthesis_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class links {
    /**
     * Liens d'une instance.
     *
     * @param int $synthesisid
     * @return array thesecmid => enregistrement
     */
    public static function get_links($synthesisid) {
        global $DB;

        $links = [];
        foreach ($DB->get_records('thesesynthesis_link', ['synthesisid' => $synthesisid], 'id') as $link) {
            $links[(int) $link->thesecmid] = $link;
        }
        return $links;
    }

    /**
     * Remplace les liens d'une instance.
     *
     * @param int $synthesisid
     * @param int[] $thesecmids
     * @return void
     */
    public static function set_links($synthesisid, array $thesecmids) {
        global $DB;

        $thesecmids = array_unique(array_map('intval', $thesecmids));
        $existing = self::get_links($synthesisid);
        foreach ($existing as $cmid => $link) {
            if (!in_array($cmid, $thesecmids, true)) {
                $DB->delete_records('thesesynthesis_link', ['id' => $link->id]);
            }
        }
        foreach ($thesecmids as $cmid) {
            if (!isset($existing[$cmid])) {
                $DB->insert_record('thesesynthesis_link', (object) [
                    'synthesisid' => $synthesisid,
                    'thesecmid' => $cmid,
                    'timecreated' => time(),
                ]);
            }
        }
        // Les paramètres du circuit des activités suivies changent de portée.
        scopesettings::reset_cache();
    }

    /**
     * Activités « Gestion des thèses » qu'un utilisateur peut lier : celles où il voit tous les
     * dossiers (DEVE, bibliothèque, gestionnaire), pour ne jamais exposer le nom d'un cours auquel il
     * n'a pas accès.
     *
     * @param int $userid
     * @return array thesecmid => {thesecmid, thesename, coursename, visible, coursevisible}
     */
    public static function get_available_activities($userid) {
        global $DB;

        $sql = "SELECT cm.id AS thesecmid, t.name AS thesename, c.fullname AS coursename, cm.visible,
                       c.visible AS coursevisible
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'these'
                  JOIN {these} t ON t.id = cm.instance
                  JOIN {course} c ON c.id = cm.course
                 WHERE cm.deletioninprogress = 0
              ORDER BY c.fullname, t.name";
        $available = [];
        foreach ($DB->get_records_sql($sql) as $record) {
            $userrights = rights::get_rights(context_module::instance($record->thesecmid), null, $userid);
            if ($userrights->viewall) {
                $available[(int) $record->thesecmid] = $record;
            }
        }
        return $available;
    }

    /**
     * Activités liées encore présentes, avec les droits de l'utilisateur dans chacune.
     *
     * @param int $synthesisid
     * @param int $userid
     * @return array Liste d'objets {cm, these, course, context, rights, direct}
     */
    public static function get_active_links($synthesisid, $userid) {
        global $DB;

        $active = [];
        foreach (array_keys(self::get_links($synthesisid)) as $cmid) {
            $cm = get_coursemodule_from_id('these', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm || !empty($cm->deletioninprogress)) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $active[] = (object) [
                'cm' => $cm,
                'these' => $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST),
                'course' => get_course($cm->course),
                'context' => $context,
                'rights' => rights::get_rights($context, null, $userid),
                'direct' => has_capability('mod/these:direct', $context, $userid),
            ];
        }
        return $active;
    }
}
