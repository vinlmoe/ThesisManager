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
 * Import des validations de l'A6 par la DEVE : fichier Excel, OpenDocument ou CSV (voir
 * \mod_these\local\a6::import()).
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

$PAGE->set_url('/mod/these/a6import.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('a6import', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$dashboardurl = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
$mform = new \mod_these\form\a6import_form($PAGE->url);
$mform->set_data(['id' => $cm->id]);

$report = null;
$error = null;
if ($mform->is_cancelled()) {
    redirect($dashboardurl);
} else if ($data = $mform->get_data()) {
    $path = $mform->save_temp_file('a6file');
    try {
        $rows = \mod_these\local\a6::read_rows($path, (string) $mform->get_new_filename('a6file'));
        $report = \mod_these\local\a6::import($these, $rows, !empty($data->dryrun), $USER->id);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    } catch (\Throwable $e) {
        $error = get_string('a6importunreadable', 'mod_these');
    }
    @unlink($path);
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('a6import', 'mod_these'));
echo these_render_navlinks($cm, $context);
echo $renderer->render(output\message::help(get_string('a6import_desc', 'mod_these')));

if ($error !== null) {
    echo $OUTPUT->notification($error, 'error');
}
if ($report !== null) {
    $ok = count(array_filter($report, function ($row) {
        return $row['ok'];
    }));
    echo $OUTPUT->notification(
        get_string(!empty($data->dryrun) ? 'a6dryrunresult' : 'a6result', 'mod_these', (object) [
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
    echo $renderer->render(new output\table($head, $rows));
}

$mform->display();
echo $OUTPUT->footer();
