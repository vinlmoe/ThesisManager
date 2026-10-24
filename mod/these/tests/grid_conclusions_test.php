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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Grille de conformité modifiable par la DEVE et conclusions limitées à une page.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_get_grid_items
 * @covers     ::these_save_grid_items
 * @covers     ::these_conclusions_error
 */
final class grid_conclusions_test extends these_testcase {
    /**
     * Éléments d'origine enregistrés à la première consultation, puis modifiés : libellé changé,
     * élément supprimé, élément ajouté ; les réponses restent attachées aux clés.
     */
    public function test_grid_items(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        $items = these_get_grid_items(these_local_scope($this->these));
        $this->assertCount(6, $items['fond']);
        $this->assertCount(15, $items['forme']);
        $this->assertTrue((bool) $items['fond']['tool']->conditional);

        $list = [];
        foreach ($items as $sectionitems) {
            foreach ($sectionitems as $item) {
                $list[] = $item;
            }
        }
        $list[0]->label = 'La question de recherche est clairement formulée.';
        $list[1]->label = '';
        $list[] = (object) ['itemkey' => '', 'section' => 'forme', 'label' => 'Le manuscrit compte moins de 150 pages.',
            'conditional' => 0];
        these_save_grid_items(these_local_scope($this->these), $list);

        $items = these_get_grid_items(these_local_scope($this->these));
        $this->assertSame('La question de recherche est clairement formulée.', $items['fond']['question']->label);
        $this->assertArrayNotHasKey('answer', $items['fond']);
        $this->assertCount(16, $items['forme']);
        $added = end($items['forme']);
        $this->assertStringStartsWith('item', $added->itemkey);

        // La grille remplie suit les éléments en vigueur.
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'conformitygrid' => json_encode(['question' => 1, 'answer' => 1, $added->itemkey => 1]),
        ]);
        $data = these_document_context($this->these, $this->context, $dossier, 'grid');
        $labels = array_column($data['gridsections'][0]['items'], 'label');
        $this->assertContains('La question de recherche est clairement formulée.', $labels);
        $this->assertNotContains(get_string('gridanswer', 'mod_these'), $labels);
        $this->assertTrue($data['grid'][$added->itemkey]);
        $this->assertStringStartsWith('%PDF', these_signed_document_pdf($this->these, $this->context, $dossier, 'grid'));

        // Les autres activités gardent la grille d'origine.
        $other = $this->getDataGenerator()->create_module('these', ['course' => $this->course->id]);
        $this->assertArrayHasKey('answer', these_get_grid_items(these_local_scope($other))['fond']);
    }

    /**
     * Conclusions : longueur maximale et mise en page sur une page, signatures comprises.
     */
    public function test_conclusions_one_page(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        $this->assertNull(these_conclusions_error($this->these, $this->context, str_repeat('Conclusion courte. ', 40)));
        $this->assertNotNull(these_conclusions_error($this->these, $this->context, str_repeat('a', THESE_CONCLUSIONS_MAX + 1)));
        // Sous la longueur maximale, mais trop de paragraphes pour tenir sur la page.
        $this->assertSame(
            get_string('conclusionsonepage', 'mod_these'),
            these_conclusions_error($this->these, $this->context, implode("\n\n", array_fill(0, 40, 'Paragraphe.')))
        );
    }
}
