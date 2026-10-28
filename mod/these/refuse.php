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
 * Refus de signer d'un agent DEVE, depuis « Mes signatures » (voir \mod_these\local\refusal) : le
 * dossier revient, avec le motif, à l'étape où les documents refusés ont été créés.
 *
 * Comme « Mes signatures », la page ne dépend pas d'un cours : seuls les documents qui attendent
 * l'agent connecté (these_get_user_pending_signatures()) peuvent être refusés.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\refusal;
use mod_these\local\signatureagent;

$dossierid = required_param('dossierid', PARAM_INT);

require_login();
if (isguestuser()) {
    throw new require_login_exception('Guests are not allowed here.');
}
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/these/refuse.php', ['dossierid' => $dossierid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('refusetitle', 'mod_these'));
$PAGE->set_heading(get_string('refusetitle', 'mod_these'));
$returnurl = new moodle_url('/mod/these/mysignatures.php');

// Documents de ce dossier qui attendent l'agent et peuvent encore être refusés.
$signatures = array_filter(these_get_user_pending_signatures($USER), function ($signature) use ($dossierid) {
    return (int) $signature->dossierid === $dossierid && refusal::refusable($signature, $signature->dossier);
});
if (!$signatures) {
    redirect($returnurl, get_string('refusenothing', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}
$first = reset($signatures);
$dossier = $first->dossier;
$student = $DB->get_record('user', ['id' => $dossier->userid]);
$documents = [];
foreach ($signatures as $signature) {
    $step = refusal::creation_steps()[$signature->doctype];
    $label = signatureagent::document_label($signature->doctype, $signature->slot);
    $validated = signatureagent::validator_label($signature);
    $documents[$signature->id] = $label . ($validated !== '' ? ' — ' . $validated : '') . ' — '
        . get_string('refusereturnto' . $step['target'], 'mod_these');
}
$heading = ($student ? fullname($student) : '-') . ' — ' . format_string(
    these_subject_field($dossier, 'title', context_module::instance($first->cm->id)),
    true,
    ['escape' => false]
);
$mform = new \mod_these\form\refuse_form($PAGE->url, ['documents' => $documents, 'heading' => $heading]);
$mform->set_data(['dossierid' => $dossierid]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $refused = array_values(array_filter($signatures, function ($signature) use ($data) {
        return !empty($data->{'sig' . $signature->id});
    }));
    $dossier = refusal::refuse($first->these, $first->cm, $dossier, $refused, $USER->id, $data->reason);
    redirect(
        $returnurl,
        get_string('refusedone', 'mod_these', these_status_label($dossier->status)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
