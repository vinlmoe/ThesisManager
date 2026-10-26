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
 * Fiche complète d'un dossier de thèse : progression dans le circuit, actions possibles pour
 * l'utilisateur, sujet, enregistrements, soutenance, documents et historique.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_view($context);

$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);
if (!these_can_view_dossier($rights)) {
    throw new required_capability_exception($context, 'mod/these:viewall', 'nopermissions', '');
}

$PAGE->set_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('dossier', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$student = $DB->get_record('user', ['id' => $dossier->userid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'));
echo these_render_navlinks($cm, $context);
if ($rights->viewall) {
    echo these_render_jury_conflicts($dossier);
}
echo these_render_dossier($these, $cm, $context, $dossier, $rights);
echo $OUTPUT->footer();
