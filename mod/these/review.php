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

use mod_these\form\review_form;

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
    foreach ($doctypes as $doctype) {
        if (in_array($slot, these_document_slots($dossier, $doctype), true)) {
            $signs[$doctype] = these_document_label($doctype);
        }
    }
}
$mform = new review_form($PAGE->url, [
    'stepkey' => $step['key'],
    'returnto' => $step['returnto'],
    'theseid' => $these->id,
    'scope' => these_settings_scope($these),
    'minjury' => $these->minjury,
    'jurycount' => count($jury),
    'jurorids' => array_map('intval', array_column($jury, 'jurorid')),
    'signs' => implode(', ', $signs),
    'signername' => fullname($USER),
    'externaldirector' => !empty($dossier->externaldirector),
    'report' => (object) [
        'text' => (string) $dossier->reporttext,
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
        foreach (these_get_grid($dossier) as $item => $checked) {
            $formdata->{'grid_' . $item} = $checked;
        }
        break;
    case 'devedefense':
        $formdata->devenumber = $dossier->devenumber ?: these_suggest_devenumber($these->id);
        $formdata->defensedate = $dossier->defensedate ?: $dossier->proposeddate;
        $formdata->defenselocation = $dossier->defenselocation;
        $formdata = (object) array_merge((array) $formdata, these_jury_form_data($jury));
        // Agrément administratif : à défaut, ce que l'étudiant a déclaré dans sa fiche signalétique.
        $agreement = these_get_agreement($dossier);
        $declared = these_get_bordereau($dossier);
        $formdata->agdefvdate = $agreement['defvdate'] ?? ($declared['defvdate'] ?? 0);
        $formdata->agmobility = $agreement['mobility'] ?? 0;
        $formdata->aga6notyet = $agreement['a6notyet'] ?? ($declared['a6notyet'] ?? 0);
        $formdata->aga6date = $agreement['a6date'] ?? ($declared['a6date'] ?? 0);
        break;
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    $remark = trim((string) $data->remark);
    if (!empty($data->returndossier)) {
        these_return_dossier($these, $cm, $dossier, $USER->id, $data->returntarget, $remark);
        redirect(
            $dossierurl,
            get_string('dossierreturned', 'mod_these', these_actor_label($data->returntarget)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
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
            $fields['defensedate'] = (int) $data->defensedate;
            $fields['defenselocation'] = trim($data->defenselocation);
            $fields['agreement'] = json_encode(array_merge(these_get_agreement($dossier), [
                'defvdate' => (int) $data->agdefvdate,
                'mobility' => (int) !empty($data->agmobility),
                'a6notyet' => (int) !empty($data->aga6notyet),
                'a6date' => !empty($data->aga6notyet) ? 0 : (int) $data->aga6date,
            ]));
            these_save_jury($dossier->id, these_extract_submitted_jury($data, true), $USER->id);
            break;
    }
    these_validate_step(
        $these,
        $cm,
        $dossier,
        $USER->id,
        $fields,
        $remark,
        $signs ? these_signature_from_form($data) : null
    );
    $message = get_string('stepvalidated', 'mod_these');
    if ($step['key'] === 'devedefense') {
        // Éligibilité validée : les membres du jury sont prévenus et reçoivent l'accès aux documents.
        $notified = these_notify_jury($these, $cm, $DB->get_record('these_dossier', ['id' => $dossier->id]));
        $message .= ' ' . get_string('jurynotified', 'mod_these', $notified->sent)
            . ($notified->missing ? ' ' . get_string('jurynotifiedmissing', 'mod_these', implode(', ', $notified->missing)) : '');
    }
    redirect($dossierurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('review' . $step['key'], 'mod_these'));
echo html_writer::link($dossierurl, get_string('back'));
echo $OUTPUT->box(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-'), 'generalbox mb-3');
$mform->display();
echo these_render_dossier($these, $cm, $context, $dossier, $rights, false);
echo $OUTPUT->footer();
