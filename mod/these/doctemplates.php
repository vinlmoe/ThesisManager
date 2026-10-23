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
 * Modèles des documents de l'activité (DEVE) : liste des documents et images des modèles,
 * modification du modèle de chacun, aperçu PDF avec des données fictives, retour au modèle
 * d'origine. Comme les courriels, chaque activité a ses propres modèles.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\doctemplate_form;
use mod_these\form\doctemplate_images_form;

$id = required_param('id', PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');

$types = these_doctemplate_types();
if ($type !== '' && !array_key_exists($type, $types)) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$listurl = new moodle_url('/mod/these/doctemplates.php', ['id' => $cm->id]);
$PAGE->set_url($type !== '' ? new moodle_url($listurl, ['type' => $type]) : $listurl);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('doctemplates', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Aperçu du modèle en vigueur.
if ($type !== '' && $action === 'preview') {
    send_file(
        these_render_doctemplate($these, $type, these_sample_document_context($context, $these)),
        $type . '.pdf',
        0,
        0,
        true,
        false,
        'application/pdf'
    );
}

// Retour au modèle d'origine.
if ($type !== '' && $action === 'reset') {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        these_set_doctemplate($these->id, $type, null);
        redirect(
            $listurl,
            get_string('doctplresetdone', 'mod_these', these_doctemplate_label($type)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('doctplresetconfirm', 'mod_these', these_doctemplate_label($type)),
        new moodle_url($listurl, ['type' => $type, 'action' => 'reset', 'confirm' => 1, 'sesskey' => sesskey()]),
        $listurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Modification d'un modèle.
if ($type !== '') {
    $mform = new doctemplate_form($PAGE->url, ['these' => $these, 'context' => $context]);
    if ($mform->is_cancelled()) {
        redirect($listurl);
    }
    $mform->set_data(['id' => $cm->id, 'type' => $type, 'content' => these_get_doctemplate($these->id, $type)]);
    if ($data = $mform->get_data()) {
        if (!empty($data->previewbutton)) {
            // Aperçu du modèle en cours de modification, sans l'enregistrer.
            send_file(
                these_render_doctemplate($these, $type, these_sample_document_context($context, $these), $data->content),
                $type . '.pdf',
                0,
                0,
                true,
                false,
                'application/pdf'
            );
        }
        these_set_doctemplate($these->id, $type, $data->content);
        redirect(
            $listurl,
            get_string('doctplsaved', 'mod_these', these_doctemplate_label($type)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('doctemplates', 'mod_these') . ' : ' . these_doctemplate_label($type));
    echo these_render_navlinks($cm, $context);
    echo html_writer::link($listurl, get_string('back'));
    echo html_writer::div(get_string('doctplintro', 'mod_these'), 'my-3');
    $mform->display();

    // Variables disponibles, avec un exemple de valeur.
    $table = new html_table();
    $table->head = [get_string('doctplvariable', 'mod_these'), get_string('doctplexample', 'mod_these')];
    $table->attributes['class'] = 'generaltable table-sm';
    foreach (these_doctemplate_variables(these_sample_document_context($context, $these)) as $name => $example) {
        $table->data[] = [html_writer::tag('code', '{{' . s($name) . '}}'), s($example)];
    }
    echo $OUTPUT->heading(get_string('doctplvariables', 'mod_these'), 3, 'mt-4');
    echo html_writer::div(get_string('doctplvariables_desc', 'mod_these'), 'mb-2');
    echo html_writer::table($table);
    echo $OUTPUT->footer();
    exit;
}

// Images des modèles.
$imageoptions = ['subdirs' => 0, 'maxfiles' => 20, 'accepted_types' => ['.png', '.jpg', '.jpeg']];
$imagesform = new doctemplate_images_form($listurl, ['options' => $imageoptions]);
$draftitemid = file_get_submitted_draft_itemid('docimages');
file_prepare_draft_area($draftitemid, $context->id, 'mod_these', 'docimages', 0, $imageoptions);
$imagesform->set_data(['id' => $cm->id, 'docimages' => $draftitemid]);
if ($data = $imagesform->get_data()) {
    file_save_draft_area_files($data->docimages, $context->id, 'mod_these', 'docimages', 0, $imageoptions);
    redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Liste des modèles.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('doctemplates', 'mod_these'));
echo these_render_navlinks($cm, $context);
echo html_writer::div(get_string('doctemplates_desc', 'mod_these'), 'mb-3');
$table = new html_table();
$table->head = [get_string('doctpldocument', 'mod_these'), get_string('status'), get_string('actions')];
$table->attributes['class'] = 'generaltable';
foreach (array_keys($types) as $key) {
    $customised = these_doctemplate_customised($these->id, $key);
    $actions = [
        html_writer::link(
            new moodle_url($listurl, ['type' => $key]),
            get_string('edit'),
            ['class' => 'btn btn-sm btn-primary']
        ),
        html_writer::link(
            new moodle_url($listurl, ['type' => $key, 'action' => 'preview']),
            get_string('doctplpreview', 'mod_these'),
            ['class' => 'btn btn-sm btn-secondary', 'target' => '_blank']
        ),
    ];
    if ($customised) {
        $actions[] = html_writer::link(
            new moodle_url($listurl, ['type' => $key, 'action' => 'reset']),
            get_string('doctplreset', 'mod_these'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
    }
    $table->data[] = [
        these_doctemplate_label($key),
        $customised ? html_writer::span(get_string('doctplcustomised', 'mod_these'), 'badge badge-warning bg-warning text-dark')
            : html_writer::span(get_string('doctploriginal', 'mod_these'), 'badge badge-secondary bg-secondary'),
        implode(' ', $actions),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->heading(get_string('doctplimages', 'mod_these'), 3, 'mt-4');
$imagesform->display();
echo $OUTPUT->footer();
