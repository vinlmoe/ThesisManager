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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Questionnaire préalable au jury d'une activité non suivie : questions, réponses, contrôle avant
 * le dépôt de la soutenance, transfert et suppression.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_save_question
 * @covers     ::these_save_answers
 * @covers     ::these_questionnaire_missing
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('these_save_question')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_save_answers')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_questionnaire_missing')]
final class questionnaire_test extends \advanced_testcase {
    /**
     * Questions : ajout en fin de liste, modification, ordre, suppression avec les réponses.
     */
    public function test_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $these = $this->getDataGenerator()->create_module('these', ['course' => $course->id]);
        $scope = these_settings_scope($these);
        $this->assertFalse($scope->shared);

        $first = these_save_question($scope, (object) ['qtype' => 'text', 'label' => ' Laboratoire ', 'options' => 'ignoré',
            'required' => 1]);
        $second = these_save_question($scope, (object) ['qtype' => 'list', 'label' => 'Espèce',
            'options' => "Chien\n\nChat\r\nChien", 'required' => 0]);
        $questions = these_get_questions($scope);
        $this->assertSame([$first, $second], array_keys($questions));
        $this->assertSame('Laboratoire', $questions[$first]->label);
        $this->assertSame('', $questions[$first]->options);
        $this->assertSame(['Chien', 'Chat'], these_question_options($questions[$second]));

        these_move_question($scope, $second, -1);
        $this->assertSame([$second, $first], array_keys(these_get_questions($scope)));
        these_move_question($scope, $second, -1);
        $this->assertSame([$second, $first], array_keys(these_get_questions($scope)));

        these_save_question($scope, (object) ['id' => $first, 'qtype' => 'textarea', 'label' => 'Laboratoire d\'accueil',
            'options' => '', 'required' => 0]);
        $this->assertSame('textarea', these_get_questions($scope)[$first]->qtype);
        $this->assertSame([$second, $first], array_keys(these_get_questions($scope)));

        // Une question d'une autre activité n'est pas modifiable depuis celle-ci.
        $other = $this->getDataGenerator()->create_module('these', ['course' => $course->id]);
        these_delete_question(these_local_scope($other), $first);
        $this->assertTrue($DB->record_exists('these_question', ['id' => $first]));

        $dossier = $this->getDataGenerator()->get_plugin_generator('mod_these')->create_dossier(
            $these,
            $this->getDataGenerator()->create_user()->id
        );
        these_save_answers($dossier, these_get_questions($scope), ['q' . $first => 'Virologie']);
        these_delete_question($scope, $first);
        $this->assertSame([$second], array_keys(these_get_questions($scope)));
        $this->assertFalse($DB->record_exists('these_answer', ['questionid' => $first]));

        // Suppression de l'activité.
        course_delete_module($these->cmid);
        $this->assertFalse($DB->record_exists('these_question', ['id' => $second]));
    }

    /**
     * Réponses : texte, liste (choix inconnu refusé), QCM ; questions obligatoires manquantes ;
     * affichage ; transfert des questions.
     */
    public function test_answers(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $these = $gen->create_module('these', ['course' => $course->id]);
        $scope = these_local_scope($these);
        $text = these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 1]);
        $list = these_save_question($scope, (object) ['qtype' => 'list', 'label' => 'Espèce', 'options' => "Chien\nChat",
            'required' => 1]);
        $multi = these_save_question($scope, (object) ['qtype' => 'multi', 'label' => 'Méthodes',
            'options' => "Enquête\nEssai\nRevue", 'required' => 0]);
        $questions = these_get_questions($scope);
        $dossier = $gen->get_plugin_generator('mod_these')->create_dossier($these, $gen->create_user()->id);

        $this->assertSame(['Laboratoire', 'Espèce'], these_questionnaire_missing($these, $dossier));
        these_save_answers($dossier, $questions, ['q' . $text => ' Virologie ', 'q' . $list => 'Cheval',
            'q' . $multi . '_0' => 1, 'q' . $multi . '_2' => 1]);
        $answers = these_get_answers($dossier->id);
        $this->assertSame('Virologie', $answers[$text]);
        $this->assertArrayNotHasKey($list, $answers);
        $this->assertSame(['Enquête', 'Revue'], $answers[$multi]);
        $this->assertSame(['Espèce'], these_questionnaire_missing($these, $dossier));

        these_save_answers($dossier, $questions, ['q' . $text => 'Virologie', 'q' . $list => 'Chat']);
        $answers = these_get_answers($dossier->id);
        $this->assertSame('Chat', $answers[$list]);
        // Un QCM décoché est effacé.
        $this->assertArrayNotHasKey($multi, $answers);
        $this->assertSame([], these_questionnaire_missing($these, $dossier));

        $html = these_render_answers($these, $dossier);
        $this->assertStringContainsString('Virologie', $html);
        $this->assertStringContainsString(get_string('questionnaireunanswered', 'mod_these'), $html);

        // Transfert : les questions de la destination sont remplacées, avec leurs réponses.
        $target = $gen->create_module('these', ['course' => $course->id]);
        $targetq = these_save_question(these_local_scope($target), (object) ['qtype' => 'text', 'label' => 'Ancienne',
            'options' => '', 'required' => 0]);
        $data = these_export_settings($these, \context_module::instance($these->cmid), ['questions']);
        $this->assertCount(3, $data['groups']['questions']);
        $result = these_import_settings($target, \context_module::instance($target->cmid), $data, ['questions']);
        $this->assertSame(3, $result->questions);
        $labels = array_column(these_get_questions(these_local_scope($target)), 'label');
        $this->assertSame(['Laboratoire', 'Espèce', 'Méthodes'], array_values($labels));
        $this->assertFalse($DB->record_exists('these_question', ['id' => $targetq]));

        // Suppression du dossier.
        these_delete_dossiers([$dossier->id]);
        $this->assertFalse($DB->record_exists('these_answer', ['dossierid' => $dossier->id]));
    }
}
