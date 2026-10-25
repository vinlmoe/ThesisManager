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

namespace mod_thesesynthesis\output;

use mod_these\output\parts;
use mod_these\output\status_badge;
use mod_these\output\table_context;
use moodle_url;

/**
 * Résultats d'une recherche de thèses.
 *
 * Pour une thèse confidentielle dont l'utilisateur ne connaît pas le sujet (knowssubject, évalué par
 * thesesynthesis_search()), le titre, les mots-clés et le résumé sont remplacés par « Thèse
 * confidentielle » et les réponses au questionnaire omises : ils n'entrent pas dans le contexte du
 * gabarit.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_results implements \renderable, \templatable {
    /** @var array */
    protected $rows;
    /** @var array */
    protected $questions;

    /**
     * Constructeur.
     *
     * @param array $rows Voir thesesynthesis_search().
     * @param array $questions Questions du questionnaire (voir these_get_questions()).
     */
    public function __construct(array $rows, array $questions) {
        $this->rows = $rows;
        $this->questions = $questions;
    }

    /**
     * Détails d'un résultat (mots-clés, résumé, réponses), une ligne par détail.
     *
     * @param \stdClass $row
     * @return array[] Fragments, lignes séparées par un saut de ligne.
     */
    protected function details(\stdClass $row): array {
        global $DB;

        $lines = [];
        if (!$row->knowssubject) {
            $masked = parts::em(get_string('confidentialtitle', 'mod_these'));
            $lines[] = [parts::strong(get_string('subjectkeywords', 'mod_these')), parts::text(' : '), $masked];
            $lines[] = [parts::strong(get_string('subjectsummary', 'mod_these')), parts::text(' : '), $masked];
        } else {
            if (trim((string) $row->dossier->keywords) !== '') {
                $lines[] = [parts::strong(get_string('subjectkeywords', 'mod_these')),
                    parts::text(' : ' . implode(', ', these_split_keywords($row->dossier->keywords)))];
            }
            if (trim((string) $row->dossier->summary) !== '') {
                $lines[] = [parts::html(format_text($row->dossier->summary, FORMAT_PLAIN))];
            }
            $questions = $this->questions;
            foreach (array_diff(array_keys($row->answers), array_keys($questions)) as $questionid) {
                if ($question = $DB->get_record('these_question', ['id' => $questionid])) {
                    $questions[$questionid] = $question;
                }
            }
            foreach ($questions as $question) {
                if (isset($row->answers[(int) $question->id])) {
                    $lines[] = [parts::stronghtml(format_string($question->label)),
                        parts::text(' : ' . these_answer_text($row->answers[(int) $question->id]))];
                }
            }
        }
        $body = [];
        foreach ($lines as $line) {
            if ($body) {
                $body[] = parts::br();
            }
            $body = array_merge($body, $line);
        }
        return $body;
    }

    /**
     * Données du gabarit mod_thesesynthesis/search_results.
     *
     * @param \renderer_base $output
     * @return array isempty, emptymessage, table (voir \mod_these\output\table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        if (!$this->rows) {
            return ['isempty' => true, 'emptymessage' => get_string('searchnoresult', 'mod_thesesynthesis'), 'table' => false];
        }
        $head = [
            get_string('promotion', 'mod_thesesynthesis'),
            get_string('defenseyear', 'mod_thesesynthesis'),
            get_string('student', 'mod_these'),
            get_string('subjecttitle', 'mod_these'),
            get_string('director', 'mod_these'),
            get_string('status'),
            '',
        ];
        $rows = [];
        foreach ($this->rows as $row) {
            $title = $row->knowssubject ? parts::html(format_string($row->dossier->title))
                : parts::em(get_string('confidentialtitle', 'mod_these'));
            $details = $this->details($row);
            if ($details) {
                $title = parts::details([$title], $details);
            }
            $director = $row->director ? fullname($row->director) : (string) $row->dossier->extdirectorname;
            $open = [];
            if ($row->canopen) {
                $url = new moodle_url('/mod/these/dossier.php', ['id' => $row->link->cm->id, 'dossierid' => $row->dossier->id]);
                $open[] = parts::link($url, get_string('open', 'mod_thesesynthesis'), 'btn btn-sm btn-secondary');
            }
            $rows[] = [
                [parts::html(format_string($row->link->course->shortname ?: $row->link->course->fullname))],
                [parts::text($row->year ? (string) $row->year : '-')],
                [parts::text($row->student ? fullname($row->student) : '-')],
                [$title],
                [parts::text($director !== '' ? $director : '-')],
                [parts::status((new status_badge($row->dossier->status))->export_for_template($output))],
                $open,
            ];
        }
        return [
            'isempty' => false,
            'emptymessage' => '',
            'table' => table_context::export('generaltable these-search', $head, $rows),
        ];
    }
}
