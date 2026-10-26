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
 * Scheduled tasks for mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Une fois par jour au petit matin : les délais de relance se comptent en jours.
        'classname' => 'mod_these\task\send_reminders',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '7',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        // Le lundi matin : récapitulatif de la semaine pour la DEVE.
        'classname' => 'mod_these\task\send_digest',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '7',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '1',
    ],
];
