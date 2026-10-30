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
 * Historique du sujet : anciens titres et PDF archivés.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\subject_history
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\subject_history::class)]
final class subject_history_test extends output_testcase {
    /**
     * Contexte : ancien titre, directeur, motif ; lien vers le PDF archivé quand il existe.
     */
    public function test_export_for_template(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $entry = $DB->get_record('these_subjecthistory', ['dossierid' => $dossier->id]);
        get_file_storage()->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_these',
            'filearea' => 'subjectarchive', 'itemid' => $dossier->id, 'filepath' => '/',
            'filename' => 'sujet-' . $entry->id . '.pdf'], 'x');

        $data = (new subject_history($this->context, $dossier))->export_for_template($this->renderer());
        $this->assertTrue($data['hasentries']);
        $cells = $data['table']['rows'][0]['cells'];
        $this->assertSame(self::SECRETS['oldtitle'], $cells[1]['parts'][0]['phtml']['value']);
        $this->assertSame('Dr Ancien', $cells[2]['parts'][0]['ptext']['value']);
        $this->assertSame('sujet-' . $entry->id . '.pdf', $cells[4]['parts'][0]['plink']['value']);

        $DB->delete_records('these_subjecthistory', ['dossierid' => $dossier->id]);
        $this->assertFalse((new subject_history($this->context, $dossier))->export_for_template($this->renderer())['hasentries']);
    }

    /**
     * Rendu : tableau these-subjecthistory, « - » sans PDF archivé ; rien sans changement de sujet.
     */
    public function test_render(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $html = $this->renderer()->render(new subject_history($this->context, $dossier));
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-subjecthistory') . ']';
        $this->assert_html_has($html, $table);
        // Dernière colonne (PDF archivé) : « - » sans fichier.
        $this->assert_html_has($html, $table . '//tbody/tr/td[last()][normalize-space() = "-"]');
        $DB->delete_records('these_subjecthistory', ['dossierid' => $dossier->id]);
        $this->assertSame('', these_render_subject_history($this->context, $dossier));
    }
}
