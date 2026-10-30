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
 * Tableau du jury : colonnes selon les droits, éligibilité pour la DEVE.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\jury
 */
final class jury_test extends output_testcase {
    /**
     * Contexte : adresse et éligibilité seulement si demandées ; badges d'éligibilité ; jury vide.
     */
    public function test_export_for_template(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $members = these_get_jury($dossier->id);
        $first = reset($members);
        $DB->set_field('these_jury', 'eligible', 1, ['id' => $first->id]);
        $DB->set_field('these_jury', 'notifiedtime', make_timestamp(2026, 11, 2, 9), ['id' => $first->id]);
        $DB->set_field('these_jury', 'userid', $this->director->id, ['id' => $first->id]);
        $members = these_get_jury($dossier->id);

        $public = (new jury($members, false))->export_for_template($this->renderer());
        $this->assertTrue($public['hasmembers']);
        $this->assertCount(5, $public['table']['head']);
        $this->assertStringNotContainsString('president@example.com', json_encode($public));

        $staff = (new jury($members, true, true))->export_for_template($this->renderer());
        $this->assertCount(7, $staff['table']['head']);
        $this->assertSame(get_string('juryeligiblecheck', 'mod_these'), $staff['table']['head'][6]['text']);
        $cells = $staff['table']['rows'][0]['cells'];
        $this->assertSame('president@example.com', $cells[5]['parts'][0]['ptext']['value']);
        $this->assertSame('badge badge-success bg-success', $cells[6]['parts'][0]['pbadge']['class']);
        $this->assertNotFalse($cells[6]['parts'][1]['pmuted']);
        // Compte rattaché, ou accès par lien sans compte.
        $account = get_string('juryuseraccount', 'mod_these', fullname($this->director));
        $this->assertSame($account, $cells[6]['parts'][2]['pmuted']['value']);
        $guest = end($staff['table']['rows'])['cells'][6];
        $this->assertSame(get_string('jurynoaccount', 'mod_these'), $guest['parts'][1]['pmuted']['value']);
        // Invité : pas d'éligibilité.
        $this->assertSame('-', end($staff['table']['rows'])['cells'][6]['parts'][0]['ptext']['value']);

        $this->assertFalse((new jury([]))->export_for_template($this->renderer())['hasmembers']);
    }

    /**
     * Rendu : titre et tableau ; rien sans membre.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $html = $this->renderer()->render(new jury(these_get_jury($dossier->id)));
        $this->assertStringStartsWith('<h4 class="mt-4">' . get_string('jury', 'mod_these') . '</h4>', $html);
        $this->assertStringContainsString('<th class="header c0" scope="col">', $html);
        $this->assertStringContainsString('Paul Président', $html);
        $this->assertSame('', these_render_jury([]));
    }
}
