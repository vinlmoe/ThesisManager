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
 * Tableau de bord du personnel : la file « À traiter » de l'utilisateur (dossiers qui attendent
 * son action, selon qu'il est directeur, DEVE ou bibliothèque), le décompte des dossiers par
 * statut et la liste filtrable des dossiers qu'il peut consulter (tous pour la DEVE et la
 * bibliothèque, les siens pour un directeur).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$id = required_param('id', PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_RAW_TRIMMED);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
$rights = these_require_view($context);
$isdirector = has_capability('mod/these:direct', $context);
if (!$rights->viewall && !$isdirector) {
    redirect(new moodle_url('/mod/these/view.php', ['id' => $cm->id]));
}

$baseparams = ['id' => $cm->id];
if ($statusfilter !== '') {
    $baseparams['status'] = (int) $statusfilter;
}
if ($search !== '') {
    $baseparams['search'] = $search;
}
$PAGE->set_url('/mod/these/dashboard.php', $baseparams);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('dashboard', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($these->name));
echo these_render_navlinks($cm, $context);

// File « À traiter » : les étapes qui attendent l'utilisateur, tous rôles confondus.
$queue = [];
if ($isdirector) {
    $queue[] = 'director';
}
if ($rights->deve) {
    $queue[] = 'deve';
}
if ($rights->library) {
    $queue[] = 'library';
}
echo $OUTPUT->heading(get_string('todo', 'mod_these'), 3);
if ($queue) {
    $todo = these_get_dossiers($these->id, ['queue' => $queue, 'queuedirectorid' => $USER->id]);
    echo these_render_dossier_table($todo, $cm);
} else {
    echo html_writer::div(get_string('nothingtodo', 'mod_these'), 'alert alert-info');
}

// Décompte par statut, cliquable pour filtrer la liste ci-dessous.
$restrictdirector = $rights->viewall ? null : (int) $USER->id;
$counts = these_count_by_status($these->id, $restrictdirector);
echo $OUTPUT->heading($rights->viewall ? get_string('alldossiers', 'mod_these') : get_string('mydirecteddossiers', 'mod_these'), 3);
$badges = [];
foreach (these_all_statuses() as $status) {
    if (empty($counts[$status])) {
        continue;
    }
    $label = these_status_label($status) . ' : ' . $counts[$status];
    $badges[] = html_writer::link(
        new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id, 'status' => $status]),
        $label,
        ['class' => 'badge ' . these_status_badgeclass($status) . ' mr-1 mb-1 p-2']
    );
}
if ($badges) {
    echo html_writer::div(implode(' ', $badges), 'these-counts mb-3');
}

// Filtres.
$statusoptions = ['' => get_string('allstatuses', 'mod_these')];
foreach (these_all_statuses() as $status) {
    $statusoptions[$status] = these_status_label($status);
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
    'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::label(get_string('status', 'mod_these'), 'these-status', true, ['class' => 'mr-1']);
echo html_writer::select($statusoptions, 'status', $statusfilter, false, ['id' => 'these-status', 'class' => 'mr-2']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search', 'value' => $search,
    'placeholder' => get_string('searchdossiers', 'mod_these'), 'class' => 'form-control mr-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'), 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

// La DEVE connaît le sujet de toutes les thèses, un directeur (sans droit de tout voir) celui des
// siennes : eux seuls cherchent aussi dans le titre des thèses confidentielles.
$filters = ['search' => $search, 'confidentialsubjects' => $rights->deve || !$rights->viewall];
if ($statusfilter !== '') {
    $filters['status'] = (int) $statusfilter;
}
if (!$rights->viewall) {
    $filters['directorid'] = $USER->id;
}
$dossiers = these_get_dossiers($these->id, $filters);
[$pageitems, $pagingbar] = these_paginate($dossiers, $page, $PAGE->url);
echo these_render_dossier_table($pageitems, $cm);
echo $pagingbar;

echo $OUTPUT->footer();
