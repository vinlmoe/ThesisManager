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
 * Frise de progression : état de chaque étape, phase dormante.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\progress
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\progress::class)]
final class progress_test extends output_testcase {
    /**
     * Contexte : étapes faites, en cours, à venir, résumé de l'étape en cours ; un dossier abandonné
     * n'a pas d'étape en cours.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $dossier = (object) ['status' => THESE_STATUS_DEFENSE_APPROVED, 'cancelledfromstatus' => 0];
        $data = (new progress($dossier))->export_for_template($this->renderer());
        $steps = $data['steps'];
        $this->assertStringStartsWith(get_string('progresscurrent', 'mod_these', (object) [
            'position' => 7, 'total' => count(these_progress_steps()), 'label' => '',
        ]), $data['current']['label']);
        $this->assertCount(count(these_progress_steps()), $steps);
        $this->assertStringStartsWith('1. ', $steps[0]['label']);
        $states = array_column($steps, 'stateclass');
        $this->assertSame(1, count(array_keys($states, 'these-step-current')));
        $this->assertSame('these-step-current', $states[6]);
        $this->assertSame(get_string('dormantphase', 'mod_these'), $steps[3]['separator']);
        $this->assertSame([3], array_keys(array_filter(array_column($steps, 'separator'))));

        $cancelled = (object) ['status' => THESE_STATUS_CANCELLED, 'cancelledfromstatus' => THESE_STATUS_DEFENSE_SUBMITTED];
        $data = (new progress($cancelled))->export_for_template($this->renderer());
        $this->assertNotContains('these-step-current', array_column($data['steps'], 'stateclass'));
        $this->assertFalse($data['current']);
    }

    /**
     * Rendu : étape en cours résumée et frise repliée pour les petits écrans, frise complète pour les
     * autres ; séparateur de la phase dormante après la quatrième étape.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render(new progress((object) ['status' => THESE_STATUS_DRAFT, 'cancelledfromstatus' => 0]));
        // Résumé compact et détail repliable sur petit écran, frise complète sur grand écran.
        $small = self::xclass('d-md-none');
        $this->assert_html_has($html, './*[1][' . self::xclass('these-progress-compact') . ' and ' . $small . ']');
        $this->assert_html_has($html, './details[' . self::xclass('these-progress-mobile') . ' and ' . $small . ']');
        $this->assert_html_has($html, './div[' . self::xclass('d-none') . ' and ' . self::xclass('d-md-block') . ']');
        $this->assert_html_has($html, './/ol[' . self::xclass('these-progress') . ']', 2);
        $this->assert_html_has($html, './/ol/li[' . self::xclass('these-step') . ']', 2 * count(these_progress_steps()));
        $this->assert_html_has($html, './/ol/li[' . self::xclass('these-step-separator') . ']', 2);
        $labels = $this->html_texts($html, './/li[' . self::xclass('these-step') . ']//span['
            . self::xclass('these-step-label') . ']');
        $this->assertStringStartsWith('1. ', $labels[0]);
    }
}
