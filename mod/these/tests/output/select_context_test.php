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
 * Liste déroulante au balisage de html_writer::select().
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\select_context
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\select_context::class)]
final class select_context_test extends output_testcase {
    /**
     * Contexte : classes, identifiant par défaut, option choisie par comparaison de chaînes.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        $data = select_context::export('status', ['' => 'Tous', 0 => 'Brouillon', 10 => 'Soumis'], '', 'mr-2');
        $this->assertSame('menustatus', $data['id']);
        $this->assertSame('select custom-select mr-2', $data['class']);
        $this->assertSame([true, false, false], array_column($data['options'], 'selected'));
        $data = select_context::export('status', ['' => 'Tous', 0 => 'Brouillon'], 0, 'mr-2', 'these-status');
        $this->assertSame('these-status', $data['id']);
        $this->assertSame([false, true], array_column($data['options'], 'selected'));
    }

    /**
     * Rendu du partiel : identique à html_writer::select().
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $options = ['' => 'Tous', 0 => 'Brouillon', 10 => 'Soumis'];
        $html = $this->renderer()->render_from_template(
            'mod_these/select',
            select_context::export('status', $options, 10, 'mr-2', 'these-status')
        );
        $expected = \html_writer::select($options, 'status', 10, false, ['id' => 'these-status', 'class' => 'mr-2']);
        // Moodle 5.0 (Bootstrap 5) nomme la classe « form-select » ; le module garde « custom-select »,
        // encore stylée par compatibilité.
        $expected = str_replace('form-select', 'custom-select', $expected);
        // Même balisage, à l'ordre des attributs de la balise select près (sans effet).
        $sortattributes = function ($html) {
            return preg_replace_callback('/<select ([^>]*)>/', function ($match) {
                preg_match_all('/\w+="[^"]*"/', $match[1], $attributes);
                sort($attributes[0]);
                return '<select ' . implode(' ', $attributes[0]) . '>';
            }, $html);
        };
        $this->assertSame($sortattributes($expected), $sortattributes($html));
    }
}
