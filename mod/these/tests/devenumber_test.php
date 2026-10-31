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

use mod_these\local\dossiers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/these/tests/these_testcase.php');

/**
 * Numéro d'enregistrement DEVE : unique sur tout le site, attribué sous un verrou commun et garanti
 * par un index unique.
 *
 * @package    mod_these
 * @category   test
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\dossiers
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\dossiers::class)]
final class devenumber_test extends these_testcase {
    /** @var \stdClass Seconde activité (autre promotion) du même cours. */
    protected $these2;
    /** @var \stdClass */
    protected $cm2;

    /**
     * Prépare deux activités « Gestion des thèses » (deux promotions).
     *
     * @return void
     */
    protected function setup_two_activities(): void {
        global $DB;
        $this->setup_activity();
        $these2 = $this->getDataGenerator()->create_module('these', ['course' => $this->course->id, 'minjury' => 2]);
        $this->these2 = $DB->get_record('these', ['id' => $these2->id]);
        $this->cm2 = get_coursemodule_from_instance('these', $this->these2->id);
    }

    /**
     * Dossier d'une nouvelle étudiante à l'étape de validation de la DEVE.
     *
     * @param \stdClass $these
     * @return \stdClass
     */
    protected function dossier_at_deve_step(\stdClass $these): \stdClass {
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        return $this->generator->create_dossier($these, $student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id,
            'proposeddate' => time() + 30 * DAYSECS,
        ]);
    }

    /**
     * La suggestion continue la numérotation de l'année d'une activité à l'autre, et l'unicité vaut
     * pour tout le site.
     */
    public function test_numbering_is_site_wide(): void {
        $this->setup_two_activities();
        $year = userdate(time(), '%Y');
        $this->generator->create_dossier($this->these, $this->student->id, ['devenumber' => $year . '-004']);
        $this->assertEquals($year . '-005', dossiers::suggest_devenumber());
        $this->assertTrue(dossiers::devenumber_taken($year . '-004'));
        $this->assertFalse(dossiers::devenumber_taken(''));
        $this->assertNull(dossiers::normalise_devenumber('  '));
    }

    /**
     * Deux validations de la DEVE qui ont proposé le même numéro, enregistrées l'une après l'autre (dans
     * la même seconde), sur deux promotions : la seconde reçoit le numéro libre suivant.
     */
    public function test_same_proposal_gets_distinct_numbers(): void {
        $this->setup_two_activities();
        $this->redirectEmails();
        $first = $this->dossier_at_deve_step($this->these);
        $second = $this->dossier_at_deve_step($this->these2);
        $proposed = dossiers::suggest_devenumber();
        $fields = ['devenumber' => $proposed, 'devenumberproposed' => $proposed, 'defensedate' => time() + 30 * DAYSECS];

        $first = these_validate_step($this->these, $this->cm, $first, $this->deve->id, $fields);
        $second = these_validate_step($this->these2, $this->cm2, $second, $this->deve->id, $fields);

        $this->assertEquals($proposed, $first->devenumber);
        $this->assertNotEquals($first->devenumber, $second->devenumber);
        $this->assertEquals($second->devenumber, $this->reload($second->id)->devenumber);
        $this->assertEquals(THESE_STATUS_DEFENSE_VALIDATED, $this->reload($second->id)->status);
    }

    /**
     * Un numéro saisi, déjà attribué à un dossier d'une autre activité, est refusé par l'opération
     * (devenumbertaken, pas une erreur de base de données) et rien n'est enregistré.
     */
    public function test_typed_duplicate_refused(): void {
        global $DB;
        // Opérations annulées (rollback) suivies d'autres : pas de transaction englobante.
        $this->preventResetByRollback();
        $this->setup_two_activities();
        $this->redirectEmails();
        $other = $this->dossier_at_deve_step($this->these2);
        $DB->set_field('these_dossier', 'devenumber', 'ANCIEN-1', ['id' => $other->id]);
        $dossier = $this->dossier_at_deve_step($this->these);

        try {
            these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [
                'devenumber' => ' ANCIEN-1 ', 'devenumberproposed' => dossiers::suggest_devenumber(),
                'defensedate' => time() + 30 * DAYSECS,
            ]);
            $this->fail('Numéro en double accepté');
        } catch (\moodle_exception $e) {
            $this->assertNotInstanceOf(\dml_exception::class, $e);
            $this->assertEquals('devenumbertaken', $e->errorcode);
        }
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_APPROVED, $dossier->status);
        $this->assertNull($dossier->devenumber);

        // Modification par la DEVE : même refus ; un numéro vide est enregistré NULL.
        try {
            dossiers::edit_dossier($this->these, $this->cm, $dossier, ['devenumber' => 'ANCIEN-1'], [], $this->deve->id);
            $this->fail('Numéro en double accepté');
        } catch (\moodle_exception $e) {
            $this->assertEquals('devenumbertaken', $e->errorcode);
        }
        dossiers::edit_dossier($this->these, $this->cm, $dossier, ['devenumber' => '  '], [], $this->deve->id);
        $this->assertNull($this->reload($dossier->id)->devenumber);
    }

    /**
     * La base refuse elle-même un numéro en double (index unique) ; les numéros vides (NULL) ne
     * comptent pas.
     */
    public function test_unique_index(): void {
        global $DB;
        $this->setup_two_activities();
        $a = $this->dossier_at_deve_step($this->these);
        $b = $this->dossier_at_deve_step($this->these2);
        $this->dossier_at_deve_step($this->these2);
        $DB->set_field('these_dossier', 'devenumber', '2026-001', ['id' => $a->id]);
        $this->expectException(\dml_write_exception::class);
        $DB->set_field('these_dossier', 'devenumber', '2026-001', ['id' => $b->id]);
    }

    /**
     * Le numéro n'est attribué que sous le verrou : hors d'une opération sur un dossier, l'attribution
     * est une erreur de programmation.
     */
    public function test_claim_requires_lock(): void {
        $this->resetAfterTest();
        $this->assertEquals('X-1', dossiers::with_devenumbers_locked(function () {
            return dossiers::claim_devenumber(0, ' X-1 ');
        }));
        $this->expectException(\coding_exception::class);
        dossiers::claim_devenumber(0, 'X-1');
    }

    /**
     * Import : un numéro déjà attribué ailleurs sur le site est refusé.
     */
    public function test_import_refuses_site_duplicate(): void {
        global $DB;
        $this->setup_two_activities();
        $other = $this->dossier_at_deve_step($this->these2);
        $DB->set_field('these_dossier', 'devenumber', '2019-001', ['id' => $other->id]);
        $report = \mod_these\local\import::import_rows($this->these, [
            ['student', 'title', 'devenumber'],
            [$this->student->username, 'Sujet', '2019-001'],
        ], false, $this->deve->id);
        $this->assertFalse($report[0]['ok']);
        $this->assertEquals(get_string('devenumbertaken', 'mod_these'), $report[0]['message']);
    }
}
