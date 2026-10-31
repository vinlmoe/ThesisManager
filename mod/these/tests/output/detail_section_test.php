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
 * Section de détail : lignes vides omises, texte échappé, HTML formaté rendu tel quel.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\detail_section
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\detail_section::class)]
final class detail_section_test extends output_testcase {
    /**
     * Contexte : lignes nulles ou vides omises, libellé en gras, classes de html_writer::table().
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $section = new detail_section('Sujet', ['Titre' => [parts::text('Un & deux')], 'Vide' => [], 'Nul' => null]);
        $data = $section->export_for_template($this->renderer());
        $this->assertTrue($data['hasrows']);
        $this->assertSame('Sujet', $data['heading']);
        $this->assertSame('generaltable these-detail', $data['table']['tableclass']);
        $this->assertFalse($data['table']['hashead']);
        $this->assertCount(1, $data['table']['rows']);
        $row = $data['table']['rows'][0];
        $this->assertSame('lastrow', $row['class']);
        $this->assertSame(['cell c0', 'cell c1 lastcol'], array_column($row['cells'], 'class'));
        $this->assertSame(['value' => 'Titre'], $row['cells'][0]['parts'][0]['pstrong']);
        $this->assertSame(['value' => 'Un & deux'], $row['cells'][1]['parts'][0]['ptext']);
        // Chaque fragment porte toutes les clés de type : rien n'est hérité du contexte parent.
        $this->assertSame(parts::TYPES, array_keys($row['cells'][1]['parts'][0]));

        $this->assertTrue((new detail_section('T', ['a' => null]))->is_empty());
        $legacy = detail_section::from_html('T', ['<b>L</b>' => '<em>v</em>', 'Vide' => '', 'Nul' => null]);
        $cells = $legacy->export_for_template($this->renderer())['table']['rows'][0]['cells'];
        $this->assertSame(['value' => '<b>L</b>'], $cells[0]['parts'][0]['pstronghtml']);
        $this->assertSame(['value' => '<em>v</em>'], $cells[1]['parts'][0]['phtml']);
    }

    /**
     * Rendu : titre et tableau ; texte brut échappé, HTML formaté tel quel ; rien sans ligne.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new detail_section('Sujet & co', [
            'Titre' => [parts::text('<script>x</script>')],
            'Résumé' => [parts::html(format_text("ligne 1\nligne 2", FORMAT_PLAIN))],
        ]));
        $this->assert_html_has($html, './*[1][self::h4][normalize-space() = "Sujet & co"]');
        $table = './/div[' . self::xclass('table-responsive') . ']/table[' . self::xclass('generaltable') . ' and '
            . self::xclass('these-detail') . ']';
        $this->assert_html_has($html, $table);
        // Libellé en gras dans la première colonne, valeur dans la seconde.
        $this->assert_html_has($html, $table . '//tr[td[1]/strong[normalize-space() = "Titre"]]/td[2]');
        // Texte brut : affiché tel quel, sans élément script.
        $this->assert_html_text_contains($html, $table . '//tr/td[2]', '<script>x</script>');
        $this->assert_html_lacks($html, './/script');
        // HTML formaté : gardé (retour à la ligne).
        $this->assert_html_has($html, $table . '//td[contains(., "ligne 1")]//br');
        $this->assert_html_has($html, $table . '//tr[' . self::xclass('lastrow') . ']', 1);
        $this->assertSame('', $this->renderer()->render(new detail_section('Vide', ['a' => null])));
        $this->assertSame('', these_render_detail_section('Vide', ['a' => '']));
    }
}
