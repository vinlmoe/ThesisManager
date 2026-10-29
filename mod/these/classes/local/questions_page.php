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
use moodle_url;
use mod_these\form\question_form;

/**
 * Page des questions du questionnaire préalable au jury d'une portée de paramètres (activité
 * « Gestion des thèses » non suivie, ou activité « Suivi des thèses » pour toutes les promotions
 * suivies) : liste, ajout, modification, suppression et ordre des questions.
 *
 * Chaque page appelante vérifie ses droits et prépare $PAGE avant d'appeler output().
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_page {
    /**
     * Traite l'action demandée et affiche la page.
     *
     * @param \stdClass $scope
     * @param moodle_url $listurl Adresse de la liste (avec ses paramètres d'accès).
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(\stdClass $scope, moodle_url $listurl, $navigation = '') {
        global $DB, $OUTPUT, $PAGE;

        $action = optional_param('action', '', PARAM_ALPHA);
        $questionid = optional_param('questionid', 0, PARAM_INT);
        $questions = these_get_questions($scope);
        if ($questionid && !isset($questions[$questionid])) {
            throw new \moodle_exception('invalidrecord', 'error', '', 'these_question');
        }

        if (($action === 'up' || $action === 'down') && confirm_sesskey()) {
            these_move_question($scope, $questionid, $action === 'up' ? -1 : 1);
            redirect($listurl);
        }

        if ($action === 'delete') {
            if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
                these_delete_question($scope, $questionid);
                redirect($listurl, get_string('questiondeleted', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            $answers = $DB->count_records('these_answer', ['questionid' => $questionid]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('questiondeleteconfirm', 'mod_these', (object) [
                    'label' => format_string($questions[$questionid]->label),
                    'answers' => $answers,
                ]),
                new moodle_url($listurl, ['action' => 'delete', 'questionid' => $questionid, 'confirm' => 1,
                    'sesskey' => sesskey()]),
                $listurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        if ($action === 'edit') {
            $mform = new question_form(new moodle_url($listurl));
            $formdata = ['id' => $listurl->get_param('id'), 'questionid' => $questionid, 'qtype' => 'text', 'required' => 1];
            if ($questionid) {
                $question = $questions[$questionid];
                $formdata = array_merge($formdata, ['qtype' => $question->qtype, 'label' => $question->label,
                    'options' => $question->options, 'required' => $question->required]);
            }
            $mform->set_data($formdata);
            if ($mform->is_cancelled()) {
                redirect($listurl);
            } else if ($data = $mform->get_data()) {
                $data->id = $data->questionid;
                these_save_question($scope, $data);
                redirect($listurl, get_string('questionsaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string($questionid ? 'questionedit' : 'questionadd', 'mod_these'));
            echo backlinks::render(backlinks::target($listurl, get_string('questions', 'mod_these')));
            $mform->display();
            echo $OUTPUT->footer();
            exit;
        }

        $renderer = $PAGE->get_renderer('mod_these');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('questions', 'mod_these'));
        echo $navigation;
        $intro = get_string($scope->shared ? 'questionsshared_desc' : 'questions_desc', 'mod_these');
        echo $renderer->render(output\message::help($intro));
        if ($questions) {
            echo $renderer->render(new output\question_list($questions, $listurl));
        } else {
            echo $OUTPUT->notification(get_string('questionsnone', 'mod_these'), 'info');
        }
        echo $renderer->render(new output\link(
            new moodle_url($listurl, ['action' => 'edit']),
            get_string('questionadd', 'mod_these'),
            'btn btn-primary',
            '',
            'mb-3'
        ));
        echo $OUTPUT->footer();
    }
}
