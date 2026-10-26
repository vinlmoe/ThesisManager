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

use mod_these\local\a6;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Validation de l'A6 saisie par la DEVE : lecture des dates et des écoles, import par fichier, action
 * groupée, reprise de la déclaration de l'étudiant à défaut.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\a6
 */
final class a6_test extends these_testcase {
    /**
     * Dates d'un tableur, jj/mm/aaaa, aaaa-mm-jj, « non » ; écoles par clé ou libellé.
     */
    public function test_parse(): void {
        $this->resetAfterTest();
        $expected = make_timestamp(2025, 7, 1, 12);
        $this->assertSame($expected, a6::parse_date('01/07/2025'));
        $this->assertSame($expected, a6::parse_date('2025-07-01'));
        $this->assertSame($expected, a6::parse_date('45839'));
        $this->assertSame(0, a6::parse_date('Non'));
        $this->assertNull(a6::parse_date('31/02/2025'));
        $this->assertNull(a6::parse_date(''));
        $this->assertSame('lyon', a6::parse_school('Lyon'));
        $this->assertSame('alfort', a6::parse_school('maisons-alfort'));
        $this->assertSame('', a6::parse_school(''));
        $this->assertNull(a6::parse_school('Ailleurs'));
    }

    /**
     * Sans saisie de la DEVE, la déclaration de l'étudiant ; la saisie de la DEVE la remplace et est
     * inscrite à l'historique.
     */
    public function test_get_set(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE,
            'bordereau' => json_encode(['a6date' => make_timestamp(2024, 6, 1, 12), 'a6school' => 'nantes', 'a6notyet' => 0]),
        ]);
        $a6 = a6::get($dossier);
        $this->assertFalse($a6['recorded']);
        $this->assertSame('nantes', $a6['school']);

        $dossier = a6::set($dossier, make_timestamp(2025, 7, 1, 12), '', $this->deve->id);
        $a6 = a6::get($this->reload($dossier->id));
        $this->assertTrue($a6['recorded']);
        $this->assertSame(make_timestamp(2025, 7, 1, 12), $a6['date']);
        $this->assertSame('nantes', $a6['school']);
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'a6recorded']));

        a6::set($dossier, 0, 'lyon', $this->deve->id);
        $this->assertSame(get_string('a6notvalidated', 'mod_these'), a6::label($this->reload($dossier->id)));
    }

    /**
     * Import : lignes reconnues à leurs en-têtes, simulation sans écriture, erreurs signalées par ligne ;
     * lecture d'un CSV et d'un classeur Excel.
     */
    public function test_import(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_ACTIVE]);
        $outsider = $this->getDataGenerator()->create_user();
        $rows = [
            ['Étudiant', 'Date A6', 'École'],
            [$this->student->username, '01/07/2025', 'Lyon'],
            [$outsider->username, '01/07/2025', ''],
            ['inconnu', '01/07/2025', ''],
            [$this->student->email, 'demain', ''],
        ];
        $report = a6::import($this->these, $rows, true, $this->deve->id);
        $this->assertSame([true, false, false, false], array_column($report, 'ok'));
        $this->assertFalse(a6::get($this->reload($dossier->id))['recorded']);

        a6::import($this->these, array_slice($rows, 0, 2), false, $this->deve->id);
        $a6 = a6::get($this->reload($dossier->id));
        $this->assertSame(make_timestamp(2025, 7, 1, 12), $a6['date']);
        $this->assertSame('lyon', $a6['school']);

        $this->expectException(\moodle_exception::class);
        a6::import($this->these, [['nom', 'prenom']], true, $this->deve->id);
    }

    /**
     * Lecture des fichiers : CSV au point-virgule, classeur Excel.
     */
    public function test_read_rows(): void {
        global $CFG;
        $this->resetAfterTest();
        $dir = make_request_directory();
        file_put_contents("$dir/a6.csv", "etudiant;date\nemma;01/07/2025\n\n");
        $this->assertSame([['etudiant', 'date'], ['emma', '01/07/2025']], a6::read_rows("$dir/a6.csv", 'a6.csv'));

        $autoload = $CFG->libdir . '/phpspreadsheet/vendor/autoload.php';
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') && file_exists($autoload)) {
            require_once($autoload);
        }
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['etudiant', 'date'], ['emma', 45839]]);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save("$dir/a6.xlsx");
        $rows = a6::read_rows("$dir/a6.xlsx", 'A6.XLSX');
        $this->assertSame('emma', $rows[1][0]);
        $this->assertSame(make_timestamp(2025, 7, 1, 12), a6::parse_date($rows[1][1]));

        $this->expectException(\moodle_exception::class);
        a6::read_rows("$dir/a6.csv", 'a6.pdf');
    }

    /**
     * Action groupée : la date et l'école choisies, pour chaque dossier coché.
     */
    public function test_bulk(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_ACTIVE]);
        $date = make_timestamp(2025, 7, 1, 12);
        $this->assertFalse(\mod_these\local\bulk::apply($this->these, $this->cm, $this->context, $dossier, 'a6', $this->deve->id));
        $this->assertTrue(\mod_these\local\bulk::apply(
            $this->these,
            $this->cm,
            $this->context,
            $dossier,
            'a6',
            $this->deve->id,
            ['a6date' => $date, 'a6school' => 'toulouse']
        ));
        $a6 = a6::get($this->reload($dossier->id));
        $this->assertSame($date, $a6['date']);
        $this->assertSame('toulouse', $a6['school']);
    }
}
