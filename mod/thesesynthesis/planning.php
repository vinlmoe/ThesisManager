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
 * Planning des soutenances de toutes les promotions suivies : soutenances de la semaine ou du mois,
 * navigation d'une période à l'autre, planning imprimable et téléchargeable. Chaque utilisateur y
 * voit les soutenances qu'il peut consulter dans chaque activité suivie.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('thesesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$synthesis = $DB->get_record('thesesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/thesesynthesis:view', $context);

$baseurl = new moodle_url('/mod/thesesynthesis/planning.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name) . ' - ' . get_string('planning', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$links = thesesynthesis_get_active_links($synthesis->id, $USER->id);
$first = reset($links);

\mod_these\local\planning_page::output(
    $links,
    $baseurl,
    these_synthesis_scope($synthesis->id),
    $first ? $first->these : null,
    true,
    get_string('planning', 'mod_these') . ' — ' . format_string($synthesis->name),
    thesesynthesis_render_navlinks($cm, $context)
);
