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
 * Rédaction du rapport du directeur sur le manuscrit (phase 2, étape 2), avant la validation de la
 * soutenance : le texte, enregistrable en plusieurs fois, est imprimé dans le rapport PDF que le
 * directeur signe en validant la soutenance.
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

use mod_these\form\report_form;

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
$dossierurl = new moodle_url('/mod/these/dossier.php', $params);
if (!$rights->director) {
    throw new moodle_exception('notyourstep', 'mod_these', $dossierurl);
}
if (!these_report_editable($dossier)) {
    redirect($dossierurl, get_string('reportnoteditable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/report.php', $params);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('writereport', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new report_form($PAGE->url, ['these' => $these]);
$draftitemid = file_get_submitted_draft_itemid('directorreport');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_these',
    'directorreport',
    $dossier->id,
    these_filemanager_options('directorreport')
);
$mform->set_data($params + [
    'reporttext' => $dossier->reporttext,
    'reportfavorable' => $dossier->reportfavorable,
    'directorreport' => $draftitemid,
]);

if ($mform->is_cancelled()) {
    redirect($dossierurl);
} else if ($data = $mform->get_data()) {
    if (!empty($data->previewbutton)) {
        // Aperçu du rapport tel qu'il sera imprimé, sans l'enregistrer.
        $preview = clone $dossier;
        $preview->reporttext = trim((string) $data->reporttext);
        $preview->reportfavorable = (int) !empty($data->reportfavorable);
        send_file(
            these_signed_document_pdf($these, $context, $preview, 'report'),
            'rapport.pdf',
            0,
            0,
            true,
            false,
            'application/pdf'
        );
    }
    file_save_draft_area_files(
        $data->directorreport,
        $context->id,
        'mod_these',
        'directorreport',
        $dossier->id,
        these_filemanager_options('directorreport')
    );
    these_save_report($dossier, $USER->id, (string) $data->reporttext, !empty($data->reportfavorable));
    redirect($dossierurl, get_string('reportsaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);
$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('writereport', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
echo $OUTPUT->box($renderer->render(new output\fragment([
    parts::text(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-')),
    parts::br(),
    parts::html(format_string($dossier->title)),
])), 'generalbox my-3');
echo $renderer->render(new output\message(get_string('writereport_desc', 'mod_these'), 'mb-3'));
$mform->display();
echo $OUTPUT->footer();
