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
 * Suivi des thèses, toutes promotions confondues : dossiers à traiter, mes jurys, puis la liste
 * des dossiers suivis (dirigés, ou tous pour la DEVE et la bibliothèque) avec filtres.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

use mod_thesesynthesis\output;

$id = required_param('id', PARAM_INT);
$status = optional_param('status', -999, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filtercmid = optional_param('cmid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('thesesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$synthesis = $DB->get_record('thesesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/thesesynthesis:view', $context);

$baseurl = new moodle_url('/mod/thesesynthesis/view.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$links = thesesynthesis_get_active_links($synthesis->id, $USER->id);
$filters = ['status' => $status === -999 ? null : $status, 'search' => $search, 'cmid' => $filtercmid];
$rows = thesesynthesis_get_dossiers($links, $USER->id, $filters);
$alltodo = array_filter(thesesynthesis_get_dossiers($links, $USER->id), function ($row) {
    return $row->todo;
});
$juries = thesesynthesis_get_juries($links, $USER);

$renderer = $PAGE->get_renderer('mod_thesesynthesis');
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($synthesis->name));

echo thesesynthesis_render_navlinks($cm, $context);
if (!$links) {
    echo $OUTPUT->notification(get_string('nolinks', 'mod_thesesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// À traiter.
echo $OUTPUT->heading(get_string('todo', 'mod_these'), 3);
echo $renderer->render(new output\dossier_table($alltodo, get_string('nothingtodo', 'mod_these')));

// Mes jurys.
echo $OUTPUT->heading(get_string('myjuries', 'mod_thesesynthesis'), 3, 'mt-4');
echo $renderer->render(new output\jury_table($juries));

// Dossiers suivis, avec filtres.
echo $OUTPUT->heading(get_string('followed', 'mod_thesesynthesis'), 3, 'mt-4');
echo $renderer->render(new output\dossier_filters($baseurl, $cm->id, $links, $filtercmid, $status, $search));
echo $renderer->render(new output\dossier_table($rows, get_string('nodossiers', 'mod_thesesynthesis')));

echo $OUTPUT->footer();
