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
 * Dépôt des documents de soutenance par l'étudiant (phase 2, étape 1) : manuscrit, proposition de
 * date et de jury (la salle est attribuée par la DEVE), réponses au questionnaire préalable au jury.
 * Ouvert tant que le dossier est actif (préparation en plusieurs fois) ou renvoyé en phase 2.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\output;
use mod_these\output\parts;

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
$questions = these_get_questions(these_settings_scope($these));
$mform = new defense_form($PAGE->url, [
    'jurycount' => count($jury),
    'jurorids' => array_map('intval', array_column($jury, 'jurorid')),
    'minjury' => $these->minjury,
    'defvlabel' => get_string('a6bydevestatus', 'mod_these', \mod_these\local\a6::defv_label($dossier)),
    'a6label' => get_string('a6bydevestatus', 'mod_these', \mod_these\local\a6::label($dossier)),
    'teachinglisturl' => new moodle_url('/mod/these/teachinglist.php', ['cmid' => $cm->id]),
    'these' => $these,
    'context' => $context,
    'submitlabel' => $returned ? 'resubmitdefense' : 'submitdefense',
    'signername' => fullname($USER),
    // Délai minimal entre le dépôt et la soutenance (paramètres du circuit).
    'mindelayweeks' => (int) \mod_these\local\scopesettings::for_context($context)['mindelayweeks'],
    'earliestdate' => \mod_these\local\scopesettings::earliest_defense($context),
    // Questionnaire préalable au jury : rempli dans le dépôt.
    'questions' => $questions,
]);

$formdata = (object) array_merge(
    ['id' => $cm->id, 'dossierid' => $dossier->id],
    these_jury_form_data($jury),
    \mod_these\form\questionnaire_form::answers_data($questions, these_get_answers($dossier->id))
);
$draftitemid = file_get_submitted_draft_itemid('manuscript');
$fileoptions = these_filemanager_options('manuscript');
file_prepare_draft_area($draftitemid, $context->id, 'mod_these', 'manuscript', $dossier->id, $fileoptions);
$formdata->manuscript = $draftitemid;
$formdata->proposeddate = $dossier->proposeddate ?: null;
$formdata->titleen = $dossier->titleen;
$formdata->conclusions = $dossier->conclusions;
$formdata->ethicsnumber = $dossier->ethicsnumber;
foreach (these_get_grid($dossier) as $item => $checked) {
    $formdata->{'grid_' . $item} = $checked;
}
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
// Ligne du directeur de thèse (la deuxième, figée) préremplie au premier dépôt : le directeur extérieur
// s'il y en a un, sinon le directeur du dossier.
if (empty($jury) && !empty($dossier->externaldirector) && trim((string) $dossier->extdirectorname) !== '') {
    $formdata->juryname = [1 => trim($dossier->extdirectorname)];
    $formdata->jurytitle = [1 => (string) $dossier->extdirectorgrade];
    $formdata->juryemail = [1 => (string) $dossier->extdirectoremail];
    $formdata->juryinstitution = [1 => (string) $dossier->extdirectorinstitution];
} else if (empty($jury) && $dossier->directorid && ($director = $DB->get_record('user', ['id' => $dossier->directorid]))) {
    $juror = \mod_these\local\jurors::find_by_email($director->email);
    if ($juror) {
        // Directeur présent dans l'annuaire du jury : choisi dans la liste.
        $formdata->jurorid = [1 => $juror->id];
    } else {
        $formdata->juryname = [1 => fullname($director)];
        $formdata->jurytitle = [1 => (string) $dossier->directortitle];
        $formdata->juryemail = [1 => $director->email];
        $formdata->juryinstitution = [1 => $these->establishmentname];
    }
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files(
        $data->manuscript,
        $context->id,
        'mod_these',
        'manuscript',
        $dossier->id,
        these_filemanager_options('manuscript')
    );
    // Réponses au questionnaire, enregistrées avec le brouillon comme à la soumission.
    if ($questions) {
        these_save_answers($dossier, $questions, $data);
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

// Avancement : rubriques complètes repliées, rubriques à compléter dépliées et listées en tête.
$progress = null;
if (!$mform->is_submitted()) {
    $sections = $mform->section_progress((array) $formdata);
    $mform->expand_incomplete($sections);
    $items = array_map(function ($section) use ($cm) {
        $url = new moodle_url('/mod/these/defense.php', ['id' => $cm->id], 'id_' . $section['name']);
        return [
            parts::link($url, $section['label'], ''),
            parts::text(' '),
            $section['missing']
                ? parts::badge(get_string('defensesectionmissing', 'mod_these', $section['missing']), 'badge badge-warning')
                : parts::badge(get_string('defensesectioncomplete', 'mod_these'), 'badge badge-success'),
        ];
    }, $sections);
    $complete = count(array_filter(array_column($sections, 'missing'), function ($missing) {
        return !$missing;
    }));
    $progress = new output\message(array_merge(
        [parts::strong(get_string('defenseprogress', 'mod_these', (object) [
            'complete' => $complete,
            'total' => count($sections),
        ]))],
        [parts::html($PAGE->get_renderer('mod_these')->render(new output\bullet_list($items)))]
    ), 'alert alert-secondary these-defense-progress');
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('depositdefense', 'mod_these'));
echo backlinks::render(backlinks::student_dossier($cm));
if ($returned && trim((string) $dossier->returncomment) !== '') {
    echo $renderer->render(new output\message([
        parts::strong(get_string('returncomment', 'mod_these')),
        parts::br(),
        parts::html(format_text($dossier->returncomment, FORMAT_PLAIN)),
    ], 'alert alert-warning'));
}
if (these_is_adjourned($dossier)) {
    echo $renderer->render(new output\message(get_string(
        'adjournednoticestudent',
        'mod_these',
        userdate($dossier->statustime, get_string('strftimedate', 'langconfig'))
    ), 'alert alert-warning'));
}
echo $OUTPUT->box(get_string('defenseintro', 'mod_these', (int) $these->minjury), 'generalbox mb-3');
if ($progress) {
    echo $renderer->render($progress);
}
$mform->display();
echo $OUTPUT->footer();
