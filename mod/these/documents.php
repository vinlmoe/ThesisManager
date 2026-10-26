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
 * Documents de soutenance (phase 2, étape 5), page de la DEVE : génération des convocations, de la
 * feuille de jury et du procès-verbal à signer, puis transmission au jury, à l'étudiant et à
 * l'université. La première transmission programme la soutenance ; régénérer et retransmettre
 * reste possible ensuite (report de date, changement de jury).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\output;

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
$action = optional_param('action', '', PARAM_ALPHA);

if (!in_array((int) $dossier->status, [THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED], true)) {
    redirect($dossierurl, get_string('documentsnotavailable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/documents.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('documents', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'generate' && confirm_sesskey()) {
    these_generate_documents($these, $context, $dossier, $USER->id);
    redirect($PAGE->url, get_string('documentsgenerated', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'transmit' && confirm_sesskey()) {
    if (empty($dossier->documentstime) || !these_has_files($context, 'generated', $dossier->id)) {
        redirect($PAGE->url, get_string('generatefirst', 'mod_these'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $result = these_transmit_documents($these, $cm, $context, $dossier, $USER->id);
    $message = get_string('transmitsummary', 'mod_these', $result);
    if ($result->missing) {
        $message .= ' ' . get_string('transmitmissing', 'mod_these', implode(', ', $result->missing));
    }
    redirect(
        $dossierurl,
        $message,
        null,
        $result->failed || $result->missing ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
    );
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('documents', 'mod_these'));
echo $renderer->render(new output\link($dossierurl, get_string('back')));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
echo $renderer->render(output\message::help(get_string('documentsintro', 'mod_these')));

$datetimeformat = get_string('strftimedatetimeshort', 'langconfig');
$files = new output\file_links($context, 'generated', $dossier->id, 'mb-3');
if (!$files->is_empty() && !empty($dossier->documentstime)) {
    $generated = get_string('documentsgeneratedon', 'mod_these', userdate($dossier->documentstime, $datetimeformat));
    echo $renderer->render(new output\message($generated, ''));
    echo $renderer->render($files);
} else if (!$files->is_empty()) {
    // Les documents existent mais le dossier a été modifié depuis (voir edit.php) : ils sont à
    // régénérer avant toute transmission.
    echo $OUTPUT->notification(get_string('documentsstale', 'mod_these'), 'warning');
    echo $renderer->render($files);
}
if (!empty($dossier->senttime)) {
    $sent = get_string('documentssenton', 'mod_these', userdate($dossier->senttime, $datetimeformat));
    echo $renderer->render(new output\message($sent, 'mb-3'));
}

$jury = these_get_jury($dossier->id);
echo these_render_jury($jury);
$missing = array_filter($jury, function ($member) {
    return $member->email === '';
});
if ($missing) {
    echo $OUTPUT->notification(get_string('transmitmissing', 'mod_these', implode(', ', array_map(function ($member) {
        return s($member->fullname);
    }, $missing))), 'info');
}
if (trim((string) $these->universityemail) === '') {
    echo $OUTPUT->notification(get_string('nouniversityemail', 'mod_these'), 'info');
}

echo $OUTPUT->single_button(
    new moodle_url($PAGE->url, ['action' => 'generate', 'sesskey' => sesskey()]),
    get_string(!$files->is_empty() ? 'regeneratedocuments' : 'generatedocuments', 'mod_these')
);
if (!$files->is_empty() && !empty($dossier->documentstime)) {
    echo $OUTPUT->single_button(
        new moodle_url($PAGE->url, ['action' => 'transmit', 'sesskey' => sesskey()]),
        get_string(empty($dossier->senttime) ? 'transmitdocuments' : 'retransmitdocuments', 'mod_these')
    );
}

echo $OUTPUT->footer();
