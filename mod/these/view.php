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
 * Vue principale de l'activité : le dossier de thèse de l'étudiant connecté. Le personnel
 * (directeurs, DEVE, bibliothèque) est redirigé vers le tableau de bord, sa page d'atterrissage
 * habituelle, comme dans mod_stage.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$rights = these_require_view($context);

// L'activité déclare FEATURE_COMPLETION_TRACKS_VIEWS : la consultation est enregistrée avant la
// redirection du personnel, qui n'affiche jamais cette page.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Un étudiant qui a un dossier le voit ici ; sans dossier, seul un étudiant (et non un membre du
// personnel, qui détient souvent aussi la capacité de dépôt par héritage) y est invité à proposer
// un sujet.
$dossier = these_get_user_dossier($these->id, $USER->id);
$isstaff = $rights->viewall || has_capability('mod/these:direct', $context);
if (!$dossier && ($isstaff || !has_capability('mod/these:submit', $context))) {
    redirect(new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]));
}

$PAGE->set_url('/mod/these/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($these->name));

if ($these->intro) {
    echo $OUTPUT->box(format_module_intro('these', $these, $cm->id), 'generalbox mod_introbox', 'theseintro');
}

echo $OUTPUT->heading(get_string('mydossier', 'mod_these'), 3);
if (!$dossier) {
    echo $OUTPUT->notification(get_string('nodossieryet', 'mod_these'), 'info');
    echo html_writer::link(
        new moodle_url('/mod/these/subject.php', ['id' => $cm->id]),
        get_string('proposesubject', 'mod_these'),
        ['class' => 'btn btn-primary']
    );
} else {
    $rights = these_get_rights($context, $dossier, $USER->id);
    echo these_render_dossier($these, $cm, $context, $dossier, $rights);
}

echo $OUTPUT->footer();
