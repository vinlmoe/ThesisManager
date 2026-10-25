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
 * Formulaire de recherche des thèses.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\search_form
 */
final class search_form_test extends output_testcase {
    /**
     * Contexte : quatre champs, puis un champ par question (texte ou liste avec « Indifférent »),
     * valeurs saisies reprises.
     */
    public function test_export_for_template(): void {
        $this->setup_env();
        $links = $this->links_for($this->env->director);
        $questions = these_get_questions($this->env->scope);
        $listid = (int) array_values($questions)[1]->id;
        $base = new \moodle_url('/mod/thesesynthesis/search.php', ['id' => $this->env->cm->id]);
        $data = (new search_form($base, $this->env->cm->id, $links, $questions, ['q' => 'rage', 'year' => 2026,
            'answers' => [$listid => 'Chat']]))->export_for_template($this->renderer());
        $this->assertCount(4, $data['fields']);
        $this->assertSame(['name' => 'q', 'value' => 'rage', 'size' => 50, 'id' => 'search-q'], $data['fields'][0]['text']);
        $this->assertSame('menuyear', $data['fields'][2]['select']['id']);
        $this->assertSame(get_string('questionnaire', 'mod_these'), $data['questionheading']);
        $this->assertCount(2, $data['questionfields']);
        $list = $data['questionfields'][1]['select'];
        $this->assertSame('search-a' . $listid, $list['id']);
        $this->assertSame(['', 'Chien', 'Chat'], array_column($list['options'], 'value'));
        $this->assertSame([false, false, true], array_column($list['options'], 'selected'));
        $reset = 'https://www.example.com/moodle/mod/thesesynthesis/search.php?id=' . $this->env->cm->id;
        $this->assertSame($reset, $data['reseturl']);

        $data = (new search_form($base, $this->env->cm->id, $links, [], []))->export_for_template($this->renderer());
        $this->assertFalse($data['questionheading']);
    }

    /**
     * Rendu : formulaire thesesynthesis-search, titre du questionnaire, boutons.
     */
    public function test_render(): void {
        $this->setup_env();
        $base = new \moodle_url('/mod/thesesynthesis/search.php', ['id' => $this->env->cm->id]);
        $html = $this->renderer()->render(new search_form(
            $base,
            $this->env->cm->id,
            $this->links_for($this->env->director),
            these_get_questions($this->env->scope),
            []
        ));
        $this->assertStringContainsString('id="thesesynthesis-search"', $html);
        $field = '<div class="form-inline d-flex align-items-center mb-2"><label class="mr-2 me-2 mb-1">';
        $this->assertStringContainsString($field, $html);
        $this->assertStringContainsString('<h5 class="mt-3">' . get_string('questionnaire', 'mod_these') . '</h5>', $html);
        $input = '<input type="text" name="q" value="" size="50" class="form-control" id="search-q" />';
        $this->assertStringContainsString($input, $html);
        $this->assertStringEndsWith('class="btn btn-secondary">' . get_string('reset') . '</a></form>', $html);
    }
}
