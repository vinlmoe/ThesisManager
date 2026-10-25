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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Machine à états du circuit : la table des transitions couvre tous les statuts et toutes les
 * étapes, et these_set_status() refuse une transition qui n'y figure pas.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_status_transitions
 * @covers     ::these_transition_allowed
 * @covers     ::these_set_status
 */
final class transitions_test extends these_testcase {
    /**
     * Chaque statut a son entrée, chaque cible est un statut connu, et chaque étape de validation
     * (suite et renvois) est une transition autorisée.
     */
    public function test_table_is_consistent_with_the_steps(): void {
        $statuses = these_all_statuses();
        $table = these_status_transitions();
        $this->assertEqualsCanonicalizing(
            array_values(array_diff($statuses, [THESE_STATUS_CANCELLED])),
            array_keys($table)
        );
        foreach ($table as $from => $targets) {
            foreach ($targets as $to) {
                $this->assertContains($to, $statuses, "$from → $to");
                $this->assertNotSame($from, $to);
            }
        }

        foreach (these_review_steps() as $status => $step) {
            $this->assertTrue(these_transition_allowed($status, $step['next']), "$status : suite");
            $phase = these_phase($status);
            foreach ($step['returnto'] as $target) {
                if ($target === 'student') {
                    $to = these_in_corrections($status) ? THESE_STATUS_CORRECTIONS
                        : ($phase === 1 ? THESE_STATUS_SUBJECT_RETURNED : THESE_STATUS_DEFENSE_RETURNED);
                    // Après correction, le dossier revient à l'étape qui l'a renvoyé.
                    $this->assertTrue(these_transition_allowed($to, $status), "$to → $status (resoumission)");
                } else {
                    $to = $phase === 1 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_DEFENSE_SUBMITTED;
                }
                $this->assertTrue(these_transition_allowed($status, $to), "$status : renvoi $target");
            }
        }

        // Abandon depuis tout statut, réouverture dans tout statut.
        foreach ($statuses as $status) {
            $this->assertTrue(these_transition_allowed($status, THESE_STATUS_CANCELLED));
            $this->assertTrue(these_transition_allowed(THESE_STATUS_CANCELLED, $status));
            $this->assertTrue(these_transition_allowed($status, $status));
        }
        // Un dossier clos ne repart pas dans le circuit.
        foreach (array_diff($statuses, [THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED]) as $status) {
            $this->assertFalse(these_transition_allowed(THESE_STATUS_CLOSED, $status), "clos → $status");
        }
    }

    /**
     * these_set_status() refuse une transition hors table, sans rien enregistrer.
     */
    public function test_forbidden_transition_is_refused(): void {
        global $DB;
        $this->setup_activity();
        $forbidden = [
            [THESE_STATUS_DRAFT, THESE_STATUS_CLOSED],
            [THESE_STATUS_SUBJECT_SUBMITTED, THESE_STATUS_ACTIVE],
            [THESE_STATUS_ACTIVE, THESE_STATUS_SCHEDULED],
            [THESE_STATUS_DEFENSE_SUBMITTED, THESE_STATUS_CONFORM],
            [THESE_STATUS_CLOSED, THESE_STATUS_ACTIVE],
        ];
        foreach ($forbidden as [$from, $to]) {
            $dossier = $this->generator->create_dossier($this->these, $this->getDataGenerator()->create_user()->id, [
                'status' => $from,
            ]);
            try {
                these_set_status($dossier, $to, $this->deve->id, 'test');
                $this->fail("$from → $to accepté");
            } catch (\coding_exception $e) {
                $this->assertStringContainsString('Transition de statut interdite', $e->getMessage());
            }
            $this->assertEquals($from, $DB->get_field('these_dossier', 'status', ['id' => $dossier->id]));
            $this->assertFalse($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'test']));
        }
    }
}
