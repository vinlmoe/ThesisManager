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
 * Changement de sujet de thèse par l'étudiant, depuis un dossier actif : motif, nouveau sujet
 * (et éventuellement nouveau directeur), signature. L'ancien sujet est archivé, l'ancien
 * directeur, la DEVE et la bibliothèque sont prévenus, et le nouveau sujet repasse la phase 1.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\local\page;
use mod_these\output;

use mod_these\form\subject_form;

$id = required_param('id', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
require_capability('mod/these:submit', $context);

$viewurl = new moodle_url('/mod/these/view.php', ['id' => $cm->id]);
$dossier = these_get_user_dossier($these->id, $USER->id);
if (!$dossier || !these_subject_changeable($dossier)) {
    redirect($viewurl, get_string('subjectchangenotallowed', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/subjectchange.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('subjectchange', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new subject_form($PAGE->url, [
    'directors' => these_director_options($context, $dossier->directorid),
    'submitlabel' => 'subjectchangesubmit',
    'allowdraft' => false,
    'subjectchange' => true,
    'signername' => fullname($USER),
]);

$formdata = clone $dossier;
$formdata->id = $cm->id;
$formdata->dossierid = $dossier->id;
// Les engagements se prennent de nouveau pour le nouveau sujet.
$formdata->commitguide = 0;
$formdata->commitduplicate = 0;
$formdata->commitethics = 0;
unset($formdata->signaturedata);
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    these_change_subject(
        $these,
        $cm,
        $dossier,
        $data,
        $USER->id,
        (string) $data->changereason,
        these_signature_from_form($data)
    );
    redirect($viewurl, get_string('subjectchangedmsg', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('subjectchange', 'mod_these'));
echo backlinks::render(backlinks::student_dossier($cm));
$current = get_string('subjectchangecurrent', 'mod_these', format_string($dossier->title, true, ['escape' => false]));
echo $renderer->render(new output\message($current, 'alert alert-info'));
if ((int) $dossier->status === THESE_STATUS_CANCELLED) {
    echo $renderer->render(new output\message(get_string('subjectchangecancelled', 'mod_these'), 'alert alert-warning'));
}
$mform->display();
echo $OUTPUT->footer();
