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
 * Choix des activités « Gestion des thèses » (une par promotion) suivies dans cette activité :
 * cochée, ses dossiers y remontent ; décochée, elle disparaît du suivi sans que rien ne change dans
 * l'activité d'origine.
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
\mod_these\local\rights::require_management($context, 'mod/thesesynthesis:managelinks');

$baseurl = new moodle_url('/mod/thesesynthesis/administration.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name) . ' - ' . get_string('managelinks', 'mod_thesesynthesis'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$available = thesesynthesis_get_available_activities($USER->id);

if (optional_param('save', 0, PARAM_INT) && confirm_sesskey()) {
    // Seules les activités proposées à cet utilisateur peuvent être cochées ; les liens déjà en
    // place vers des activités hors de sa portée (ajoutés par un autre gestionnaire) sont gardés.
    $selected = array_intersect(optional_param_array('thesecmid', [], PARAM_INT), array_keys($available));
    $outofscope = array_diff(array_keys(thesesynthesis_get_links($synthesis->id)), array_keys($available));
    thesesynthesis_set_links($synthesis->id, array_merge($selected, $outofscope));
    redirect(
        new moodle_url('/mod/thesesynthesis/view.php', ['id' => $cm->id]),
        get_string('linkssaved', 'mod_thesesynthesis'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$linked = thesesynthesis_get_links($synthesis->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($synthesis->name));
echo $OUTPUT->heading(get_string('managelinks', 'mod_thesesynthesis'), 3);
$renderer = $PAGE->get_renderer('mod_thesesynthesis');
echo $renderer->render(new \mod_these\output\message(get_string('managelinks_help', 'mod_thesesynthesis'), '', 'p'));

if (empty($available)) {
    echo $OUTPUT->notification(get_string('noactivities', 'mod_thesesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $renderer->render(new \mod_thesesynthesis\output\links_form($baseurl, $available, $linked));
echo $OUTPUT->footer();
