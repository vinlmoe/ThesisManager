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
 * Badge de statut : libellé et couleur, rendu.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\status_badge
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\status_badge::class)]
final class status_badge_test extends output_testcase {
    /**
     * Contexte : libellé et classe du statut.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $data = (new status_badge(THESE_STATUS_CORRECTIONS))->export_for_template($this->renderer());
        $this->assertSame(['label' => these_status_label(THESE_STATUS_CORRECTIONS), 'badgeclass' => 'badge-warning'], $data);
    }

    /**
     * Rendu : un span.badge, identique à l'ancien html_writer::span().
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new status_badge(THESE_STATUS_CLOSED));
        $this->assertSame('<span class="badge badge-dark">' . s(these_status_label(THESE_STATUS_CLOSED)) . '</span>', $html);
        $this->assertSame($html, these_render_status_badge(THESE_STATUS_CLOSED));
    }
}
