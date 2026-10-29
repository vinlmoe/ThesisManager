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
 * Renvois : les signatures de la phase sont révoquées ; un dossier renvoyé à l'étudiant refait, à
 * sa nouvelle soumission, toute la phase ; un renvoi de la DEVE au directeur le ramène à l'étape du
 * directeur de la même phase, en gardant les signatures de l'étudiant.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_return_dossier
 * @covers     ::these_submission_target
 */
final class return_test extends these_testcase {
    /**
     * Le directeur renvoie le sujet : l'étudiant le corrige et le soumet de nouveau au directeur.
     */
    public function test_director_returns_subject_to_student(): void {
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);

        these_return_dossier($this->these, $this->cm, $dossier, $this->director->id, 'student', 'Préciser la problématique');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_RETURNED, $dossier->status);
        $this->assertEquals(0, $dossier->returnstatus);
        $this->assertEquals('Préciser la problématique', $dossier->returncomment);
        $this->assertTrue(these_subject_editable($dossier));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->student->email, $messages[0]->to);
        $this->assertStringContainsString('Préciser la problématique', quoted_printable_decode($messages[0]->body));

        $dossier->title = 'Titre corrigé';
        these_save_subject($this->these, $this->cm, $dossier, $dossier, true, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $this->assertEquals('', $dossier->returncomment);
        $this->assertEquals(0, $dossier->returnedby);
    }

    /**
     * La DEVE renvoie le dossier de soutenance à l'étudiant : les documents signés de la soutenance
     * sont révoqués (pas le dépôt de sujet), et le dossier resoumis repasse par le directeur.
     */
    public function test_deve_return_to_student_restarts_phase(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id,
        ]);
        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'subject', 'student', $this->student->id, 'Étudiant', $png);
        these_sign_document($this->context, $dossier, 'conclusions', 'director', $this->director->id, 'Directeur', $png);
        these_sign_document($this->context, $dossier, 'bordereau', 'student', $this->student->id, 'Étudiant', $png);

        these_return_dossier($this->these, $this->cm, $dossier, $this->deve->id, 'student', 'Annexe manquante');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_RETURNED, $dossier->status);
        $this->assertTrue(these_defense_editable($dossier));
        $this->assertSame(['subject:student'], array_keys(these_get_signatures($dossier->id)));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'signaturesrevoked']));

        these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), true, $this->student->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $this->reload($dossier->id)->status);
    }

    /**
     * La DEVE renvoie le dossier au directeur, qui est prévenu ; le motif ne s'affiche pas à
     * l'étudiant.
     */
    public function test_deve_returns_to_director(): void {
        global $PAGE;
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id,
        ]);

        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'bordereau', 'student', $this->student->id, 'Étudiant', $png);
        these_sign_document($this->context, $dossier, 'conclusions', 'director', $this->director->id, 'Directeur', $png);
        these_return_dossier($this->these, $this->cm, $dossier, $this->deve->id, 'director', 'Rapport non signé');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $dossier->status);
        // Signature de l'étudiant conservée, celle du directeur redemandée.
        $this->assertSame(['bordereau:student'], array_keys(these_get_signatures($dossier->id)));
        $this->assertEquals(0, $dossier->returnstatus);
        $this->assertEquals($this->director->email, $sink->get_messages()[0]->to);

        $PAGE->set_url(new \moodle_url('/mod/these/view.php', ['id' => $this->cm->id]));
        $studentview = these_render_dossier(
            $this->these,
            $this->cm,
            $this->context,
            $dossier,
            these_get_rights($this->context, $dossier, $this->student->id)
        );
        $this->assertStringNotContainsString('Rapport non signé', $studentview);
        $directorview = these_render_dossier(
            $this->these,
            $this->cm,
            $this->context,
            $dossier,
            these_get_rights($this->context, $dossier, $this->director->id)
        );
        $this->assertStringContainsString('Rapport non signé', $directorview);
    }

    /**
     * Un renvoi qui n'est pas prévu à l'étape courante est refusé.
     */
    public function test_invalid_return_target(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED, 'directorid' => $this->director->id,
        ]);
        $this->expectException(\coding_exception::class);
        these_return_dossier($this->these, $this->cm, $dossier, $this->librarian->id, 'director', 'Non');
    }

    /**
     * Abandon puis réouverture : le dossier reprend le statut atteint.
     */
    public function test_cancel_and_reopen(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_ACTIVE]);
        these_cancel_dossier($dossier, $this->deve->id, 'Réorientation');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CANCELLED, $dossier->status);
        these_reopen_dossier($dossier, $this->deve->id);
        $this->assertEquals(THESE_STATUS_ACTIVE, $this->reload($dossier->id)->status);
    }
}
