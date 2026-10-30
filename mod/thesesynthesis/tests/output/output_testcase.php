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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

/**
 * Socle des tests des composants de rendu de mod_thesesynthesis : deux promotions suivies, une thèse
 * confidentielle dont chaque donnée révélant le sujet porte un marqueur, un juré, et un enseignant
 * qui ne connaît pas le sujet.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class output_testcase extends \advanced_testcase {
    /** @var string[] Marqueurs de chaque donnée qui révèle le sujet. */
    const SECRETS = [
        'title' => 'SECRETTITRE',
        'keywords' => 'SECRETMOTCLE',
        'summary' => 'SECRETRESUME',
        'answer' => 'SECRETREPONSE',
    ];

    /** @var \stdClass Environnement : synthesis, cm, context, director, deve, juror, teacher, activities, confidential, scope. */
    protected $env;

    /**
     * Prépare les promotions, l'activité de suivi et les dossiers.
     *
     * @return void
     */
    protected function setup_env(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        $env = new \stdClass();
        $env->director = $gen->create_user(['firstname' => 'Diane', 'lastname' => 'Directrice']);
        $env->deve = $gen->create_user(['firstname' => 'Denis', 'lastname' => 'Scolarité']);
        $env->juror = $gen->create_user(['firstname' => 'Jules', 'lastname' => 'Juré', 'email' => 'jury@example.org']);
        $env->teacher = $gen->create_user(['firstname' => 'Tom', 'lastname' => 'Enseignant']);
        $synthcourse = $gen->create_course(['fullname' => 'Suivi', 'shortname' => 'S']);
        $env->synthesis = $gen->create_module('thesesynthesis', ['course' => $synthcourse->id]);
        $env->cm = get_coursemodule_from_instance('thesesynthesis', $env->synthesis->id);
        $env->context = \context_module::instance($env->cm->id);
        $roles = ['director' => 'teacher', 'deve' => 'editingteacher', 'juror' => 'teacher', 'teacher' => 'teacher'];
        foreach ($roles as $user => $role) {
            $gen->enrol_user($env->$user->id, $synthcourse->id, $role);
        }
        $env->activities = [];
        foreach (['Promo 2026', 'Promo 2027'] as $i => $name) {
            $course = $gen->create_course(['fullname' => $name, 'shortname' => 'P' . $i]);
            $these = $gen->create_module('these', ['course' => $course->id, 'name' => 'Thèses ' . $i]);
            $gen->enrol_user($env->director->id, $course->id, 'teacher');
            $gen->enrol_user($env->teacher->id, $course->id, 'teacher');
            if ($i === 0) {
                $gen->enrol_user($env->deve->id, $course->id, 'editingteacher');
            }
            $env->activities[] = (object) ['course' => $course, 'these' => $these];
        }
        thesesynthesis_set_links($env->synthesis->id, array_map(function ($activity) {
            return $activity->these->cmid;
        }, $env->activities));
        $env->scope = \mod_thesesynthesis\local\scope::for_synthesis($env->synthesis->id);
        $question = these_save_question($env->scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        these_save_question($env->scope, (object) ['qtype' => 'list', 'label' => 'Espèce', 'options' => "Chien\nChat",
            'required' => 0]);

        $course = $env->activities[0]->course;
        $env->confidential = $thesegen->create_dossier($env->activities[0]->these, $gen->create_and_enrol($course, 'student')->id, [
            'directorid' => $env->director->id, 'status' => THESE_STATUS_SCHEDULED, 'confidential' => 1,
            'title' => self::SECRETS['title'] . ' sur la rage', 'keywords' => 'chien ; ' . self::SECRETS['keywords'],
            'summary' => 'Résumé ' . self::SECRETS['summary'], 'defensedate' => make_timestamp(2026, 6, 15, 14),
            'defenselocation' => 'Amphithéâtre A',
        ]);
        these_save_answers($env->confidential, these_get_questions($env->scope), ['q' . $question => self::SECRETS['answer']]);
        these_save_jury($env->confidential->id, [(object) ['juryrole' => 'president', 'fullname' => 'Jules Juré',
            'jurytitle' => 'Pr', 'institution' => '', 'email' => 'jury@example.org', 'jurorid' => 0, 'jurystatus' => 'pr',
            'eligible' => true]], $env->deve->id);
        $thesegen->create_dossier($env->activities[1]->these, $gen->create_and_enrol($env->activities[1]->course, 'student')->id, [
            'directorid' => $env->director->id, 'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'title' => 'Arthrose du chien',
        ]);
        $this->env = $env;
    }

    /**
     * Renderer de mod_thesesynthesis.
     *
     * @return renderer
     */
    protected function renderer(): renderer {
        global $PAGE;
        $PAGE->set_url(new \moodle_url('/mod/thesesynthesis/view.php'));
        return $PAGE->get_renderer('mod_thesesynthesis');
    }

    /**
     * Contexte d'un composant, sérialisé en JSON.
     *
     * @param \templatable $component
     * @return string
     */
    protected function context_json(\templatable $component): string {
        return json_encode($component->export_for_template($this->renderer()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Vérifie qu'aucun marqueur du sujet n'apparaît dans un texte.
     *
     * @param string $text
     * @param string $message
     * @return void
     */
    protected function assert_no_secret(string $text, string $message = ''): void {
        foreach (self::SECRETS as $field => $secret) {
            $this->assertStringNotContainsString($secret, $text, trim("$message ($field)"));
        }
    }

    /**
     * Liens actifs pour un utilisateur.
     *
     * @param \stdClass $user
     * @return array Voir thesesynthesis_get_active_links().
     */
    protected function links_for(\stdClass $user): array {
        return thesesynthesis_get_active_links($this->env->synthesis->id, $user->id);
    }
}
