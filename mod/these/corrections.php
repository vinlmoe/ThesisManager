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
 * Dépôt du manuscrit corrigé par l'étudiant, quand le jury a demandé des corrections : le dossier
 * passe ensuite au directeur, à la bibliothèque puis à la DEVE, qui le clôt.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\corrections_form;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/these:submit', $context);

$dossier = $DB->get_record(
    'these_dossier',
    ['id' => $dossierid, 'theseid' => $these->id, 'userid' => $USER->id],
    '*',
    MUST_EXIST
);
$viewurl = new moodle_url('/mod/these/view.php', ['id' => $cm->id]);
if ((int) $dossier->status !== THESE_STATUS_CORRECTIONS) {
    redirect($viewurl, get_string('correctionsnotexpected', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$params = ['id' => $cm->id, 'dossierid' => $dossier->id];
$PAGE->set_url('/mod/these/corrections.php', $params);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('depositcorrections', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$mform = new corrections_form($PAGE->url);
$draftitemid = file_get_submitted_draft_itemid('corrections');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_these',
    'corrections',
    $dossier->id,
    these_filemanager_options('corrections')
);
$mform->set_data($params + ['corrections' => $draftitemid, 'correctionsdone' => $dossier->correctionsdone]);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files(
        $data->corrections,
        $context->id,
        'mod_these',
        'corrections',
        $dossier->id,
        these_filemanager_options('corrections')
    );
    these_submit_corrections($these, $cm, $dossier, $USER->id, $data->correctionsdone);
    redirect($viewurl, get_string('correctionssubmittedmsg', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('depositcorrections', 'mod_these'));
if (trim((string) $dossier->returncomment) !== '') {
    echo html_writer::div(html_writer::tag('strong', get_string('returncomment', 'mod_these')) . html_writer::empty_tag('br')
        . format_text($dossier->returncomment, FORMAT_PLAIN), 'alert alert-warning');
}
echo $OUTPUT->box(html_writer::tag('strong', get_string('correctionsnote', 'mod_these')) . html_writer::empty_tag('br')
    . format_text((string) $dossier->correctionsnote, FORMAT_PLAIN), 'generalbox mb-3');
$mform->display();
echo $OUTPUT->footer();
