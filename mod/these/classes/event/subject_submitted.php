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
 * Événement : sujet de thèse soumis au directeur (première soumission ou nouvelle soumission).
 *
 * @property-read array $other {
 *      - bool resubmitted: nouvelle soumission après un renvoi.
 * }
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subject_submitted extends dossier_event {
    /**
     * Initialisation.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubjectsubmitted', 'mod_these');
    }

    /**
     * Description de l'événement.
     *
     * @return string
     */
    public function get_description() {
        $again = !empty($this->other['resubmitted']) ? ' again' : '';
        return "The user with id '$this->userid' submitted$again the subject of the thesis file with id '$this->objectid' "
            . "in the thesis management activity with course module id '$this->contextinstanceid'.";
    }
}
