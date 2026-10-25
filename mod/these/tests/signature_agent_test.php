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
 * Agent responsable de la DEVE (signature après la vérification par l'équipe) et signature
 * enregistrée des utilisateurs.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_request_agent_signatures
 * @covers     ::these_get_user_pending_signatures
 * @covers     ::these_signature_from_form
 */
final class signature_agent_test extends these_testcase {
    /**
     * L'équipe DEVE valide sans signer ; l'agent est prévenu, retrouve le document dans « Mes
     * signatures » et le signe ; sans agent désigné, la DEVE qui valide signe.
     */
    public function test_agent_signs_after_team(): void {
        $this->setup_activity();
        $agent = $this->getDataGenerator()->create_user(['username' => 'agentdeve', 'email' => 'agent@example.org']);
        set_config('deveagent', 'agentdeve', 'mod_these');
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id,
        ]);
        $this->assertFalse(these_signs_slot_at_validation('deve', $this->deve->id));
        $this->assertTrue(these_signs_slot_at_validation('deve', $agent->id));
        $this->assertTrue(these_signs_slot_at_validation('director', $this->deve->id));

        $signature = (object) ['name' => 'Équipe DEVE', 'png' => these_sample_signature_image()];
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [], '', $signature);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_REGISTERED, $dossier->status);
        $signatures = these_get_signatures($dossier->id);
        $this->assertEquals(0, $signatures['subject:deve']->timesigned);
        $this->assertEquals($agent->id, $signatures['subject:deve']->userid);
        $this->assertContains('agent@example.org', array_column($sink->get_messages(), 'to'));

        $pending = these_get_user_pending_signatures($agent);
        $this->assertCount(1, $pending);
        $item = reset($pending);
        these_sign_document(
            $this->context,
            $item->dossier,
            $item->doctype,
            $item->slot,
            $agent->id,
            fullname($agent),
            these_sample_signature_image()
        );
        $this->assertNotEmpty(these_get_signatures($dossier->id)['subject:deve']->timesigned);
        $this->assertCount(0, these_get_user_pending_signatures($agent));

        // Sans agent : la DEVE qui valide signe elle-même.
        set_config('deveagent', '', 'mod_these');
        $other = $this->generator->create_dossier($this->these, $this->getDataGenerator()->create_user()->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id,
        ]);
        these_validate_step($this->these, $this->cm, $other, $this->deve->id, [], '', $signature);
        $this->assertNotEmpty(these_get_signatures($other->id)['subject:deve']->timesigned);
    }

    /**
     * Plusieurs agents DEVE : chacun est prévenu et retrouve le document ; le premier qui signe
     * appose la signature et le document disparaît pour les autres ; un agent retiré du réglage ne
     * voit plus les documents en attente.
     */
    public function test_several_agents(): void {
        $this->setup_activity();
        $gen = $this->getDataGenerator();
        $first = $gen->create_user(['username' => 'agent1', 'email' => 'agent1@example.org']);
        $second = $gen->create_user(['username' => 'agent2', 'email' => 'agent2@example.org']);
        set_config('deveagent', "agent1,\nAgent2@example.org ; inconnu", 'mod_these');
        $this->assertSame([(int) $first->id, (int) $second->id], array_map('intval', array_keys(these_deve_agents())));
        $this->assertTrue(these_signs_slot_at_validation('deve', $first->id));
        $this->assertTrue(these_signs_slot_at_validation('dgrep', $second->id));
        $this->assertFalse(these_signs_slot_at_validation('deve', $this->deve->id));

        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id,
        ]);
        $signature = (object) ['name' => 'Équipe DEVE', 'png' => these_sample_signature_image()];
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [], '', $signature);
        $request = these_get_signatures($dossier->id)['subject:deve'];
        $this->assertEquals(0, $request->timesigned);
        $this->assertEquals(0, $request->userid);
        $recipients = array_column($sink->get_messages(), 'to');
        $this->assertContains('agent1@example.org', $recipients);
        $this->assertContains('agent2@example.org', $recipients);
        $this->assertCount(1, these_get_user_pending_signatures($first));
        $this->assertCount(1, these_get_user_pending_signatures($second));
        $this->assertCount(0, these_get_user_pending_signatures($this->deve));

        $pending = these_get_user_pending_signatures($second);
        $item = reset($pending);
        these_sign_document(
            $this->context,
            $item->dossier,
            $item->doctype,
            $item->slot,
            $second->id,
            fullname($second),
            these_sample_signature_image()
        );
        $signed = these_get_signatures($dossier->id)['subject:deve'];
        $this->assertNotEmpty($signed->timesigned);
        $this->assertEquals($second->id, $signed->userid);
        $this->assertCount(0, these_get_user_pending_signatures($first));

        // Un agent retiré du réglage ne voit plus les documents qui attendent un agent.
        $other = $this->generator->create_dossier($this->these, $gen->create_user()->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id,
        ]);
        these_validate_step($this->these, $this->cm, $other, $this->deve->id, [], '', $signature);
        set_config('deveagent', 'agent2', 'mod_these');
        $this->assertCount(0, these_get_user_pending_signatures($first));
        $this->assertCount(1, these_get_user_pending_signatures($second));
    }

    /**
     * Un membre du jury invité par courriel et qui a un compte à cette adresse retrouve sa
     * signature en attente dans « Mes signatures ».
     */
    public function test_invited_signer_with_account(): void {
        $this->setup_activity();
        $president = $this->getDataGenerator()->create_user(['email' => 'President@Example.com']);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED, 'directorid' => $this->director->id, 'conclusions' => 'Conclusions.',
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        $this->redirectEmails();
        these_request_signature($this->these, $this->cm, $dossier, 'president', ['conclusions']);
        $pending = these_get_user_pending_signatures($president);
        $this->assertCount(1, $pending);
        $this->assertSame('conclusions', reset($pending)->doctype);
        $this->assertCount(0, these_get_user_pending_signatures($this->student));
    }

    /**
     * Signature enregistrée : enregistrée au premier tracé, reprise ensuite sans tracé, supprimable.
     */
    public function test_saved_signature(): void {
        $this->setup_activity();
        $this->setUser($this->director);
        $png = these_sample_signature_image();
        $this->assertNull(these_get_saved_signature($this->director->id));
        $this->assertNotEmpty(these_signature_errors(['usesavedsignature' => 1, 'signername' => 'D', 'signatureconsent' => 1]));

        $data = (object) ['signaturedata' => 'data:image/png;base64,' . base64_encode($png), 'signername' => 'Hélène Durand',
            'savesignature' => 1];
        $this->assertSame($png, these_signature_from_form($data)->png);
        $this->assertSame($png, these_get_saved_signature($this->director->id));

        $this->assertSame([], these_signature_errors(['usesavedsignature' => 1, 'signername' => 'D', 'signatureconsent' => 1]));
        $reused = these_signature_from_form((object) ['usesavedsignature' => 1, 'signername' => 'Hélène Durand']);
        $this->assertSame($png, $reused->png);

        these_delete_saved_signature($this->director->id);
        $this->assertNull(these_get_saved_signature($this->director->id));
        $this->assertNull(these_signature_from_form((object) ['usesavedsignature' => 1, 'signername' => 'H']));
    }
}
