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

use mod_these\local\prerequisites;
use mod_these\local\university;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Signatures préalables aux étapes, pièces scellées pour l'université et compte DEVE validateur des
 * signatures d'agent.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\prerequisites
 * @covers     \mod_these\local\university
 */
final class prerequisites_test extends these_testcase {
    /**
     * Signe un emplacement d'un document.
     *
     * @param \stdClass $dossier
     * @param string $doctype
     * @param string $slot
     * @param array $options Voir these_sign_document().
     * @return void
     */
    protected function sign(\stdClass $dossier, string $doctype, string $slot, array $options = []): void {
        these_sign_document(
            $this->context,
            $dossier,
            $doctype,
            $slot,
            0,
            'Signataire ' . $slot,
            empty($options['method']) ? these_sample_signature_image() : '',
            '',
            $options
        );
    }

    /**
     * Phase 1 : la DEVE n'enregistre le sujet qu'une fois signé par l'étudiant et le directeur, la
     * bibliothèque qu'une fois le visa de la DEVE apposé.
     */
    public function test_subject_steps(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id, 'title' => 'Sujet',
        ]);
        $slots = function ($dossier) {
            return array_map(function ($item) {
                return $item['doctype'] . ':' . $item['slot'];
            }, prerequisites::missing($this->reload($dossier->id)));
        };
        $this->assertSame(['subject:student', 'subject:director'], $slots($dossier));
        $this->sign($dossier, 'subject', 'student');
        $this->sign($dossier, 'subject', 'director');
        $this->assertSame([], $slots($dossier));

        $DB->set_field('these_dossier', 'status', THESE_STATUS_SUBJECT_REGISTERED, ['id' => $dossier->id]);
        $this->assertSame(['subject:deve'], $slots($dossier));
        // Pendant les corrections, plus aucun document n'est attendu.
        $dossier->status = THESE_STATUS_CORRECTIONS;
        $this->assertSame([], prerequisites::missing($dossier));
    }

    /**
     * Pièces de l'université : dès qu'un document n'attend plus que l'université, il est scellé pour
     * l'envoi ; au retour, le scan inscrit la signature, le document est scellé définitivement et le
     * procès-verbal n'est plus bloqué.
     */
    public function test_university_documents(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_CONFORM, 'directorid' => $this->director->id, 'title' => 'Sujet',
            'agreement' => json_encode(['defvdate' => time(), 'mobility' => 1]),
        ]);
        foreach (['deve', 'director', 'dgrep'] as $slot) {
            $this->sign($dossier, 'agreement', $slot);
        }
        $dossier = $this->reload($dossier->id);
        $this->assertSame(['agreement' => ['ccem']], university::pending($dossier));
        // Manuscrit conforme : l'université n'est pas encore attendue.
        $this->assertNotContains('agreement', array_column(prerequisites::missing($dossier), 'doctype'));

        // Envoi : pièce scellée, archive, historique.
        [$zippath, $zipname] = university::package($this->these, $this->context, $dossier, $this->deve->id);
        $this->assertFileExists($zippath);
        $this->assertStringEndsWith('.zip', $zipname);
        $this->assertNotNull(university::get_sealed($this->context, $dossier->id, 'agreement'));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'universitysealed']));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'universitydownloaded']));

        // Soutenance programmée : le procès-verbal attend la signature de l'université.
        $dossier->status = THESE_STATUS_SCHEDULED;
        $this->assertTrue(prerequisites::only_university(array_filter(
            prerequisites::missing($dossier),
            function ($item) {
                return $item['doctype'] === 'agreement';
            }
        )));

        // Retour : scan déposé pour le CCEM, document scellé définitivement.
        $this->sign($dossier, 'agreement', 'ccem', ['method' => 'scan', 'verifiedby' => $this->deve->id]);
        $this->assertSame([], university::pending($dossier));
        $this->assertNotContains('agreement', array_column(prerequisites::missing($dossier), 'doctype'));
        $this->assertNotNull(these_get_sealed_document($this->context, $dossier->id, 'agreement'));

        // Une signature qui disparaît retire aussi la pièce scellée pour l'université.
        university::seal($this->these, $this->context, $dossier, 'agreement', $this->deve->id);
        these_unseal_document($this->context, $dossier->id, 'agreement');
        $this->assertNull(university::get_sealed($this->context, $dossier->id, 'agreement'));
    }

    /**
     * Signatures demandées aux agents DEVE : le compte de l'équipe DEVE qui a validé l'étape est
     * inscrit, gardé à la relance, et imprimé sous la signature.
     */
    public function test_validator_recorded(): void {
        $this->setup_activity();
        $agent = $this->getDataGenerator()->create_user(['username' => 'agentdeve', 'email' => 'agent@example.org']);
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deveagent' => 'agentdeve']);
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id,
        ]);
        $signature = (object) ['name' => 'Équipe DEVE', 'png' => these_sample_signature_image()];
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [], '', $signature);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals($this->deve->id, these_get_signatures($dossier->id)['subject:deve']->validatedby);

        // Relance : le validateur est gardé.
        these_request_agent_signatures($this->these, $this->cm, $dossier, ['subject']);
        $this->assertEquals($this->deve->id, these_get_signatures($dossier->id)['subject:deve']->validatedby);

        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'subject', 'deve', $agent->id, fullname($agent), $png);
        $this->assertEquals($this->deve->id, these_get_signatures($dossier->id)['subject:deve']->validatedby);
        $caption = \mod_these\local\doctemplates::signature_caption(['name' => fullname($agent), 'time' => time(),
            'hash' => 'abc', 'validator' => fullname($this->deve)]);
        $this->assertStringContainsString(get_string('pdfvalidatedby', 'mod_these', fullname($this->deve)), $caption);
    }

    /**
     * Refus de signer d'un agent DEVE : le dossier revient à l'étape de création des documents
     * refusés (la première s'il y en a plusieurs), avec le motif ; les demandes de la même validation
     * sont retirées, celles d'une étape antérieure restent.
     */
    public function test_agent_refusal(): void {
        global $DB;
        $this->setup_activity();
        $agent = $this->getDataGenerator()->create_user(['username' => 'agentdeve', 'email' => 'agent@example.org']);
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deveagent' => 'agentdeve']);
        $this->redirectEmails();
        $make = function () {
            return $this->generator->create_dossier($this->these, $this->getDataGenerator()->create_user()->id, [
                'status' => THESE_STATUS_DEFENSE_VALIDATED, 'directorid' => $this->director->id, 'title' => 'Sujet',
                'agreement' => json_encode(['defvdate' => time(), 'mobility' => 1]), 'conclusions' => 'Conclusions.',
            ]);
        };
        $pending = function ($dossier) use ($agent) {
            return array_values(array_filter(these_get_user_pending_signatures($agent), function ($signature) use ($dossier) {
                return (int) $signature->dossierid === (int) $dossier->id;
            }));
        };

        // Fiche d'agréments seule : retour à la validation de la DEVE.
        $dossier = $make();
        these_request_agent_signatures($this->these, $this->cm, $dossier, ['subject', 'agreement', 'conclusions'], $this->deve->id);
        $signatures = $pending($dossier);
        $this->assertNotEmpty($signatures);
        foreach ($signatures as $signature) {
            // Le visa du sujet (étape antérieure) ne se refuse plus depuis la phase de soutenance.
            $refusable = \mod_these\local\refusal::refusable($signature, $dossier);
            $this->assertSame($signature->doctype !== 'subject', $refusable, $signature->doctype);
            $this->assertNotEmpty($signature->validatedtime);
        }
        $agreement = array_values(array_filter($signatures, function ($signature) {
            return $signature->doctype === 'agreement';
        }));
        $dossier = \mod_these\local\refusal::refuse($this->these, $this->cm, $dossier, $agreement, $agent->id, 'DEFV erronée');
        $this->assertEquals(THESE_STATUS_DEFENSE_APPROVED, $dossier->status);
        $this->assertSame('DEFV erronée', $dossier->returncomment);
        $this->assertSame(['subject'], array_column($pending($dossier), 'doctype'));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'agentrefused']));

        // Fiche d'agréments et conclusions : la première étape, l'étudiant, qui revient ensuite à la DEVE.
        $dossier = $make();
        these_request_agent_signatures($this->these, $this->cm, $dossier, ['agreement', 'conclusions'], $this->deve->id);
        $refused = $pending($dossier);
        $dossier = \mod_these\local\refusal::refuse($this->these, $this->cm, $dossier, $refused, $agent->id, 'À reprendre');
        $this->assertEquals(THESE_STATUS_DEFENSE_RETURNED, $dossier->status);
        $this->assertEquals(THESE_STATUS_DEFENSE_APPROVED, $dossier->returnstatus);

        // Plus rien à refuser une fois le dossier renvoyé ; motif obligatoire.
        $this->expectException(\moodle_exception::class);
        \mod_these\local\refusal::refuse($this->these, $this->cm, $dossier, [], $agent->id, '');
    }
}
