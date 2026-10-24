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

namespace mod_thesesynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

/**
 * Paramètres partagés par l'activité de suivi (modèles, grille, questionnaire) et recherche des
 * thèses dans les promotions suivies.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_settings_scope
 * @covers     ::thesesynthesis_search
 * @covers     ::thesesynthesis_delete_instance
 */
final class shared_settings_test extends \advanced_testcase {
    /**
     * Deux promotions suivies par une activité de suivi ; une troisième activité non suivie.
     *
     * @return \stdClass
     */
    private function setup_env(): \stdClass {
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        $env = new \stdClass();
        $env->director = $gen->create_user(['firstname' => 'Diane', 'lastname' => 'Directrice']);
        $env->teacher = $gen->create_user();
        $env->deve = $gen->create_user();
        $env->activities = [];
        foreach (['Promo 2026', 'Promo 2027', 'Hors suivi'] as $i => $name) {
            $course = $gen->create_course(['fullname' => $name, 'shortname' => 'P' . $i]);
            $these = $gen->create_module('these', ['course' => $course->id]);
            $gen->enrol_user($env->director->id, $course->id, 'teacher');
            $gen->enrol_user($env->teacher->id, $course->id, 'teacher');
            $gen->enrol_user($env->deve->id, $course->id, 'editingteacher');
            $env->activities[] = (object) ['course' => $course, 'these' => $these];
        }
        $synthcourse = $gen->create_course();
        $env->synthesis = $gen->create_module('thesesynthesis', ['course' => $synthcourse->id]);
        thesesynthesis_set_links($env->synthesis->id, [$env->activities[0]->these->cmid, $env->activities[1]->these->cmid]);
        $env->scope = these_synthesis_scope($env->synthesis->id);

        // Dossiers : enregistré et public, enregistré mais confidentiel, sujet en cours de validation.
        $course = $env->activities[0]->course;
        $env->public = $thesegen->create_dossier($env->activities[0]->these, $gen->create_and_enrol($course, 'student')->id, [
            'directorid' => $env->director->id, 'status' => THESE_STATUS_ACTIVE, 'title' => 'Épidémiologie de la rage canine',
            'keywords' => 'rage ; chien ; vaccination', 'defensedate' => make_timestamp(2027, 6, 15)]);
        $env->confidential = $thesegen->create_dossier(
            $env->activities[0]->these,
            $gen->create_and_enrol($course, 'student')->id,
            ['status' => THESE_STATUS_ACTIVE, 'confidential' => 1,
            'title' => 'Vaccin confidentiel contre la rage',
            'defensedate' => make_timestamp(
                2027,
                3,
                1
            )]
        );
        $env->submitted = $thesegen->create_dossier(
            $env->activities[1]->these,
            $gen->create_and_enrol($env->activities[1]->course, 'student')->id,
            ['status' => THESE_STATUS_SUBJECT_SUBMITTED,
            'title' => 'Rage chez le renard',
            'directorid' => $env->director->id]
        );
        return $env;
    }

