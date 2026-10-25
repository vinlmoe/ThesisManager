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
 * Suite de fragments sans bloc.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\fragment
 */
final class fragment_test extends output_testcase {
    /**
     * Contexte : les fragments reçus.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $parts = [parts::text('a'), parts::br()];
        $this->assertSame(['parts' => $parts], (new fragment($parts))->export_for_template($this->renderer()));
    }

    /**
     * Rendu : fragments collés, texte échappé, HTML formaté tel quel.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new fragment([parts::text('a & b'), parts::br(), parts::html('<em>c</em>')]));
        $this->assertSame('a &amp; b<br /><em>c</em>', $html);
    }
}
