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
 * Fiche de sujet de l'étudiant (phase 1, étape 1) : création du dossier au premier
 * enregistrement, modification tant que le sujet est en brouillon ou renvoyé, et soumission au
 * directeur de thèse (ou, après un renvoi, à l'étape qui l'a renvoyé).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\output;
use mod_these\output\parts;

use mod_these\form\subject_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/these:submit', $context);

$viewurl = new moodle_url('/mod/these/view.php', ['id' => $cm->id]);
$dossier = these_get_user_dossier($these->id, $USER->id);
if ($dossier && !these_subject_editable($dossier)) {
    redirect($viewurl, get_string('subjectnoteditable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/subject.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('subject', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$returned = $dossier && (int) $dossier->status === THESE_STATUS_SUBJECT_RETURNED;
$mform = new subject_form($PAGE->url, [
    'codirectors' => \mod_these\local\jurors::internal_options($context, $dossier ? (int) $dossier->codirectorjurorid : 0),
    'directors' => these_director_options($context, $dossier ? $dossier->directorid : 0),
    'submitlabel' => $returned ? 'resubmitsubject' : 'submitsubject',
    'allowdraft' => true,
    'signername' => fullname($USER),
]);

$draftitemid = file_get_submitted_draft_itemid('subjectdocs');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_these',
    'subjectdocs',
    $dossier ? $dossier->id : 0,
    these_filemanager_options('subjectdocs')
);
$formdata = $dossier ? clone $dossier : new stdClass();
$formdata->id = $cm->id;
$formdata->dossierid = $dossier ? $dossier->id : 0;
$formdata->subjectdocs = $draftitemid;
$formdata->codirectortype = subject_form::codirector_type($formdata);
$commitments = $dossier ? (int) $dossier->commitments : 0;
$formdata->commitguide = $commitments & THESE_COMMIT_GUIDE ? 1 : 0;
$formdata->commitduplicate = $commitments & THESE_COMMIT_DUPLICATE ? 1 : 0;
$formdata->commitethics = $commitments & THESE_COMMIT_ETHICS ? 1 : 0;
unset($formdata->signaturedata);
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    if (!$dossier) {
        $dossier = these_create_dossier($these->id, $USER->id, $USER->id);
    }
    file_save_draft_area_files(
        $data->subjectdocs,
        $context->id,
        'mod_these',
        'subjectdocs',
        $dossier->id,
        these_filemanager_options('subjectdocs')
    );
    $submit = !empty($data->submitsubject);
    these_save_subject($these, $cm, $dossier, $data, $submit, $USER->id, $submit ? these_signature_from_form($data) : null);
    redirect(
        $viewurl,
        get_string($submit ? 'subjectsubmittedmsg' : 'draftsaved', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subject', 'mod_these'));
echo backlinks::render(backlinks::student_dossier($cm));
if ($returned && trim((string) $dossier->returncomment) !== '') {
    echo $renderer->render(new output\message([
        parts::strong(get_string('returncomment', 'mod_these')),
        parts::br(),
        parts::html(format_text($dossier->returncomment, FORMAT_PLAIN)),
    ], 'alert alert-warning'));
}
echo $OUTPUT->box(get_string('subjectintro', 'mod_these'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
