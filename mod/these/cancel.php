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

use mod_these\local\backlinks;
use mod_these\local\page;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
these_require_service($context, 'deve');

['dossier' => $dossier, 'rights' => $rights] = (array) page::load_dossier($activity, $dossierid, $USER->id);
// Dossier réduit à son procès-verbal (données de l'étudiant effacées) : plus aucune action.
\mod_these\local\dossiers::require_in_circuit($dossier);
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
    redirect(backlinks::to_dossier($cm, $dossier)->url);
} else if ($data = $mform->get_data()) {
    if ($reopen) {
        these_reopen_dossier($dossier, $USER->id, trim((string) $data->remark));
    } else {
        these_cancel_dossier($dossier, $USER->id, trim((string) $data->remark));
    }
    redirect(
        backlinks::after_action($cm, $dossierurl),
        get_string($reopen ? 'dossierreopened' : 'dossiercancelled', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($reopen ? 'reopendossier' : 'canceldossier', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
