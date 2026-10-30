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

namespace mod_these\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/output_testcase.php');

/**
 * Annuaire des enseignants du jury.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\juror_list
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\juror_list::class)]
final class juror_list_test extends output_testcase {
    /**
     * Contexte : nom, statut, matière, présidence possible, adresse, état et actions ; annuaire vide.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => "O'Neil", 'firstname' => 'Anne',
            'email' => 'anne@example.org', 'status' => 'pr', 'institution' => 'ENV', 'discipline' => 'Anatomie', 'active' => 1]);
        $url = new \moodle_url('/mod/these/jurors.php', ['id' => $this->cm->id]);
        $list = new juror_list(\mod_these\local\jurors::get_all(), $url);
        $this->assertFalse($list->is_empty());
        $cells = $list->export_for_template($this->renderer())['rows'][0]['cells'];
        $this->assertStringContainsString("O'NEIL", $cells[0]['parts'][0]['ptext']['value']);
        $this->assertSame('Anatomie', $cells[2]['parts'][0]['ptext']['value']);
        $this->assertSame(get_string('yes'), $cells[3]['parts'][0]['ptext']['value']);
        $this->assertSame('anne@example.org', $cells[5]['parts'][0]['ptext']['value']);
        $this->assertStringContainsString('action=delete', $cells[7]['parts'][2]['plink']['url']);
        $this->assertTrue((new juror_list([], $url))->is_empty());
    }

    /**
     * Rendu : tableau these-jurors, nom échappé.
     */
    public function test_render(): void {
        $this->setup_activity();
        \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => 'Durand & co', 'firstname' => 'Paul',
            'email' => '', 'status' => 'other', 'institution' => '', 'active' => 0]);
        $list = new juror_list(\mod_these\local\jurors::get_all(), new \moodle_url('/mod/these/jurors.php'));
        $html = $this->renderer()->render($list);
        $this->assert_html_has($html, './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-jurors') . ']');
        $this->assertStringContainsString('DURAND &amp; CO', $html);
        $this->assertStringContainsString(get_string('juroractiveno', 'mod_these'), $html);
    }
}
