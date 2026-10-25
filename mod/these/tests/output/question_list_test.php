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
 * Questions du questionnaire, avec leurs actions.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\question_list
 */
final class question_list_test extends output_testcase {
    /**
     * Contexte : libellé formaté, type, options, obligatoire ; « monter » sauf pour la première,
     * « descendre » sauf pour la dernière.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $scope = these_settings_scope($this->these);
        these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Labo & co', 'options' => '', 'required' => 1]);
        these_save_question($scope, (object) ['qtype' => 'list', 'label' => 'Espèce', 'options' => "Chien\nChat", 'required' => 0]);
        $url = new \moodle_url('/mod/these/questions.php', ['id' => $this->cm->id]);
        $data = (new question_list(these_get_questions($scope), $url))->export_for_template($this->renderer());
        $this->assertSame('generaltable these-questions', $data['tableclass']);
        [$first, $second] = $data['rows'];
        $this->assertSame('Labo &amp; co', $first['cells'][0]['parts'][0]['phtml']['value']);
        $this->assertSame('Chien ; Chat', $second['cells'][2]['parts'][0]['ptext']['value']);
        $this->assertSame(get_string('yes'), $first['cells'][3]['parts'][0]['ptext']['value']);
        $labels = function ($row) {
            return array_values(array_filter(array_map(function ($part) {
                return $part['plink'] ? $part['plink']['value'] : null;
            }, $row['cells'][4]['parts'])));
        };
        $this->assertSame([get_string('edit'), get_string('movedown'), get_string('delete')], $labels($first));
        $this->assertSame([get_string('edit'), get_string('moveup'), get_string('delete')], $labels($second));
    }

    /**
     * Rendu : actions séparées par une barre verticale.
     */
    public function test_render(): void {
        $this->setup_activity();
        $scope = these_settings_scope($this->these);
        these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Labo', 'options' => '', 'required' => 1]);
        $url = new \moodle_url('/mod/these/questions.php', ['id' => $this->cm->id]);
        $html = $this->renderer()->render(new question_list(these_get_questions($scope), $url));
        $this->assertStringContainsString('<table class="generaltable these-questions">', $html);
        $this->assertStringContainsString('</a> | <a href=', $html);
    }
}
