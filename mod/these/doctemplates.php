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
 * Modèles des documents de l'activité (DEVE) : liste des documents et images des modèles,
 * modification du modèle de chacun, aperçu PDF avec des données fictives, retour au modèle
 * d'origine. Comme les courriels, chaque activité a ses propres modèles.
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

$listurl = new moodle_url('/mod/these/doctemplates.php', ['id' => $cm->id]);
$type = optional_param('type', '', PARAM_ALPHA);
$PAGE->set_url($type !== '' ? new moodle_url($listurl, ['type' => $type]) : $listurl);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('doctemplates', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Activité suivie par une activité « Suivi des thèses » : ses modèles y sont définis.
$scope = these_settings_scope($these);
if ($scope->shared) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('doctemplates', 'mod_these'));
    echo these_render_navlinks($cm, $context);
    echo \mod_these\page\doctemplates_page::shared_notice($scope, get_string('doctemplates', 'mod_these'));
    echo $OUTPUT->footer();
    exit;
}
\mod_these\page\doctemplates_page::output($scope, $listurl, $these, these_render_navlinks($cm, $context));
