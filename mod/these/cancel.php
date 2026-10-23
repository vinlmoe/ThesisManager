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
 * Abandon d'un dossier par la DEVE (motif obligatoire), ou réouverture d'un dossier abandonné
 * dans le statut qu'il avait atteint.
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
require_capability('mod/these:managedeve', $context);

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$student = $DB->get_record('user', ['id' => $dossier->userid]);
$reopen = (int) $dossier->status === THESE_STATUS_CANCELLED;
if (!$reopen && (int) $dossier->status === THESE_STATUS_CLOSED) {
    redirect($dossierurl);
}

$PAGE->set_url('/mod/these/cancel.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string($reopen ? 'reopendossier' : 'canceldossier', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new \mod_these\form\cancel_form($PAGE->url, ['reopen' => $reopen]);
$mform->set_data(['id' => $cm->id, 'dossierid' => $dossier->id]);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    if ($reopen) {
        these_reopen_dossier($dossier, $USER->id, trim((string) $data->remark));
    } else {
        these_cancel_dossier($dossier, $USER->id, trim((string) $data->remark));
    }
    redirect(
        $dossierurl,
        get_string($reopen ? 'dossierreopened' : 'dossiercancelled', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($reopen ? 'reopendossier' : 'canceldossier', 'mod_these'));
echo html_writer::link($dossierurl, get_string('back'));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
