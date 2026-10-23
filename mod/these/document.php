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
 * Documents de l'établissement produits à la demande (voir these_document_definitions()) : ils
 * reflètent toujours l'état courant du dossier et portent les signatures déjà apposées.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$dossierid = required_param('dossierid', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);

these_require_view($context);

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);
if (!these_can_view_dossier($rights)) {
    throw new required_capability_exception($context, 'mod/these:viewall', 'nopermissions', '');
}

if (
    !array_key_exists($type, these_document_definitions())
        || !these_document_available($dossier, $type) || !these_can_view_document($type, $rights)
) {
    throw new moodle_exception('documentnotavailable', 'mod_these');
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);
send_file(
    these_signed_document_pdf($these, $context, $dossier, $type),
    these_document_filename($type, $student ? fullname($student) : ''),
    0,
    0,
    true,
    true,
    'application/pdf'
);
