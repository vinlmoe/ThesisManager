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
 * Suivi des thèses, toutes promotions confondues : dossiers à traiter (avec l'action attendue),
 * signatures à apposer et, pour la DEVE, signatures attendues, mes jurys, puis la liste
 * des dossiers suivis (dirigés, ou tous pour la DEVE et la bibliothèque) avec filtres.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

use mod_thesesynthesis\output;

$id = required_param('id', PARAM_INT);
$status = optional_param('status', -999, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$filtercmid = optional_param('cmid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$todopage = optional_param('todopage', 0, PARAM_INT);

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
// Listes paginées en base (dossiers à traiter, dossiers suivis) : une page hors limites ramène à la
// dernière.
$perpage = \mod_thesesynthesis\local\dossiers::PERPAGE;
$todocount = thesesynthesis_count_dossiers($links, $USER->id, ['todo' => true]);
$todopage = max(0, min($todopage, (int) ceil($todocount / $perpage) - 1));
$count = thesesynthesis_count_dossiers($links, $USER->id, $filters);
$page = max(0, min($page, (int) ceil($count / $perpage) - 1));
// Filtres et pages, repris dans les liens de pagination et dans la page d'origine des dossiers ouverts
// d'ici (voir \mod_these\local\backlinks).
$originparams = [];
if ($status !== -999) {
    $originparams['status'] = $status;
}
if ($search !== '') {
    $originparams['search'] = $search;
}
if ($filtercmid) {
    $originparams['cmid'] = $filtercmid;
}
if ($todopage) {
    $originparams['todopage'] = $todopage;
}
if ($page) {
    $originparams['page'] = $page;
}
$pageurl = new moodle_url($baseurl, $originparams);
\mod_these\local\backlinks::remember(
    $pageurl,
    get_string('modulename', 'mod_thesesynthesis'),
    array_map(function ($link) {
        return (int) $link->these->id;
    }, $links)
);
$rows = thesesynthesis_get_dossiers($links, $USER->id, $filters, $page * $perpage, $perpage);
$todo = thesesynthesis_get_dossiers($links, $USER->id, ['todo' => true], $todopage * $perpage, $perpage);
$juries = thesesynthesis_get_juries($links, $USER);

$renderer = $PAGE->get_renderer('mod_thesesynthesis');
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($synthesis->name));

echo thesesynthesis_render_navlinks($cm, $context, \mod_these\local\backlinks::course($course->id));
if (!$links) {
    echo $OUTPUT->notification(get_string('nolinks', 'mod_thesesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// À traiter.
echo $OUTPUT->heading(get_string('todo', 'mod_these'), 3, null, 'todo');
echo $renderer->render(new output\dossier_table($todo, get_string('nothingtodo', 'mod_these'), true));
echo $OUTPUT->paging_bar($todocount, $todopage, $perpage, new moodle_url($pageurl, null, 'todo'), 'todopage');

// Signatures, centralisées : celles que l'utilisateur appose, puis, pour la DEVE, celles attendues
// d'autres signataires.
$signatures = thesesynthesis_get_signatures($links, $USER);
echo $OUTPUT->heading(get_string('mysignaturestodo', 'mod_thesesynthesis'), 3, 'mt-4');
echo $renderer->render(new output\signature_table($signatures['mine'], get_string('nosignaturestodo', 'mod_thesesynthesis')));
$deve = array_filter($links, function ($link) {
    return !empty($link->rights->deve);
});
if ($deve) {
    echo $OUTPUT->heading(get_string('awaitedsignatures', 'mod_thesesynthesis'), 3, 'mt-4');
    $empty = get_string('noawaitedsignatures', 'mod_thesesynthesis');
    echo $renderer->render(new output\signature_table($signatures['awaited'], $empty));
}

// Mes jurys.
echo $OUTPUT->heading(get_string('myjuries', 'mod_thesesynthesis'), 3, 'mt-4');
echo $renderer->render(new output\jury_table($juries));

// Dossiers suivis, avec filtres.
echo $OUTPUT->heading(get_string('followed', 'mod_thesesynthesis'), 3, 'mt-4', 'followed');
echo $renderer->render(new output\dossier_filters($baseurl, $cm->id, $links, $filtercmid, $status, $search));
echo $renderer->render(new output\dossier_table($rows, get_string('nodossiers', 'mod_thesesynthesis')));
echo $OUTPUT->paging_bar($count, $page, $perpage, new moodle_url($pageurl, null, 'followed'), 'page');

echo $OUTPUT->footer();
