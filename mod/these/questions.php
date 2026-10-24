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
 * Questions du questionnaire préalable au jury de l'activité (DEVE), rempli par l'étudiant avant le
 * dépôt de la soutenance. Si une activité « Suivi des thèses » suit l'activité, ses questions
 * s'appliquent et se modifient depuis celle-ci.
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
these_require_service($context, 'deve');

$PAGE->set_url('/mod/these/questions.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('questions', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$scope = these_settings_scope($these);
if ($scope->shared) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('questions', 'mod_these'));
    echo these_render_navlinks($cm, $context);
    echo \mod_these\local\doctemplates_page::shared_notice($scope, get_string('questions', 'mod_these'));
    echo $OUTPUT->footer();
    exit;
}
\mod_these\local\questions_page::output($scope, new moodle_url($PAGE->url), these_render_navlinks($cm, $context));
