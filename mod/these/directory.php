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
 * Annuaire des enseignants en lecture seule, ouvert à quiconque peut consulter l'activité (étudiants
 * compris) : enseignants actifs internes et extérieurs, avec leur statut, leur matière, leur
 * établissement et la possibilité de présider un jury ; ni adresse ni modification (la DEVE tient
 * l'annuaire depuis jurors.php).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\jurors;
use mod_these\output;

$id = required_param('id', PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$presidents = optional_param('presidents', 0, PARAM_BOOL);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_view($context);

$PAGE->set_url('/mod/these/directory.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('jurordirectory', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('jurordirectory', 'mod_these'));
echo $renderer->render(new output\link(new moodle_url('/mod/these/view.php', ['id' => $cm->id]), get_string('back')));
echo $renderer->render(new output\message(get_string('jurordirectory_desc', 'mod_these'), 'mb-3'));
echo $renderer->render(new output\link(
    new moodle_url('/mod/these/teachinglist.php', ['cmid' => $cm->id]),
    get_string('teachinglist', 'mod_these'),
    'btn btn-secondary mb-3'
));
echo $renderer->render(new output\juror_directory($PAGE->url, jurors::directory($search, $presidents), $search, $presidents));
echo $OUTPUT->footer();
