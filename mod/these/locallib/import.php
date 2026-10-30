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
 * Import des dossiers par fichier CSV.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Import.

/**
 * Voir \mod_these\local\import::find_user().
 *
 * @param string $value
 * @return stdClass|null
 */
function these_find_user($value) {
    return \mod_these\local\import::find_user($value);
}

/**
 * Voir \mod_these\local\import::import_dossiers().
 *
 * @param stdClass $these
 * @param string $content Contenu du fichier.
 * @param string $delimiter Nom du séparateur (voir csv_import_reader::get_delimiter_list()).
 * @param string $encoding
 * @param bool $dryrun Vérifie sans rien créer.
 * @param int $userid Auteur de l'import.
 * @return array Liste de ['line' => int, 'student' => string, 'ok' => bool, 'message' => string].
 */
function these_import_dossiers(stdClass $these, $content, $delimiter, $encoding, $dryrun, $userid) {
    return \mod_these\local\import::import_dossiers($these, $content, $delimiter, $encoding, $dryrun, $userid);
}
