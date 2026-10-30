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
 * Signature enregistrée de l'utilisateur.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\saved_signature
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\saved_signature::class)]
final class saved_signature_test extends output_testcase {
    /**
     * Contexte : image en data URI et adresse de suppression ; absence de signature.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $url = new \moodle_url('/mod/these/mysignatures.php', ['deletesaved' => 1]);
        $data = (new saved_signature('PNG', $url))->export_for_template($this->renderer());
        $this->assertTrue($data['hassignature']);
        $this->assertSame('data:image/png;base64,' . base64_encode('PNG'), $data['src']);
        $this->assertFalse((new saved_signature(null, $url))->export_for_template($this->renderer())['hassignature']);
    }

    /**
     * Rendu : image puis bouton ; mention grisée sans signature.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $url = new \moodle_url('/mod/these/mysignatures.php', ['deletesaved' => 1]);
        $html = $this->renderer()->render(new saved_signature('PNG', $url));
        // L'image en premier, puis le bouton de suppression vers l'adresse donnée.
        $this->assert_html_has($html, '(.//img)[1][@src = "data:image/png;base64,UE5H"]');
        $this->assert_html_has($html, '(.//img)[1]/following::a[1][@href = "' . $url->out(false) . '"]['
            . self::xclass('btn') . '][starts-with(normalize-space(), "' . get_string('signaturesaveddelete', 'mod_these') . '")]');
        $none = $this->renderer()->render(new saved_signature(null, $url));
        $this->assert_html_lacks($none, './/img');
        $this->assertSame(
            [get_string('signaturesavednone', 'mod_these')],
            $this->html_texts($none, './div[' . self::xclass('text-muted') . ']')
        );
    }
}
