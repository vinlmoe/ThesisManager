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
 * Suivi des thèses, toutes promotions confondues : dossiers à traiter, mes jurys, puis la liste
 * des dossiers suivis (dirigés, ou tous pour la DEVE et la bibliothèque) avec filtres.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$status = optional_param('status', -999, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filtercmid = optional_param('cmid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('thesesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$synthesis = $DB->get_record('thesesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/thesesynthesis:view', $context);

$baseurl = new moodle_url('/mod/thesesynthesis/view.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$links = thesesynthesis_get_active_links($synthesis->id, $USER->id);
$filters = ['status' => $status === -999 ? null : $status, 'search' => $search, 'cmid' => $filtercmid];
$rows = thesesynthesis_get_dossiers($links, $USER->id, $filters);
$alltodo = array_filter(thesesynthesis_get_dossiers($links, $USER->id), function ($row) {
    return $row->todo;
});
$juries = thesesynthesis_get_juries($links, $USER);

// Tableau de dossiers.
$table = function (array $rows) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-dossiers';
    $table->head = [
        get_string('promotion', 'mod_thesesynthesis'),
        get_string('student', 'mod_these'),
        get_string('subjecttitle', 'mod_these'),
        get_string('director', 'mod_these'),
        get_string('status'),
        get_string('waitingfor', 'mod_thesesynthesis'),
        get_string('defensedate', 'mod_these'),
        '',
    ];
    foreach ($rows as $row) {
        $date = (int) ($row->dossier->defensedate ?: $row->dossier->proposeddate);
        $table->data[] = [
            format_string($row->link->course->shortname ?: $row->link->course->fullname),
            s($row->student ? fullname($row->student) : '-'),
            format_string($row->dossier->title),
            s($row->director ? fullname($row->director) : '-'),
            these_render_status_badge($row->dossier->status),
            $row->actor ? these_actor_label($row->actor) : '-',
            $date ? userdate($date, get_string('strftimedatefullshort', 'langconfig')) : '-',
            html_writer::link(
                new moodle_url('/mod/these/dossier.php', ['id' => $row->link->cm->id, 'dossierid' => $row->dossier->id]),
                get_string('open', 'mod_thesesynthesis'),
                ['class' => 'btn btn-sm btn-secondary']
            ),
        ];
    }
    return html_writer::table($table);
};

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($synthesis->name));

echo thesesynthesis_render_navlinks($cm, $context);
if (!$links) {
    echo $OUTPUT->notification(get_string('nolinks', 'mod_thesesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// À traiter.
echo $OUTPUT->heading(get_string('todo', 'mod_these'), 3);
echo $alltodo ? $table($alltodo) : html_writer::div(get_string('nothingtodo', 'mod_these'), 'alert alert-info');

// Mes jurys.
echo $OUTPUT->heading(get_string('myjuries', 'mod_thesesynthesis'), 3, 'mt-4');
if ($juries) {
    $roles = these_jury_role_options();
    $jurytable = new html_table();
    $jurytable->attributes['class'] = 'generaltable';
    $jurytable->head = [
        get_string('promotion', 'mod_thesesynthesis'),
        get_string('student', 'mod_these'),
        get_string('subjecttitle', 'mod_these'),
        get_string('juryrole', 'mod_these'),
        get_string('defensedate', 'mod_these'),
        get_string('defenselocation', 'mod_these'),
        get_string('juryaccessdocuments', 'mod_these'),
    ];
    foreach ($juries as $jury) {
        $date = (int) ($jury->dossier->defensedate ?: $jury->dossier->proposeddate);
        $access = !empty($jury->member->accesstoken)
            ? html_writer::link(
                new moodle_url('/mod/these/jury.php', ['token' => $jury->member->accesstoken]),
                get_string('juryaccess', 'mod_these'),
                ['class' => 'btn btn-sm btn-primary']
            )
            : html_writer::span(get_string('juryaccesspending', 'mod_thesesynthesis'), 'text-muted');
        $jurytable->data[] = [
            format_string($jury->link->course->shortname ?: $jury->link->course->fullname),
            s($jury->student ? fullname($jury->student) : '-'),
            format_string($jury->dossier->title),
            s($roles[$jury->member->juryrole] ?? $jury->member->juryrole),
            $date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-',
            s($jury->dossier->defenselocation),
            $access,
        ];
    }
    echo html_writer::table($jurytable);
} else {
    echo html_writer::div(get_string('nojuries', 'mod_thesesynthesis'), 'alert alert-info');
}

// Dossiers suivis, avec filtres.
echo $OUTPUT->heading(get_string('followed', 'mod_thesesynthesis'), 3, 'mt-4');
$promotions = [0 => get_string('allpromotions', 'mod_thesesynthesis')];
foreach ($links as $link) {
    $promotions[$link->cm->id] = format_string($link->course->fullname) . ' : ' . format_string($link->these->name);
}
$statuses = [-999 => get_string('allstatuses', 'mod_thesesynthesis')];
foreach (these_all_statuses() as $value) {
    $statuses[$value] = these_status_label($value);
}
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => $baseurl->out_omit_querystring(),
    'class' => 'form-inline mb-3',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::select($promotions, 'cmid', $filtercmid, false, ['class' => 'custom-select mr-2 mb-1']);
echo html_writer::select($statuses, 'status', $status, false, ['class' => 'custom-select mr-2 mb-1']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'value' => $search, 'class' => 'form-control mr-2 mb-1',
    'placeholder' => get_string('search')]);
echo html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-secondary mb-1']);
echo html_writer::end_tag('form');
echo $rows ? $table($rows) : html_writer::div(get_string('nodossiers', 'mod_thesesynthesis'), 'alert alert-info');

echo $OUTPUT->footer();
