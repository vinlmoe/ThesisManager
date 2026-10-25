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
 * Navigation du planning : vues, périodes, accès à une date.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\planning_nav
 */
final class planning_nav_test extends output_testcase {
    /**
     * Composant de la vue par semaine (semaine du 2 novembre 2026, période en cours), date demandée le 12.
     *
     * @return planning_nav
     */
    private function week_nav(): planning_nav {
        $monday = make_timestamp(2026, 11, 2);
        // La semaine affichée n'est pas forcément celle de la date demandée : seule la navigation est testée.
        return new planning_nav(
            new \moodle_url('/mod/these/planning.php', ['id' => 7]),
            'week',
            $monday,
            these_planning_shift_week($monday, -1),
            $monday,
            these_planning_shift_week($monday, 1),
            make_timestamp(2026, 11, 12, 12)
        );
    }

    /**
     * Contexte : vue courante en bouton plein, période courante active, champs cachés du formulaire
     * (paramètres de la page puis vue), date du champ.
     */
    public function test_export_for_template(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');
        $data = $this->week_nav()->export_for_template($this->renderer());
        $views = array_column($data['views'], 'class');
        $this->assertSame(['btn btn-sm btn-primary mr-1', 'btn btn-sm btn-outline-primary mr-1'], $views);
        $this->assertStringContainsString('view=month', $data['views'][1]['url']);
        $this->assertSame(
            ['btn btn-secondary mb-1 mr-1', 'btn btn-secondary mb-1 mr-1 active', 'btn btn-secondary mb-1 mr-3'],
            array_column($data['periods'], 'class')
        );
        $this->assertSame('« ' . get_string('planningprevweek', 'mod_these'), $data['periods'][0]['label']);
        $this->assertSame([['name' => 'id', 'value' => '7'], ['name' => 'view', 'value' => 'week']], $data['hidden']);
        $this->assertSame('2026-11-12', $data['gotovalue']);
        $this->assertSame('https://www.example.com/moodle/mod/these/planning.php', $data['action']);

        $month = make_timestamp(2026, 11, 1);
        $nav = new planning_nav(
            new \moodle_url('/mod/these/planning.php', ['id' => 7]),
            'month',
            $month,
            these_planning_shift_month($month, -1),
            make_timestamp(2026, 10, 1),
            these_planning_shift_month($month, 1),
            $month
        );
        $data = $nav->export_for_template($this->renderer());
        $this->assertSame('btn btn-sm btn-primary mr-1', $data['views'][1]['class']);
        $this->assertSame('btn btn-secondary mb-1 mr-1', $data['periods'][1]['class']);
        $this->assertSame(get_string('planningnextmonth', 'mod_these') . ' »', $data['periods'][2]['label']);
    }

    /**
     * Rendu : deux blocs, boutons collés, formulaire GET avec le champ de date these-planning-goto.
     */
    public function test_render(): void {
        $this->resetAfterTest();
        $html = $this->renderer()->render($this->week_nav());
        $this->assertStringStartsWith('<div class="mb-3"><a href=', $html);
        $this->assertStringContainsString('</a><a href=', $html);
        $this->assertStringContainsString('<div class="these-planning-nav mb-3">', $html);
        $action = 'action="https://www.example.com/moodle/mod/these/planning.php"';
        $this->assertStringContainsString('<form method="get" ' . $action, $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="7" />', $html);
        $this->assertStringContainsString('<label class="mr-1" for="these-planning-goto">', $html);
        $this->assertStringContainsString('id="these-planning-goto"', $html);
    }
}
