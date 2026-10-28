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
 * Modification d'un dossier par la DEVE, sans changer son statut (voir dossier_edit_form). Si la
 * date, le lieu ou le jury change après la génération des documents de soutenance, ceux-ci sont
 * marqués comme à régénérer avant toute nouvelle transmission.
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
$PAGE->set_url('/mod/these/edit.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('editdossier', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$jury = these_get_jury($dossier->id);
$mform = new \mod_these\form\dossier_edit_form($PAGE->url, [
    'directors' => these_director_options($context, $dossier->directorid),
    'codirectors' => \mod_these\local\jurors::internal_options($context, (int) $dossier->codirectorjurorid),
    'theseid' => $these->id,
    'minjury' => $these->minjury,
    'rooms' => these_defense_rooms($these),
    'jurycount' => count($jury),
    'jury' => array_values($jury),
    'jurorids' => array_map('intval', array_column($jury, 'jurorid')),
    'juryconflicts' => these_render_jury_conflicts($dossier),
    'roomconflicts' => these_render_room_conflicts($dossier),
]);
$formdata = (object) array_merge((array) $dossier, these_jury_form_data($jury));
$formdata->id = $cm->id;
$formdata->dossierid = $dossier->id;
$formdata->codirectortype = \mod_these\form\subject_form::codirector_type($dossier);
// Jury présenté en lecture : la saisie ne s'ouvre que pour le modifier.
$formdata->juryedit = empty($jury) ? 1 : 0;
$a6 = \mod_these\local\a6::get($dossier);
$formdata->aga6notyet = (int) ($a6['notyet'] || !$a6['date']);
$formdata->aga6date = $a6['date'];
$formdata->aga6school = $a6['school'];
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    $fields = [];
    $textfields = ['title', 'titleen', 'summary', 'keywords', 'devenumber', 'libraryref', 'defenselocation'];
    foreach ($textfields as $field) {
        $fields[$field] = trim((string) $data->$field);
    }
    $fields += \mod_these\local\dossiers::codirector_fields($data);
    $fields['directorid'] = (int) $data->directorid;
    $fields['defensedate'] = (int) ($data->defensedate ?? 0);

    // Validation de l'A6 : enregistrée (et inscrite à l'historique) seulement si elle change.
    $a6date = !empty($data->aga6notyet) ? 0 : (int) ($data->aga6date ?? 0);
    $a6school = (string) ($data->aga6school ?? '');
    if ($a6date !== $a6['date'] || $a6school !== $a6['school']) {
        $dossier = \mod_these\local\a6::set($dossier, $a6date, $a6school, $USER->id);
    }

    $newjury = these_extract_submitted_jury($data, true);
    $signature = function ($member) {
        return [$member->juryrole, $member->fullname, $member->jurytitle, $member->institution, $member->email,
            (int) ($member->jurorid ?? 0), (string) ($member->jurystatus ?? '')];
    };
    $jurychanged = array_map($signature, $jury) !== array_map($signature, $newjury);
    $eligibilitychanged = array_map(function ($member) {
        return (int) !empty($member->eligible);
    }, $jury) !== array_map(function ($member) {
        return (int) !empty($member->eligible);
    }, $newjury);
    if ($jurychanged || $eligibilitychanged) {
        these_save_jury($dossier->id, $newjury, $USER->id);
        // Jury déjà validé par la DEVE : les nouveaux membres sont prévenus à leur tour.
        if ((int) $dossier->status >= THESE_STATUS_DEFENSE_VALIDATED) {
            these_notify_jury($these, $cm, $dossier);
        }
    }
    $defensechanged = $jurychanged || $fields['defensedate'] !== (int) $dossier->defensedate
        || $fields['defenselocation'] !== (string) $dossier->defenselocation;
    if ($defensechanged && !empty($dossier->documentstime)) {
        $fields['documentstime'] = 0;
    }

    these_set_status($dossier, $dossier->status, $USER->id, 'edited', '', $fields);
    redirect($dossierurl, get_string('dossiersaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editdossier', 'mod_these'));
echo $renderer->render(new output\link($dossierurl, get_string('back')));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
echo $renderer->render(output\message::help(get_string('editdossierhelp', 'mod_these')));
$mform->display();
echo $OUTPUT->footer();
