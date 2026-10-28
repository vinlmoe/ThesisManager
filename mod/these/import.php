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
 * Import (CSV, Excel ou OpenDocument) de dossiers existants par la DEVE (voir these_import_dossiers()), avec un mode de
 * simulation activé par défaut qui affiche le compte rendu sans rien créer.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\output;
use mod_these\output\parts;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');

$PAGE->set_url('/mod/these/import.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('importdossiers', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$dashboardurl = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
$mform = new \mod_these\form\import_form($PAGE->url);
$mform->set_data(['id' => $cm->id]);

$report = null;
$error = null;
if ($mform->is_cancelled()) {
    redirect($dashboardurl);
} else if ($data = $mform->get_data()) {
    // Excel et OpenDocument lus comme l'import de l'A6 ; CSV avec le séparateur et l'encodage choisis.
    $filename = (string) $mform->get_new_filename('csvfile');
    try {
        if (in_array(core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['xlsx', 'xls', 'ods'], true)) {
            $path = $mform->save_temp_file('csvfile');
            try {
                $rows = \mod_these\local\a6::read_rows($path, $filename);
            } finally {
                @unlink($path);
            }
            $report = \mod_these\local\import::import_rows($these, $rows, !empty($data->dryrun), $USER->id);
        } else {
            $report = these_import_dossiers(
                $these,
                $mform->get_file_content('csvfile'),
                $data->delimiter,
                $data->encoding,
                !empty($data->dryrun),
                $USER->id
            );
        }
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        $error = get_string('a6importunreadable', 'mod_these');
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importdossiers', 'mod_these'));
echo these_render_navlinks($cm, $context);

if ($error !== null) {
    echo $OUTPUT->notification($error, 'error');
}
if ($report !== null) {
    $ok = count(array_filter($report, function ($row) {
        return $row['ok'];
    }));
    echo $OUTPUT->notification(
        get_string(!empty($data->dryrun) ? 'importdryrunresult' : 'importresult', 'mod_these', (object) [
            'ok' => $ok,
            'errors' => count($report) - $ok,
        ]),
        $ok === count($report) ? 'success' : 'warning'
    );
    $rows = [];
    foreach ($report as $row) {
        $rows[] = [
            [parts::text((string) $row['line'])],
            [parts::text($row['student'])],
            [parts::span($row['message'], 'badge ' . ($row['ok'] ? 'badge-success' : 'badge-danger'))],
        ];
    }
    $head = [get_string('importline', 'mod_these'), get_string('student', 'mod_these'), get_string('status', 'mod_these')];
    echo $PAGE->get_renderer('mod_these')->render(new output\table($head, $rows));
}

// Validations de l'A6 : import séparé (Excel, OpenDocument ou CSV).
echo $PAGE->get_renderer('mod_these')->render(new output\link(
    new moodle_url('/mod/these/a6import.php', ['id' => $cm->id]),
    get_string('a6import', 'mod_these')
));
$mform->display();
echo $OUTPUT->footer();
