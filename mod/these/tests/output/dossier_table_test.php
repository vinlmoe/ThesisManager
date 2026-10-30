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
 * Tableau de dossiers du tableau de bord : titre confidentiel masqué.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\dossier_table
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\dossier_table::class)]
final class dossier_table_test extends output_testcase {
    /**
     * Contexte : le titre d'une thèse confidentielle n'est présent que pour qui connaît le sujet.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $cm = $this->cm;

        $this->setUser($this->librarian);
        $this->assertStringContainsString(self::SECRETS['title'], $this->context_json(new dossier_table([$dossier], $cm)));

        $this->setUser($this->viewall_user());
        $json = $this->context_json(new dossier_table([$dossier], $cm));
        $this->assert_no_secret($json);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $json);

        $empty = (new dossier_table([], $cm))->export_for_template($this->renderer());
        $this->assertTrue($empty['isempty']);
        $this->assertSame(get_string('nodossiers', 'mod_these'), $empty['emptymessage']);
    }

    /**
     * Rendu : tableau these-dossiers avec badge de statut et bouton ; message sans dossier.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $this->setUser($this->viewall_user());
        $html = $this->renderer()->render(new dossier_table([$dossier], $this->cm));
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-dossiers') . ']';
        $this->assert_html_has($html, $table);
        // Titre confidentiel remplacé par la mention, en italique.
        $confidential = get_string('confidentialtitle', 'mod_these');
        $this->assert_html_has($html, $table . '//td//em[normalize-space() = "' . $confidential . '"]');
        $this->assertStringContainsString(these_render_status_badge(THESE_STATUS_SCHEDULED), $html);
        // Colonne des actions : sans titre.
        $this->assert_html_has($html, '(' . $table . '//thead/tr)[1]/*[last()][not(normalize-space())]');
        $this->assert_no_secret($html);
        $empty = these_render_dossier_table([], $this->cm);
        $this->assert_html_lacks($empty, './/table');
        $alert = './div[' . self::xclass('alert-info') . ']';
        $this->assertSame([get_string('nodossiers', 'mod_these')], $this->html_texts($empty, $alert));
    }
}
