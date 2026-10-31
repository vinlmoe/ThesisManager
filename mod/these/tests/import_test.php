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
#[\PHPUnit\Framework\Attributes\CoversFunction('these_import_dossiers')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\import::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\rendering::class)]
final class import_test extends these_testcase {
    /**
     * Contenu CSV de test : une ligne valide, un étudiant inconnu, un doublon, un directeur
     * inconnu, un étudiant non inscrit au cours, un directeur non inscrit au cours.
     *
     * @return string
     */
    private function csv(): string {
        $gen = $this->getDataGenerator();
        $other = $gen->create_and_enrol($this->course, 'student');
        $third = $gen->create_and_enrol($this->course, 'student');
        $notenrolled = $gen->create_user();
        $outsidedirector = $gen->create_user();
        return "student;title;director;devenumber;libraryref\n"
            . "{$this->student->username};Sujet importé;{$this->director->email};2019-004;NOT-1\n"
            . "inconnu;Sujet;;;\n"
            . "{$this->student->email};Doublon;;;\n"
            . "{$other->username};Sujet;personne@nulle.part;;\n"
            . "{$notenrolled->username};Sujet;;;\n"
            . "{$third->username};Sujet;{$outsidedirector->email};;\n";
    }

    /**
     * La simulation ne crée rien mais rend le même compte rendu.
     */
    public function test_dry_run(): void {
        global $DB;
        $this->setup_activity();
        $report = these_import_dossiers($this->these, $this->csv(), 'semicolon', 'UTF-8', true, $this->deve->id);
        $this->assertEquals([true, false, false, false, false, false], array_column($report, 'ok'));
        $this->assertSame(get_string('importusernotenrolled', 'mod_these', $report[4]['student']), $report[4]['message']);
        $this->assertStringContainsString(get_string('importusernotenrolled', 'mod_these', ''), $report[5]['message']);
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
        $users = [];
        for ($i = 0; $i < 6; $i++) {
            $users[] = $this->getDataGenerator()->create_and_enrol($this->course, 'student')->username;
        }
        $rows = [
            ['Student', 'Title', 'Status', 'DefenseDate', 'DefenseLocation', 'Mention', 'Corrections'],
            [$users[0], 'Soutenue', 'corrections', '12/06/2025 10:30', 'Amphi A', 'très honorable', 'Page 12'],
            [$users[1], 'Close', 'File closed', 45820, '', 'felicitations', ''],
            [$users[2], 'En cours', '', '', '', '', ''],
            [$users[3], 'Statut', 'scheduled', '12/06/2025', '', '', ''],
            [$users[4], 'Sans date', 'closed', '', '', '', ''],
            [$users[5], 'Mention', 'closed', '2025-06-12', '', 'passable', ''],
        ];
        $report = \mod_these\local\import::import_rows($this->these, $rows, false, $this->deve->id);
        $this->assertEquals([true, true, true, false, false, false], array_column($report, 'ok'));

        $dossier = $this->dossier_of($users[0]);
        $this->assertEquals(THESE_STATUS_CORRECTIONS, $dossier->status);
        $this->assertEquals(make_timestamp(2025, 6, 12, 10, 30), $dossier->defensedate);
        $this->assertSame('Amphi A', $dossier->defenselocation);
        $this->assertEquals(THESE_RESULT_ADMITTED, $dossier->defenseresult);
        $this->assertSame('treshonorable', $dossier->mention);
        $this->assertEquals(1, $dossier->correctionsrequested);
        $this->assertSame('Page 12', $dossier->correctionsnote);

        // Date du tableur (12/06/2025), 14 h par défaut.
        $dossier = $this->dossier_of($users[1]);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
        $this->assertEquals(make_timestamp(2025, 6, 12, 14, 0), $dossier->defensedate);
        $this->assertSame('felicitations', $dossier->mention);
        $this->assertEquals(0, $dossier->correctionsrequested);

        $dossier = $this->dossier_of($users[2]);
        $this->assertEquals(THESE_STATUS_ACTIVE, $dossier->status);
        $this->assertEquals(0, $dossier->defenseresult);

        // Colonnes ajoutées à l'export.
        $row = these_register_row($this->dossier_of($users[0]), $this->context, null, null);
        $this->assertSame('', $row['extdirector']);
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
     * Importe un contenu CSV avec un séparateur et un encodage proposés par le formulaire d'import.
     *
     * @param string $content
     * @param string $delimiter Clé de csv_import_reader::get_delimiter_list().
     * @param string $encoding Clé de core_text::get_encodings().
     * @return array Compte rendu.
     */
    private function import_csv(string $content, string $delimiter = 'semicolon', string $encoding = 'UTF-8'): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');
        $this->assertArrayHasKey($delimiter, \csv_import_reader::get_delimiter_list());
        $this->assertArrayHasKey($encoding, \core_text::get_encodings());
        return these_import_dossiers($this->these, $content, $delimiter, $encoding, false, $this->deve->id);
    }

    /**
     * CSV en UTF-8 précédé de l'indicateur d'ordre des octets (BOM), comme l'enregistre Excel en
     * « CSV UTF-8 » : la première colonne reste reconnue.
     */
    public function test_csv_utf8_bom(): void {
        $this->setup_activity();
        $content = "\xEF\xBB\xBFstudent;title\n{$this->student->username};Étude de l'œdème chez le chien\n";

        $report = $this->import_csv($content);
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertSame("Étude de l'œdème chez le chien", $dossier->title);
    }

