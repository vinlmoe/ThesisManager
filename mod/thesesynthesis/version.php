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
 * Version details for mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_thesesynthesis';
$plugin->version   = 2026103003;
$plugin->requires  = 2022112800; // Moodle 4.1+.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.8.1';
$plugin->dependencies = [
    // Annuaire du jury, paramètres partagés (modèles, grille, questionnaire, paramètres du circuit),
    // composants de rendu, menu « Paramètres », liens de retour.
    'mod_these' => 2026103003,
];
