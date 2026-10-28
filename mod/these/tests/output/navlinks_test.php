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
 * Barre de navigation du personnel : liens selon les droits.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\navlinks
 */
final class navlinks_test extends output_testcase {
    /**
     * Contexte : la DEVE a les liens de gestion ; le directeur le tableau de bord, le planning et
     * l'annuaire des enseignants ; l'étudiant l'annuaire seulement.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $labels = function ($user) {
            $this->setUser($user);
            $data = (new navlinks($this->cm, $this->context))->export_for_template($this->renderer());
            return [$data['haslinks'], array_column($data['buttons']['actions'], 'label'),
                $data['settings'] ? array_column($data['settings']['items'], 'label') : []];
        };
        [$has, $deve, $settings] = $labels($this->deve);
        $this->assertTrue($has);
        $this->assertContains(get_string('createdossier', 'mod_these'), $deve);
        $this->assertContains(get_string('export', 'mod_these'), $deve);
        $this->assertContains(get_string('a6validation', 'mod_these'), $deve);
        $this->assertNotContains(get_string('jurors', 'mod_these'), $deve);
        // Paramétrage : dans le menu « Paramètres », toutes les pages quand l'activité n'est pas suivie.
        $this->assertContains(get_string('jurors', 'mod_these'), $settings);
        $this->assertContains(get_string('scopesettings', 'mod_these'), $settings);
        $this->assertContains(get_string('doctemplates', 'mod_these'), $settings);
        [, $director, $settings] = $labels($this->director);
        $this->assertSame([get_string('dashboard', 'mod_these'), get_string('planning', 'mod_these'),
            get_string('jurordirectory', 'mod_these')], $director);
        $this->assertSame([], $settings);
        [$has, $student] = $labels($this->student);
        $this->assertTrue($has);
        $this->assertSame([get_string('jurordirectory', 'mod_these')], $student);
    }

    /**
     * Rendu : bloc these-navlinks ; pour l'étudiant, le seul lien vers l'annuaire.
     */
    public function test_render(): void {
        $this->setup_activity();
        $this->setUser($this->director);
        $html = $this->renderer()->render(new navlinks($this->cm, $this->context));
        $this->assertStringStartsWith('<div class="these-navlinks mb-3"><a href=', $html);
        $this->assertSame($html, these_render_navlinks($this->cm, $this->context));
        $this->setUser($this->student);
        $html = these_render_navlinks($this->cm, $this->context);
        $this->assertStringContainsString('directory.php', $html);
        $this->assertStringNotContainsString('dashboard.php', $html);
    }
}
