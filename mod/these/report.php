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
 * Dépôt du rapport du directeur sur le manuscrit (phase 2, étape 2), avant la validation de la
 * soutenance : un fichier, rédigé à partir du modèle déposé dans les paramètres du circuit, et l'avis
 * favorable du directeur.
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

use mod_these\form\report_form;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
['dossier' => $dossier, 'rights' => $rights] = (array) page::load_dossier($activity, $dossierid, $USER->id);
// Dossier réduit à son procès-verbal (données de l'étudiant effacées) : plus aucune action.
\mod_these\local\dossiers::require_in_circuit($dossier);
$params = ['id' => $cm->id, 'dossierid' => $dossier->id];
$dossierurl = new moodle_url('/mod/these/dossier.php', $params);
if (!$rights->director) {
    throw new moodle_exception('notyourstep', 'mod_these', $dossierurl);
}
if (!these_report_editable($dossier)) {
    redirect($dossierurl, get_string('reportnoteditable', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url('/mod/these/report.php', $params);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('depositreport', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$template = \mod_these\local\scopesettings::report_template(these_settings_scope($these));
$mform = new report_form($PAGE->url, [
    'templateurl' => $template ? new moodle_url('/mod/these/reporttemplate.php', $params) : null,
]);
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
    'reportfavorable' => $dossier->reportfavorable,
    'directorreport' => $draftitemid,
]);

if ($mform->is_cancelled()) {
    redirect(backlinks::to_dossier($cm, $dossier)->url);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files(
        $data->directorreport,
        $context->id,
        'mod_these',
        'directorreport',
        $dossier->id,
        these_filemanager_options('directorreport')
    );
    these_save_report($dossier, $USER->id, !empty($data->reportfavorable));
    redirect(
        backlinks::after_action($cm, $dossierurl),
        get_string('reportsaved', 'mod_these'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);
$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('depositreport', 'mod_these'));
echo backlinks::render(backlinks::to_dossier($cm, $dossier));
echo $OUTPUT->box($renderer->render(new output\fragment([
    parts::text(get_string('dossierof', 'mod_these', $student ? fullname($student) : '-')),
    parts::br(),
    parts::html(format_string($dossier->title)),
])), 'generalbox my-3');
echo $renderer->render(new output\message(get_string('depositreport_desc', 'mod_these'), 'mb-3'));
$mform->display();
echo $OUTPUT->footer();
