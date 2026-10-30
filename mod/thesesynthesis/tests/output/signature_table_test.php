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
 * Signatures centralisées : celles que l'utilisateur appose, celles que la DEVE attend d'autres
 * signataires, chacune avec le lien vers la page où agir.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\signature_table
 * @covers     ::thesesynthesis_get_signatures
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_thesesynthesis\output\signature_table::class)]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_get_signatures')]
final class signature_table_test extends output_testcase {
    /**
     * Demande de signature du formulaire de sujet adressée au juré (signataire invité par lien).
     *
     * @return void
     */
    protected function request_signature(): void {
        global $DB;
        $DB->insert_record('these_signature', (object) [
            'dossierid' => $this->env->confidential->id, 'doctype' => 'subject', 'slot' => 'extdirector', 'userid' => 0,
            'signername' => 'Jules Juré', 'signeremail' => 'jury@example.org', 'token' => hash('sha256', 'jeton'),
            'requesttime' => make_timestamp(2026, 5, 2), 'timesigned' => 0, 'timecreated' => time(),
        ]);
    }

    /**
     * Une signature à lien ne se signe que par le lien reçu : « Mes signatures » du juré ne la propose
     * pas, même si l'adresse est celle de son compte ; la DEVE suit la signature attendue depuis le
     * suivi des signatures du dossier ; le directeur n'a rien.
     */
    public function test_get_signatures(): void {
        $this->setup_env();
        $this->request_signature();
        $label = these_document_label('subject') . ' (' . these_slot_label('extdirector') . ')';

        $juror = thesesynthesis_get_signatures($this->links_for($this->env->juror), $this->env->juror);
        $this->assertSame([], $juror['mine']);
        $this->assertSame([], $juror['awaited']);

        $deve = thesesynthesis_get_signatures($this->links_for($this->env->deve), $this->env->deve);
        $this->assertSame([], $deve['mine']);
        $this->assertCount(1, $deve['awaited']);
        $this->assertSame([$label], $deve['awaited'][0]->documents);
        $this->assertStringStartsWith('/mod/these/signatures.php?', $deve['awaited'][0]->url->out_as_local_url(false));
        $this->assertEquals($this->env->confidential->id, $deve['awaited'][0]->url->get_param('dossierid'));

        $director = thesesynthesis_get_signatures($this->links_for($this->env->director), $this->env->director);
        $this->assertSame(['mine' => [], 'awaited' => []], $director);
    }

    /**
     * Rendu : tableau these-signatures, bouton vers la page où agir ; titre confidentiel masqué à qui
     * ne connaît pas le sujet ; alerte sans signature.
     */
    public function test_render(): void {
        $this->setup_env();
        $this->request_signature();
        $rows = thesesynthesis_get_signatures($this->links_for($this->env->deve), $this->env->deve)['awaited'];
        $html = $this->renderer()->render(new signature_table($rows, ''));
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-signatures') . ']';
        $this->assert_html_has($html, $table);
        // Bouton vers la gestion des signatures de l'activité.
        $href = '/mod/these/signatures.php?id=' . $this->env->activities[0]->these->cmid;
        $this->assert_html_has($html, $table . '//td//a[contains(@href, "' . $href . '")][' . self::xclass('btn-primary') . ']'
            . '[starts-with(normalize-space(), "' . get_string('managesignatures', 'mod_these') . '")]');

        $rows[0]->knowssubject = false;
        $this->assert_no_secret($this->context_json(new signature_table($rows, '')));
        $empty = $this->renderer()->render(new signature_table([], 'Rien'));
        $this->assert_html_lacks($empty, './/table');
        $this->assertSame(['Rien'], $this->html_texts($empty, './div[' . self::xclass('alert-info') . ']'));
    }
}
