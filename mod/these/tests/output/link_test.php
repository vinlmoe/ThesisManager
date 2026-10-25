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
 * Lien seul.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\link
 */
final class link_test extends output_testcase {
    /**
     * Contexte : adresse, libellé, options.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $data = (new link(new \moodle_url('/a.php', ['x' => 1]), 'Retour'))->export_for_template($this->renderer());
        $this->assertSame(['url' => 'https://www.example.com/moodle/a.php?x=1', 'label' => 'Retour', 'class' => '',
            'target' => '', 'divclass' => ''], $data);
    }

    /**
     * Rendu : attributs seulement s'ils sont renseignés, bloc à la demande.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $url = new \moodle_url('/a.php');
        $this->assertSame(
            '<a href="https://www.example.com/moodle/a.php">Un &amp; deux</a>',
            $this->renderer()->render(new link($url, 'Un & deux'))
        );
        $this->assertSame(
            '<div class="mb-3"><a href="https://www.example.com/moodle/a.php" class="btn" target="_blank">L</a></div>',
            $this->renderer()->render(new link($url, 'L', 'btn', '_blank', 'mb-3'))
        );
    }
}
