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
 * Historique d'un dossier : motifs masqués selon le lecteur.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\history
 */
final class history_test extends output_testcase {
    /**
     * Prépare un dossier avec un renvoi au directeur et un changement de sujet dans l'historique.
     *
     * @return \stdClass
     */
    private function dossier_with_logs(): \stdClass {
        global $DB;
        $dossier = $this->confidential_dossier();
        $logs = [['returnedtodirector', 'Motif DEVE'], ['subjectchanged', 'Ancien titre : ' . self::SECRETS['oldtitle']]];
        foreach ($logs as $i => [$action, $remark]) {
            $DB->insert_record('these_log', (object) ['dossierid' => $dossier->id, 'userid' => $this->deve->id,
                'logaction' => $action, 'fromstatus' => 0, 'tostatus' => THESE_STATUS_ACTIVE, 'remark' => $remark,
                'timecreated' => make_timestamp(2026, 3, 1 + $i)]);
        }
        return $dossier;
    }

    /**
     * Contexte : motif du renvoi au directeur hors vue du personnel, ancien titre pour qui ne connaît
     * pas le sujet, tous deux absents du contexte.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $dossier = $this->dossier_with_logs();

        $staff = $this->context_json(new history($dossier->id));
        $this->assertStringContainsString('Motif DEVE', $staff);
        $this->assertStringContainsString(self::SECRETS['oldtitle'], $staff);
        $this->assertStringContainsString(fullname($this->deve), $staff);

        $student = $this->context_json(new history($dossier->id, false, true));
        $this->assertStringNotContainsString('Motif DEVE', $student);
        $this->assertStringContainsString(self::SECRETS['oldtitle'], $student);

        $outsider = $this->context_json(new history($dossier->id, true, false));
        $this->assert_no_secret($outsider);
        $this->assertStringContainsString('Motif DEVE', $outsider);

        $empty = (new history(0))->export_for_template($this->renderer());
        $this->assertSame(['haslogs' => false, 'heading' => '', 'table' => false], $empty);
    }

    /**
     * Rendu : tableau these-history, entrée la plus récente en tête ; rien sans entrée.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->dossier_with_logs();
        $html = $this->renderer()->render(new history($dossier->id, false, false));
        $this->assertStringContainsString('<table class="generaltable these-history">', $html);
        $returned = strpos($html, s(get_string('logreturnedtodirector', 'mod_these')));
        $this->assertLessThan($returned, strpos($html, s(get_string('logsubjectchanged', 'mod_these'))));
        $this->assert_no_secret($html);
        $this->assertSame('', these_render_history(0));
    }
}
