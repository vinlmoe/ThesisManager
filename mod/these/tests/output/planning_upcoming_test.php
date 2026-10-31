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
 * Semaines à venir qui ont des soutenances.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\planning_upcoming
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\planning_upcoming::class)]
final class planning_upcoming_test extends output_testcase {
    /**
     * Contexte : une entrée par semaine, avec ses dates et son nombre de soutenances.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');
        $week = make_timestamp(2026, 11, 2);
        $data = (new planning_upcoming(new \moodle_url('/mod/these/planning.php', ['id' => 7]), [$week => 2]))
            ->export_for_template($this->renderer());
        $this->assertCount(1, $data['weeks']);
        $this->assertStringContainsString('view=week&date=' . $week, $data['weeks'][0]['url']);
        $this->assertSame(get_string('planningweekof', 'mod_these', (object) [
            'start' => userdate($week, get_string('strftimedate', 'langconfig')),
            'end' => userdate(make_timestamp(2026, 11, 8), get_string('strftimedate', 'langconfig')),
        ]), $data['weeks'][0]['label']);
        $this->assertSame(get_string('planningcount', 'mod_these', 2), $data['weeks'][0]['count']);
    }

    /**
     * Rendu : liste à puces, lien puis nombre de soutenances.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new planning_upcoming(new \moodle_url('/mod/these/planning.php'), [1 => 3]));
        $this->assert_html_has($html, './ul/li', 1);
        $item = $this->assert_html_has($html, './ul/li[a[@href]]');
        // Lien de la semaine, puis le nombre de soutenances.
        $this->assertStringEndsWith('— ' . get_string('planningcount', 'mod_these', 3), trim($item->textContent));
    }
}
