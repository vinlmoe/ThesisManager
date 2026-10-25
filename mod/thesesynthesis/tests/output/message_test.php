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

namespace mod_thesesynthesis\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/output_testcase.php');

/**
 * Message en bloc.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\message
 */
final class message_test extends output_testcase {
    /**
     * Contexte : texte et classes.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $this->assertSame(
            ['text' => 'Un & deux', 'class' => 'alert alert-info'],
            (new message('Un & deux'))->export_for_template($this->renderer())
        );
    }

    /**
     * Rendu : texte échappé dans le bloc.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new message('Un & <deux>', 'mb-3'));
        $this->assertSame('<div class="mb-3">Un &amp; &lt;deux&gt;</div>', $html);
    }
}
