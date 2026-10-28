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
 * Dépôt par la DEVE du scan d'un document signé hors du module (signature sur papier d'un
 * signataire sans compte). Le scan est joint au dossier pour conservation et inscrit à l'historique.
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
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$student = $DB->get_record('user', ['id' => $dossier->userid]);
if (!\mod_these\local\scans::allowed($dossier)) {
    redirect($dossierurl, get_string('scanunavailable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/scan.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('addsignedscan', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new \mod_these\form\scan_form($PAGE->url, ['slots' => \mod_these\local\scans::pending_slots($dossier)]);
// Brouillon vide : les scans déjà joints au dossier ne sont pas repris, seuls les nouveaux fichiers le sont.
$draftitemid = file_get_submitted_draft_itemid('scan');
$mform->set_data((object) [
    'id' => $cm->id,
    'dossierid' => $dossier->id,
    'scan' => $draftitemid,
    'signeddate' => time(),
]);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    // Emplacements cochés pour le document choisi (direction générale : toujours par délégation).
    $slots = [];
    foreach (array_keys(\mod_these\local\scans::pending_slots($dossier)[$data->doctype] ?? []) as $slot) {
        $name = 'sign_' . $data->doctype . '_' . $slot;
        if (!empty($data->$name)) {
            $slots[$slot] = $slot === 'dg';
        }
    }
    $count = \mod_these\local\scans::add(
        $context,
        $dossier,
        $USER->id,
        $data->doctype,
        (int) $data->signeddate,
        $data->scan,
        trim((string) $data->remark),
        $slots
    );
    $message = get_string('scanrecorded', 'mod_these', $count);
    if ($count && $slots) {
        $message .= ' ' . get_string('scansignaturesrecorded', 'mod_these', count($slots));
    }
    redirect($dossierurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addsignedscan', 'mod_these'));
echo $renderer->render(new output\link($dossierurl, get_string('back')));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
