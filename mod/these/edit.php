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
 * Modification d'un dossier par la DEVE, sans changer son statut (voir dossier_edit_form). Si la
 * date, le lieu ou le jury change après la génération des documents de soutenance, ceux-ci sont
 * marqués comme à régénérer avant toute nouvelle transmission.
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
$PAGE->set_url('/mod/these/edit.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('editdossier', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$jury = these_get_jury($dossier->id);
$mform = new \mod_these\form\dossier_edit_form($PAGE->url, [
    'directors' => these_director_options($context, $dossier->directorid),
    'theseid' => $these->id,
    'minjury' => $these->minjury,
    'jurycount' => count($jury),
]);
$formdata = (object) array_merge((array) $dossier, these_jury_form_data($jury));
$formdata->id = $cm->id;
$formdata->dossierid = $dossier->id;
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    $fields = [];
    $textfields = ['title', 'titleen', 'summary', 'keywords', 'codirectorname', 'devenumber', 'libraryref', 'defenselocation'];
    foreach ($textfields as $field) {
        $fields[$field] = trim((string) $data->$field);
    }
    $fields['directorid'] = (int) $data->directorid;
    $fields['defensedate'] = (int) ($data->defensedate ?? 0);

    $newjury = these_extract_submitted_jury($data);
    $oldjury = array_map(function ($member) {
        return [$member->juryrole, $member->fullname, $member->jurytitle, $member->institution, $member->email];
    }, $jury);
    $jurychanged = $oldjury !== array_map(function ($member) {
        return [$member->juryrole, $member->fullname, $member->jurytitle, $member->institution, $member->email];
    }, $newjury);
    if ($jurychanged) {
        these_save_jury($dossier->id, $newjury);
    }
    $defensechanged = $jurychanged || $fields['defensedate'] !== (int) $dossier->defensedate
        || $fields['defenselocation'] !== (string) $dossier->defenselocation;
    if ($defensechanged && !empty($dossier->documentstime)) {
        $fields['documentstime'] = 0;
    }

    these_set_status($dossier, $dossier->status, $USER->id, 'edited', '', $fields);
    redirect($dossierurl, get_string('dossiersaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editdossier', 'mod_these'));
echo html_writer::link($dossierurl, get_string('back'));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
echo $OUTPUT->box(get_string('editdossierhelp', 'mod_these'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
