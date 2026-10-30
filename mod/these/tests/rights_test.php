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
 * @covers     ::these_generate_documents
 * @covers     \mod_these\local\documents
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
     * La zone « Documents générés », que l'étudiant consulte, ne reçoit jamais les documents réservés
     * au personnel (grille, rapport déposé par le directeur) ; l'archive transmise à l'université, si.
     */
    public function test_generated_documents_hide_staff_only_from_student(): void {
        $this->setup_activity(['universityemail' => 'scolarite@univ.example.com']);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_CONFORM,
            'directorid' => $this->director->id,
            'defensedate' => time() + 10 * DAYSECS,
            'conformitygrid' => json_encode(['fondlist' => 1]),
        ]);
        $this->assertTrue(these_document_available($dossier, 'grid'));
        $student = these_get_rights($this->context, $dossier, $this->student->id);
        $this->assertTrue(these_can_view_filearea('generated', $student));
        $this->assertFalse(these_can_view_document('grid', $student));
        // Rapport déposé par le directeur : transmis à l'université, pas à l'étudiant.
        get_file_storage()->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_these',
            'filearea' => 'directorreport', 'itemid' => $dossier->id, 'filepath' => '/', 'filename' => 'rapport.pdf'], '%PDF');
        $this->assertFalse(these_can_view_filearea('directorreport', $student));

        $files = these_generate_documents($this->these, $this->context, $dossier, $this->deve->id);
        $names = array_map(function ($file) {
            return $file->get_filename();
        }, $files);
        $this->assertContains(these_document_filename('proces-verbal'), $names);
        $this->assertContains(these_document_filename('conformity'), $names);
        foreach (these_document_definitions() as $doctype => $definition) {
            if ($definition['staffonly']) {
                $this->assertNotContains(these_document_filename($doctype), $names);
            }
        }

        $archive = \mod_these\local\documents::archive_files($this->these, $this->context, $dossier);
        foreach ($names as $name) {
            $this->assertArrayHasKey($name, $archive);
        }
        $grid = these_document_filename('grid');
        $this->assertArrayHasKey($grid, $archive);
        $this->assertStringStartsWith('%PDF', file_get_contents($archive[$grid]));
        $this->assertArrayHasKey('rapport-directeur-rapport.pdf', $archive);
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

    /**
     * Accès à l'activité : la capacité de consultation, ou les droits d'un service (DEVE par sa
     * capacité, bibliothèque par la liste de ses agents).
     */
    public function test_require_view(): void {
        $this->setup_activity();
        $this->setUser($this->student);
        $this->assertFalse(these_require_view($this->context)->deve);
        $this->setUser($this->deve);
        $this->assertTrue(these_require_view($this->context)->deve);
        $this->setUser($this->librarian);
        $rights = these_require_view($this->context);
        $this->assertTrue($rights->library);
        $this->assertFalse($rights->deve);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        these_require_view($this->context);
    }
}
