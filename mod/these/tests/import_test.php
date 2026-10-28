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
 * Import de dossiers existants (CSV, Excel, OpenDocument) : simulation, création de dossiers actifs
 * ou soutenus, refus des lignes en erreur sans bloquer les autres ; colonnes de l'export.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_import_dossiers
 * @covers     \mod_these\local\import
 * @covers     \mod_these\local\rendering::register_row
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

    /**
     * Dossiers soutenus (corrections en cours, clos) avec date, mention et corrections ; statut,
     * date ou mention illisibles refusés ; co-directeur interne rattaché à l'annuaire.
     */
    public function test_import_states(): void {
        $this->setup_activity();
        $jurorid = \mod_these\local\jurors::save((object) ['title' => 'Dr', 'lastname' => 'Martin', 'firstname' => 'Claire',
            'email' => 'claire.martin@example.com', 'status' => 'mcf', 'institution' => 'École nationale vétérinaire',
            'active' => 1]);
        $users = [];
        for ($i = 0; $i < 6; $i++) {
            $users[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student')->username;
        }
        $rows = [
            ['Student', 'Title', 'Codirector', 'Status', 'DefenseDate', 'DefenseLocation', 'Mention', 'Corrections'],
            [$users[0], 'Soutenue', 'Dr MARTIN Claire', 'corrections', '12/06/2025 10:30', 'Amphi A', 'très honorable', 'Page 12'],
            [$users[1], 'Close', 'Pr Durand (Lyon)', 'File closed', 45820, '', 'felicitations', ''],
            [$users[2], 'En cours', 'claire martin', '', '', '', '', ''],
            [$users[3], 'Statut', '', 'scheduled', '12/06/2025', '', '', ''],
            [$users[4], 'Sans date', '', 'closed', '', '', '', ''],
            [$users[5], 'Mention', '', 'closed', '2025-06-12', '', 'passable', ''],
        ];
        $report = \mod_these\local\import::import_rows($this->these, $rows, false, $this->deve->id);
        $this->assertEquals([true, true, true, false, false, false], array_column($report, 'ok'));
        $this->assertStringContainsString('Dr MARTIN Claire', $report[0]['message']);

        $dossier = $this->dossier_of($users[0]);
        $this->assertEquals(THESE_STATUS_CORRECTIONS, $dossier->status);
        $this->assertEquals(make_timestamp(2025, 6, 12, 10, 30), $dossier->defensedate);
        $this->assertSame('Amphi A', $dossier->defenselocation);
        $this->assertEquals(THESE_RESULT_ADMITTED, $dossier->defenseresult);
        $this->assertSame('treshonorable', $dossier->mention);
        $this->assertEquals(1, $dossier->correctionsrequested);
        $this->assertSame('Page 12', $dossier->correctionsnote);
        $this->assertEquals($jurorid, $dossier->codirectorjurorid);
        $this->assertSame('Dr MARTIN Claire', $dossier->codirectorname);

        // Date du tableur (12/06/2025), 14 h par défaut ; co-directeur extérieur.
        $dossier = $this->dossier_of($users[1]);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
        $this->assertEquals(make_timestamp(2025, 6, 12, 14, 0), $dossier->defensedate);
        $this->assertSame('felicitations', $dossier->mention);
        $this->assertEquals(0, $dossier->correctionsrequested);
        $this->assertEquals(0, $dossier->codirectorjurorid);
        $this->assertSame('Pr Durand (Lyon)', $dossier->codirectorname);

        $dossier = $this->dossier_of($users[2]);
        $this->assertEquals(THESE_STATUS_ACTIVE, $dossier->status);
        $this->assertEquals($jurorid, $dossier->codirectorjurorid);
        $this->assertEquals(0, $dossier->defenseresult);

        // Colonnes ajoutées à l'export.
        $row = these_register_row($this->dossier_of($users[0]), $this->context, null, null);
        $this->assertSame(get_string('codirectorinternal', 'mod_these'), $row['codirectortype']);
        $this->assertSame('', $row['missingsignatures']);
        $this->assertSame('', $row['lastrefusal']);
        foreach (['a6', 'directortitle', 'universitypieces'] as $column) {
            $this->assertArrayHasKey($column, $row);
        }
    }

    /**
     * Fichier Excel : lu comme celui de l'A6, puis importé ligne à ligne.
     */
    public function test_import_excel(): void {
        global $CFG;
        $this->setup_activity();
        $autoload = $CFG->libdir . '/phpspreadsheet/vendor/autoload.php';
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') && file_exists($autoload)) {
            require_once($autoload);
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['student', 'title', 'status', 'defensedate'],
            [$this->student->email, 'Sujet Excel', 'closed', 45820.4375],
        ]);
        $path = make_request_directory() . '/dossiers.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        $rows = \mod_these\local\a6::read_rows($path, 'dossiers.xlsx');
        $report = \mod_these\local\import::import_rows($this->these, $rows, false, $this->deve->id);
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertSame('Sujet Excel', $dossier->title);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
        $this->assertEquals(make_timestamp(2025, 6, 12, 10, 30), $dossier->defensedate);
    }

    /**
     * Dossier importé d'un étudiant, par identifiant de connexion.
     *
     * @param string $username
     * @return \stdClass
     */
    private function dossier_of(string $username): \stdClass {
        global $DB;
        $userid = $DB->get_field('user', 'id', ['username' => $username]);
        return these_get_user_dossier($this->these->id, $userid);
    }
}
