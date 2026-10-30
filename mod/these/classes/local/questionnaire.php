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

namespace mod_these\local;

use mod_these\output;
use stdClass;

/**
 * Questionnaire préalable au jury.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire {
    /**
     * Types de questions.
     *
     * @return array type => libellé
     */
    public static function question_types() {
        $types = [];
        foreach (['text', 'textarea', 'list', 'multi'] as $type) {
            $types[$type] = get_string('qtype' . $type, 'mod_these');
        }
        return $types;
    }

    /**
     * Questions du questionnaire préalable au jury d'une portée (voir these_settings_scope()), dans
     * l'ordre.
     *
     * @param stdClass $scope
     * @return array id => question
     */
    public static function get_questions(stdClass $scope) {
        global $DB;
        return $DB->get_records('these_question', doctemplates::scope_conditions($scope), 'sortorder ASC, id ASC');
    }

    /**
     * Choix proposés par une question à liste ou à choix multiples.
     *
     * @param stdClass $question
     * @return string[]
     */
    public static function question_options(stdClass $question) {
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $question->options)),
            'strlen'
        )));
    }

    /**
     * Enregistre une question d'une portée (ajout en fin de questionnaire, ou modification).
     *
     * @param stdClass $scope
     * @param stdClass $data {id?, qtype, label, options, required}
     * @return int id
     */
    public static function save_question(stdClass $scope, stdClass $data) {
        global $DB;

        $record = (object) array_merge(doctemplates::scope_conditions($scope), [
            'qtype' => array_key_exists($data->qtype, self::question_types()) ? $data->qtype : 'text',
            'label' => trim((string) $data->label),
            'options' => in_array($data->qtype, ['list', 'multi'], true) ? trim((string) $data->options) : '',
            'required' => empty($data->required) ? 0 : 1,
            'timemodified' => time(),
        ]);
        $existing = !empty($data->id)
            ? $DB->get_record('these_question', ['id' => $data->id] + doctemplates::scope_conditions($scope)) : null;
        if ($existing) {
            $record->id = $existing->id;
            $record->sortorder = $existing->sortorder;
            $DB->update_record('these_question', $record);
            return (int) $existing->id;
        }
        $record->sortorder = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {these_question} WHERE theseid = :theseid AND synthesisid = :synthesisid',
            doctemplates::scope_conditions($scope)
        );
        return (int) $DB->insert_record('these_question', $record);
    }

    /**
     * Supprime une question d'une portée et les réponses qui lui ont été données.
     *
     * @param stdClass $scope
     * @param int $id
     * @return void
     */
    public static function delete_question(stdClass $scope, $id) {
        global $DB;
        if ($DB->record_exists('these_question', ['id' => $id] + doctemplates::scope_conditions($scope))) {
            $DB->delete_records('these_answer', ['questionid' => $id]);
            $DB->delete_records('these_question', ['id' => $id]);
        }
    }

    /**
     * Déplace une question d'un rang vers le haut ou vers le bas.
     *
     * @param stdClass $scope
     * @param int $id
     * @param int $direction -1 ou 1
     * @return void
     */
    public static function move_question(stdClass $scope, $id, $direction) {
        global $DB;

        $ids = array_keys(self::get_questions($scope));
        $index = array_search((int) $id, array_map('intval', $ids), true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($target === false || $target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        foreach (array_values($ids) as $sortorder => $questionid) {
            $DB->set_field('these_question', 'sortorder', $sortorder, ['id' => $questionid]);
        }
    }

    /**
     * Réponses d'un dossier au questionnaire.
     *
     * @param int $dossierid
     * @return array questionid => valeur (texte, ou liste de choix pour un QCM)
     */
    public static function get_answers($dossierid) {
        global $DB;

        $answers = [];
        foreach ($DB->get_records('these_answer', ['dossierid' => $dossierid]) as $answer) {
            $decoded = json_decode((string) $answer->value, true);
            $answers[(int) $answer->questionid] = is_array($decoded) ? $decoded : (string) $answer->value;
        }
        return $answers;
    }

    /**
     * Enregistre les réponses d'un dossier (formulaire du questionnaire : champs q<id>, ou q<id>_<n>
     * pour les choix d'un QCM).
     *
     * @param stdClass $dossier
     * @param array $questions Voir these_get_questions().
     * @param stdClass|array $data
     * @return void
     */
    public static function save_answers(stdClass $dossier, array $questions, $data) {
        global $DB;

        $data = (array) $data;
        foreach ($questions as $question) {
            $options = self::question_options($question);
            if ($question->qtype === 'multi') {
                $chosen = [];
                foreach ($options as $n => $option) {
                    if (!empty($data['q' . $question->id . '_' . $n])) {
                        $chosen[] = $option;
                    }
                }
                $value = $chosen ? json_encode($chosen, JSON_UNESCAPED_UNICODE) : '';
            } else {
                $value = trim((string) ($data['q' . $question->id] ?? ''));
                if ($question->qtype === 'list' && !in_array($value, $options, true)) {
                    $value = '';
                }
            }
            $existing = $DB->get_record('these_answer', ['dossierid' => $dossier->id, 'questionid' => $question->id]);
            if ($value === '') {
                if ($existing) {
                    $DB->delete_records('these_answer', ['id' => $existing->id]);
                }
                continue;
            }
            $record = (object) ['dossierid' => $dossier->id, 'questionid' => $question->id, 'value' => $value,
                'timemodified' => time()];
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('these_answer', $record);
            } else {
                $DB->insert_record('these_answer', $record);
            }
        }
    }

    /**
     * Questions obligatoires sans réponse.
     *
     * @param stdClass $these
     * @param stdClass $dossier
     * @return string[] Libellés.
     */
    public static function questionnaire_missing(stdClass $these, stdClass $dossier) {
        $answers = self::get_answers($dossier->id);
        $missing = [];
        foreach (self::get_questions(doctemplates::settings_scope($these)) as $question) {
            $answer = $answers[(int) $question->id] ?? '';
            if ($question->required && ($answer === '' || $answer === [])) {
                $missing[] = $question->label;
            }
        }
        return $missing;
    }

    /**
     * Réponse lisible.
     *
     * @param mixed $answer Voir these_get_answers().
     * @return string
     */
    public static function answer_text($answer) {
        return is_array($answer) ? implode(' ; ', $answer) : (string) $answer;
    }

    /**
     * Section des réponses au questionnaire d'un dossier ; vide (non affichée) sans question.
     *
     * Les réponses citent souvent le sujet : l'appelant ne l'affiche qu'à qui connaît le sujet.
     *
     * @param stdClass $these Instance (seul id est utilisé).
     * @param stdClass $dossier
     * @return output\detail_section
     */
    public static function answers_section(stdClass $these, stdClass $dossier): output\detail_section {
        global $DB;

        $section = new output\detail_section(get_string('questionnaire', 'mod_these'));
        $answers = self::get_answers($dossier->id);
        $questions = self::get_questions(doctemplates::settings_scope($these));
        foreach (array_diff(array_keys($answers), array_keys($questions)) as $questionid) {
            if ($question = $DB->get_record('these_question', ['id' => $questionid])) {
                $questions[$questionid] = $question;
            }
        }
        foreach ($questions as $question) {
            $answer = $answers[(int) $question->id] ?? '';
            $section->add_row(
                output\parts::stronghtml(format_string($question->label)),
                [$answer === '' || $answer === []
                    ? output\parts::span(get_string('questionnaireunanswered', 'mod_these'), 'text-muted')
                    : output\parts::html(format_text(self::answer_text($answer), FORMAT_PLAIN))]
            );
        }
        return $section;
    }

    /**
     * Réponses d'un dossier au questionnaire, pour l'affichage (questions en vigueur, puis réponses à
     * des questions d'une autre portée, si l'activité a changé de suivi).
     *
     * @param stdClass $these
     * @param stdClass $dossier
     * @return string HTML ('' si le questionnaire est vide et sans réponse)
     */
    public static function render_answers(stdClass $these, stdClass $dossier) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(self::answers_section($these, $dossier));
    }
}
