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
 * Liste du corps enseignant en PDF (voir \mod_these\local\jurors::teaching_list_context()), à inclure
 * dans les thèses : téléchargeable depuis une activité « Gestion des thèses » ou « Suivi des thèses »
 * par quiconque peut la consulter, étudiants compris.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
if (!in_array($cm->modname, ['these', 'thesesynthesis'], true)) {
    throw new moodle_exception('invalidcoursemodule');
}
$course = get_course($cm->course);
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if ($cm->modname === 'these') {
    these_require_view($context);
    $activity = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);
    $scope = these_settings_scope($activity);
} else {
    require_capability('mod/thesesynthesis:view', $context);
    $activity = null;
    $scope = these_synthesis_scope($cm->instance);
}

send_file(
    these_render_scope_doctemplate($scope, 'teachinglist', \mod_these\local\jurors::teaching_list_context($scope, $activity)),
    'liste-corps-enseignant-' . userdate(time(), '%Y-%m-%d') . '.pdf',
    0,
    0,
    true,
    true,
    'application/pdf'
);
