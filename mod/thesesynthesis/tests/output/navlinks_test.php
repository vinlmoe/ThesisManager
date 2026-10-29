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

namespace mod_thesesynthesis\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/output_testcase.php');

/**
 * Barre de navigation de l'activité de suivi : liens selon les capacités.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\navlinks
 */
final class navlinks_test extends output_testcase {
    /**
     * Libellés des boutons et du menu « Paramètres » pour l'utilisateur courant.
     *
     * @return string[]
     */
    private function labels(): array {
        $data = (new navlinks($this->env->cm, $this->env->context))->export_for_template($this->renderer());
        return array_merge(
            array_column($data['buttons']['actions'], 'label'),
            $data['settings'] ? array_column($data['settings']['items'], 'label') : []
        );
    }

    /**
     * Contexte : tableau de bord et planning pour tous ; recherche pour un enseignant ; liens de
     * gestion pour un enseignant éditeur.
     */
    public function test_export_for_template(): void {
        $this->setup_env();
        $this->setUser($this->env->teacher);
        $labels = $this->labels();
        $this->assertSame([get_string('dashboard', 'mod_these'), get_string('planning', 'mod_these'),
            get_string('searchtheses', 'mod_thesesynthesis')], $labels);

        $this->setUser($this->env->deve);
        $labels = $this->labels();
        $this->assertContains(get_string('managelinks', 'mod_thesesynthesis'), $labels);
        $this->assertContains(get_string('jurors', 'mod_these'), $labels);
        $this->assertContains(get_string('scopesettings', 'mod_these'), $labels);
    }

    /**
     * Rendu : lien de retour vers l'accueil du suivi, puis bloc these-navlinks, boutons collés.
     */
    public function test_render(): void {
        $this->setup_env();
        $this->setUser($this->env->teacher);
        $html = $this->renderer()->render(new navlinks($this->env->cm, $this->env->context));
        $this->assertStringStartsWith('<div class="these-navlinks mb-3"><a href=', $html);
        $this->assertSame(3, substr_count($html, 'class="btn btn-secondary mr-1 mb-1"'));
        $this->assertStringNotContainsString("</a>\n", $html);
        $back = \mod_these\local\backlinks::render(thesesynthesis_back_home($this->env->cm));
        $this->assertStringContainsString('/mod/thesesynthesis/view.php', $back);
        $this->assertSame($back . $html, thesesynthesis_render_navlinks($this->env->cm, $this->env->context));
    }
}
