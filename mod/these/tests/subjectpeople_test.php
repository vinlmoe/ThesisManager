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

use mod_these\form\subject_form;
use mod_these\local\dossiers;
use mod_these\local\jurors;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Personnes du sujet : titre du directeur repris de l'annuaire des enseignants, co-directeur
 * interne choisi dans l'annuaire ou extérieur saisi.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\dossiers::people_fields
 * @covers     \mod_these\local\jurors::titles_for_users
 */
final class subjectpeople_test extends these_testcase {
    /**
     * Titre du directeur : celui de l'annuaire s'il y figure, sinon celui saisi.
     */
    public function test_director_title(): void {
        $this->setup_activity();
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        jurors::save((object) ['title' => 'Pr', 'lastname' => $this->director->lastname,
            'firstname' => $this->director->firstname, 'email' => $this->director->email, 'status' => 'pr',
            'institution' => 'École nationale vétérinaire', 'active' => 1]);

        $this->assertSame([(int) $this->director->id => 'Pr'], jurors::titles_for_users([$this->director->id, $other->id]));

        $dossier = $this->generator->create_dossier($this->these, $this->student->id);
        $data = (object) ['directorid' => $this->director->id, 'directortitle' => 'Dr'];
        $dossier = these_save_subject($this->these, $this->cm, $dossier, $data, false, $this->student->id);
        $this->assertSame('Pr', $this->reload($dossier->id)->directortitle);

        $data = (object) ['directorid' => $other->id, 'directortitle' => 'Dr'];
        $dossier = these_save_subject($this->these, $this->cm, $dossier, $data, false, $this->student->id);
        $this->assertSame('Dr', $this->reload($dossier->id)->directortitle);
    }

    /**
     * Co-directeur interne (nom repris de l'annuaire), extérieur, ou aucun.
     */
    public function test_codirector(): void {
        $this->setup_activity();
        $jurorid = jurors::save((object) ['title' => 'Dr', 'lastname' => 'Martin', 'firstname' => 'Claire',
            'email' => 'claire.martin@example.com', 'status' => 'mcf', 'institution' => 'École nationale vétérinaire',
            'active' => 1]);
        $this->assertSame([$jurorid => 'Dr MARTIN Claire'], jurors::internal_options($this->context));

        $dossier = $this->generator->create_dossier($this->these, $this->student->id);
        $save = function ($data) use (&$dossier) {
            $dossier = these_save_subject($this->these, $this->cm, $dossier, (object) $data, false, $this->student->id);
            return $this->reload($dossier->id);
        };

        $saved = $save(['codirectortype' => 'internal', 'codirectorjurorid' => $jurorid, 'codirectorname' => 'Saisi']);
        $this->assertEquals($jurorid, $saved->codirectorjurorid);
        $this->assertSame('Dr MARTIN Claire', $saved->codirectorname);
        $this->assertSame('internal', subject_form::codirector_type($saved));

        $saved = $save(['codirectortype' => 'external', 'codirectorjurorid' => $jurorid, 'codirectorname' => ' Pr Durand (Lyon) ']);
        $this->assertEquals(0, $saved->codirectorjurorid);
        $this->assertSame('Pr Durand (Lyon)', $saved->codirectorname);
        $this->assertSame('external', subject_form::codirector_type($saved));

        $saved = $save(['codirectortype' => '', 'codirectorjurorid' => $jurorid, 'codirectorname' => 'Pr Durand']);
        $this->assertEquals(0, $saved->codirectorjurorid);
        $this->assertSame('', $saved->codirectorname);
        $this->assertSame('', subject_form::codirector_type($saved));

        // Formulaire sans le choix (import, anciens appels) : co-directeur saisi tel quel.
        $this->assertSame([], dossiers::codirector_fields((object) ['codirectorname' => 'X']));

        $this->assertArrayHasKey('codirectorjurorid', subject_form::codirector_errors(['codirectortype' => 'internal']));
        $this->assertArrayHasKey('codirectorname', subject_form::codirector_errors(['codirectortype' => 'external']));
        $this->assertSame([], subject_form::codirector_errors(['codirectortype' => '']));
    }
}
