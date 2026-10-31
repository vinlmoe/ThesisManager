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
 * Soutenances d'une période : un tableau par jour, titre confidentiel masqué.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\planning
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\planning::class)]
final class planning_test extends output_testcase {
    /**
     * Soutenances de la semaine du dossier confidentiel (le 3 décembre 2026) et d'une thèse ordinaire
     * proposée le même jour, vues par un utilisateur.
     *
     * @param \stdClass $user
     * @return \stdClass[] Voir these_get_defenses().
     */
    private function defenses_for(\stdClass $user): array {
        $sources = [these_planning_source($this->these, $this->cm, $this->context, $user->id)];
        $monday = these_planning_week_start(make_timestamp(2026, 12, 3));
        return these_get_defenses($sources, $monday, these_planning_shift_week($monday, 1), $user->id);
    }

    /**
     * Prépare le dossier confidentiel et une soutenance proposée le même jour, plus tôt.
     *
     * @return void
     */
    private function setup_defenses(): void {
        $this->setTimezone('Europe/Paris');
        $this->setup_activity();
        $this->confidential_dossier();
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->create_dossier($this->these, $other->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'title' => 'Arthrose du chien', 'directorid' => $this->director->id,
            'defensedate' => make_timestamp(2026, 12, 3, 9), 'proposeddate' => make_timestamp(2026, 12, 3, 9),
            'defenselocation' => 'Salle 2',
        ]);
    }

    /**
     * Contexte : un jour, deux lignes dans l'ordre des heures ; colonne de la promotion à la demande ;
     * président en tête du jury ; date proposée ou confirmée ; titre confidentiel absent du contexte
     * pour viewall, présent pour la bibliothèque.
     */
    public function test_export_for_template(): void {
        $this->setup_defenses();
        $viewall = $this->viewall_user();

        $this->setUser($viewall);
        $defenses = $this->defenses_for($viewall);
        $data = (new planning($defenses, false))->export_for_template($this->renderer());
        $this->assertFalse($data['isempty']);
        $this->assertCount(1, $data['days']);
        $this->assertSame(
            userdate(make_timestamp(2026, 12, 3), get_string('strftimedaydate', 'langconfig')),
            $data['days'][0]['heading']
        );
        $table = $data['days'][0]['table'];
        $this->assertSame('generaltable these-planning', $table['tableclass']);
        $this->assertCount(6, $table['head']);
        $this->assertCount(2, $table['rows']);
        [$proposed, $scheduled] = $table['rows'];
        $this->assertSame('badge badge-warning', $proposed['cells'][0]['parts'][2]['pbadge']['class']);
        $this->assertSame('badge badge-success', $scheduled['cells'][0]['parts'][2]['pbadge']['class']);
        $this->assertSame('Arthrose du chien', $proposed['cells'][2]['parts'][0]['ptext']['value']);
        $this->assertSame('Professeur Paul Président', $scheduled['cells'][4]['parts'][0]['pstrong']['value']);
        $this->assertStringContainsString('dossier.php', $scheduled['cells'][1]['parts'][0]['plink']['url']);

        $json = $this->context_json(new planning($defenses, false));
        $this->assert_no_secret($json);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $json);

        $this->assertCount(7, (new planning($defenses, true))->export_for_template($this->renderer())['days'][0]['table']['head']);

        $this->setUser($this->librarian);
        $json = $this->context_json(new planning($this->defenses_for($this->librarian), false));
        $this->assertStringContainsString(self::SECRETS['title'], $json);

        $empty = (new planning([], false))->export_for_template($this->renderer());
        $this->assertTrue($empty['isempty']);
        $this->assertSame(get_string('planningnone', 'mod_these'), $empty['emptymessage']);
    }

    /**
     * Rendu : titre du jour, tableau these-planning, lien vers le dossier ; message sans soutenance.
     */
    public function test_render(): void {
        $this->setup_defenses();
        $viewall = $this->viewall_user();
        $this->setUser($viewall);
        $defenses = $this->defenses_for($viewall);
        $html = $this->renderer()->render(new planning($defenses, false));
        $this->assertSame($html, these_render_planning($defenses, false));
        $this->assert_html_has($html, './*[1][self::h4]');
        $table = './/table[' . self::xclass('generaltable') . ' and ' . self::xclass('these-planning') . ']';
        $this->assert_html_has($html, $table);
        // Président du jury en gras, suivi de sa qualité entre parenthèses.
        $president = $table . '//td//strong[normalize-space() = "Professeur Paul Président"]';
        $this->assert_html_has($html, $president . '[starts-with(normalize-space(following-sibling::text()[1]), "(")]');
        $badge = self::xclass('badge') . ' and ' . self::xclass('badge-success');
        $this->assert_html_has($html, $table . '//td//span[' . $badge . ']');
        $this->assert_no_secret($html);
        $empty = these_render_planning([], false);
        $this->assert_html_lacks($empty, './/table');
        $alert = './div[' . self::xclass('alert-info') . ']';
        $this->assertSame([get_string('planningnone', 'mod_these')], $this->html_texts($empty, $alert));
    }
}
