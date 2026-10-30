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
 * Dossier complet : décisions d'accès dans le contexte, rendu.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\dossier
 */
final class dossier_test extends output_testcase {
    /**
     * Contexte pour un utilisateur.
     *
     * @param \stdClass $dossier
     * @param \stdClass $user
     * @return string Contexte sérialisé en JSON.
     */
    private function json_for(\stdClass $dossier, \stdClass $user): string {
        $this->setUser($user);
        $rights = these_get_rights($this->context, $dossier, $user->id);
        return $this->context_json(new dossier($this->these, $this->cm, $this->context, $dossier, $rights));
    }

    /**
     * Thèse confidentielle : aucun champ du contexte ne contient le titre, le titre anglais, le résumé,
     * les mots-clés, l'ancien titre, la réponse au questionnaire ni le motif d'embargo pour qui ne
     * connaît pas le sujet ; tout y est pour qui le connaît.
     */
    public function test_export_for_template_confidential(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');

        foreach (['viewall' => $this->viewall_user(), 'autre enseignant' => $other] as $name => $user) {
            $json = $this->json_for($dossier, $user);
            $this->assert_no_secret($json, $name);
            $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $json, $name);
        }
        foreach (
            ['étudiant' => $this->student, 'directeur' => $this->director, 'DEVE' => $this->deve,
                'bibliothèque' => $this->librarian] as $name => $user
        ) {
            $json = $this->json_for($dossier, $user);
            foreach (['title', 'titleen', 'summary', 'keywords', 'oldtitle', 'answer', 'embargoreason'] as $field) {
                $this->assertStringContainsString(self::SECRETS[$field], $json, "$name ($field)");
            }
        }
    }

    /**
     * Contexte : statut, acteur attendu, actions selon les droits, sections de la soutenance.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $this->setUser($this->deve);
        $rights = these_get_rights($this->context, $dossier, $this->deve->id);
        $data = (new dossier($this->these, $this->cm, $this->context, $dossier, $rights))->export_for_template($this->renderer());
        $this->assertSame(these_status_label(THESE_STATUS_SCHEDULED), $data['status']['label']);
        $this->assertStringContainsString(these_actor_label(these_step_actor(THESE_STATUS_SCHEDULED)), $data['waiting']);
        $this->assertFalse($data['returnnotice']);
        $this->assertFalse($data['adjourned']);
        $this->assertContains(get_string('editdossier', 'mod_these'), array_column($data['actionbar']['actions'], 'label'));
        $this->assertTrue($data['defense']['hasrows']);
        $this->assertTrue($data['jury']['hasmembers']);
        $this->assertStringContainsString('jurynotify.php', $data['juryresend']['url']);

        $noactions = new dossier($this->these, $this->cm, $this->context, $dossier, $rights, false);
        $this->assertFalse($noactions->export_for_template($this->renderer())['actionbar']);

        // Un détenteur de viewall n'a ni actions, ni colonne d'éligibilité du jury, ni bouton de renvoi des accès.
        $viewall = $this->viewall_user();
        $this->setUser($viewall);
        $rights = these_get_rights($this->context, $dossier, $viewall->id);
        $data = (new dossier($this->these, $this->cm, $this->context, $dossier, $rights))->export_for_template($this->renderer());
        $this->assertFalse($data['actionbar']);
        $this->assertFalse($data['juryresend']);
        $this->assertFalse($data['answers']);
        $this->assertFalse($data['subjecthistory']);
        $this->assertCount(6, $data['jury']['table']['head']);
    }

    /**
     * Rendu : mêmes blocs, classes et ordre que l'ancien rendu ; sujet masqué pour viewall.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $viewall = $this->viewall_user();
        $this->setUser($viewall);
        $rights = these_get_rights($this->context, $dossier, $viewall->id);
        $html = $this->renderer()->render(new dossier($this->these, $this->cm, $this->context, $dossier, $rights));
        $this->assertSame($html, these_render_dossier($this->these, $this->cm, $this->context, $dossier, $rights));
        $this->assert_no_secret($html);
        // Avancement en tête, puis le statut, puis les rubriques dans cet ordre.
        $this->assert_html_has($html, './*[1][' . self::xclass('these-progress-compact') . ']');
        $this->assert_html_has($html, './/ol[' . self::xclass('these-progress') . ']');
        $sections = ['subject', 'diffusion', 'registration', 'defense', 'jury'];
        $headings = $this->html_texts($html, './/h4');
        $positions = [];
        foreach ($sections as $section) {
            $position = array_search(get_string($section, 'mod_these'), $headings, true);
            $this->assertNotFalse($position, $section);
            $positions[] = $position;
        }
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $first = get_string('subject', 'mod_these');
        $this->assert_html_has($html, './/span[' . self::xclass('badge') . ']/following::h4[normalize-space() = "' . $first . '"]');
        $this->assert_html_has($html, './/em[normalize-space() = "' . get_string('confidentialtitle', 'mod_these') . '"]');
        $this->assert_html_has($html, './/span[' . self::xclass('badge') . ' and ' . self::xclass('badge-danger') . ']');
    }
}
