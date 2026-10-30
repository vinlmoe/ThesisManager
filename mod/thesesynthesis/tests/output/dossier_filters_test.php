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
 * Filtres des dossiers suivis.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\dossier_filters
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_thesesynthesis\output\dossier_filters::class)]
final class dossier_filters_test extends output_testcase {
    /**
     * Contexte : promotions et statuts avec la valeur choisie, texte recherché.
     */
    public function test_export_for_template(): void {
        $this->setup_env();
        $links = $this->links_for($this->env->director);
        $base = new \moodle_url('/mod/thesesynthesis/view.php', ['id' => $this->env->cm->id]);
        $data = (new dossier_filters($base, $this->env->cm->id, $links, $links[1]->cm->id, THESE_STATUS_CLOSED, 'rage'))
            ->export_for_template($this->renderer());
        $this->assertSame('https://www.example.com/moodle/mod/thesesynthesis/view.php', $data['action']);
        $this->assertSame('menucmid', $data['promotions']['id']);
        $this->assertSame('select custom-select custom-select mr-2 mb-1', $data['promotions']['class']);
        $this->assertCount(3, $data['promotions']['options']);
        $this->assertSame([false, false, true], array_column($data['promotions']['options'], 'selected'));
        $this->assertSame('Promo 2027 : Thèses 1', $data['promotions']['options'][2]['label']);
        $selected = array_filter($data['statuses']['options'], function ($option) {
            return $option['selected'];
        });
        $this->assertSame([(string) THESE_STATUS_CLOSED], array_column($selected, 'value'));
        $this->assertSame('rage', $data['search']);
    }

    /**
     * Rendu : formulaire GET en ligne, listes au balisage de html_writer::select().
     */
    public function test_render(): void {
        $this->setup_env();
        $links = $this->links_for($this->env->director);
        $base = new \moodle_url('/mod/thesesynthesis/view.php', ['id' => $this->env->cm->id]);
        $html = $this->renderer()->render(new dossier_filters($base, $this->env->cm->id, $links, 0, -999, 'a & b'));
        $form = './*[1][self::form][@method = "get"][@action = "https://www.example.com/moodle/mod/thesesynthesis/view.php"]';
        $this->assert_html_has($html, $form . '[' . self::xclass('form-inline') . ']');
        // Statut choisi (-999 : à traiter) présélectionné en tête de la liste.
        $status = $form . '//select[@id = "menustatus"][@name = "status"]';
        $this->assert_html_has($html, $status . '/option[1][@value = "-999"][@selected]');
        $this->assert_html_has($html, $form . '//input[@value = "a & b"]');
        $this->assert_html_has($html, $form . '//button[@type = "submit"]');
    }
}
