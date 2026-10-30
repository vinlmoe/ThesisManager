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
 * Bouton du cœur dans un bloc.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\single_button_block
 */
final class single_button_block_test extends output_testcase {
    /**
     * Contexte : bloc et contexte du bouton du cœur.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $data = (new single_button_block(new \moodle_url('/a.php', ['x' => 1]), 'Ajouter', 'get'))
            ->export_for_template($this->renderer());
        $this->assertSame('mb-3', $data['divclass']);
        $this->assertSame('get', $data['button']->method);
        $this->assertSame('Ajouter', $data['button']->label);
    }

    /**
     * Rendu : le bouton du cœur (formulaire GET vers l'adresse) dans le bloc.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new single_button_block(new \moodle_url('/a.php'), 'Ajouter', 'get'));
        // Un seul bloc, qui contient le formulaire du bouton.
        $this->assert_html_has($html, './*', 1);
        $form = './div//form[@method = "get"][@action = "https://www.example.com/moodle/a.php"]';
        $this->assert_html_has($html, $form);
        $button = '(self::button and normalize-space() = "Ajouter") or (self::input and @value = "Ajouter")';
        $this->assert_html_has($html, $form . '//*[' . $button . ']');
    }
}
