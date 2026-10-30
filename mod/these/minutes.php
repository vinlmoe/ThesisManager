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
 * Dépôt du procès-verbal de soutenance par la DEVE (phase 2, étape 6) : PV signé par le jury et
 * scanné, décisions du jury (résultat, mention, proposition pour un prix, corrections). Sans
 * corrections, le dépôt clôt le dossier ; avec corrections, il ouvre le circuit des corrections.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\local\page;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
these_require_service($context, 'deve');

['dossier' => $dossier, 'rights' => $rights] = (array) page::load_dossier($activity, $dossierid, $USER->id);
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$student = $DB->get_record('user', ['id' => $dossier->userid]);
if ((int) $dossier->status !== THESE_STATUS_SCHEDULED) {
    redirect($dossierurl, get_string('minutesnotavailable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/minutes.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('depositminutes', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Toutes les signatures, université comprise (scans des pièces revenues), avant le procès-verbal.
$missing = \mod_these\local\prerequisites::missing($dossier);
if ($missing) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('depositminutes', 'mod_these'));
    echo backlinks::render(backlinks::to_dossier($cm, $dossier));
    echo \mod_these\local\university::render_section($cm, $context, $dossier);
    echo \mod_these\local\prerequisites::render_missing($cm, $context, $dossier, $missing);
    echo $OUTPUT->footer();
    exit;
}

$mform = new \mod_these\form\minutes_form($PAGE->url);
$draftitemid = file_get_submitted_draft_itemid('pv');
file_prepare_draft_area($draftitemid, $context->id, 'mod_these', 'pv', $dossier->id, these_filemanager_options('pv'));
$mform->set_data((object) [
    'id' => $cm->id,
    'dossierid' => $dossier->id,
    'pv' => $draftitemid,
    'defenseresult' => THESE_RESULT_ADMITTED,
    'mention' => '',
]);

if ($mform->is_cancelled()) {
    redirect(backlinks::to_dossier($cm, $dossier)->url);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files($data->pv, $context->id, 'mod_these', 'pv', $dossier->id, these_filemanager_options('pv'));
    $dossier = these_record_minutes($these, $cm, $dossier, $USER->id, (object) [
        'result' => (int) $data->defenseresult,
        'mention' => (string) ($data->mention ?? ''),
        'prizeproposal' => !empty($data->prizeproposal),
        'correctionsrequested' => !empty($data->correctionsrequested),
        'correctionsnote' => (string) ($data->correctionsnote ?? ''),
    ], trim((string) $data->remark));
    $message = (int) $dossier->status === THESE_STATUS_CLOSED ? 'dossierclosed' : 'correctionsopened';
    redirect(
        backlinks::after_action($cm, $dossierurl),
        get_string($message, 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('depositminutes', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
