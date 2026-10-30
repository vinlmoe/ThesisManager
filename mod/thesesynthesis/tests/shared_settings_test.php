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
 * @covers     \mod_these\local\scopesettings
 * @covers     ::thesesynthesis_search
 * @covers     ::thesesynthesis_render_search_results
 * @covers     ::thesesynthesis_delete_instance
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('these_settings_scope')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\scopesettings::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_search')]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_render_search_results')]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_delete_instance')]
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
        $env->scope = \mod_thesesynthesis\local\scope::for_synthesis($env->synthesis->id);

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
        these_set_doctemplate($env->scope, 'conclusions', '<p>Conclusions communes {{student.lastname}}</p>');
        these_set_doctemplate(these_local_scope($env->activities[0]->these), 'conclusions', '<p>Conclusions locales</p>');
        $this->assertSame('<p>Conclusions communes {{student.lastname}}</p>', these_get_doctemplate($followed, 'conclusions'));
        $this->assertSame(these_doctemplate_default('conclusions'), these_get_doctemplate($local, 'conclusions'));
        $this->assertSame(
            '<p>Conclusions communes {{student.lastname}}</p>',
            these_get_doctemplate(these_settings_scope($env->activities[0]->these), 'conclusions')
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
        $this->assertSame('<p>Conclusions locales</p>', these_get_doctemplate($scope, 'conclusions'));
    }

    /**
     * Paramètres du circuit : ceux de l'activité de suivi valent pour les promotions suivies ; une
     * activité non suivie garde les siens. Aucun réglage du site n'est lu.
     */
    public function test_scope_settings(): void {
        $this->resetAfterTest();
        $env = $this->setup_env();
        $followed = $env->activities[0]->these;
        $alone = $env->activities[2]->these;
        $agent = $this->getDataGenerator()->create_user(['username' => 'agentsuivi']);
        \mod_these\local\scopesettings::set($env->scope, [
            'deveagent' => 'agentsuivi', 'institution' => 'École du suivi', 'dgname' => 'Pr Suivi',
        ]);
        \mod_these\local\scopesettings::set(these_local_scope($alone), ['institution' => 'École seule']);

        $this->assertSame('École du suivi', \mod_these\local\scopesettings::for_these($followed)['institution']);
        $this->assertSame('École seule', \mod_these\local\scopesettings::for_these($alone)['institution']);
        $this->assertSame([(int) $agent->id], array_map('intval', array_keys(these_deve_agents($followed))));
        $this->assertSame([], these_deve_agents($alone));
        $this->assertSame('Pr Suivi', these_external_signers($env->public)['dg']['name']);
        $this->assertFalse(get_config('mod_these', 'deveagent'));

        // Retirée du suivi, l'activité reprend ses propres paramètres.
        thesesynthesis_set_links($env->synthesis->id, [$env->activities[1]->these->cmid]);
        $this->assertSame([], these_deve_agents($followed));
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
     * Thèse confidentielle dans le suivi multi-promotions : un détenteur de mod/these:viewall sans rôle
     * de service ne la trouve ni par son sujet ni par ses réponses au questionnaire, et ne voit ni son
     * titre ni ses réponses ; la DEVE et la bibliothèque la trouvent et la voient.
     */
    public function test_confidential_thesis_is_not_disclosed(): void {
        $this->resetAfterTest();
        $env = $this->setup_env();
        $gen = $this->getDataGenerator();
        $course = $env->activities[0]->course;
        $library = $gen->create_user(['username' => 'bibliosuivi']);
        $gen->enrol_user($library->id, $course->id, 'teacher');
        \mod_these\local\scopesettings::set($env->scope, ['libraryagent' => 'bibliosuivi']);
        $roleid = $gen->create_role(['shortname' => 'theseviewall']);
        assign_capability('mod/these:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        assign_capability('mod/these:viewall', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $viewall = $gen->create_user();
        $gen->enrol_user($viewall->id, $course->id, $roleid);
        accesslib_clear_all_caches_for_unit_testing();

        $text = these_save_question($env->scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        $questions = these_get_questions($env->scope);
        these_save_answers($env->confidential, $questions, ['q' . $text => 'Unité secrète de vaccinologie']);
        $ids = function (array $rows) {
            return array_map(function ($row) {
                return (int) $row->dossier->id;
            }, $rows);
        };

        $links = thesesynthesis_get_active_links($env->synthesis->id, $viewall->id);
        $this->assertTrue($links[0]->rights->viewall);
        $this->assertFalse($links[0]->rights->library);
        $search = function (array $filters) use ($links, $viewall, $questions, $ids) {
            return $ids(thesesynthesis_search($links, $viewall->id, $filters, $questions));
        };

        // Ni par le titre, ni par les réponses.
        $this->assertSame([], $search(['text' => 'confidentiel']));
        $this->assertSame([], $search(['text' => 'vaccinologie']));
        $this->assertSame([], $search(['answers' => [$text => 'secrète']]));
        $this->assertSame([(int) $env->public->id], $search(['text' => 'rage']));

        // Trouvée par un critère qui ne révèle pas le sujet, elle reste masquée.
        $rows = thesesynthesis_search($links, $viewall->id, ['year' => 2027], $questions);
        $confidential = array_values(array_filter($rows, function ($row) use ($env) {
            return (int) $row->dossier->id === (int) $env->confidential->id;
        }));
        $this->assertCount(1, $confidential);
        $this->assertFalse($confidential[0]->knowssubject);
        $this->assertSame([], $confidential[0]->answers);

        // Dossiers suivis : titre masqué, recherche sans effet sur le titre.
        foreach (thesesynthesis_get_dossiers($links, $viewall->id) as $row) {
            $this->assertSame((int) $row->dossier->id !== (int) $env->confidential->id, $row->knowssubject);
        }
        $this->assertSame([], thesesynthesis_get_dossiers($links, $viewall->id, ['search' => 'confidentiel']));

        // La DEVE et la bibliothèque connaissent le sujet.
        foreach (['deve' => $env->deve, 'library' => $library] as $name => $user) {
            $links = thesesynthesis_get_active_links($env->synthesis->id, $user->id);
            $rows = thesesynthesis_get_dossiers($links, $user->id, ['search' => 'confidentiel']);
            $this->assertCount(1, $rows, $name);
            $this->assertTrue($rows[0]->knowssubject, $name);
            $found = thesesynthesis_search($links, $user->id, ['text' => 'vaccinologie'], $questions);
            $this->assertSame([(int) $env->confidential->id], $ids($found), $name);
        }
    }

    /**
     * Une thèse confidentielle se trouve dans la recherche (étudiant, année, promotion) et figure au
     * planning, mais, pour qui ne doit pas connaître son sujet, titre, mots-clés et résumé y sont
     * remplacés par « Thèse confidentielle » ; elle ne se trouve jamais par son sujet.
     */
    public function test_confidential_thesis_is_found_but_masked(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_env();
        $student = $DB->get_record('user', ['id' => $env->confidential->userid]);
        $DB->update_record('these_dossier', (object) ['id' => $env->confidential->id, 'title' => 'SECRETTITRE',
            'summary' => 'SECRETRESUME', 'keywords' => 'SECRETMOTCLE ; chien', 'status' => THESE_STATUS_SCHEDULED,
            'defensedate' => these_planning_week_start(time()) + DAYSECS]);
        $text = these_save_question($env->scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        $questions = these_get_questions($env->scope);
        these_save_answers($this->reload_dossier($env->confidential->id), $questions, ['q' . $text => 'SECRETREPONSE']);
        $secrets = ['SECRETTITRE', 'SECRETRESUME', 'SECRETMOTCLE', 'SECRETREPONSE'];

        // Un enseignant qui ne dirige pas la thèse : trouvée par l'étudiant ou l'année, jamais par le sujet.
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->teacher->id);
        $search = function (array $filters) use ($links, $env, $questions) {
            return thesesynthesis_search($links, $env->teacher->id, $filters, $questions);
        };
        foreach (['SECRETTITRE', 'SECRETRESUME', 'SECRETMOTCLE', 'SECRETREPONSE'] as $word) {
            $this->assertSame([], $search(['text' => $word]), $word);
        }
        $this->assertSame([], $search(['answers' => [$text => 'SECRETREPONSE']]));
        foreach ([['text' => $student->lastname], ['year' => (int) userdate(time(), '%Y')]] as $filters) {
            $rows = array_values(array_filter($search($filters), function ($row) use ($env) {
                return (int) $row->dossier->id === (int) $env->confidential->id;
            }));
            $this->assertCount(1, $rows, json_encode($filters));
            $this->assertFalse($rows[0]->knowssubject);
            $this->assertFalse($rows[0]->canopen);
            $html = thesesynthesis_render_search_results($rows, $questions);
            foreach ($secrets as $secret) {
                $this->assertStringNotContainsString($secret, $html);
            }
            $this->assertSame(3, substr_count($html, get_string('confidentialtitle', 'mod_these')));
            $this->assertStringContainsString(fullname($student), $html);
        }

        // La DEVE : trouvée par son sujet, tout est affiché.
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->deve->id);
        $rows = thesesynthesis_search($links, $env->deve->id, ['text' => 'SECRETMOTCLE'], $questions);
        $this->assertCount(1, $rows);
        $html = thesesynthesis_render_search_results($rows, $questions);
        foreach ($secrets as $secret) {
            $this->assertStringContainsString($secret, $html);
        }

        // Planning des promotions suivies : la soutenance y figure, sujet masqué pour qui ne le connaît pas.
        $monday = these_planning_week_start(time());
        $viewall = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'theseviewall']);
        assign_capability('mod/these:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        assign_capability('mod/these:viewall', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $this->getDataGenerator()->enrol_user($viewall->id, $env->activities[0]->course->id, $roleid);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($viewall);
        $links = thesesynthesis_get_active_links($env->synthesis->id, $viewall->id);
        $defenses = these_get_defenses($links, $monday, these_planning_shift_week($monday, 1), $viewall->id);
        $this->assertCount(1, $defenses);
        $this->assertSame(get_string('confidentialtitle', 'mod_these'), these_defense_row($defenses[0])['title']);
        $this->assertStringNotContainsString('SECRETTITRE', these_render_planning($defenses, true));
    }

    /**
     * Dossier relu depuis la base.
     *
     * @param int $id
     * @return \stdClass
     */
    private function reload_dossier(int $id): \stdClass {
        global $DB;
        return $DB->get_record('these_dossier', ['id' => $id], '*', MUST_EXIST);
    }
}
