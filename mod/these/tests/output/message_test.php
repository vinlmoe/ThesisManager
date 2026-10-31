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
 * Bloc de texte : texte brut ou fragments, div ou paragraphe.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\message
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\message::class)]
final class message_test extends output_testcase {
    /**
     * Contexte : un texte brut devient un fragment texte ; balise et classes.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $data = (new message('Un & deux'))->export_for_template($this->renderer());
        $this->assertSame([parts::text('Un & deux')], $data['parts']);
        $this->assertSame('alert alert-info', $data['class']);
        $this->assertFalse($data['isparagraph']);
        $this->assertTrue((new message('x', '', 'p'))->export_for_template($this->renderer())['isparagraph']);
    }

    /**
     * Rendu : texte échappé ; sans classe, pas d'attribut class ; paragraphe ; fragments.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $renderer = $this->renderer();
        $this->assertSame('<div class="mb-3">Un &amp; &lt;deux&gt;</div>', $renderer->render(new message('Un & <deux>', 'mb-3')));
        $this->assertSame('<div>Texte</div>', $renderer->render(new message('Texte', '')));
        $this->assertSame('<p>Texte</p>', $renderer->render(new message('Texte', '', 'p')));
        $content = [parts::strong('Motif'), parts::br(), parts::html('a<br />b')];
        $html = $renderer->render(new message($content, 'alert alert-warning'));
        $this->assertSame('<div class="alert alert-warning"><strong>Motif</strong><br />a<br />b</div>', $html);
    }
}
