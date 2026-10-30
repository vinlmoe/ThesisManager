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

namespace mod_these\output;

use mod_these\local\questionnaire;
use moodle_url;

/**
 * Questions du questionnaire préalable au jury, avec leurs actions (modifier, déplacer, supprimer).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_list implements \renderable, \templatable {
    /** @var array */
    protected $questions;
    /** @var moodle_url */
    protected $listurl;

    /**
     * Constructeur.
     *
     * @param array $questions Voir these_get_questions().
     * @param moodle_url $listurl Adresse de la liste.
     */
    public function __construct(array $questions, moodle_url $listurl) {
        $this->questions = $questions;
        $this->listurl = $listurl;
    }

    /**
     * Données du gabarit mod_these/question_list.
     *
     * @param \renderer_base $output
     * @return array Voir table_context::export().
     */
    public function export_for_template(\renderer_base $output): array {
        $types = questionnaire::question_types();
        $count = count($this->questions);
        $rows = [];
        foreach (array_values($this->questions) as $index => $question) {
            $params = ['questionid' => $question->id];
            $links = [parts::link(new moodle_url($this->listurl, $params + ['action' => 'edit']), get_string('edit'), '')];
            if ($index > 0) {
                $links[] = parts::link(
                    new moodle_url($this->listurl, $params + ['action' => 'up', 'sesskey' => sesskey()]),
                    get_string('moveup'),
                    ''
                );
            }
            if ($index < $count - 1) {
                $links[] = parts::link(
                    new moodle_url($this->listurl, $params + ['action' => 'down', 'sesskey' => sesskey()]),
                    get_string('movedown'),
                    ''
                );
            }
            $links[] = parts::link(new moodle_url($this->listurl, $params + ['action' => 'delete']), get_string('delete'), '');
            $actions = [];
            foreach ($links as $link) {
                if ($actions) {
                    $actions[] = parts::text(' | ');
                }
                $actions[] = $link;
            }
            $rows[] = [
                [parts::html(format_string($question->label))],
                [parts::text($types[$question->qtype] ?? $question->qtype)],
                [parts::text(implode(' ; ', questionnaire::question_options($question)))],
                [parts::text($question->required ? get_string('yes') : get_string('no'))],
                $actions,
            ];
        }
        $head = [
            get_string('questionlabel', 'mod_these'),
            get_string('questiontype', 'mod_these'),
            get_string('questionoptions', 'mod_these'),
            get_string('questionrequired', 'mod_these'),
            get_string('actions'),
        ];
        return table_context::export('generaltable these-questions', $head, $rows);
    }
}
