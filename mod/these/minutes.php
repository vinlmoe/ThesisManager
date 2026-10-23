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
 * Dépôt du procès-verbal de soutenance par la DEVE (phase 2, étape 6) : PV signé, résultat et
 * mention. Le dépôt clôt le dossier.
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

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$student = $DB->get_record('user', ['id' => $dossier->userid]);
if ((int) $dossier->status !== THESE_STATUS_SCHEDULED) {
    redirect($dossierurl, get_string('minutesnotavailable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/minutes.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('depositminutes', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new \mod_these\form\minutes_form($PAGE->url);
$draftitemid = file_get_submitted_draft_itemid('pv');
file_prepare_draft_area($draftitemid, $context->id, 'mod_these', 'pv', $dossier->id, these_filemanager_options('pv'));
$mform->set_data((object) [
    'id' => $cm->id,
    'dossierid' => $dossier->id,
    'pv' => $draftitemid,
    'defenseresult' => THESE_RESULT_ADMITTED,
]);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files($data->pv, $context->id, 'mod_these', 'pv', $dossier->id, these_filemanager_options('pv'));
    these_close_dossier($these, $cm, $dossier, $USER->id, $data->defenseresult, $data->mention, trim((string) $data->remark));
    redirect($dossierurl, get_string('dossierclosed', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('depositminutes', 'mod_these'));
echo html_writer::link($dossierurl, get_string('back'));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
