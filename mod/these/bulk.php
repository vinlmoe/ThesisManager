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
 * Actions groupées de la DEVE sur les dossiers cochés au tableau de bord (voir \mod_these\local\bulk).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$id = required_param('id', PARAM_INT);
$action = required_param('bulkaction', PARAM_ALPHA);
$dossierids = optional_param_array('dossierids', [], PARAM_INT);
$a6date = optional_param('a6date', '', PARAM_RAW_TRIMMED);
$a6school = optional_param('a6school', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');
require_sesskey();

$returnurl = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
if (!array_key_exists($action, \mod_these\local\bulk::actions()) || !$dossierids) {
    redirect($returnurl, get_string('bulknothing', 'mod_these'), null, \core\output\notification::NOTIFY_WARNING);
}

// Validation de l'A6 : date du sélecteur (aaaa-mm-jj), vide pour « pas encore validée ».
$params = [];
if ($action === 'a6') {
    $params['a6date'] = $a6date === '' ? 0 : \mod_these\local\a6::parse_date($a6date);
    if ($params['a6date'] === null) {
        redirect($returnurl, get_string('a6baddate', 'mod_these', s($a6date)), null, \core\output\notification::NOTIFY_WARNING);
    }
    $params['a6school'] = array_key_exists($a6school, these_school_options()) ? $a6school : '';
}

$result = (object) ['done' => 0, 'skipped' => 0];
foreach (array_unique($dossierids) as $dossierid) {
    $dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id]);
    if ($dossier && \mod_these\local\bulk::apply($these, $cm, $context, $dossier, $action, $USER->id, $params)) {
        $result->done++;
    } else {
        $result->skipped++;
    }
}
$result->action = \mod_these\local\bulk::actions()[$action];
redirect(
    $returnurl,
    get_string('bulkdone', 'mod_these', $result),
    null,
    $result->done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
);
