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
 * Résultats de la recherche : sujet confidentiel masqué, détails dépliables.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\search_results
 */
final class search_results_test extends output_testcase {
    /**
     * Résultats d'une recherche sans critère pour un utilisateur.
     *
     * @param \stdClass $user
     * @return search_results
     */
    private function results_for(\stdClass $user): search_results {
        $this->setUser($user);
        $questions = these_get_questions($this->env->scope);
        return new search_results(
            thesesynthesis_search($this->links_for($user), $user->id, ['text' => ''], $questions),
            $questions
        );
    }

    /**
     * Contexte : pour un enseignant qui ne connaît pas le sujet, aucun marqueur (titre, mots-clés,
     * résumé, réponses) ; pour le directeur, titre et détails dépliables.
     */
    public function test_export_for_template(): void {
        $this->setup_env();
        $json = $this->context_json($this->results_for($this->env->teacher));
        $this->assert_no_secret($json, 'enseignant');
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $json);

        $data = $this->results_for($this->env->director)->export_for_template($this->renderer());
        $this->assertFalse($data['isempty']);
        $this->assertSame('generaltable these-search', $data['table']['tableclass']);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        foreach (self::SECRETS as $field => $secret) {
            $this->assertStringContainsString($secret, $json, $field);
        }
        $summaries = [];
        foreach ($data['table']['rows'] as $row) {
            $details = $row['cells'][3]['parts'][0]['pdetails'];
            $this->assertNotFalse($details);
            $summaries[] = $details['summary'][0]['phtml']['value'];
        }
        $this->assertCount(1, preg_grep('/' . self::SECRETS['title'] . '/', $summaries));

        $empty = (new search_results([], []))->export_for_template($this->renderer());
        $this->assertTrue($empty['isempty']);
        $this->assertSame(get_string('searchnoresult', 'mod_thesesynthesis'), $empty['emptymessage']);
    }

    /**
     * Rendu : titre dépliable (details, summary, détail en petit) ; aucun marqueur pour l'enseignant.
     */
    public function test_render(): void {
        $this->setup_env();
        $html = $this->renderer()->render($this->results_for($this->env->director));
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-search') . ']';
        $this->assert_html_has($html, $table);
        // Titre dépliable ; le détail (libellés en gras) suit le résumé.
        $details = $table . '//td//details[summary[starts-with(normalize-space(), "' . self::SECRETS['title'] . '")]]';
        $this->assert_html_has($html, $details);
        $this->assert_html_has($html, $details . '/summary/following-sibling::div[' . self::xclass('small') . ']//strong');

        $results = $this->results_for($this->env->teacher);
        $html = $this->renderer()->render($results);
        $this->assert_no_secret($html);
        $this->assert_html_has($html, './/em[normalize-space() = "' . get_string('confidentialtitle', 'mod_these') . '"]');
    }
}
