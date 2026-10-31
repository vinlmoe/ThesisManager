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

namespace mod_these\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/form_testcase.php');

/**
 * Validation des formulaires de dépôt de pièces : manuscrit corrigé, procès-verbal de soutenance,
 * rapport du directeur, scan d'un document signé hors module.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\corrections_form
 * @covers     \mod_these\form\minutes_form
 * @covers     \mod_these\form\report_form
 * @covers     \mod_these\form\scan_form
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\corrections_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\minutes_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\report_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\scan_form::class)]
final class deposit_forms_test extends form_testcase {
    /**
     * Manuscrit corrigé : fichier et description des corrections obligatoires.
     */
    public function test_corrections_form(): void {
        $this->setup_form_page($this->student);
        $form = new corrections_form();

        $errors = $form->validation(['corrections' => $this->draft(null), 'correctionsdone' => ' '], []);
        $this->assertSame(get_string('correctedmanuscriptrequired', 'mod_these'), $errors['corrections']);
        $this->assert_error_fields(['corrections', 'correctionsdone'], $errors);
        $this->assertSame([], $form->validation(['corrections' => $this->draft(), 'correctionsdone' => 'Chapitre 2 repris'], []));
    }

    /**
     * Procès-verbal : scan obligatoire ; une thèse admise reçoit une mention de la liste, et des
     * corrections demandées sont précisées. Une thèse ajournée n'a ni mention ni corrections.
     */
    public function test_minutes_form(): void {
        $this->setup_form_page();
        $form = new minutes_form();
        $admitted = ['pv' => $this->draft(), 'defenseresult' => THESE_RESULT_ADMITTED, 'mention' => 'treshonorable',
            'correctionsrequested' => 0, 'correctionsnote' => ''];

        $this->assertSame([], $form->validation($admitted, []));
        $errors = $form->validation(['pv' => $this->draft(null)] + $admitted, []);
        $this->assertSame(['pv' => get_string('minutesrequired', 'mod_these')], $errors);
        $this->assert_error_fields(['mention'], $form->validation(['mention' => ''] + $admitted, []));
        // Mention hors de la liste fermée (saisie libre d'une ancienne version) : refusée.
        $this->assert_error_fields(['mention'], $form->validation(['mention' => 'Très bien'] + $admitted, []));
        $this->assert_error_fields(['correctionsnote'], $form->validation(['correctionsrequested' => 1] + $admitted, []));
        $this->assertSame([], $form->validation(
            ['correctionsrequested' => 1, 'correctionsnote' => 'Reprendre la bibliographie'] + $admitted,
            []
        ));

        $adjourned = ['defenseresult' => THESE_RESULT_ADJOURNED, 'mention' => '', 'correctionsrequested' => 1] + $admitted;
        $this->assertSame([], $form->validation($adjourned, []));
    }

    /**
     * Rapport du directeur : un fichier déposé.
     */
    public function test_report_form(): void {
        $this->setup_form_page($this->director);
        $form = new report_form();

        $this->assertSame(
            ['directorreport' => get_string('directorreportrequired', 'mod_these')],
            $form->validation(['directorreport' => $this->draft(null)], [])
        );
        $this->assert_error_fields(['directorreport'], $form->validation([], []));
        $this->assertSame([], $form->validation(['directorreport' => $this->draft('rapport.pdf'), 'reportfavorable' => 0], []));
    }

    /**
     * Scan d'un document signé : au moins un fichier.
     */
    public function test_scan_form(): void {
        $this->setup_form_page();
        $form = new scan_form(null, ['slots' => []]);
        $data = ['doctype' => 'conclusions', 'signeddate' => make_timestamp(2026, 6, 1)];

        $this->assertSame(
            ['scan' => get_string('scanrequired', 'mod_these')],
            $form->validation($data + ['scan' => $this->draft(null)], [])
        );
        $this->assertSame([], $form->validation($data + ['scan' => $this->draft('scan.pdf')], []));
    }
}
