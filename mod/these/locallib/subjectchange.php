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
 * Changement de sujet de thèse.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Changement de sujet.

/**
 * Voir \mod_these\local\subjectchange::subject_changeable().
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_subject_changeable(stdClass $dossier) {
    return \mod_these\local\subjectchange::subject_changeable($dossier);
}

/**
 * Voir \mod_these\local\subjectchange::get_subject_history().
 *
 * @param int $dossierid
 * @return stdClass[]
 */
function these_get_subject_history($dossierid) {
    return \mod_these\local\subjectchange::get_subject_history($dossierid);
}

/**
 * Voir \mod_these\local\subjectchange::change_subject().
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param stdClass $data Nouveau sujet (champs du formulaire de sujet).
 * @param int $userid
 * @param string $reason Motif du changement.
 * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
 * @return stdClass Le dossier à jour.
 */
function these_change_subject(
    stdClass $these,
    stdClass $cm,
    stdClass $dossier,
    stdClass $data,
    $userid,
    $reason,
    ?stdClass $signature = null
) {
    return \mod_these\local\subjectchange::change_subject($these, $cm, $dossier, $data, $userid, $reason, $signature);
}

/**
 * Voir \mod_these\local\subjectchange::render_subject_history().
 *
 * @param context $context
 * @param stdClass $dossier
 * @return string HTML ('' sans changement de sujet)
 */
function these_render_subject_history(context $context, stdClass $dossier) {
    return \mod_these\local\subjectchange::render_subject_history($context, $dossier);
}
