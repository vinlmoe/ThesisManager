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
 * Liste à puces.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\bullet_list
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\bullet_list::class)]
final class bullet_list_test extends output_testcase {
    /**
     * Contexte : un texte brut devient un fragment texte.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $data = (new bullet_list(['Un', [parts::strong('Deux')]]))->export_for_template($this->renderer());
        $this->assertSame([['parts' => [parts::text('Un')]], ['parts' => [parts::strong('Deux')]]], $data['items']);
    }

    /**
     * Rendu : balisage de html_writer::alist(), texte échappé.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new bullet_list(['a <b>', 'c']));
        $this->assertSame("<ul>\n<li>a &lt;b&gt;</li>\n<li>c</li>\n</ul>", $html);
    }
}
