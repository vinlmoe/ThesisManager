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
 * Renvoi à tous les membres du jury de leur désignation et de leur lien d'accès aux documents
 * (DEVE), une fois l'éligibilité validée.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');
require_sesskey();

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
if ((int) $dossier->status < THESE_STATUS_DEFENSE_VALIDATED) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$result = these_notify_jury($these, $cm, $dossier, true);
$message = get_string('juryaccessresent', 'mod_these', $result->sent);
if ($result->missing) {
    $message .= ' ' . get_string('jurynotifiedmissing', 'mod_these', implode(', ', array_map('s', $result->missing)));
}
redirect(
    new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
