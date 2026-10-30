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

/**
 * Événement : dossier de thèse créé (brouillon).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_created extends dossier_event {
    /**
     * Initialisation.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdossiercreated', 'mod_these');
    }

    /**
     * Description de l'événement.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' created the thesis file with id '$this->objectid' for the user with id "
            . "'$this->relateduserid' in the thesis management activity with course module id '$this->contextinstanceid'.";
    }
}
