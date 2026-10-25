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
 * Boutons d'action : contexte et rendu.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\actions
 */
final class actions_test extends output_testcase {
    /**
     * Contexte : liens nuls ignorés, classe appliquée à chaque bouton.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $actions = new actions(['Modifier' => new \moodle_url('/mod/these/edit.php', ['id' => 1, 'dossierid' => 2]),
            'Absent' => null], 'btn x');
        $this->assertFalse($actions->is_empty());
        $this->assertSame([['label' => 'Modifier', 'url' => 'https://www.example.com/moodle/mod/these/edit.php?id=1&dossierid=2',
            'class' => 'btn x']], $actions->export_for_template($this->renderer())['actions']);
        $this->assertTrue((new actions(['Absent' => null]))->is_empty());
    }

    /**
     * Rendu : boutons collés (sans espace entre eux), libellés et adresses échappés ; rien sans lien.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new actions([
            'Un & deux' => new \moodle_url('/a.php', ['x' => 1, 'y' => 2]),
            'Trois' => new \moodle_url('/b.php'),
        ]));
        $class = 'btn btn-sm btn-secondary mr-1 mb-1';
        $this->assertSame(
            '<a href="https://www.example.com/moodle/a.php?x=1&amp;y=2" class="' . $class . '">Un &amp; deux</a>'
                . '<a href="https://www.example.com/moodle/b.php" class="' . $class . '">Trois</a>',
            $html
        );
        $this->assertSame('', $this->renderer()->render(new actions([])));
    }
}
