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
 * Suivi des signatures d'un dossier par la DEVE : état de chaque emplacement de chaque document,
 * relance par courriel des signataires sans compte et des agents DEVE (direction générale par
 * délégation comprise), signature sur place.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\output;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);
$resend = optional_param('resend', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);

$PAGE->set_url('/mod/these/signatures.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('managesignatures', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($resend === 'agent' && confirm_sesskey()) {
    $available = array_values(array_filter(array_keys(these_document_definitions()), function ($doctype) use ($dossier) {
        return these_document_available($dossier, $doctype);
    }));
    $count = these_request_agent_signatures($these, $cm, $dossier, $available);
    redirect(
        $PAGE->url,
        get_string($count ? 'agentsreminded' : 'signnothingpending', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
if ($resend !== '' && in_array($resend, these_external_slots(), true) && confirm_sesskey()) {
    $available = array_values(array_filter(array_keys(these_document_definitions()), function ($doctype) use ($dossier) {
        return these_document_available($dossier, $doctype);
    }));
    $token = these_request_signature($these, $cm, $dossier, $resend, $available);
    redirect(
        $PAGE->url,
        get_string($token ? 'signaturerequestsent' : 'signnothingpending', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesignatures', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
echo these_render_signed_documents($cm, $context, $dossier, $rights);

// Signataires sans compte : relance par courriel ou signature sur place.
$signers = new output\external_signers($cm, $dossier, $PAGE->url);
echo $renderer->render($signers);
if ($signers->is_empty()) {
    echo $OUTPUT->notification(get_string('signnothingpending', 'mod_these'), 'success');
}
echo $OUTPUT->footer();
