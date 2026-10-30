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

use mod_these\local\page;
use mod_these\output;

$id = required_param('id', PARAM_INT);
$statusfilter = optional_param('status', '', PARAM_RAW_TRIMMED);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
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
// Page d'origine des dossiers ouverts d'ici (voir \mod_these\local\backlinks).
\mod_these\local\backlinks::remember($PAGE->url, get_string('dashboard', 'mod_these'), [$these->id]);

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard', 'mod_these'));
echo these_render_navlinks($cm, $context, \mod_these\local\backlinks::course($course->id));
// Réglages du circuit encore à compléter : rappel à la DEVE, avec le lien vers les paramètres.
if ($rights->deve) {
    $scope = these_settings_scope($these);
    $missing = count(array_filter(\mod_these\local\scopesettings::checklist($scope), function ($item) {
        return !$item['complete'] && !$item['optional'];
    }));
    if ($missing) {
        $settingsurl = $scope->shared
            ? new moodle_url('/mod/thesesynthesis/parameters.php', ['id' => $scope->cmid])
            : new moodle_url('/mod/these/parameters.php', ['id' => $cm->id]);
        echo $renderer->render(new output\message([
            output\parts::text(get_string('settingsmissingnotice', 'mod_these', $missing) . ' '),
            output\parts::link($settingsurl, get_string('scopesettings', 'mod_these'), ''),
        ], 'alert alert-warning'));
    }
}

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
    echo these_render_dossier_table($todo, $cm, ['todo' => true]);
} else {
    echo $renderer->render(new output\message(get_string('nothingtodo', 'mod_these')));
}

// Décompte par statut, cliquable pour filtrer la liste ci-dessous.
$restrictdirector = $rights->viewall ? null : (int) $USER->id;
$counts = these_count_by_status($these->id, $restrictdirector);
echo $OUTPUT->heading($rights->viewall ? get_string('alldossiers', 'mod_these') : get_string('mydirecteddossiers', 'mod_these'), 3);
echo $renderer->render(new output\status_counts($cm, $counts));

// Filtres.
echo $renderer->render(new output\dossier_filter($PAGE->url, $cm->id, $statusfilter, $search));

// La DEVE et la bibliothèque connaissent le sujet de toutes les thèses, un directeur (sans droit de
// tout voir) celui des siennes : eux seuls cherchent aussi dans le titre des thèses confidentielles.
$filters = ['search' => $search, 'confidentialsubjects' => $rights->deve || $rights->library || !$rights->viewall];
if ($statusfilter !== '') {
    $filters['status'] = (int) $statusfilter;
}
if (!$rights->viewall) {
    $filters['directorid'] = $USER->id;
}
$dossiers = these_get_dossiers($these->id, $filters);
[$pageitems, $pagingbar] = these_paginate($dossiers, $page, $PAGE->url);
if ($rights->deve && $pageitems) {
    // Actions groupées de la DEVE sur les dossiers cochés.
    echo $renderer->render(new output\bulk_form($pageitems, $cm));
} else {
    echo these_render_dossier_table($pageitems, $cm);
}
echo $pagingbar;

echo $OUTPUT->footer();
