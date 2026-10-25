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
 * Suivi des thèses sur plusieurs promotions : liens, dossiers suivis selon les droits à la source,
 * jurys de l'utilisateur.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::thesesynthesis_get_dossiers
 * @covers     ::thesesynthesis_get_juries
 * @covers     ::thesesynthesis_get_available_activities
 * @covers     \mod_these\local\planning_page
 */
final class synthesis_test extends \advanced_testcase {
    /**
     * Deux promotions (deux cours), un directeur dans les deux, une DEVE dans la première seulement,
     * et une activité de suivi qui les lie.
     *
     * @return \stdClass
     */
    private function setup_promotions(): \stdClass {
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        $env = new \stdClass();
        $env->director = $gen->create_user(['email' => 'directeur@example.org']);
        $env->deve = $gen->create_user();
        $env->juror = $gen->create_user(['email' => 'jury@example.org']);
        $env->activities = [];
        foreach (['Promo 2026', 'Promo 2027'] as $i => $name) {
            $course = $gen->create_course(['fullname' => $name, 'shortname' => 'P' . $i]);
            $these = $gen->create_module('these', ['course' => $course->id]);
            $gen->enrol_user($env->director->id, $course->id, 'teacher');
            if ($i === 0) {
                $gen->enrol_user($env->deve->id, $course->id, 'editingteacher');
            }
            $student = $gen->create_and_enrol($course, 'student');
            $dossier = $thesegen->create_dossier($these, $student->id, ['directorid' => $env->director->id,
                'status' => $i === 0 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_SUBJECT_APPROVED]);
            $env->activities[] = (object) ['course' => $course, 'these' => $these, 'dossier' => $dossier];
        }
        $synthcourse = $gen->create_course();
        $env->synthesis = $gen->create_module('thesesynthesis', ['course' => $synthcourse->id]);
        thesesynthesis_set_links($env->synthesis->id, array_map(function ($activity) {
            return $activity->these->cmid;
        }, $env->activities));
        return $env;
    }

    /**
     * Le directeur voit ses thèses des deux promotions, la DEVE tous les dossiers de la sienne ;
     * « à traiter » selon l'acteur attendu.
     */
    public function test_followed_dossiers(): void {
        $this->resetAfterTest();
        $env = $this->setup_promotions();

        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->director->id);
        $rows = thesesynthesis_get_dossiers($links, $env->director->id);
        $this->assertCount(2, $rows);
        $todo = array_values(array_filter($rows, function ($row) {
            return $row->todo;
        }));
        $this->assertCount(1, $todo);
        $this->assertEquals($env->activities[0]->dossier->id, $todo[0]->dossier->id);

        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->deve->id);
        $rows = thesesynthesis_get_dossiers($links, $env->deve->id);
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->todo);

        // Filtre de statut et de promotion.
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->director->id);
        $this->assertCount(1, thesesynthesis_get_dossiers($links, $env->director->id, ['status' => THESE_STATUS_SUBJECT_APPROVED]));
        $this->assertCount(1, thesesynthesis_get_dossiers(
            $links,
            $env->director->id,
            ['cmid' => $env->activities[1]->these->cmid]
        ));

        // Activités proposées au lien : celles où l'on voit tous les dossiers.
        $available = thesesynthesis_get_available_activities($env->deve->id);
        $this->assertSame([(int) $env->activities[0]->these->cmid], array_keys($available));
        $this->assertSame([], thesesynthesis_get_available_activities($env->director->id));
    }

    /**
     * Jurys de l'utilisateur, par son adresse ou par l'annuaire.
     */
    public function test_juries(): void {
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $jurorid = \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => 'Jury', 'firstname' => 'Paul',
            'email' => 'JURY@example.org', 'status' => 'pr', 'institution' => '', 'active' => 1]);
        these_save_jury($env->activities[0]->dossier->id, [
            (object) ['juryrole' => 'president', 'fullname' => 'Paul JURY', 'jurytitle' => 'Pr', 'institution' => '',
                'email' => '', 'jurorid' => $jurorid, 'jurystatus' => 'pr'],
        ]);
        these_save_jury($env->activities[1]->dossier->id, [
            (object) ['juryrole' => 'member', 'fullname' => 'Paul Jury', 'jurytitle' => 'Pr', 'institution' => '',
                'email' => 'jury@example.org', 'jurorid' => 0, 'jurystatus' => 'pr'],
        ]);
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->juror->id);
        $juries = thesesynthesis_get_juries($links, $env->juror);
        $this->assertCount(2, $juries);
        $this->assertSame([], thesesynthesis_get_juries($links, $env->director));
    }

    /**
     * Planning des soutenances des promotions suivies : chacun n'y voit que ce qu'il voit à la source,
     * avec la promotion de chaque étudiant.
     */
    public function test_planning_page(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $monday = these_planning_week_start(time());
        foreach ($env->activities as $i => $activity) {
            $DB->update_record('these_dossier', (object) ['id' => $activity->dossier->id, 'status' => THESE_STATUS_SCHEDULED,
                'title' => 'Thèse ' . $i, 'defensedate' => $monday + DAYSECS + $i * HOURSECS, 'defenselocation' => 'Salle ' . $i]);
        }
        $cm = get_coursemodule_from_instance('thesesynthesis', $env->synthesis->id);
        $context = \context_module::instance($cm->id);
        $render = function ($user) use ($env, $cm, $context) {
            global $PAGE;
            $this->setUser($user);
            $PAGE = new \moodle_page();
            $PAGE->set_context($context);
            $PAGE->set_url(new \moodle_url('/mod/thesesynthesis/planning.php', ['id' => $cm->id]));
            ob_start();
            \mod_these\local\planning_page::output(
                thesesynthesis_get_active_links($env->synthesis->id, $user->id),
                new \moodle_url('/mod/thesesynthesis/planning.php', ['id' => $cm->id]),
                these_synthesis_scope($env->synthesis->id),
                $env->activities[0]->these,
                true,
                'Planning'
            );
            return ob_get_clean();
        };

        $html = $render($env->director);
        $this->assertStringContainsString('Thèse 0', $html);
        $this->assertStringContainsString('Thèse 1', $html);
        $this->assertStringContainsString('P1', $html);
        $this->assertStringContainsString(get_string('planningcount', 'mod_these', 2), $html);

        // La DEVE de la première promotion seulement.
        $html = $render($env->deve);
        $this->assertStringContainsString('Thèse 0', $html);
        $this->assertStringNotContainsString('Thèse 1', $html);
    }
}
