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
 * Production et transmission des documents de soutenance.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Documents de soutenance.

/**
 * Voir \mod_these\local\documents::document_data().
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @return array
 */
function these_document_data(stdClass $these, stdClass $dossier) {
    return \mod_these\local\documents::document_data($these, $dossier);
}

/**
 * Voir \mod_these\local\documents::document_filename().
 *
 * @param string $prefix
 * @param string $name
 * @return string
 */
function these_document_filename($prefix, $name = '') {
    return \mod_these\local\documents::document_filename($prefix, $name);
}

/**
 * Voir \mod_these\local\documents::generate_documents().
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param int $userid
 * @return stored_file[]
 */
function these_generate_documents(stdClass $these, context $context, stdClass $dossier, $userid) {
    return \mod_these\local\documents::generate_documents($these, $context, $dossier, $userid);
}

/**
 * Voir \mod_these\local\documents::transmit_documents().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $dossier
 * @param int $userid
 * @return stdClass {sent, failed, missing (noms des membres du jury sans adresse)}
 */
function these_transmit_documents(stdClass $these, stdClass $cm, context $context, stdClass $dossier, $userid) {
    return \mod_these\local\documents::transmit_documents($these, $cm, $context, $dossier, $userid);
}
