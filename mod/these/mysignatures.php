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
 * Mes signatures : documents qui attendent la signature de l'utilisateur connecté, toutes
 * activités confondues (agent responsable de la DEVE, membre du jury ou autre signataire invité
 * ayant un compte), et gestion de sa signature enregistrée.
 *
 * Chaque document se consulte en PDF avant d'être signé ; la signature enregistrée est reprise
 * d'office, sans nouveau tracé.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$preview = optional_param('preview', 0, PARAM_INT);
$deletesaved = optional_param('deletesaved', 0, PARAM_BOOL);

require_login();
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/mod/these/mysignatures.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mysignatures', 'mod_these'));
$PAGE->set_heading(get_string('mysignatures', 'mod_these'));

if ($deletesaved && confirm_sesskey()) {
    these_delete_saved_signature($USER->id);
    redirect($PAGE->url, get_string('signaturesaveddeleted', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$pending = these_get_user_pending_signatures($USER);
if ($preview && isset($pending[$preview])) {
    $signature = $pending[$preview];
    send_file(
        these_signed_document_pdf(
            $signature->these,
            context_module::instance($signature->cm->id),
            $signature->dossier,
            $signature->doctype
        ),
        these_document_filename($signature->doctype),
        0,
        0,
        true,
        false,
        'application/pdf'
    );
}

$documents = [];
foreach ($pending as $signature) {
    $student = $DB->get_record('user', ['id' => $signature->dossier->userid]);
    $documents[$signature->id] = s($student ? fullname($student) : '-') . ' — '
        . these_document_label($signature->doctype) . ' (' . these_slot_label($signature->slot) . ') — '
        . html_writer::link(
            new moodle_url($PAGE->url, ['preview' => $signature->id]),
            get_string('signpreview', 'mod_these'),
            ['target' => '_blank']
        );
}
$mform = new \mod_these\form\mysignatures_form($PAGE->url, ['documents' => $documents, 'signername' => fullname($USER)]);

if ($documents && ($data = $mform->get_data())) {
    $png = these_signature_from_form($data);
    $count = 0;
    foreach ($pending as $signature) {
        if (empty($data->{'sig' . $signature->id})) {
            continue;
        }
        these_sign_document(
            context_module::instance($signature->cm->id),
            $signature->dossier,
            $signature->doctype,
            $signature->slot,
            $USER->id,
            $png->name,
            $png->png,
            (string) $signature->signeremail
        );
        $count++;
    }
    redirect(
        $PAGE->url,
        get_string('signaturesrecordedcount', 'mod_these', $count),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::div(get_string('mysignatures_desc', 'mod_these'), 'mb-3');
if ($documents) {
    $mform->display();
} else {
    echo $OUTPUT->notification(get_string('signnothingpending', 'mod_these'), 'success');
}

// Signature enregistrée.
echo $OUTPUT->heading(get_string('signaturesaved', 'mod_these'), 3, 'mt-4');
$saved = these_get_saved_signature($USER->id);
if ($saved !== null) {
    echo html_writer::div(
        html_writer::empty_tag('img', ['src' => 'data:image/png;base64,' . base64_encode($saved),
        'alt' => get_string('signaturesaved', 'mod_these'), 'class' => 'border', 'style' => 'max-height:100px;background:#fff;']),
        'mb-2'
    );
    echo html_writer::link(
        new moodle_url($PAGE->url, ['deletesaved' => 1, 'sesskey' => sesskey()]),
        get_string('signaturesaveddelete', 'mod_these'),
        ['class' => 'btn btn-secondary']
    );
} else {
    echo html_writer::div(get_string('signaturesavednone', 'mod_these'), 'text-muted');
}
echo $OUTPUT->footer();
