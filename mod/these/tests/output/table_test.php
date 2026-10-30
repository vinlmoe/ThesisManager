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
 * Tableau simple au balisage de html_writer::table().
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\table
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\table::class)]
final class table_test extends output_testcase {
    /**
     * Contexte : celui de table_context ; tableau vide.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $table = new table(['Nom', ''], [[[parts::text('a')], []]]);
        $data = $table->export_for_template($this->renderer());
        $this->assertSame('generaltable', $data['tableclass']);
        $this->assertSame([true, false], array_column($data['head'], 'isth'));
        $this->assertFalse($table->is_empty());
        $this->assertTrue((new table([], []))->is_empty());
    }

    /**
     * Rendu : en-tête vide en td, sans ligne d'en-tête quand il n'y en a pas.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new table(['Nom', ''], [[[parts::text('a')], [parts::text('b')]]], 'generaltable x'));
        $this->assert_html_has($html, './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('x') . ']');
        $this->assert_html_has($html, './/thead/tr/th[normalize-space() = "Nom"]');
        // En-tête vide : une cellule td, comme html_writer::table().
        $this->assert_html_has($html, './/thead/tr/*[last()][self::td][' . self::xclass('header') . '][not(normalize-space())]');
        $this->assert_html_lacks($this->renderer()->render(new table([], [[[parts::text('a')]]])), './/thead');
    }
}
