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
 * Modèles des documents communs aux activités « Gestion des thèses » suivies : chaque promotion
 * suivie produit ses documents à partir de ces modèles et de ces images.
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
\mod_these\local\rights::require_management($context, 'mod/thesesynthesis:managesettings');

$baseurl = new moodle_url('/mod/thesesynthesis/doctemplates.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name) . ' - ' . get_string('doctemplates', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$scope = these_synthesis_scope($synthesis->id);
$navigation = thesesynthesis_render_navlinks($cm, $context);

// Aperçus : établissement de la première activité suivie, à défaut celui par défaut.
$links = thesesynthesis_get_active_links($synthesis->id, $USER->id);
\mod_these\local\doctemplates_page::output($scope, $baseurl, $links ? reset($links)->these : null, $navigation);
