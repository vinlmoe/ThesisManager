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
 * Documents signés : documents visibles selon les droits, état des signatures.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\signed_documents
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\signed_documents::class)]
final class signed_documents_test extends output_testcase {
    /**
     * Contexte : la DEVE voit tous les documents disponibles ; un détenteur de viewall ne voit pas ceux
     * qui révèlent le sujet ; état signé, demandé ou en attente.
     */
    public function test_export_for_template(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $slot = these_document_slots($dossier, 'subject')[0];
        $DB->insert_record('these_signature', (object) ['dossierid' => $dossier->id, 'doctype' => 'subject', 'slot' => $slot,
            'userid' => 0, 'signername' => 'Anne Signataire', 'requesttime' => make_timestamp(2026, 2, 1),
            'timesigned' => make_timestamp(2026, 2, 2), 'timecreated' => make_timestamp(2026, 2, 1)]);

        $deve = (new signed_documents($this->cm, $this->context, $dossier, these_get_rights(
            $this->context,
            $dossier,
            $this->deve->id
        )))->export_for_template($this->renderer());
        $this->assertTrue($deve['hasdocuments']);
        $labels = array_map(function ($row) {
            return $row['cells'][0]['parts'][0]['ptext']['value'];
        }, $deve['table']['rows']);
        $this->assertContains(these_document_label('subject'), $labels);
        $json = json_encode($deve, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Anne Signataire', $json);
        $this->assertStringContainsString('badge badge-success', $json);

        $viewall = $this->viewall_user();
        $rights = these_get_rights($this->context, $dossier, $viewall->id);
        $json = $this->context_json(new signed_documents($this->cm, $this->context, $dossier, $rights));
        $this->assertStringNotContainsString(these_document_label('subject'), $json);
        $this->assertFalse(these_can_view_document('subject', $rights));
    }

    /**
     * Rendu : tableau these-signatures, bouton de téléchargement.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $rights = these_get_rights($this->context, $dossier, $this->deve->id);
        $html = $this->renderer()->render(new signed_documents($this->cm, $this->context, $dossier, $rights));
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-signatures') . ']';
        $this->assert_html_has($html, $table);
        $download = get_string('downloadpdf', 'mod_these');
        $button = $table . '//td//a[@href][' . self::xclass('btn') . '][normalize-space() = "' . $download . '"]';
        $this->assert_html_has($html, $button);
        // Colonne des boutons : sans titre.
        $this->assert_html_has($html, '(' . $table . '//thead/tr)[1]/*[last()][not(normalize-space())]');
    }
}
