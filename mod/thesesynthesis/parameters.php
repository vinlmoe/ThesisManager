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
 * Paramètres du circuit communs aux activités « Gestion des thèses » suivies : rôles des services,
 * agents DEVE signataires, établissement, université, signataires des documents et contrat type de
 * diffusion. Aucun de ces paramètres n'est un réglage du site.
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

$baseurl = new moodle_url('/mod/thesesynthesis/parameters.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name) . ' - ' . get_string('scopesettings', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

\mod_these\page\scopesettings_page::output(
    \mod_thesesynthesis\local\scope::for_synthesis($synthesis->id),
    $baseurl,
    thesesynthesis_render_navlinks($cm, $context)
);
