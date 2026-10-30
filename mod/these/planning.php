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
 * Planning des soutenances de l'activité : soutenances de la semaine ou du mois, navigation d'une
 * période à l'autre, planning imprimable et téléchargeable. Toutes les soutenances pour la DEVE, la
 * bibliothèque et les détenteurs de mod/these:viewall ; celles de ses dossiers pour un directeur.
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
$rights = these_require_view($context);
if (!$rights->viewall && !has_capability('mod/these:direct', $context)) {
    redirect(new moodle_url('/mod/these/view.php', ['id' => $cm->id]));
}

$baseurl = new moodle_url('/mod/these/planning.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('planning', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

\mod_these\page\planning_page::output(
    [these_planning_source($these, $cm, $context, $USER->id)],
    $baseurl,
    these_settings_scope($these),
    $these,
    false,
    get_string('planning', 'mod_these') . ' — ' . format_string($these->name),
    these_render_navlinks($cm, $context, \mod_these\local\backlinks::activity_home($cm, $context))
);
