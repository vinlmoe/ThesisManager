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

namespace mod_these\event;

use context;
use context_module;
use moodle_url;
use stdClass;

/**
 * Socle des événements portant sur un dossier de thèse : l'objet est le dossier (these_dossier),
 * l'utilisateur lié son étudiant, l'adresse la page du dossier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class dossier_event extends \core\event\base {
    /**
     * Crée l'événement pour un dossier.
     *
     * @param stdClass $dossier
     * @param context $context Contexte de l'activité du dossier.
     * @param int $userid Auteur de l'action (0 pour le système : tâche planifiée, relance).
     * @param array $other Informations propres à l'événement.
     * @return static
     */
    public static function create_from_dossier(stdClass $dossier, context $context, $userid, array $other = []) {
        $event = static::create([
            'objectid' => (int) $dossier->id,
            'context' => $context,
            'userid' => (int) $userid,
            'relateduserid' => (int) $dossier->userid,
            'other' => $other,
        ]);
        $event->add_record_snapshot('these_dossier', $dossier);
        return $event;
    }

    /**
     * Contexte de l'activité d'un dossier, s'il en a encore un.
     *
     * @param stdClass $dossier
     * @return context_module|null
     */
    public static function context_for(stdClass $dossier) {
        $cm = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, IGNORE_MISSING);
        return $cm ? context_module::instance($cm->id) : null;
    }

    /**
     * Initialisation commune : l'objet est le dossier.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'these_dossier';
    }

    /**
     * Page du dossier.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('/mod/these/dossier.php', ['id' => $this->contextinstanceid, 'dossierid' => $this->objectid]);
    }

    /**
     * Correspondance avec l'objet de l'événement.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'these_dossier', 'restore' => 'these_dossier'];
    }
}
