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
use mod_these\local\jurors;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Personnes du sujet : titre du directeur repris de l'annuaire des enseignants, examinateur interne
 * enregistré comme directeur du dossier quand le directeur est extérieur.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\subjects::people_fields
 * @covers     \mod_these\local\jurors::titles_for_users
 * @covers     \mod_these\form\subject_form::apply_examiner
 */
final class subjectpeople_test extends these_testcase {
    /**
     * Titre du directeur : celui de l'annuaire s'il y figure, sinon celui des données transmises (import).
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
     * Directeur extérieur : l'examinateur interne choisi devient le directeur du dossier, avec son
     * titre de l'annuaire ; directeur interne : l'examinateur éventuellement choisi auparavant est ignoré.
     */
    public function test_internal_examiner(): void {
        $this->setup_activity();
        $examiner = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        jurors::save((object) ['title' => 'Dr', 'lastname' => $examiner->lastname,
            'firstname' => $examiner->firstname, 'email' => $examiner->email, 'status' => 'mcf',
            'institution' => 'École nationale vétérinaire', 'active' => 1]);

        $data = subject_form::apply_examiner((object) ['externaldirector' => 1, 'directorid' => $this->director->id,
            'examinerid' => $examiner->id, 'extdirectorname' => 'Pr Durand']);
        $this->assertEquals($examiner->id, $data->directorid);
        $this->assertFalse(property_exists($data, 'examinerid'));

        $dossier = $this->generator->create_dossier($this->these, $this->student->id);
        $dossier = these_save_subject($this->these, $this->cm, $dossier, $data, false, $this->student->id);
        $saved = $this->reload($dossier->id);
        $this->assertEquals($examiner->id, $saved->directorid);
        $this->assertEquals(1, $saved->externaldirector);
        $this->assertSame('Pr Durand', $saved->extdirectorname);
        $this->assertSame('Dr', $saved->directortitle);

        $data = subject_form::apply_examiner((object) ['externaldirector' => 0, 'directorid' => $this->director->id,
            'examinerid' => $examiner->id]);
        $this->assertEquals($this->director->id, $data->directorid);
        $this->assertFalse(property_exists($data, 'examinerid'));
    }
}
