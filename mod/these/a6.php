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
 * Validation de l'A6, page à part de la DEVE (voir \mod_these\local\a6) : les étudiants de
 * l'activité avec leur validation, la saisie d'un étudiant, l'enregistrement groupé pour les
 * étudiants cochés et le lien vers l'import d'un fichier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\a6;
use mod_these\output;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$state = optional_param('state', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');

$listurl = a6::url($cm);
$PAGE->set_url($userid ? a6::url($cm, $userid) : $listurl);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('a6validation', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$renderer = $PAGE->get_renderer('mod_these');

// Saisie de la validation d'un étudiant.
if ($userid) {
    $students = array_filter(a6::students($these, $context), function ($row) use ($userid) {
        return (int) $row->user->id === $userid;
    });
    $student = reset($students);
    if (!$student) {
        throw new moodle_exception('invaliduser');
    }
    $backurl = $returnurl !== '' ? new moodle_url($returnurl) : $listurl;
    $mform = new \mod_these\form\a6_form($PAGE->url, ['studentname' => fullname($student->user)]);
    $mform->set_data([
        'id' => $cm->id,
        'userid' => $userid,
        'returnurl' => $returnurl,
        'aga6notyet' => (int) ($student->a6['notyet'] || !$student->a6['date']),
        'aga6date' => $student->a6['date'],
        'aga6school' => $student->a6['school'],
    ]);
    if ($mform->is_cancelled()) {
        redirect($backurl);
    } else if ($data = $mform->get_data()) {
        $date = !empty($data->aga6notyet) ? 0 : (int) $data->aga6date;
        a6::record($these, $userid, $date, (string) ($data->aga6school ?? ''), $USER->id);
        redirect($backurl, get_string('a6saved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('a6validation', 'mod_these'));
    // Retour à la page d'où la saisie a été ouverte (dossier, validation...), à défaut à la liste.
    echo \mod_these\local\backlinks::render(\mod_these\local\backlinks::target(
        $backurl,
        get_string($returnurl !== '' ? 'previouspage' : 'a6validation', 'mod_these')
    ));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

// Enregistrement groupé pour les étudiants cochés.
$userids = optional_param_array('userids', [], PARAM_INT);
if ($userids && confirm_sesskey()) {
    $notyet = optional_param('a6notyet', 0, PARAM_BOOL);
    $rawdate = optional_param('a6date', '', PARAM_RAW_TRIMMED);
    $date = $notyet ? 0 : a6::parse_date($rawdate);
    if (!$notyet && ($rawdate === '' || $date === null)) {
        redirect($listurl, get_string('a6bulkdaterequired', 'mod_these'), null, \core\output\notification::NOTIFY_WARNING);
    }
    $school = optional_param('a6school', '', PARAM_ALPHA);
    $school = array_key_exists($school, these_school_options()) ? $school : '';
    $allowed = array_map(function ($row) {
        return (int) $row->user->id;
    }, a6::students($these, $context));
    $count = 0;
    foreach (array_unique($userids) as $studentid) {
        if (in_array((int) $studentid, $allowed, true)) {
            a6::record($these, $studentid, $date, $school, $USER->id);
            $count++;
        }
    }
    redirect($listurl, get_string('a6bulkdone', 'mod_these', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('a6validation', 'mod_these'));
echo these_render_navlinks($cm, $context);
echo $renderer->render(output\message::help(get_string('a6page_desc', 'mod_these')));
echo $renderer->render(new output\a6_list($cm, a6::students($these, $context, $state, $search), $state, $search));
echo $OUTPUT->footer();
