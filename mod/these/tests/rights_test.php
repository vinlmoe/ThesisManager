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
require_once(__DIR__ . '/these_testcase.php');

/**
 * Droits : chaque acteur ne peut agir qu'à son étape, un directeur ne voit que les dossiers qu'il
 * dirige, et le rapport du directeur n'est pas communiqué à l'étudiant.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_get_rights
 * @covers     ::these_user_can_act
 * @covers     ::these_can_view_filearea
 */
final class rights_test extends these_testcase {
    /**
     * Chaque statut n'autorise que son acteur.
     */
    public function test_only_expected_actor_can_act(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['directorid' => $this->director->id]);
        $users = [
            'student' => $this->student,
            'director' => $this->director,
            'deve' => $this->deve,
            'library' => $this->librarian,
        ];
        foreach (these_all_statuses() as $status) {
            $dossier->status = $status;
            $expected = these_step_actor($status);
            foreach ($users as $actor => $user) {
                $rights = these_get_rights($this->context, $dossier, $user->id);
                $this->assertSame(
                    $actor === $expected,
                    these_user_can_act($dossier, $rights),
                    "Statut $status, acteur $actor"
                );
            }
        }
    }

    /**
     * Un directeur n'a aucun droit sur le dossier d'un étudiant qu'il ne dirige pas.
     */
    public function test_other_director_cannot_view(): void {
        $this->setup_activity();
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'directorid' => $this->director->id, 'status' => THESE_STATUS_SUBJECT_SUBMITTED,
        ]);
        $rights = these_get_rights($this->context, $dossier, $other->id);
        $this->assertFalse(these_can_view_dossier($rights));
        $this->assertFalse(these_user_can_act($dossier, $rights));
        $this->assertTrue(these_can_view_dossier(these_get_rights($this->context, $dossier, $this->director->id)));
    }

    /**
     * Le rapport du directeur est visible du directeur, de la DEVE et de la bibliothèque, pas de
     * l'étudiant ; ses autres documents lui restent accessibles.
     */
    public function test_director_report_hidden_from_student(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['directorid' => $this->director->id]);

        $student = these_get_rights($this->context, $dossier, $this->student->id);
        $this->assertFalse(these_can_view_filearea('directorreport', $student));
        $this->assertTrue(these_can_view_filearea('manuscript', $student));
        foreach ([$this->director, $this->deve, $this->librarian] as $user) {
            $this->assertTrue(these_can_view_filearea('directorreport', these_get_rights($this->context, $dossier, $user->id)));
        }
    }

    /**
     * Seuls les enseignants autorisés à diriger sont proposés comme directeurs : la bibliothèque,
     * dont le rôle retire ce droit, n'y figure pas.
     */
    public function test_potential_directors(): void {
        $this->setup_activity();
        $directors = these_get_potential_directors($this->context);
        $this->assertArrayHasKey($this->director->id, $directors);
        $this->assertArrayHasKey($this->deve->id, $directors);
        $this->assertArrayNotHasKey($this->student->id, $directors);
        $this->assertArrayNotHasKey($this->librarian->id, $directors);
    }

    /**
     * La file « À traiter » d'un directeur ne contient que les dossiers qu'il dirige et qui
     * attendent son action.
     */
    public function test_director_queue(): void {
        $this->setup_activity();
        $gen = $this->getDataGenerator();
        $other = $gen->create_and_enrol($this->course, 'student');
        $third = $gen->create_and_enrol($this->course, 'student');
        $mine = $this->generator->create_dossier($this->these, $this->student->id, [
            'directorid' => $this->director->id, 'status' => THESE_STATUS_DEFENSE_SUBMITTED,
        ]);
        $this->generator->create_dossier($this->these, $other->id, [
            'directorid' => $this->director->id, 'status' => THESE_STATUS_ACTIVE,
        ]);
        $this->generator->create_dossier($this->these, $third->id, [
            'directorid' => $this->deve->id, 'status' => THESE_STATUS_SUBJECT_SUBMITTED,
        ]);
        $queue = these_get_dossiers($this->these->id, ['queue' => ['director'], 'queuedirectorid' => $this->director->id]);
        $this->assertEquals([$mine->id], array_keys($queue));

        $devequeue = these_get_dossiers($this->these->id, ['queue' => ['deve'], 'queuedirectorid' => $this->deve->id]);
        $this->assertCount(0, $devequeue);
    }
}
