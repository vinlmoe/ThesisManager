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
 * Jurys de l'utilisateur : accès aux documents du jury.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\jury_table
 */
final class jury_table_test extends output_testcase {
    /**
     * Contexte : le juré voit son jury et le titre (il connaît le sujet) ; accès en attente tant
     * qu'il n'est pas prévenu, puis bouton ; message sans jury.
     */
    public function test_export_for_template(): void {
        global $DB;
        $this->setup_env();
        $juror = $this->env->juror;
        $this->setUser($juror);
        $juries = thesesynthesis_get_juries($this->links_for($juror), $juror);
        $this->assertCount(1, $juries);
        $data = (new jury_table($juries))->export_for_template($this->renderer());
        $cells = $data['table']['rows'][0]['cells'];
        $this->assertStringContainsString(self::SECRETS['title'], $cells[2]['parts'][0]['phtml']['value']);
        $this->assertSame(get_string('juryaccesspending', 'mod_thesesynthesis'), $cells[6]['parts'][0]['pspan']['value']);

        $DB->set_field('these_jury', 'notifiedtime', time() - DAYSECS, ['dossierid' => $this->env->confidential->id]);
        $juries = thesesynthesis_get_juries($this->links_for($juror), $juror);
        $cells = (new jury_table($juries))->export_for_template($this->renderer())['table']['rows'][0]['cells'];
        $this->assertNotFalse($cells[6]['parts'][0]['plink'] ?: $cells[6]['parts'][0]['pspan']);

        // Un utilisateur qui ne connaît pas le sujet ne reçoit pas le titre.
        $this->setUser($this->env->teacher);
        $this->assert_no_secret($this->context_json(new jury_table($juries)));

        $empty = (new jury_table([]))->export_for_template($this->renderer());
        $this->assertTrue($empty['isempty']);
        $this->assertSame(get_string('nojuries', 'mod_thesesynthesis'), $empty['emptymessage']);
    }

    /**
     * Rendu : tableau generaltable ; alerte sans jury.
     */
    public function test_render(): void {
        $this->setup_env();
        $juror = $this->env->juror;
        $this->setUser($juror);
        $html = $this->renderer()->render(new jury_table(thesesynthesis_get_juries($this->links_for($juror), $juror)));
        $table = './/table[' . self::xclass('generaltable') . ']';
        $this->assert_html_has($html, $table);
        $this->assert_html_has($html, $table . '//td//span[' . self::xclass('text-muted') . ']');
        $empty = $this->renderer()->render(new jury_table([]));
        $this->assert_html_lacks($empty, './/table');
        $this->assertSame(
            [get_string('nojuries', 'mod_thesesynthesis')],
            $this->html_texts($empty, './div[' . self::xclass('alert-info') . ']')
        );
    }
}
