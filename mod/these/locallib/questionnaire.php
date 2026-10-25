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
 * Questionnaire préalable au jury.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Questionnaire préalable au jury.

/**
 * Voir \mod_these\local\questionnaire::question_types().
 *
 * @return array type => libellé
 */
function these_question_types() {
    return \mod_these\local\questionnaire::question_types();
}

/**
 * Voir \mod_these\local\questionnaire::get_questions().
 *
 * @param stdClass $scope
 * @return array id => question
 */
function these_get_questions(stdClass $scope) {
    return \mod_these\local\questionnaire::get_questions($scope);
}

/**
 * Voir \mod_these\local\questionnaire::question_options().
 *
 * @param stdClass $question
 * @return string[]
 */
function these_question_options(stdClass $question) {
    return \mod_these\local\questionnaire::question_options($question);
}

/**
 * Voir \mod_these\local\questionnaire::save_question().
 *
 * @param stdClass $scope
 * @param stdClass $data {id?, qtype, label, options, required}
 * @return int id
 */
function these_save_question(stdClass $scope, stdClass $data) {
    return \mod_these\local\questionnaire::save_question($scope, $data);
}

/**
 * Voir \mod_these\local\questionnaire::delete_question().
 *
 * @param stdClass $scope
 * @param int $id
 * @return void
 */
function these_delete_question(stdClass $scope, $id) {
    return \mod_these\local\questionnaire::delete_question($scope, $id);
}

/**
 * Voir \mod_these\local\questionnaire::move_question().
 *
 * @param stdClass $scope
 * @param int $id
 * @param int $direction -1 ou 1
 * @return void
 */
function these_move_question(stdClass $scope, $id, $direction) {
    return \mod_these\local\questionnaire::move_question($scope, $id, $direction);
}

/**
 * Voir \mod_these\local\questionnaire::get_answers().
 *
 * @param int $dossierid
 * @return array questionid => valeur (texte, ou liste de choix pour un QCM)
 */
function these_get_answers($dossierid) {
    return \mod_these\local\questionnaire::get_answers($dossierid);
}

/**
 * Voir \mod_these\local\questionnaire::save_answers().
 *
 * @param stdClass $dossier
 * @param array $questions Voir these_get_questions().
 * @param stdClass|array $data
 * @return void
 */
function these_save_answers(stdClass $dossier, array $questions, $data) {
    return \mod_these\local\questionnaire::save_answers($dossier, $questions, $data);
}

/**
 * Voir \mod_these\local\questionnaire::questionnaire_missing().
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @return string[] Libellés.
 */
function these_questionnaire_missing(stdClass $these, stdClass $dossier) {
    return \mod_these\local\questionnaire::questionnaire_missing($these, $dossier);
}

/**
 * Voir \mod_these\local\questionnaire::answer_text().
 *
 * @param mixed $answer Voir these_get_answers().
 * @return string
 */
function these_answer_text($answer) {
    return \mod_these\local\questionnaire::answer_text($answer);
}

/**
 * Voir \mod_these\local\questionnaire::render_answers().
 *
 * @param stdClass $these
 * @param stdClass $dossier
 * @return string HTML ('' si le questionnaire est vide et sans réponse)
 */
function these_render_answers(stdClass $these, stdClass $dossier) {
    return \mod_these\local\questionnaire::render_answers($these, $dossier);
}
