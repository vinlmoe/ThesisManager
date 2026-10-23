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
 * Import CSV de dossiers existants : simulation, création de dossiers actifs, refus des lignes
 * en erreur sans bloquer les autres.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_import_dossiers
 */
final class import_test extends these_testcase {
    /**
     * Contenu CSV de test : une ligne valide, un étudiant inconnu, un doublon, un directeur
     * inconnu.
     *
     * @return string
     */
    private function csv(): string {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        return "student;title;director;devenumber;libraryref\n"
            . "{$this->student->username};Sujet importé;{$this->director->email};2019-004;NOT-1\n"
            . "inconnu;Sujet;;;\n"
            . "{$this->student->email};Doublon;;;\n"
            . "{$other->username};Sujet;personne@nulle.part;;\n";
    }

    /**
     * La simulation ne crée rien mais rend le même compte rendu.
     */
    public function test_dry_run(): void {
        global $DB;
        $this->setup_activity();
        $report = these_import_dossiers($this->these, $this->csv(), 'semicolon', 'UTF-8', true, $this->deve->id);
        $this->assertEquals([true, false, false, false], array_column($report, 'ok'));
        $this->assertEquals(0, $DB->count_records('these_dossier', ['theseid' => $this->these->id]));
    }

    /**
     * L'import crée un dossier actif, avec son historique.
     */
    public function test_import(): void {
        global $DB;
        $this->setup_activity();
        $report = these_import_dossiers($this->these, $this->csv(), 'semicolon', 'UTF-8', false, $this->deve->id);
        $this->assertEquals(1, count(array_filter(array_column($report, 'ok'))));

        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertEquals(THESE_STATUS_ACTIVE, $dossier->status);
        $this->assertEquals($this->director->id, $dossier->directorid);
        $this->assertEquals('2019-004', $dossier->devenumber);
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'imported']));

        // Relancer l'import ne crée pas de doublon.
        $report = these_import_dossiers($this->these, $this->csv(), 'semicolon', 'UTF-8', false, $this->deve->id);
        $this->assertFalse($report[0]['ok']);
    }

    /**
     * Une colonne obligatoire manquante bloque tout l'import.
     */
    public function test_missing_column(): void {
        $this->setup_activity();
        $this->expectException(\moodle_exception::class);
        these_import_dossiers($this->these, "student;director\nx;y\n", 'semicolon', 'UTF-8', true, $this->deve->id);
    }
}
