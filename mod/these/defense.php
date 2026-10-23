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
 * Dépôt des documents de soutenance par l'étudiant (phase 2, étape 1) : manuscrit, autres
 * documents, proposition de date, de lieu et de jury. Ouvert tant que le dossier est actif
 * (préparation en plusieurs fois) ou renvoyé en phase 2.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\defense_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/these:submit', $context);

$viewurl = new moodle_url('/mod/these/view.php', ['id' => $cm->id]);
$dossier = these_get_user_dossier($these->id, $USER->id);
if (!$dossier || !these_defense_editable($dossier)) {
    redirect($viewurl, get_string('defensenoteditable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/defense.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('depositdefense', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$jury = these_get_jury($dossier->id);
$returned = (int) $dossier->status === THESE_STATUS_DEFENSE_RETURNED;
$mform = new defense_form($PAGE->url, [
    'jurycount' => count($jury),
    'minjury' => $these->minjury,
    'submitlabel' => $returned ? 'resubmitdefense' : 'submitdefense',
    'signername' => fullname($USER),
]);

$formdata = (object) array_merge(['id' => $cm->id, 'dossierid' => $dossier->id], these_jury_form_data($jury));
foreach (['manuscript', 'defensedocs'] as $filearea) {
    $draftitemid = file_get_submitted_draft_itemid($filearea);
    file_prepare_draft_area($draftitemid, $context->id, 'mod_these', $filearea, $dossier->id, these_filemanager_options($filearea));
    $formdata->$filearea = $draftitemid;
}
$formdata->proposeddate = $dossier->proposeddate ?: null;
$formdata->titleen = $dossier->titleen;
$formdata->conclusions = $dossier->conclusions;
// Données de l'étudiant : celles déjà enregistrées, à défaut son profil et sa fiche de sujet.
$bordereau = these_get_bordereau($dossier) + [
    'birthname' => core_text::strtoupper($USER->lastname),
    'firstnames' => $USER->firstname,
    'city' => $USER->city,
    'country' => $USER->country ? get_string($USER->country, 'countries') : '',
    'phone' => $USER->phone1,
    'nbvolumes' => 1,
    'summaryfr' => $dossier->summary,
    'keywords' => these_split_keywords($dossier->keywords),
];
foreach ($bordereau as $field => $value) {
    if ($field === 'keywords') {
        $formdata->bkeyword = array_values((array) $value);
    } else {
        $formdata->{'b' . $field} = $value;
    }
}
$formdata->defenselocation = $dossier->defenselocation ?: $these->defaultlocation;
// Le directeur figure presque toujours au jury : il est proposé d'office au premier dépôt.
if (empty($jury) && $dossier->directorid && ($director = $DB->get_record('user', ['id' => $dossier->directorid]))) {
    $formdata->juryrole = [1 => 'director'];
    $formdata->juryname = [1 => fullname($director)];
    $formdata->juryemail = [1 => $director->email];
    $formdata->juryinstitution = [1 => $these->establishmentname];
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    foreach (['manuscript', 'defensedocs'] as $filearea) {
        file_save_draft_area_files(
            $data->$filearea,
            $context->id,
            'mod_these',
            $filearea,
            $dossier->id,
            these_filemanager_options($filearea)
        );
    }
    $submit = !empty($data->submitdefense);
    these_save_defense($these, $cm, $dossier, $data, $submit, $USER->id, $submit ? these_signature_from_form($data) : null);
    redirect(
        $viewurl,
        get_string($submit ? 'defensesubmittedmsg' : 'draftsaved', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('depositdefense', 'mod_these'));
if ($returned && trim((string) $dossier->returncomment) !== '') {
    echo html_writer::div(
        html_writer::tag('strong', get_string('returncomment', 'mod_these')) . html_writer::empty_tag('br')
            . format_text($dossier->returncomment, FORMAT_PLAIN),
        'alert alert-warning'
    );
}
echo $OUTPUT->box(get_string('defenseintro', 'mod_these', (int) $these->minjury), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
