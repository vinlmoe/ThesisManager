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
 * Création par la DEVE d'un dossier pour le compte d'un étudiant inscrit qui n'en a pas encore
 * (étudiant qui a remis sa fiche de sujet sur papier, par exemple). Le dossier est créé en
 * brouillon ; la DEVE complète ensuite le sujet depuis la page de modification, et l'étudiant
 * peut le soumettre lui-même.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\page;

$id = required_param('id', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
these_require_service($context, 'deve');

$PAGE->set_url('/mod/these/create.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('createdossier', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Étudiants inscrits (capacité de dépôt, hors personnel) qui n'ont pas encore de dossier.
$existing = $DB->get_fieldset_select('these_dossier', 'userid', 'theseid = ?', [$these->id]);
$students = [];
foreach (get_enrolled_users($context, 'mod/these:submit', 0, 'u.*', 'u.lastname ASC, u.firstname ASC', 0, 0, true) as $user) {
    if (!in_array($user->id, $existing) && !has_capability('mod/these:managedeve', $context, $user)) {
        $students[$user->id] = fullname($user) . ' (' . $user->email . ')';
    }
}

$dashboardurl = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
$mform = new \mod_these\form\create_form($PAGE->url, ['students' => $students]);
$mform->set_data(['id' => $cm->id]);

if ($mform->is_cancelled()) {
    redirect($dashboardurl);
} else if ($data = $mform->get_data()) {
    $dossier = these_create_dossier($these->id, $data->userid, $USER->id);
    redirect(
        new moodle_url('/mod/these/edit.php', ['id' => $cm->id, 'dossierid' => $dossier->id]),
        get_string('dossiercreated', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('createdossier', 'mod_these'));
echo these_render_navlinks($cm, $context);
$mform->display();
echo $OUTPUT->footer();
