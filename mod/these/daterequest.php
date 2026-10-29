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
 * Demande d'une nouvelle date de soutenance par la DEVE, quand l'horaire proposé n'est pas
 * disponible (voir \mod_these\local\daterequest) : seule la date est remise en cause, le dossier
 * n'est pas renvoyé.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\local\daterequest;

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
if (!daterequest::allowed($dossier)) {
    redirect($dossierurl, get_string('daterequestnotallowed', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}
$student = $DB->get_record('user', ['id' => $dossier->userid]);

$PAGE->set_url('/mod/these/daterequest.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('daterequest', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$date = (int) ($dossier->defensedate ?: $dossier->proposeddate);
$mform = new \mod_these\form\daterequest_form($PAGE->url, [
    'currentdate' => $date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-',
]);
$mform->set_data(['id' => $cm->id, 'dossierid' => $dossier->id]);

if ($mform->is_cancelled()) {
    redirect(backlinks::to_dossier($cm, $dossier)->url);
} else if ($data = $mform->get_data()) {
    daterequest::request($these, $cm, $dossier, $USER->id, (string) $data->reason);
    redirect(
        backlinks::after_action($cm, $dossierurl),
        get_string('daterequestsent', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('daterequest', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