    /**
     * Les activités suivies héritent des modèles, de la grille et du questionnaire de l'activité de
     * suivi ; une activité non suivie garde les siens ; supprimer l'activité de suivi les rend.
     */
    public function test_inheritance(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_env();

        $followed = these_settings_scope($env->activities[1]->these);
        $this->assertTrue($followed->shared);
        $this->assertEquals($env->synthesis->id, $followed->synthesisid);
        $local = these_settings_scope($env->activities[2]->these);
        $this->assertFalse($local->shared);
        $this->assertEquals($env->activities[2]->these->id, $local->theseid);

        // Modèle de document.
        these_set_doctemplate($env->scope, 'report', '<p>Rapport commun {{student.lastname}}</p>');
        these_set_doctemplate(these_local_scope($env->activities[0]->these), 'report', '<p>Rapport local</p>');
        $this->assertSame('<p>Rapport commun {{student.lastname}}</p>', these_get_doctemplate($followed, 'report'));
        $this->assertSame(these_doctemplate_default('report'), these_get_doctemplate($local, 'report'));
        $this->assertSame(
            '<p>Rapport commun {{student.lastname}}</p>',
            these_get_doctemplate(these_settings_scope($env->activities[0]->these), 'report')
        );

        // Images : celles de l'activité de suivi.
        get_file_storage()->create_file_from_string([
            'contextid' => $env->scope->contextid, 'component' => 'mod_thesesynthesis', 'filearea' => 'docimages',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'logo.png',
        ], these_sample_signature_image());
        $this->assertArrayHasKey('logo', these_doctemplate_images($followed));
        $this->assertArrayNotHasKey('logo', these_doctemplate_images($local));

        // Grille.
        $items = these_get_grid_items($env->scope);
        $items['fond']['answer']->label = 'Question traitée (commune)';
        these_save_grid_items($env->scope, array_merge(...array_values(array_map('array_values', $items))));
        $this->assertSame('Question traitée (commune)', these_get_grid_items($followed)['fond']['answer']->label);
        $this->assertNotSame('Question traitée (commune)', these_get_grid_items($local)['fond']['answer']->label);

        // Questionnaire et réponses.
        $questionid = these_save_question($env->scope, (object) ['qtype' => 'list', 'label' => 'Espèce',
            'options' => "Chien\nChat", 'required' => 1]);
        $this->assertSame([$questionid], array_keys(these_get_questions($followed)));
        $this->assertSame([], these_get_questions($local));
        $this->assertSame(['Espèce'], these_questionnaire_missing($env->activities[0]->these, $env->public));
        these_save_answers($env->public, these_get_questions($followed), ['q' . $questionid => 'Chien']);
        $this->assertSame([], these_questionnaire_missing($env->activities[0]->these, $env->public));

        // Suppression de l'activité de suivi : ses paramètres et les réponses à ses questions.
        course_delete_module($env->synthesis->cmid);
        $this->assertFalse(these_settings_scope($env->activities[1]->these)->shared);
        $this->assertFalse($DB->record_exists('these_doctemplate', ['synthesisid' => $env->synthesis->id]));
        $this->assertFalse($DB->record_exists('these_question', ['id' => $questionid]));
        $this->assertFalse($DB->record_exists('these_answer', ['questionid' => $questionid]));
        $scope = these_settings_scope($env->activities[0]->these);
        $this->assertSame('<p>Rapport local</p>', these_get_doctemplate($scope, 'report'));
    }

