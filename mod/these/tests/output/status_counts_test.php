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
 * Décompte des dossiers par statut.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\status_counts
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\status_counts::class)]
final class status_counts_test extends output_testcase {
    /**
     * Contexte : un badge par statut qui a des dossiers, dans l'ordre du circuit.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $data = (new status_counts($this->cm, [THESE_STATUS_CLOSED => 1, THESE_STATUS_ACTIVE => 3, THESE_STATUS_DRAFT => 0]))
            ->export_for_template($this->renderer());
        $this->assertTrue($data['hasbadges']);
        $this->assertSame(
            [these_status_label(THESE_STATUS_ACTIVE) . ' : 3', these_status_label(THESE_STATUS_CLOSED) . ' : 1'],
            array_column($data['badges'], 'label')
        );
        $this->assertSame([false, true], array_column($data['badges'], 'separator'));
        $this->assertSame('badge badge-success mr-1 mb-1 p-2', $data['badges'][0]['class']);
        $this->assertFalse((new status_counts($this->cm, []))->export_for_template($this->renderer())['hasbadges']);
    }

    /**
     * Rendu : badges séparés par une espace ; rien sans dossier.
     */
    public function test_render(): void {
        $this->setup_activity();
        $html = $this->renderer()->render(new status_counts($this->cm, [THESE_STATUS_ACTIVE => 3, THESE_STATUS_CLOSED => 1]));
        $counts = './div[' . self::xclass('these-counts') . ']';
        $this->assert_html_has($html, $counts . '/a[@href]', 2);
        // Une espace sépare deux badges.
        $this->assert_html_has($html, $counts . '/a[following-sibling::node()[1][self::text() and normalize-space() = ""]]'
            . '[following-sibling::*[1][self::a]]');
        $this->assertSame('', $this->renderer()->render(new status_counts($this->cm, [])));
    }
}
