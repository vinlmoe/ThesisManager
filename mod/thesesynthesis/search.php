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
 * Recherche de thèses dans les activités « Gestion des thèses » suivies : texte libre (titre,
 * résumé, mots-clés, étudiant, directeur, jury, réponses au questionnaire), promotion, année de
 * soutenance, statut et réponses au questionnaire préalable au jury.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

use mod_thesesynthesis\output;

$id = required_param('id', PARAM_INT);
$text = optional_param('q', '', PARAM_TEXT);
$filtercmid = optional_param('cmid', 0, PARAM_INT);
$status = optional_param('status', -999, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('thesesynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$synthesis = $DB->get_record('thesesynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/thesesynthesis:search', $context);

$baseurl = new moodle_url('/mod/thesesynthesis/search.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($synthesis->name) . ' - ' . get_string('searchtheses', 'mod_thesesynthesis'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$links = thesesynthesis_get_active_links($synthesis->id, $USER->id);
// Page d'origine des dossiers ouverts depuis les résultats, critères compris (voir \mod_these\local\backlinks).
\mod_these\local\backlinks::remember(
    new moodle_url(qualified_me()),
    get_string('searchtheses', 'mod_thesesynthesis'),
    array_map(function ($link) {
        return (int) $link->these->id;
    }, $links)
);
$scope = \mod_thesesynthesis\local\scope::for_synthesis($synthesis->id);
$questions = $scope ? these_get_questions($scope) : [];
$answerfilters = [];
foreach ($questions as $question) {
    $value = optional_param('a' . $question->id, '', PARAM_TEXT);
    if (trim($value) !== '') {
        $answerfilters[(int) $question->id] = $value;
    }
}
$searched = $text !== '' || $filtercmid || $status !== -999 || $year || $answerfilters
    || optional_param('search', 0, PARAM_BOOL);

$renderer = $PAGE->get_renderer('mod_thesesynthesis');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('searchtheses', 'mod_thesesynthesis'));
echo thesesynthesis_render_navlinks($cm, $context);
echo $renderer->render(new \mod_these\output\message(get_string('searchtheses_desc', 'mod_thesesynthesis'), 'mb-3'));
if (!$links) {
    echo $OUTPUT->notification(get_string('nolinks', 'mod_thesesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Formulaire de recherche.
echo $renderer->render(new output\search_form($baseurl, $cm->id, $links, $questions, [
    'q' => $text,
    'cmid' => $filtercmid,
    'year' => $year,
    'status' => $status,
    'answers' => $answerfilters,
]));

if (!$searched) {
    echo $OUTPUT->footer();
    exit;
}

$filters = [
    'text' => $text,
    'cmid' => $filtercmid,
    'status' => $status === -999 ? null : $status,
    'year' => $year,
    'answers' => $answerfilters,
];
// Résultats paginés en base ; une page hors limites ramène à la dernière. Les critères sont repris dans
// les liens de pagination.
$perpage = \mod_thesesynthesis\local\dossiers::PERPAGE;
$count = thesesynthesis_count_search($links, $USER->id, $filters, $questions);
$page = max(0, min($page, (int) ceil($count / $perpage) - 1));
$rows = thesesynthesis_search($links, $USER->id, $filters, $questions, $page * $perpage, $perpage);
$pageurl = new moodle_url($baseurl, ['search' => 1]);
foreach (['q' => $text, 'cmid' => $filtercmid, 'year' => $year] as $name => $value) {
    if ($value) {
        $pageurl->param($name, $value);
    }
}
if ($status !== -999) {
    $pageurl->param('status', $status);
}
foreach ($answerfilters as $questionid => $value) {
    $pageurl->param('a' . $questionid, $value);
}

echo $OUTPUT->heading(get_string('searchresults', 'mod_thesesynthesis', $count), 3, 'mt-4');
echo thesesynthesis_render_search_results($rows, $questions);
echo $OUTPUT->paging_bar($count, $page, $perpage, $pageurl);
echo $OUTPUT->footer();
