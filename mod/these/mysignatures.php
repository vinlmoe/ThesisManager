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

use mod_these\local\signatureagent;
use mod_these\output;
use mod_these\output\parts;

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

$renderer = $PAGE->get_renderer('mod_these');
// Documents groupés par dossier (étudiant, titre, n° DEVE), aucun coché d'office : chacun se coche
// après avoir consulté son PDF.
$documents = [];
$groups = [];
foreach ($pending as $signature) {
    $dossier = $signature->dossier;
    if (!isset($groups[$dossier->id])) {
        $student = $DB->get_record('user', ['id' => $dossier->userid]);
        $context = context_module::instance($signature->cm->id);
        $groups[$dossier->id] = ['heading' => get_string('mysignaturesdossier', 'mod_these', (object) [
            'student' => $student ? fullname($student) : '-',
            'title' => format_string(
                these_subject_field($dossier, 'title', $context),
                true,
                ['context' => $context, 'escape' => false]
            ),
            'number' => trim((string) $dossier->devenumber) !== '' ? ' — ' . get_string('signinfonumber', 'mod_these')
                . ' ' . $dossier->devenumber : '',
        ]), 'ids' => [], 'refuseurl' => null];
    }
    $groups[$dossier->id]['ids'][] = $signature->id;
    // Agent DEVE : il peut refuser de signer, ce qui renvoie le dossier à l'étape de création des documents.
    if (\mod_these\local\refusal::refusable($signature, $dossier)) {
        $groups[$dossier->id]['refuseurl'] = new moodle_url('/mod/these/refuse.php', ['dossierid' => $dossier->id]);
    }
    // Signature d'agent (sans jeton) : « par délégation » aux emplacements de la direction générale et de l'université.
    $label = $signature->token === null ? signatureagent::document_label($signature->doctype, $signature->slot)
        : these_document_label($signature->doctype) . ' (' . these_slot_label($signature->slot) . ')';
    // Compte de l'équipe DEVE qui a validé les documents que l'agent signe.
    $validated = signatureagent::validator_label($signature);
    if ($validated !== '') {
        $label .= ' — ' . $validated;
    }
    $documents[$signature->id] = $renderer->render(new output\fragment([
        parts::text($label . ' — '),
        parts::link(
            new moodle_url($PAGE->url, ['preview' => $signature->id]),
            get_string('signpreview', 'mod_these'),
            '',
            '_blank'
        ),
    ]));
}
$mform = new \mod_these\form\mysignatures_form($PAGE->url, [
    'documents' => $documents,
    'groups' => array_values($groups),
    'signername' => fullname($USER),
    // Nom de signature saisi seulement par un agent DEVE (signatures sans jeton, par délégation).
    'editablename' => (bool) array_filter($pending, function ($signature) {
        return $signature->token === null;
    }),
]);

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
            (string) $signature->signeremail,
            // Direction générale et université : signées par délégation.
            ['delegated' => signatureagent::delegated_slot($signature->slot)]
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
echo $renderer->render(new output\message(get_string('mysignatures_desc', 'mod_these'), 'mb-3'));
if ($documents) {
    $mform->display();
} else {
    echo $OUTPUT->notification(get_string('signnothingpending', 'mod_these'), 'success');
}

// Signature enregistrée.
echo $OUTPUT->heading(get_string('signaturesaved', 'mod_these'), 3, 'mt-4');
echo $renderer->render(new output\saved_signature(
    these_get_saved_signature($USER->id),
    new moodle_url($PAGE->url, ['deletesaved' => 1, 'sesskey' => sesskey()])
));
echo $OUTPUT->footer();