    /**
     * CSV d'Excel en français : séparateur « ; », fins de ligne Windows, encodage Windows-1252
     * (caractères absents d'ISO-8859-1 compris : œ, tiret cadratin, apostrophe courbe).
     */
    public function test_csv_excel_french(): void {
        $this->setup_activity();
        $title = 'Maladie de Carré — l’œil du furet';
        $content = "student;title;defenselocation;mention;status;defensedate\r\n"
            . "{$this->student->username};{$title};Amphithéâtre Bourgelat;Très honorable;closed;12/06/2025\r\n";
        $content = \core_text::convert($content, 'UTF-8', 'WINDOWS-1252');
        $this->assertFalse(mb_check_encoding($content, 'UTF-8'));

        $report = $this->import_csv($content, 'semicolon', 'WINDOWS-1252');
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertSame($title, $dossier->title);
        $this->assertSame('Amphithéâtre Bourgelat', $dossier->defenselocation);
        $this->assertSame('treshonorable', $dossier->mention);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
    }

    /**
     * CSV en ISO-8859-1 (Latin-1) : les accents sont convertis.
     */
    public function test_csv_latin1(): void {
        $this->setup_activity();
        $content = "student;title\n{$this->student->username};Sérologie équine à Évreux\n";
        $content = \core_text::convert($content, 'UTF-8', 'ISO-8859-1');

        $report = $this->import_csv($content, 'semicolon', 'ISO-8859-1');
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertSame('Sérologie équine à Évreux', $dossier->title);
    }

    /**
     * Valeurs entre guillemets : séparateur, guillemets doublés et retour à la ligne à l'intérieur
     * d'une valeur ; séparateur virgule.
     */
    public function test_csv_quoted_values(): void {
        $this->setup_activity();
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $content = "student,title,summary,keywords\n"
            . "{$this->student->username},\"Chiens, chats et furets\",\"Résumé sur\ndeux lignes\",\"chien; chat\"\n"
            . "{$other->username},\"Le \"\"syndrome\"\" du chat\",,\n";

        $report = $this->import_csv($content, 'comma');
        $this->assertEquals([true, true], array_column($report, 'ok'));
        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertSame('Chiens, chats et furets', $dossier->title);
        $this->assertSame("Résumé sur\ndeux lignes", $dossier->summary);
        $this->assertSame('chien; chat', $dossier->keywords);
        $this->assertSame('Le "syndrome" du chat', these_get_user_dossier($this->these->id, $other->id)->title);
    }

    /**
     * En-têtes en majuscules, entourés d'espaces, dans un ordre quelconque ; colonnes inconnues
     * ignorées ; valeurs débarrassées de leurs espaces.
     */
    public function test_csv_header_case_and_spaces(): void {
        $this->setup_activity();
        $content = " Title ;Remarque; STUDENT ;DevENumber \n"
            . "  Sujet avec espaces  ;à ignorer; {$this->student->username} ; 2026-010 \n";

        $report = $this->import_csv($content);
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
        $dossier = these_get_user_dossier($this->these->id, $this->student->id);
        $this->assertSame('Sujet avec espaces', $dossier->title);
        $this->assertSame('2026-010', $dossier->devenumber);
    }

    /**
     * Séparateur tabulation : une valeur vide en fin de ligne n'est pas perdue.
     */
    public function test_csv_tab(): void {
        $this->setup_activity();
        $content = "student\ttitle\tdirector\n{$this->student->username}\tSujet\t\n";

        $report = $this->import_csv($content, 'tab');
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
        $this->assertEquals(0, these_get_user_dossier($this->these->id, $this->student->id)->directorid);
    }

    /**
     * Mauvais séparateur choisi : la ligne d'en-tête n'est qu'une colonne, l'import est refusé
     * avant toute création.
     */
    public function test_csv_wrong_delimiter(): void {
        global $DB;
        $this->setup_activity();
        try {
            $this->import_csv("student;title\n{$this->student->username};Sujet\n", 'comma');
            $this->fail('Import accepté sans colonne « student » reconnue.');
        } catch (\moodle_exception $e) {
            $this->assertSame('importmissingcolumn', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('these_dossier', ['theseid' => $this->these->id]));
    }

    /**
     * Fichier vide (un BOM et une fin de ligne) : refusé avec le message de l'import.
     */
    public function test_csv_empty(): void {
        $this->setup_activity();
        try {
            $this->import_csv("\xEF\xBB\xBF\r\n");
            $this->fail('Fichier vide accepté.');
        } catch (\moodle_exception $e) {
            $this->assertSame('importinvalidfile', $e->errorcode);
        }
    }

    /**
     * Fichier .csv lu comme un tableur (voir a6::read_rows()) : séparateur deviné d'après la ligne
     * d'en-tête, Windows-1252 reconnu, BOM UTF-8 retiré de la première cellule.
     */
    public function test_read_rows_csv(): void {
        $this->setup_activity();
        $dir = make_request_directory();
        $username = $this->student->username;

        $content = "student;title\r\n{$username};Œdème, « cas » cliniques\r\n";
        file_put_contents($dir . '/excel.csv', \core_text::convert($content, 'UTF-8', 'WINDOWS-1252'));
        $rows = \mod_these\local\a6::read_rows($dir . '/excel.csv', 'excel.csv');
        $this->assertSame([['student', 'title'], [$username, 'Œdème, « cas » cliniques']], $rows);

        file_put_contents($dir . '/bom.csv', "\xEF\xBB\xBFstudent,title\n{$username},\"Chiens; chats\"\n");
        $rows = \mod_these\local\a6::read_rows($dir . '/bom.csv', 'bom.csv');
        $this->assertSame([['student', 'title'], [$username, 'Chiens; chats']], $rows);

        $report = \mod_these\local\import::import_rows($this->these, $rows, false, $this->deve->id);
        $this->assertTrue($report[0]['ok'], $report[0]['message']);
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
