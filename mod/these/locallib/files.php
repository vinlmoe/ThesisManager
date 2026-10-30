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
 * Zones de fichiers des dossiers.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Fichiers.

/**
 * Voir \mod_these\local\files::filemanager_options().
 *
 * @param string $filearea
 * @return array
 */
function these_filemanager_options($filearea) {
    return \mod_these\local\files::filemanager_options($filearea);
}

/**
 * Voir \mod_these\local\files::get_files().
 *
 * @param context $context
 * @param string $filearea
 * @param int $dossierid
 * @return stored_file[]
 */
function these_get_files(context $context, $filearea, $dossierid) {
    return \mod_these\local\files::get_files($context, $filearea, $dossierid);
}

/**
 * Voir \mod_these\local\files::has_files().
 *
 * @param context $context
 * @param string $filearea
 * @param int $dossierid
 * @return bool
 */
function these_has_files(context $context, $filearea, $dossierid) {
    return \mod_these\local\files::has_files($context, $filearea, $dossierid);
}

/**
 * Voir \mod_these\local\files::count_draft_files().
 *
 * @param int $draftitemid
 * @return int
 */
function these_count_draft_files($draftitemid) {
    return \mod_these\local\files::count_draft_files($draftitemid);
}
