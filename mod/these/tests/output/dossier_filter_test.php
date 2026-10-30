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
 * Filtres de la liste des dossiers.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\dossier_filter
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\dossier_filter::class)]
final class dossier_filter_test extends output_testcase {
    /**
     * Contexte : liste des statuts avec « tous », statut choisi, texte.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $url = new \moodle_url('/mod/these/dashboard.php', ['id' => $this->cm->id]);
        $filter = new dossier_filter($url, $this->cm->id, (string) THESE_STATUS_ACTIVE, 'rage');
        $data = $filter->export_for_template($this->renderer());
        $this->assertSame('https://www.example.com/moodle/mod/these/dashboard.php', $data['action']);
        $this->assertSame('these-status', $data['statuses']['id']);
        $this->assertSame('', $data['statuses']['options'][0]['value']);
        $selected = array_values(array_filter($data['statuses']['options'], function ($option) {
            return $option['selected'];
        }));
        $this->assertSame((string) THESE_STATUS_ACTIVE, $selected[0]['value']);
        $this->assertSame('rage', $data['search']);
    }

    /**
     * Rendu : formulaire GET en ligne avec libellé, liste et zone de texte.
     */
    public function test_render(): void {
        $this->setup_activity();
        $url = new \moodle_url('/mod/these/dashboard.php', ['id' => $this->cm->id]);
        $html = $this->renderer()->render(new dossier_filter($url, $this->cm->id, '', 'a & b'));
        $form = './*[1][self::form][@method = "get"][@action = "https://www.example.com/moodle/mod/these/dashboard.php"]';
        $this->assert_html_has($html, $form . '[' . self::xclass('form-inline') . ']');
        // Libellé associé à la liste des statuts.
        $this->assert_html_has($html, $form . '//label[@for = "these-status"]');
        $this->assert_html_has($html, $form . '//select[@id = "these-status"][@name = "status"]');
        $this->assert_html_has($html, $form . '//input[@value = "a & b"]');
    }
}
