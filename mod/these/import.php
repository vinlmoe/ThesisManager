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
 * Import CSV de dossiers existants par la DEVE (voir these_import_dossiers()), avec un mode de
 * simulation activé par défaut qui affiche le compte rendu sans rien créer.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

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
if ($mform->is_cancelled()) {
    redirect($dashboardurl);
} else if ($data = $mform->get_data()) {
    $report = these_import_dossiers(
        $these,
        $mform->get_file_content('csvfile'),
        $data->delimiter,
        $data->encoding,
        !empty($data->dryrun),
        $USER->id
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importdossiers', 'mod_these'));
echo these_render_navlinks($cm, $context);

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
    $table = new html_table();
    $table->head = [get_string('importline', 'mod_these'), get_string('student', 'mod_these'), get_string('status', 'mod_these')];
    foreach ($report as $row) {
        $table->data[] = [
            $row['line'],
            s($row['student']),
            html_writer::span($row['message'], 'badge ' . ($row['ok'] ? 'badge-success' : 'badge-danger')),
        ];
    }
    echo html_writer::table($table);
}

$mform->display();
echo $OUTPUT->footer();
