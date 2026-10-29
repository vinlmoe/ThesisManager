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
 * Questionnaire préalable au jury : rempli par l'étudiant tant que le dépôt de la soutenance est
 * modifiable (les questions obligatoires conditionnent ce dépôt) ; la DEVE peut corriger les
 * réponses à tout moment.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\questionnaire_form;
use mod_these\local\backlinks;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
$rights = these_get_rights($context, $dossier, $USER->id);

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
$formdata = $params;
foreach (these_get_answers($dossier->id) as $questionid => $answer) {
    if (!isset($questions[$questionid])) {
        continue;
    }
    if ($questions[$questionid]->qtype === 'multi') {
        foreach (these_question_options($questions[$questionid]) as $n => $option) {
            $formdata['q' . $questionid . '_' . $n] = in_array($option, (array) $answer, true) ? 1 : 0;
        }
    } else {
        $formdata['q' . $questionid] = these_answer_text($answer);
    }
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    these_save_answers($dossier, $questions, $data);
    redirect($returnurl, get_string('questionnairesaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
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
