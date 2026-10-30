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
use moodle_url;
use stdClass;

/**
 * Portée des paramètres partagés d'une activité « Suivi des thèses » : ses modèles de documents,
 * images, grille de conformité, questionnaire et paramètres du circuit valent pour toutes les
 * activités « Gestion des thèses » qu'elle suit. Ces méthodes répondent aux rappels (callbacks)
 * de mod_these déclarés dans lib.php.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scope {
    /**
     * Portée d'une activité « Suivi des thèses ».
     *
     * @param int $synthesisid
     * @return stdClass|null {theseid, synthesisid, shared, component, contextid, cmid, name}, voir
     *                       \mod_these\local\doctemplates::local_scope() ; null si l'activité n'existe pas.
     */
    public static function for_synthesis($synthesisid) {
        global $DB;

        $synthesis = $DB->get_record('thesesynthesis', ['id' => $synthesisid]);
        $cm = $synthesis ? get_coursemodule_from_instance('thesesynthesis', $synthesis->id, 0, false, IGNORE_MISSING) : null;
        if (!$cm) {
            return null;
        }
        return (object) [
            'theseid' => 0,
            'synthesisid' => (int) $synthesis->id,
            'shared' => true,
            'component' => 'mod_thesesynthesis',
            'contextid' => context_module::instance($cm->id)->id,
            'cmid' => (int) $cm->id,
            'name' => (string) $synthesis->name,
        ];
    }

    /**
     * Portée partagée en vigueur pour une activité « Gestion des thèses » : celle de l'activité de
     * suivi qui la suit (la plus ancienne si plusieurs la suivent).
     *
     * @param stdClass $these
     * @return stdClass|null Voir for_synthesis() ; null si aucune activité de suivi ne la suit.
     */
    public static function for_these(stdClass $these) {
        global $DB;

        if (empty($these->id)) {
            return null;
        }
        $cm = get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $synthesisids = $DB->get_fieldset_sql(
            "SELECT l.synthesisid
               FROM {thesesynthesis_link} l
               JOIN {thesesynthesis} t ON t.id = l.synthesisid
              WHERE l.thesecmid = :cmid
           ORDER BY l.synthesisid",
            ['cmid' => $cm->id]
        );
        foreach ($synthesisids as $synthesisid) {
            if ($scope = self::for_synthesis($synthesisid)) {
                return $scope;
            }
        }
        return null;
    }

    /**
     * Modules de cours des activités « Gestion des thèses » suivies dans une portée.
     *
     * @param stdClass $scope Voir for_synthesis().
     * @return int[]
     */
    public static function linked_cmids(stdClass $scope) {
        global $DB;

        if (empty($scope->synthesisid)) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select(
            'thesesynthesis_link',
            'thesecmid',
            'synthesisid = ?',
            [$scope->synthesisid]
        ));
    }

    /**
     * Pages de l'activité de suivi d'une portée : son accueil et ses paramètres du circuit.
     *
     * @param stdClass $scope Voir for_synthesis().
     * @return moodle_url[] {home, settings}
     */
    public static function urls(stdClass $scope) {
        return [
            'home' => new moodle_url('/mod/thesesynthesis/view.php', ['id' => $scope->cmid]),
            'settings' => new moodle_url('/mod/thesesynthesis/parameters.php', ['id' => $scope->cmid]),
        ];
    }
}
