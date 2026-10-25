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
 * Bouton du planning imprimable.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\planning_print
 */
final class planning_print_test extends output_testcase {
    /**
     * Contexte : adresse du PDF de la période, libellé selon la vue.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $base = new \moodle_url('/mod/these/planning.php', ['id' => 7]);
        $data = (new planning_print($base, 'month', 1234))->export_for_template($this->renderer());
        $url = 'https://www.example.com/moodle/mod/these/planning.php?id=7&view=month&date=1234&format=pdf';
        $this->assertSame($url, $data['url']);
        $this->assertSame(get_string('planningpdfmonth', 'mod_these'), $data['label']);
        $data = (new planning_print($base, 'week', 1234))->export_for_template($this->renderer());
        $this->assertSame(get_string('planningpdfweek', 'mod_these'), $data['label']);
    }

    /**
     * Rendu : un bouton dans un bloc mt-3 mb-2.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $base = new \moodle_url('/mod/these/planning.php', ['id' => 7]);
        $html = $this->renderer()->render(new planning_print($base, 'week', 5));
        $label = s(get_string('planningpdfweek', 'mod_these'));
        $this->assertSame('<div class="mt-3 mb-2"><a href="https://www.example.com/moodle/mod/these/planning.php?id=7&amp;view=week'
            . '&amp;date=5&amp;format=pdf" class="btn btn-primary">' . $label . '</a></div>', $html);
    }
}
