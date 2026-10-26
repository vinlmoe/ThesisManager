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
 * Export du registre des thèses de l'activité (un dossier par ligne) dans le format choisi :
 * CSV, Excel, ODS... via l'API de formats de données de Moodle.
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
$dataformat = optional_param('dataformat', '', PARAM_ALPHA);

$rights = these_require_view($context);
if (!$rights->viewall) {
    require_capability('mod/these:viewall', $context);
}

$PAGE->set_url('/mod/these/export.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('export', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($dataformat !== '') {
    $columns = [
        'devenumber' => get_string('devenumber', 'mod_these'),
        'student' => get_string('student', 'mod_these'),
        'email' => get_string('email'),
        'title' => get_string('subjecttitle', 'mod_these'),
        'titleen' => get_string('subjecttitleen', 'mod_these'),
        'keywords' => get_string('subjectkeywords', 'mod_these'),
        'director' => get_string('director', 'mod_these'),
        'codirector' => get_string('codirector', 'mod_these'),
        'libraryref' => get_string('libraryref', 'mod_these'),
        'status' => get_string('status', 'mod_these'),
        'statustime' => get_string('since', 'mod_these', ''),
        'defensedate' => get_string('defensedate', 'mod_these'),
        'defenselocation' => get_string('defenselocation', 'mod_these'),
        'jury' => get_string('jury', 'mod_these'),
        'result' => get_string('defenseresult', 'mod_these'),
        'mention' => get_string('mention', 'mod_these'),
        'embargo' => get_string('embargoexport', 'mod_these'),
    ];
    $dossiers = these_get_dossiers($these->id);
    $students = $DB->get_records_list('user', 'id', array_column($dossiers, 'userid'));
    $directors = $DB->get_records_list('user', 'id', array_filter(array_column($dossiers, 'directorid')));

    $rows = new ArrayIterator(array_values($dossiers));
    $filename = clean_filename(format_string($these->name) . '-' . userdate(time(), '%Y%m%d'));
    \core\dataformat::download_data($filename, $dataformat, $columns, $rows, function ($dossier) use (
        $students,
        $directors,
        $context
    ) {
        $director = $directors[$dossier->directorid] ?? null;
        return these_register_row($dossier, $context, $students[$dossier->userid] ?? null, $director);
    });
    die();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('export', 'mod_these'));
echo these_render_navlinks($cm, $context);
echo $PAGE->get_renderer('mod_these')->render(\mod_these\output\message::help(get_string('exporthelp', 'mod_these')));
echo $OUTPUT->download_dataformat_selector(get_string('exportformat', 'mod_these'), 'export.php', 'dataformat', ['id' => $cm->id]);
echo $OUTPUT->footer();
