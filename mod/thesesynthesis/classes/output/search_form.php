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

use mod_these\output\select_context;
use moodle_url;

/**
 * Formulaire de recherche des thèses : texte, promotion, année de soutenance, statut et réponses au
 * questionnaire préalable au jury.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_form implements \renderable, \templatable {
    /** @var moodle_url */
    protected $baseurl;
    /** @var int */
    protected $cmid;
    /** @var array */
    protected $links;
    /** @var array */
    protected $questions;
    /** @var array */
    protected $values;

    /**
     * Constructeur.
     *
     * @param moodle_url $baseurl Adresse de la page.
     * @param int $cmid Module de l'activité de suivi.
     * @param array $links Voir thesesynthesis_get_active_links().
     * @param array $questions Questions filtrables (voir these_get_questions()).
     * @param array $values Valeurs saisies : q, cmid, year, status, answers (questionid => valeur).
     */
    public function __construct(moodle_url $baseurl, $cmid, array $links, array $questions, array $values) {
        $this->baseurl = $baseurl;
        $this->cmid = (int) $cmid;
        $this->links = $links;
        $this->questions = $questions;
        $this->values = $values + ['q' => '', 'cmid' => 0, 'year' => 0, 'status' => -999, 'answers' => []];
    }

    /**
     * Choix de la promotion : toutes, puis chaque activité suivie (texte brut).
     *
     * @param array $links Voir thesesynthesis_get_active_links().
     * @return array cmid => libellé
     */
    public static function promotion_options(array $links): array {
        $plain = ['escape' => false];
        $options = [0 => get_string('allpromotions', 'mod_thesesynthesis')];
        foreach ($links as $link) {
            $options[$link->cm->id] = format_string($link->course->fullname, true, $plain) . ' : '
                . format_string($link->these->name, true, $plain);
        }
        return $options;
    }

    /**
     * Choix du statut : tous, puis chaque statut du circuit.
     *
     * @return array statut => libellé
     */
    public static function status_options(): array {
        $options = [-999 => get_string('allstatuses', 'mod_thesesynthesis')];
        foreach (these_all_statuses() as $value) {
            $options[$value] = these_status_label($value);
        }
        return $options;
    }

    /**
     * Champ du formulaire : libellé et zone de texte ou liste.
     *
     * @param string $label Texte brut.
     * @param array|false $text Zone de texte : name, value, size, id.
     * @param array|false $select Liste (voir select_context).
     * @return array
     */
    protected static function field(string $label, $text, $select): array {
        return ['label' => $label, 'text' => $text, 'select' => $select];
    }

    /**
     * Données du gabarit mod_thesesynthesis/search_form.
     *
     * @param \renderer_base $output
     * @return array action, cmid, fields, questionheading, questionfields, searchlabel, reseturl, resetlabel
     */
    public function export_for_template(\renderer_base $output): array {
        $class = 'custom-select form-select';
        $years = [0 => get_string('allyears', 'mod_thesesynthesis')];
        for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 15; $y--) {
            $years[$y] = $y;
        }
        $fields = [
            self::field(
                get_string('searchtext', 'mod_thesesynthesis'),
                ['name' => 'q', 'value' => (string) $this->values['q'], 'size' => 50, 'id' => 'search-q'],
                false
            ),
            self::field(
                get_string('promotion', 'mod_thesesynthesis'),
                false,
                select_context::export('cmid', self::promotion_options($this->links), $this->values['cmid'], $class)
            ),
            self::field(
                get_string('defenseyear', 'mod_thesesynthesis'),
                false,
                select_context::export('year', $years, $this->values['year'], $class)
            ),
            self::field(
                get_string('status'),
                false,
                select_context::export('status', self::status_options(), $this->values['status'], $class)
            ),
        ];
        $plain = ['escape' => false];
        $questionfields = [];
        foreach ($this->questions as $question) {
            $name = 'a' . $question->id;
            $value = (string) ($this->values['answers'][(int) $question->id] ?? '');
            $label = format_string($question->label, true, $plain);
            if (in_array($question->qtype, ['list', 'multi'], true)) {
                $options = ['' => get_string('any')];
                foreach (these_question_options($question) as $option) {
                    $options[$option] = format_string($option, true, $plain);
                }
                $select = select_context::export($name, $options, $value, $class, 'search-' . $name);
                $questionfields[] = self::field($label, false, $select);
            } else {
                $text = ['name' => $name, 'value' => $value, 'size' => 40, 'id' => 'search-' . $name];
                $questionfields[] = self::field($label, $text, false);
            }
        }
        return [
            'action' => $this->baseurl->out_omit_querystring(),
            'cmid' => $this->cmid,
            'fields' => $fields,
            'questionheading' => $questionfields ? get_string('questionnaire', 'mod_these') : false,
            'questionfields' => $questionfields,
            'searchlabel' => get_string('search'),
            'reseturl' => $this->baseurl->out(false),
            'resetlabel' => get_string('reset'),
        ];
    }
}
