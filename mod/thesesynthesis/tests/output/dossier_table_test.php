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
 * Tableau des dossiers suivis : titre confidentiel masqué.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\dossier_table
 */
final class dossier_table_test extends output_testcase {
    /**
     * Contexte : le titre d'une thèse confidentielle est présent pour son directeur, absent pour qui
     * ne connaît pas le sujet ; message sans dossier.
     */
    public function test_export_for_template(): void {
        $this->setup_env();
        $director = $this->env->director;
        $rows = thesesynthesis_get_dossiers($this->links_for($director), $director->id);
        $data = (new dossier_table($rows, 'Vide'))->export_for_template($this->renderer());
        $this->assertFalse($data['isempty']);
        $this->assertCount(8, $data['table']['head']);
        $this->assertCount(2, $data['table']['rows']);
        $this->assertStringContainsString(self::SECRETS['title'], $this->context_json(new dossier_table($rows, '')));

        // Un dossier vu sans connaître le sujet : mention « Thèse confidentielle » seulement.
        foreach ($rows as $row) {
            $row->knowssubject = false;
        }
        $json = $this->context_json(new dossier_table($rows, ''));
        $this->assert_no_secret($json);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $json);

        $this->assertSame(
            ['isempty' => true, 'emptymessage' => 'Vide', 'table' => false],
            (new dossier_table([], 'Vide'))->export_for_template($this->renderer())
        );
    }

    /**
     * Rendu : tableau these-dossiers, badge de statut, bouton ; alerte sans dossier.
     */
    public function test_render(): void {
        $this->setup_env();
        $director = $this->env->director;
        $html = $this->renderer()->render(new dossier_table(thesesynthesis_get_dossiers(
            $this->links_for($director),
            $director->id
        ), ''));
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-dossiers') . ']';
        $this->assert_html_has($html, $table);
        $this->assert_html_has($html, $table . '//td//span[' . self::xclass('badge') . ' and ' . self::xclass('badge-info') . ']');
        $open = get_string('open', 'mod_thesesynthesis');
        $button = $table . '//td//a[@href][' . self::xclass('btn-secondary') . ']';
        $this->assert_html_has($html, $button . '[normalize-space() = "' . $open . '"]');
        $this->assert_html_lacks($html, './/*[' . self::xclass('btn-primary') . ']');

        // File « À traiter » de la DEVE : l'action attendue est proposée avant « Ouvrir ».
        $deve = $this->env->deve;
        $this->setUser($deve);
        $todo = array_filter(thesesynthesis_get_dossiers($this->links_for($deve), $deve->id), function ($row) {
            return $row->todo;
        });
        $this->assertCount(1, $todo);
        $html = $this->renderer()->render(new dossier_table($todo, '', true));
        $action = these_primary_action(reset($todo)->link->cm, reset($todo)->dossier, reset($todo)->link->rights);
        $this->assertNotNull($action);
        $primary = './/td//a[@href][' . self::xclass('btn-primary') . '][starts-with(normalize-space(), "' . $action[0] . '")]';
        $this->assert_html_has($html, $primary . '/following::a[normalize-space() = "' . $open . '"]');
        $empty = $this->renderer()->render(new dossier_table([], 'Aucun & rien'));
        $this->assert_html_lacks($empty, './/table');
        $this->assertSame(['Aucun & rien'], $this->html_texts($empty, './div[' . self::xclass('alert-info') . ']'));
    }
}