    /**
     * Recherche : texte (sans accents), année, réponses au questionnaire ; visibilité selon les droits.
     */
    public function test_search(): void {
        $this->resetAfterTest();
        $env = $this->setup_env();
        $list = these_save_question($env->scope, (object) ['qtype' => 'multi', 'label' => 'Espèces',
            'options' => "Chien\nChat\nRenard", 'required' => 0]);
        $text = these_save_question($env->scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        $questions = these_get_questions($env->scope);
        these_save_answers($env->public, $questions, ['q' . $list . '_0' => 1, 'q' . $list . '_2' => 1,
            'q' . $text => 'Unité de virologie']);
        these_save_answers($env->confidential, $questions, ['q' . $list . '_1' => 1]);

        $ids = function (array $rows) {
            $ids = array_map(function ($row) {
                return (int) $row->dossier->id;
            }, $rows);
            sort($ids);
            return $ids;
        };
        $search = function ($userid, array $filters) use ($env, $questions, $ids) {
            $links = thesesynthesis_get_active_links($env->synthesis->id, $userid);
            return $ids(thesesynthesis_search($links, $userid, $filters, $questions));
        };

        // La DEVE trouve tous les dossiers.
        $this->assertSame($ids([(object) ['dossier' => $env->public], (object) ['dossier' => $env->confidential],
            (object) ['dossier' => $env->submitted]]), $search($env->deve->id, ['text' => 'rage']));
        // Un enseignant : seulement la thèse enregistrée et non confidentielle.
        $this->assertSame([(int) $env->public->id], $search($env->teacher->id, ['text' => 'rage']));
        // Le directeur trouve aussi celles qu'il dirige.
        $this->assertSame([(int) $env->public->id, (int) $env->submitted->id], $search($env->director->id, ['text' => 'rage']));

        // Texte sans accents, sur plusieurs champs (titre, directeur, réponses).
        $this->assertSame([(int) $env->public->id], $search($env->deve->id, ['text' => 'epidemiologie directrice']));
        $this->assertSame([(int) $env->public->id], $search($env->deve->id, ['text' => 'virologie']));
        // Année de soutenance.
        $this->assertSame(
            [(int) $env->public->id, (int) $env->confidential->id],
            $search($env->deve->id, ['year' => 2027])
        );
        // Réponses : un choix d'un QCM, un texte contenu.
        $this->assertSame([(int) $env->confidential->id], $search($env->deve->id, ['answers' => [$list => 'Chat']]));
        $this->assertSame([(int) $env->public->id], $search($env->deve->id, ['answers' => [$list => 'Renard',
            $text => 'VIROLOGIE']]));
        $this->assertSame([], $search($env->teacher->id, ['answers' => [$list => 'Chat']]));
    }

    /**
     * Thèse confidentielle dans le suivi multi-promotions : la bibliothèque ne la trouve ni par son
     * sujet ni par ses réponses au questionnaire, et ne voit ni son titre ni ses réponses ; la DEVE
     * la trouve et la voit.
     */
    public function test_confidential_thesis_is_not_disclosed(): void {
        $this->resetAfterTest();
        $env = $this->setup_env();
        $roleid = these_setup_default_roles();
        $library = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($library->id, $env->activities[0]->course->id, $roleid);
        accesslib_clear_all_caches_for_unit_testing();

        $text = these_save_question($env->scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        $questions = these_get_questions($env->scope);
        these_save_answers($env->confidential, $questions, ['q' . $text => 'Unité secrète de vaccinologie']);

        $links = thesesynthesis_get_active_links($env->synthesis->id, $library->id);
        $this->assertTrue($links[0]->rights->library);
        $search = function (array $filters) use ($links, $library, $questions) {
            return array_map(function ($row) {
                return (int) $row->dossier->id;
            }, thesesynthesis_search($links, $library->id, $filters, $questions));
        };

        // Ni par le titre, ni par les réponses.
        $this->assertSame([], $search(['text' => 'confidentiel']));
        $this->assertSame([], $search(['text' => 'vaccinologie']));
        $this->assertSame([], $search(['answers' => [$text => 'secrète']]));
        $this->assertSame([(int) $env->public->id], $search(['text' => 'rage']));

        // Trouvée par un critère qui ne révèle pas le sujet, elle reste masquée.
        $rows = thesesynthesis_search($links, $library->id, ['year' => 2027], $questions);
        $confidential = array_values(array_filter($rows, function ($row) use ($env) {
            return (int) $row->dossier->id === (int) $env->confidential->id;
        }));
        $this->assertCount(1, $confidential);
        $this->assertFalse($confidential[0]->knowssubject);
        $this->assertSame([], $confidential[0]->answers);

        // Dossiers suivis : titre masqué, recherche sans effet sur le titre.
        $rows = thesesynthesis_get_dossiers($links, $library->id);
        foreach ($rows as $row) {
            $this->assertSame((int) $row->dossier->id !== (int) $env->confidential->id, $row->knowssubject);
        }
        $this->assertSame([], thesesynthesis_get_dossiers($links, $library->id, ['search' => 'confidentiel']));

        // La DEVE connaît le sujet.
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->deve->id);
        $rows = thesesynthesis_get_dossiers($links, $env->deve->id, ['search' => 'confidentiel']);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->knowssubject);
        $found = thesesynthesis_search($links, $env->deve->id, ['text' => 'vaccinologie'], $questions);
        $this->assertSame([(int) $env->confidential->id], array_map(function ($row) {
            return (int) $row->dossier->id;
        }, $found));
    }
}
