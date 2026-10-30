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

namespace mod_these\form;

use mod_these\local\questionnaire;

/**
 * Réponses d'un dossier au questionnaire préalable au jury : un champ q<id> par question (zone de
 * texte, liste déroulante), ou une case q<id>_<n> par choix d'un QCM.
 *
 * customdata : questions (voir these_get_questions()).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questionnaire_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_hidden_ids(true);

        self::add_question_fields($mform, $this->_customdata['questions']);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Questions obligatoires.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        return array_merge(parent::validation($data, $files), self::question_errors($this->_customdata['questions'], $data));
    }

    /**
     * Ajoute les questions à un formulaire : celui du questionnaire, ou celui du dépôt de la
     * soutenance, où l'étudiant y répond.
     *
     * @param \MoodleQuickForm $mform
     * @param \stdClass[] $questions Voir these_get_questions().
     * @return void
     */
    public static function add_question_fields(\MoodleQuickForm $mform, array $questions) {
        foreach ($questions as $question) {
            $name = 'q' . $question->id;
            $label = format_string($question->label);
            switch ($question->qtype) {
                case 'textarea':
                    $mform->addElement('textarea', $name, $label, ['rows' => 6, 'cols' => 80]);
                    $mform->setType($name, PARAM_TEXT);
                    break;
                case 'list':
                    $options = ['' => get_string('choosedots')];
                    foreach (questionnaire::question_options($question) as $option) {
                        $options[$option] = format_string($option);
                    }
                    $mform->addElement('select', $name, $label, $options);
                    break;
                case 'multi':
                    $boxes = [];
                    foreach (questionnaire::question_options($question) as $n => $option) {
                        $boxes[] = $mform->createElement('advcheckbox', $name . '_' . $n, '', format_string($option));
                    }
                    $mform->addGroup($boxes, $name, $label, \html_writer::empty_tag('br'), false);
                    break;
                default:
                    $mform->addElement('text', $name, $label, ['size' => 70]);
                    $mform->setType($name, PARAM_TEXT);
            }
            if ($question->required) {
                // Contrôlé dans question_errors() : une règle « required » ne vaut pas pour un groupe de cases.
                $mform->addElement('static', $name . 'required', '', \html_writer::span(
                    get_string('questionrequired', 'mod_these'),
                    'text-muted small'
                ));
            }
        }
    }

    /**
     * Questions obligatoires sans réponse dans les données soumises.
     *
     * @param \stdClass[] $questions
     * @param array $data
     * @return array Erreurs, indexées par champ.
     */
    public static function question_errors(array $questions, array $data) {
        $errors = [];
        foreach ($questions as $question) {
            if (!$question->required) {
                continue;
            }
            $name = 'q' . $question->id;
            if ($question->qtype === 'multi') {
                $answered = false;
                foreach (array_keys(questionnaire::question_options($question)) as $n) {
                    $answered = $answered || !empty($data[$name . '_' . $n]);
                }
            } else {
                $answered = trim((string) ($data[$name] ?? '')) !== '';
            }
            if (!$answered) {
                $errors[$name] = get_string('required');
            }
        }
        return $errors;
    }

    /**
     * Réponses enregistrées d'un dossier, sous la forme des champs du formulaire.
     *
     * @param \stdClass[] $questions
     * @param array $answers Voir these_get_answers().
     * @return array
     */
    public static function answers_data(array $questions, array $answers) {
        $data = [];
        foreach ($answers as $questionid => $answer) {
            if (!isset($questions[$questionid])) {
                continue;
            }
            if ($questions[$questionid]->qtype === 'multi') {
                foreach (questionnaire::question_options($questions[$questionid]) as $n => $option) {
                    $data['q' . $questionid . '_' . $n] = in_array($option, (array) $answer, true) ? 1 : 0;
                }
            } else {
                $data['q' . $questionid] = questionnaire::answer_text($answer);
            }
        }
        return $data;
    }
}
