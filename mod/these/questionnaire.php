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
 * Questionnaire préalable au jury : l'étudiant y répond dans le dépôt de la soutenance (defense.php),
 * dont les questions obligatoires conditionnent la soumission, ou sur cette page tant que le dépôt
 * est modifiable ; la DEVE peut corriger les réponses à tout moment.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\questionnaire_form;
use mod_these\local\backlinks;
use mod_these\local\page;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
['dossier' => $dossier, 'rights' => $rights] = (array) page::load_dossier($activity, $dossierid, $USER->id);

$params = ['id' => $cm->id, 'dossierid' => $dossier->id];
$returnurl = $rights->student ? new moodle_url('/mod/these/view.php', ['id' => $cm->id])
    : new moodle_url('/mod/these/dossier.php', $params);
if (!$rights->deve) {
    if (!$rights->student) {
        throw new required_capability_exception($context, 'mod/these:submit', 'nopermissions', '');
    }
    if (!these_defense_editable($dossier)) {
        redirect($returnurl, get_string('defensenoteditable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
    }
}

$PAGE->set_url('/mod/these/questionnaire.php', $params);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('questionnaire', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$questions = these_get_questions(these_settings_scope($these));
$mform = new questionnaire_form($PAGE->url, ['questions' => $questions]);
$mform->set_data($params + questionnaire_form::answers_data($questions, these_get_answers($dossier->id)));

if ($mform->is_cancelled()) {
    redirect(backlinks::to_dossier($cm, $dossier, $rights)->url);
} else if ($data = $mform->get_data()) {
    these_save_answers($dossier, $questions, $data);
    redirect(
        $rights->student ? $returnurl : backlinks::after_action($cm, $returnurl),
        get_string('questionnairesaved', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('questionnaire', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier, $rights));
if (!$rights->student) {
    echo $OUTPUT->heading(fullname($DB->get_record('user', ['id' => $dossier->userid])), 4);
}
echo $OUTPUT->box(get_string('questionnaire_desc', 'mod_these'), 'generalbox mb-3');
if ($questions) {
    $mform->display();
} else {
    echo $OUTPUT->notification(get_string('questionsnone', 'mod_these'), 'info');
}
echo $OUTPUT->footer();
