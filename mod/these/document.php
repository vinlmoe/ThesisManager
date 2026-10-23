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
 * Documents produits à la demande, sans être stockés : la fiche de sujet (dès l'enregistrement
 * DEVE) et l'attestation de conformité du manuscrit (dès sa délivrance par la bibliothèque). Ils
 * reflètent toujours l'état courant du dossier.
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

require_capability('mod/these:view', $context);

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);
if (!these_can_view_dossier($rights)) {
    throw new required_capability_exception($context, 'mod/these:viewall', 'nopermissions', '');
}

$data = these_document_data($these, $dossier);
if ($type === 'subject' && these_rank_reached($dossier, THESE_STATUS_SUBJECT_REGISTERED)) {
    $content = \mod_these\pdf\document_pdf::subject_sheet($data);
    $filename = these_document_filename('fiche-sujet', $data['student']);
} else if ($type === 'conformity' && these_rank_reached($dossier, THESE_STATUS_CONFORM)) {
    // Bibliothécaire et date : ceux de la dernière attestation inscrite à l'historique.
    $logs = $DB->get_records(
        'these_log',
        ['dossierid' => $dossier->id, 'logaction' => 'validatedlibraryconformity'],
        'timecreated DESC',
        '*',
        0,
        1
    );
    $log = reset($logs);
    $librarian = $log && $log->userid ? $DB->get_record('user', ['id' => $log->userid]) : null;
    $data['librarian'] = $librarian ? fullname($librarian) : '';
    $data['conformitydate'] = userdate($log ? $log->timecreated : time(), get_string('strftimedate', 'langconfig'));
    $content = \mod_these\pdf\document_pdf::conformity($data);
    $filename = these_document_filename('attestation-conformite', $data['student']);
} else {
    throw new moodle_exception('documentnotavailable', 'mod_these');
}

send_file($content, $filename, 0, 0, true, true, 'application/pdf');
