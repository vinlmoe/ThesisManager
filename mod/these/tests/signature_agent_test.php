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
 * @covers     \mod_these\local\signatureagent::signs_dg_by_delegation
 * @covers     \mod_these\local\notifications::send_signature_reminders
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
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deveagent' => 'agentdeve']);
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_APPROVED, 'directorid' => $this->director->id,
        ]);
        $this->assertFalse(these_signs_slot_at_validation('deve', $this->deve->id, $this->these));
        $this->assertTrue(these_signs_slot_at_validation('deve', $agent->id, $this->these));
        $this->assertTrue(these_signs_slot_at_validation('director', $this->deve->id, $this->these));

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
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deveagent' => '']);
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
        $agents = "agent1,\nAgent2@example.org ; inconnu";
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deveagent' => $agents]);
        $this->assertSame([(int) $first->id, (int) $second->id], array_map('intval', array_keys(these_deve_agents($this->these))));
        $this->assertTrue(these_signs_slot_at_validation('deve', $first->id, $this->these));
        $this->assertTrue(these_signs_slot_at_validation('dgrep', $second->id, $this->these));
        $this->assertFalse(these_signs_slot_at_validation('deve', $this->deve->id, $this->these));

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
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deveagent' => 'agent2']);
        $this->assertCount(0, these_get_user_pending_signatures($first));
        $this->assertCount(1, these_get_user_pending_signatures($second));
    }

    /**
     * La direction générale ne signe jamais elle-même : sans agent DEVE, aucun lien ne lui est envoyé
     * (signature sur place ou par scan) ; avec des agents, ses documents leur sont demandés à la
     * validation du dossier de soutenance, dans le même courriel que l'agrément, et l'agent les signe
     * « par délégation » depuis « Mes signatures ». Les agents sont relancés pour leurs signatures et
     * celles de la direction générale (bouton du suivi, tâche de relance).
     */
    public function test_dg_by_delegation(): void {
        global $DB;
        $this->setup_activity();
        $agent = $this->getDataGenerator()->create_user(['username' => 'agentdeve', 'email' => 'agent@example.org']);
        $scope = these_local_scope($this->these);
        \mod_these\local\scopesettings::set($scope, ['dgname' => 'Directrice générale']);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id, 'conclusions' => 'Conclusions.',
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());

        // Sans agent : signature en attente, sur place, et aucun courriel à la direction générale.
        $this->assertFalse(\mod_these\local\signatureagent::signs_dg_by_delegation($this->these));
        $this->assertSame(['deve', 'dgrep'], \mod_these\local\signatureagent::slots_for($this->these));
        $sink = $this->redirectEmails();
        $this->assertNotNull(these_request_signature($this->these, $this->cm, $dossier, 'dg', ['conclusions']));
        $this->assertCount(0, $sink->get_messages());

        \mod_these\local\scopesettings::set($scope, ['deveagent' => 'agentdeve']);
        $this->assertTrue(\mod_these\local\signatureagent::signs_dg_by_delegation($this->these));
        $this->assertSame(['deve', 'dgrep', 'dg'], \mod_these\local\signatureagent::slots_for($this->these));
        $this->assertNull(these_request_signature($this->these, $this->cm, $dossier, 'dg', ['conclusions']));
        $this->assertCount(0, $sink->get_messages());

        // Validation par l'équipe DEVE : un seul courriel à l'agent, rien à la direction générale.
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [
            'defensedate' => time() + 40 * DAYSECS, 'devenumber' => these_suggest_devenumber($this->these->id),
        ]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_VALIDATED, $dossier->status);
        $recipients = array_column($sink->get_messages(), 'to');
        $this->assertCount(1, array_keys($recipients, 'agent@example.org'));
        $request = these_get_signatures($dossier->id)['conclusions:dg'];
        $this->assertNull($request->token);
        $this->assertEquals($agent->id, $request->userid);
        $sink->clear();

        // « Mes signatures » de l'agent : les conclusions, à l'emplacement de la direction générale.
        $pending = array_values(array_filter(these_get_user_pending_signatures($agent), function ($signature) {
            return $signature->slot === 'dg';
        }));
        $this->assertSame(['conclusions'], array_column($pending, 'doctype'));
        $this->assertStringContainsString(
            get_string('signeddelegated', 'mod_these'),
            \mod_these\local\signatureagent::document_label('conclusions', 'dg')
        );

        // Relance depuis le suivi des signatures (bouton « Relancer ») : l'agent est prévenu de nouveau.
        $available = array_values(array_filter(array_keys(these_document_definitions()), function ($doctype) use ($dossier) {
            return these_document_available($dossier, $doctype);
        }));
        $this->assertGreaterThan(0, these_request_agent_signatures($this->these, $this->cm, $dossier, $available));
        $messages = $sink->get_messages();
        $this->assertSame(['agent@example.org'], array_column($messages, 'to'));
        $body = preg_replace('/\s+/', ' ', quoted_printable_decode($messages[0]->body));
        $this->assertStringContainsString(these_slot_label('dg'), $body);
        $sink->clear();

        // Relance automatique, une fois le délai écoulé : le président du jury et l'agent, pas la
        // direction générale.
        $DB->set_field('these_signature', 'requesttime', time() - 20 * DAYSECS, ['dossierid' => $dossier->id]);
        $this->assertEquals(2, \mod_these\local\notifications::send_signature_reminders());
        $recipients = array_column($sink->get_messages(), 'to');
        sort($recipients);
        $this->assertSame(['agent@example.org', 'president@example.com'], $recipients);
        $sink->clear();

        // L'agent signe : signature par délégation, à son nom.
        these_sign_document(
            $this->context,
            $pending[0]->dossier,
            'conclusions',
            'dg',
            $agent->id,
            fullname($agent),
            these_sample_signature_image(),
            '',
            ['delegated' => true]
        );
        $signed = these_get_signatures($dossier->id)['conclusions:dg'];
        $this->assertNotEmpty($signed->timesigned);
        $this->assertEquals(1, $signed->delegated);
        $this->assertEquals($agent->id, $signed->userid);

        // Agents retirés du réglage : les signatures en attente de la direction générale quittent
        // « Mes signatures » de l'ancien agent et ne donnent lieu à aucun lien.
        $other = $this->generator->create_dossier($this->these, $this->getDataGenerator()->create_user()->id, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED, 'directorid' => $this->director->id, 'conclusions' => 'Conclusions.',
        ]);
        these_request_agent_signatures($this->these, $this->cm, $other, ['conclusions']);
        $this->assertNotEmpty(array_filter(these_get_user_pending_signatures($agent), function ($signature) use ($other) {
            return (int) $signature->dossierid === (int) $other->id;
        }));
        \mod_these\local\scopesettings::set($scope, ['deveagent' => '']);
        $this->assertEmpty(array_filter(these_get_user_pending_signatures($agent), function ($signature) use ($other) {
            return (int) $signature->dossierid === (int) $other->id;
        }));
        $sink->clear();
        $this->assertNotNull(these_request_signature($this->these, $this->cm, $other, 'dg', ['conclusions']));
        $this->assertCount(0, $sink->get_messages());
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
