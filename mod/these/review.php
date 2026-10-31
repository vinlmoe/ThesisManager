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
 * Étapes de validation du circuit (voir these_review_steps()) : validation ou renvoi du sujet par
 * le directeur, enregistrement par la DEVE puis par la bibliothèque ; validation de la soutenance
 * et avis du directeur sur son rapport, validation du dossier complet et attribution du numéro par
 * la DEVE, attestation de conformité par la bibliothèque ; après une soutenance avec corrections,
 * validation des corrections par le directeur, la bibliothèque et la DEVE.
 *
 * L'étape traitée est déterminée par le statut du dossier : la page refuse l'accès à quiconque
 * n'est pas l'acteur attendu à cette étape.
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
use mod_these\output\parts;

use mod_these\form\review_form;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
these_require_view($context);

['dossier' => $dossier, 'rights' => $rights] = (array) page::load_dossier($activity, $dossierid, $USER->id);
// Dossier réduit à son procès-verbal (données de l'étudiant effacées) : plus aucune action.
\mod_these\local\dossiers::require_in_circuit($dossier);
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);

$steps = these_review_steps();
$step = $steps[(int) $dossier->status] ?? null;
if ($step === null) {
    redirect($dossierurl, get_string('nostepwaiting', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}
if (empty($rights->{$step['actor']})) {
    throw new moodle_exception('notyourstep', 'mod_these', $dossierurl);
}

$PAGE->set_url('/mod/these/review.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('review' . $step['key'], 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$jury = these_get_jury($dossier->id);
// Documents signés par l'acteur à la validation de cette étape.
$signs = [];
foreach ($step['sign'] as $slot => $doctypes) {
    // Les emplacements de l'agent DEVE, quand il est désigné, sont signés par lui seul.
    if (!these_signs_slot_at_validation($slot, $USER->id, $these)) {
        continue;
    }
    foreach ($doctypes as $doctype) {
        if (in_array($slot, these_document_slots($dossier, $doctype), true)) {
            $signs[$doctype] = these_document_label($doctype);
        }
    }
}
$juryconflicts = $rights->viewall ? these_render_jury_conflicts($dossier) : '';
// Documents des étapes précédentes encore à signer : l'étape ne peut pas être validée (le renvoi reste possible).
$missing = \mod_these\local\prerequisites::missing($dossier);
$mform = new review_form($PAGE->url, [
    'stepkey' => $step['key'],
    'returnto' => $step['returnto'],
    'theseid' => $these->id,
    'scope' => these_settings_scope($these),
    'minjury' => $these->minjury,
    'rooms' => these_defense_rooms($these),
    'jurycount' => count($jury),
    'jury' => array_values($jury),
    'jurorids' => array_map('intval', array_column($jury, 'jurorid')),
    // Conflits de jury : en tête de la rubrique du jury quand le formulaire la contient, sinon de la page.
    'juryconflicts' => $juryconflicts,
    'roomconflicts' => $rights->viewall ? these_render_room_conflicts($dossier) : '',
    'signs' => implode(', ', $signs),
    'missingsignatures' => $missing ? \mod_these\local\prerequisites::render_missing($cm, $context, $dossier, $missing) : '',
    // Nouvelle date demandée à l'étudiant : la DEVE ne peut pas valider le dossier avant de l'avoir reçue.
    'datepending' => $step['key'] === 'devedefense' && \mod_these\local\daterequest::pending($dossier),
    'signername' => fullname($USER),
    // Nom de signature saisi par la DEVE seulement ; les autres signent au nom de leur compte.
    'editablename' => $step['actor'] === 'deve',
    'externaldirector' => !empty($dossier->externaldirector),
    // Validation de l'A6 : rappelée, saisie sur sa page à part (retour ici après la saisie).
    'a6label' => \mod_these\local\a6::label($dossier),
    'defvlabel' => \mod_these\local\a6::defv_label($dossier),
    'defvrecorded' => !empty(\mod_these\local\a6::get($dossier)['defvdate']),
    'a6url' => \mod_these\local\a6::url($cm, $dossier->userid, $PAGE->url),
    'report' => (object) [
        'deposited' => these_has_files($context, 'directorreport', $dossier->id),
        'favorable' => !empty($dossier->reportfavorable),
        'url' => new moodle_url('/mod/these/report.php', ['id' => $cm->id, 'dossierid' => $dossier->id]),
    ],
]);

$formdata = (object) ['id' => $cm->id, 'dossierid' => $dossier->id];
switch ($step['key']) {
    case 'librarysubject':
        $formdata->libraryref = $dossier->libraryref;
        break;
    case 'directordefense':
        $formdata->ethicsnumber = $dossier->ethicsnumber;
        $formdata->directorfunction = these_get_agreement($dossier)['directorfunction'] ?? '';
        // Grille de conformité : le directeur la remplit lui-même, sans reprendre les cases de l'étudiant.
        break;
    case 'devedefense':
        $formdata->devenumber = $dossier->devenumber ?: these_suggest_devenumber();
        $formdata->devenumberproposed = $dossier->devenumber ? '' : $formdata->devenumber;
        $formdata->defensedate = $dossier->defensedate ?: $dossier->proposeddate;
        $formdata->defenselocation = $dossier->defenselocation;
        $formdata = (object) array_merge((array) $formdata, these_jury_form_data($jury));
        // Jury présenté en lecture : la saisie ne s'ouvre que pour le modifier.
        $formdata->juryedit = empty($jury) ? 1 : 0;
        // Agrément administratif : le DEFV et l'A6 viennent de la validation des études.
        $formdata->agmobility = these_get_agreement($dossier)['mobility'] ?? 0;
        break;
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect(backlinks::to_dossier($cm, $dossier)->url);
} else if ($data = $mform->get_data()) {
    $remark = trim((string) $data->remark);
    if (!empty($data->returndossier)) {
        these_return_dossier($these, $cm, $dossier, $USER->id, $data->returntarget, $remark);
        redirect(
            backlinks::after_action($cm, $dossierurl),
            get_string('dossierreturned', 'mod_these', these_actor_label($data->returntarget)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($missing) {
        redirect($PAGE->url, get_string('prerequisitesmissing', 'mod_these'), null, \core\output\notification::NOTIFY_WARNING);
    }
    if ($step['key'] === 'devedefense' && \mod_these\local\daterequest::pending($dossier)) {
        redirect($PAGE->url, get_string('datepending', 'mod_these'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $fields = [];
    switch ($step['key']) {
        case 'librarysubject':
            $fields['libraryref'] = trim($data->libraryref);
            break;
        case 'directordefense':
            $fields['ethicsnumber'] = trim($data->ethicsnumber);
            $grid = [];
            foreach (these_get_grid_items(these_settings_scope($these)) as $items) {
                foreach (array_keys($items) as $item) {
                    $grid[$item] = (int) !empty($data->{'grid_' . $item});
                }
            }
            $fields['conformitygrid'] = json_encode($grid);
            // Fonction du directeur, imprimée dans l'agrément scientifique ; l'agrément administratif
            // sera complété par la DEVE à l'étape suivante.
            $fields['agreement'] = json_encode(array_merge(these_get_agreement($dossier), [
                'directorfunction' => trim((string) ($data->directorfunction ?? '')),
            ]));
            break;
        case 'devedefense':
            $fields['devenumber'] = trim($data->devenumber);
            $fields['devenumberproposed'] = (string) ($data->devenumberproposed ?? '');
            $fields['defensedate'] = (int) $data->defensedate;
            $fields['defenselocation'] = trim($data->defenselocation);
            // DEFV de la validation des études, repris dans l'agrément que la DEVE signe.
            $fields['agreement'] = json_encode(array_merge(these_get_agreement($dossier), [
                'defvdate' => (int) \mod_these\local\a6::get($dossier)['defvdate'],
                'mobility' => (int) !empty($data->agmobility),
            ]));
            these_save_jury($dossier->id, these_extract_submitted_jury($data, true), $USER->id);
            break;
    }
    $validated = these_validate_step(
        $these,
        $cm,
        $dossier,
        $USER->id,
        $fields,
        $remark,
        $signs ? these_signature_from_form($data) : null
    );
    $message = get_string('stepvalidated', 'mod_these');
    if (isset($fields['devenumber']) && (string) $validated->devenumber !== $fields['devenumber']) {
        // Numéro proposé attribué entre-temps à un autre dossier : le suivant a été retenu.
        $message .= ' ' . get_string('devenumberreassigned', 'mod_these', s($validated->devenumber));
    }
    if ($step['key'] === 'devedefense') {
        // Éligibilité validée : les membres du jury sont prévenus et reçoivent l'accès aux documents.
        $notified = these_notify_jury($these, $cm, $DB->get_record('these_dossier', ['id' => $dossier->id]));
        $message .= ' ' . get_string('jurynotified', 'mod_these', $notified->sent);
        if ($notified->missing) {
            $message .= ' ' . get_string('jurynotifiedmissing', 'mod_these', s(implode(', ', $notified->missing)));
        }
    }
    redirect(backlinks::after_action($cm, $dossierurl), $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);
$director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;
$extdirector = \mod_these\local\subjects::external_director_summary($dossier);

$renderer = $PAGE->get_renderer('mod_these');
// Résumé du dossier au-dessus de la décision ; le dossier complet suit, replié, pour ne pas
// repousser la lecture de ce qui est validé à plusieurs écrans sous les boutons.
$manuscript = these_can_view_filearea('manuscript', $rights)
    ? $renderer->render(new output\file_links($context, 'manuscript', $dossier->id)) : '';
$date = (int) ($dossier->defensedate ?: $dossier->proposeddate);
$summary = new output\detail_section(get_string('reviewsummary', 'mod_these'), [
    get_string('signinfostudent', 'mod_these') => [parts::text($student ? fullname($student) : '-')],
    get_string('signinfotitle', 'mod_these') => [parts::text(format_string(
        these_subject_field($dossier, 'title', $context),
        true,
        ['context' => $context, 'escape' => false]
    ))],
    get_string('status') => [parts::text(these_status_label($dossier->status))],
    get_string('slotdirector', 'mod_these') => $director ? [parts::text(fullname($director))] : null,
    // Directeur extérieur : son adresse, saisie par l'étudiant, reçoit le lien de signature à la validation.
    get_string('slotextdirector', 'mod_these') => $extdirector !== '' ? [parts::text($extdirector)] : null,
    get_string('signinfonumber', 'mod_these') => trim((string) $dossier->devenumber) !== ''
        ? [parts::text($dossier->devenumber)] : null,
    get_string('signinfodefense', 'mod_these') => $date ? [parts::text(
        userdate($date, get_string('strftimedaydatetime', 'langconfig'))
        . (trim((string) $dossier->defenselocation) !== '' ? ' — ' . $dossier->defenselocation : '')
    )] : null,
    get_string('manuscript', 'mod_these') => $manuscript !== '' ? [parts::html($manuscript)] : null,
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('review' . $step['key'], 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
if ($step['key'] !== 'devedefense') {
    echo $juryconflicts;
}
echo $renderer->render($summary);
$mform->display();
echo html_writer::tag(
    'details',
    html_writer::tag('summary', get_string('reviewfulldossier', 'mod_these'), ['class' => 'h4 my-3'])
        . these_render_dossier($these, $cm, $context, $dossier, $rights, false),
    ['class' => 'mt-4 these-review-dossier']
);
echo $OUTPUT->footer();
