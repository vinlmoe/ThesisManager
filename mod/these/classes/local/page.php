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

namespace mod_these\local;

use context_module;
use stdClass;

/**
 * Amorce des pages du module : chargement de l'activité et du dossier demandés.
 *
 * Chaque page de mod/these/ commence par les mêmes étapes (module de cours, cours, instance,
 * contexte, puis dossier et droits), regroupées ici avec les mêmes vérifications (MUST_EXIST).
 * L'appel à require_login() reste dans chaque page, juste après load_activity(), comme le veut
 * la convention Moodle (et la règle moodle.Files.RequireLogin de moodle-cs).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {
    /**
     * Charge l'activité d'une page : module de cours, cours et instance (MUST_EXIST), puis contexte
     * du module. La page appelle ensuite require_login($course, true, $cm), puis ses propres contrôles
     * d'accès (these_require_view(), require_capability()...).
     *
     * @param int $cmid Identifiant du module de cours (paramètre id des pages).
     * @return stdClass {cm, course, these, context}
     */
    public static function load_activity(int $cmid): stdClass {
        global $DB;

        $cm = get_coursemodule_from_id('these', $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);

        return (object) ['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context];
    }

    /**
     * Charge un dossier de l'activité (MUST_EXIST : il doit lui appartenir) et les droits de
     * l'utilisateur sur ce dossier (voir rights::get_rights()).
     *
     * @param stdClass $activity Voir load_activity().
     * @param int $dossierid
     * @param int $userid
     * @return stdClass {dossier, rights}
     */
    public static function load_dossier(stdClass $activity, int $dossierid, int $userid): stdClass {
        global $DB;

        $dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $activity->these->id], '*', MUST_EXIST);
        $rights = rights::get_rights($activity->context, $dossier, $userid);

        return (object) ['dossier' => $dossier, 'rights' => $rights];
    }
}
