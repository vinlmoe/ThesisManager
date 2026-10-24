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

$id = required_param('id', PARAM_INT);
$text = optional_param('q', '', PARAM_TEXT);
$filtercmid = optional_param('cmid', 0, PARAM_INT);
$status = optional_param('status', -999, PARAM_INT);
$year = optional_param('year', 0, PARAM_INT);

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
$scope = these_synthesis_scope($synthesis->id);
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('searchtheses', 'mod_thesesynthesis'));
echo thesesynthesis_render_navlinks($cm, $context);
echo html_writer::div(get_string('searchtheses_desc', 'mod_thesesynthesis'), 'mb-3');
if (!$links) {
    echo $OUTPUT->notification(get_string('nolinks', 'mod_thesesynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Formulaire de recherche.
$promotions = [0 => get_string('allpromotions', 'mod_thesesynthesis')];
foreach ($links as $link) {
    $promotions[$link->cm->id] = format_string($link->course->fullname) . ' : ' . format_string($link->these->name);
}
$statuses = [-999 => get_string('allstatuses', 'mod_thesesynthesis')];
foreach (these_all_statuses() as $value) {
    $statuses[$value] = these_status_label($value);
}
$years = [0 => get_string('allyears', 'mod_thesesynthesis')];
for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 15; $y--) {
    $years[$y] = $y;
}
$field = function ($label, $control) {
    return html_writer::div(
        html_writer::tag('label', $label, ['class' => 'mr-2 me-2 mb-1']) . $control,
        'form-inline d-flex align-items-center mb-2'
    );
};
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(), 'class' => 'mb-3',
    'id' => 'thesesynthesis-search']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'search', 'value' => 1]);
echo $field(get_string('searchtext', 'mod_thesesynthesis'), html_writer::empty_tag('input', ['type' => 'text', 'name' => 'q',
    'value' => $text, 'size' => 50, 'class' => 'form-control', 'id' => 'search-q']));
echo $field(get_string('promotion', 'mod_thesesynthesis'), html_writer::select(
    $promotions,
    'cmid',
    $filtercmid,
    false,
    ['class' => 'custom-select form-select']
));
echo $field(get_string('defenseyear', 'mod_thesesynthesis'), html_writer::select(
    $years,
    'year',
    $year,
    false,
    ['class' => 'custom-select form-select']
));
echo $field(get_string('status'), html_writer::select(
    $statuses,
    'status',
    $status,
    false,
    ['class' => 'custom-select form-select']
));
if ($questions) {
    echo $OUTPUT->heading(get_string('questionnaire', 'mod_these'), 5, 'mt-3');
    foreach ($questions as $question) {
        $name = 'a' . $question->id;
        $value = $answerfilters[(int) $question->id] ?? '';
        if (in_array($question->qtype, ['list', 'multi'], true)) {
            $options = [];
            foreach (these_question_options($question) as $option) {
                $options[$option] = format_string($option);
            }
            $control = html_writer::select(
                $options,
                $name,
                $value,
                ['' => get_string('any')],
                ['class' => 'custom-select form-select', 'id' => 'search-' . $name]
            );
        } else {
            $control = html_writer::empty_tag('input', ['type' => 'text', 'name' => $name, 'value' => $value, 'size' => 40,
                'class' => 'form-control', 'id' => 'search-' . $name]);
        }
        echo $field(format_string($question->label), $control);
    }
}
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary mr-1']);
echo html_writer::link($baseurl, get_string('reset'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (!$searched) {
    echo $OUTPUT->footer();
    exit;
}

$rows = thesesynthesis_search($links, $USER->id, [
    'text' => $text,
    'cmid' => $filtercmid,
    'status' => $status === -999 ? null : $status,
    'year' => $year,
    'answers' => $answerfilters,
], $questions);

echo $OUTPUT->heading(get_string('searchresults', 'mod_thesesynthesis', count($rows)), 3, 'mt-4');
if (!$rows) {
    echo html_writer::div(get_string('searchnoresult', 'mod_thesesynthesis'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}
$table = new html_table();
$table->attributes['class'] = 'generaltable these-search';
$table->head = [
    get_string('promotion', 'mod_thesesynthesis'),
    get_string('defenseyear', 'mod_thesesynthesis'),
    get_string('student', 'mod_these'),
    get_string('subjecttitle', 'mod_these'),
    get_string('director', 'mod_these'),
    get_string('status'),
    '',
];
foreach ($rows as $row) {
    // Détails : résumé, mots-clés et réponses au questionnaire.
    $details = [];
    if (trim((string) $row->dossier->keywords) !== '') {
        $details[] = html_writer::tag('strong', get_string('subjectkeywords', 'mod_these')) . ' : '
            . s(implode(', ', these_split_keywords($row->dossier->keywords)));
    }
    if (trim((string) $row->dossier->summary) !== '') {
        $details[] = format_text($row->dossier->summary, FORMAT_PLAIN);
    }
    $allquestions = $questions;
    foreach (array_diff(array_keys($row->answers), array_keys($allquestions)) as $questionid) {
        if ($question = $DB->get_record('these_question', ['id' => $questionid])) {
            $allquestions[$questionid] = $question;
        }
    }
    foreach ($allquestions as $question) {
        if (isset($row->answers[(int) $question->id])) {
            $details[] = html_writer::tag('strong', format_string($question->label)) . ' : '
                . s(these_answer_text($row->answers[(int) $question->id]));
        }
    }
    $title = $row->knowssubject ? format_string($row->dossier->title)
        : html_writer::tag('em', get_string('confidentialtitle', 'mod_these'));
    if ($details) {
        $title = html_writer::tag('details', html_writer::tag('summary', $title)
            . html_writer::div(implode(html_writer::empty_tag('br'), $details), 'small mt-1'));
    }
    $director = $row->director ? fullname($row->director) : (string) $row->dossier->extdirectorname;
    $table->data[] = [
        format_string($row->link->course->shortname ?: $row->link->course->fullname),
        $row->year ?: '-',
        s($row->student ? fullname($row->student) : '-'),
        $title,
        s($director !== '' ? $director : '-'),
        these_render_status_badge($row->dossier->status),
        $row->canopen ? html_writer::link(
            new moodle_url('/mod/these/dossier.php', ['id' => $row->link->cm->id, 'dossierid' => $row->dossier->id]),
            get_string('open', 'mod_thesesynthesis'),
            ['class' => 'btn btn-sm btn-secondary']
        ) : '',
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
