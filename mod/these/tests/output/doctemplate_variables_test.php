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
 * Variables disponibles dans un modèle.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\doctemplate_variables
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\doctemplate_variables::class)]
final class doctemplate_variables_test extends output_testcase {
    /**
     * Contexte : nom de la variable entre accolades et valeur d'exemple, en texte brut.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $sample = these_doctemplate_sample_context(these_settings_scope($this->these), 'subject', $this->these);
        $data = (new doctemplate_variables($sample))->export_for_template($this->renderer());
        $this->assertSame('generaltable table-sm', $data['tableclass']);
        $this->assertCount(count(these_doctemplate_variables($sample)), $data['rows']);
        $first = array_key_first(these_doctemplate_variables($sample));
        $this->assertSame('{{' . $first . '}}', $data['rows'][0]['cells'][0]['parts'][0]['pcode']['value']);
        $this->assertNotFalse($data['rows'][0]['cells'][1]['parts'][0]['ptext']);
    }

    /**
     * Rendu : noms en code, exemples échappés ; les accolades des données ne sont pas interprétées
     * par Mustache.
     */
    public function test_render(): void {
        $this->setup_activity();
        $sample = these_doctemplate_sample_context(these_settings_scope($this->these), 'subject', $this->these);
        $html = $this->renderer()->render(new doctemplate_variables($sample));
        $first = array_key_first(these_doctemplate_variables($sample));
        $this->assert_html_has($html, './/code[normalize-space() = "{{' . $first . '}}"]');
        $this->assertStringContainsString('{{{...}}}', $html);
    }
}
