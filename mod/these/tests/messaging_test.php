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

use mod_these\local\messaging;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/these_testcase.php');

/**
 * Remise des courriels : messagerie de Moodle pour les comptes, courriel direct sans compte.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\messaging
 * @covers     \mod_these\local\notifications::send_email
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\messaging::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\notifications::class)]
final class messaging_test extends these_testcase {
    /**
     * Un destinataire qui a un compte reçoit un message de la messagerie (fournisseur de la famille
     * du courriel, lien vers le dossier, cours), un destinataire sans compte un courriel direct ; le
     * sujet et le texte sont les mêmes.
     */
    public function test_account_gets_message_external_gets_email(): void {
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $vars = these_email_base_vars($this->these, $this->cm, $dossier);
        $vars['status'] = these_status_label($dossier->status);
        $external = these_make_external_user('externe@example.com', 'Agent Externe');

        $sent = these_send_email($this->these, 'actionrequired', [$this->director, $external], $vars, '', '', $dossier);
        $this->assertSame(2, $sent);

        $expected = these_resolve_email_text($this->these->id, 'actionrequired', $vars);
        $notifications = $sink->get_notifications();
        $this->assertCount(1, $notifications);
        $notification = reset($notifications);
        $this->assertEquals($this->director->id, $notification->useridto);
        $this->assertSame('mod_these', $notification->component);
        $this->assertSame('dossierawaiting', $notification->eventtype);
        $this->assertSame($expected->subject, $notification->subject);
        $this->assertSame($expected->body, $notification->fullmessage);
        $this->assertEquals(FORMAT_PLAIN, $notification->fullmessageformat);
        $this->assertSame($expected->subject, $notification->smallmessage);
        $this->assertStringContainsString('/mod/these/dossier.php', (string) $notification->contexturl);
        $this->assertStringContainsString('dossierid=' . $dossier->id, (string) $notification->contexturl);
        $this->assertNotEmpty($notification->fullmessagehtml);

        $emails = $sink->get_emails();
        $this->assertCount(1, $emails);
        $this->assertSame('externe@example.com', $emails[0]->to);
        $this->assertSame($expected->subject, $emails[0]->subject);
        $this->assertStringContainsString('dossierid=' . $dossier->id, quoted_printable_decode($emails[0]->body));

        // Les deux envois sont présentés comme des courriels aux tests existants.
        $recipients = array_column($sink->get_messages(), 'to');
        $this->assertEqualsCanonicalizing([$this->director->email, 'externe@example.com'], $recipients);
        $sink->close();
    }

    /**
     * Une pièce jointe (fichier temporaire) part par courriel direct, même pour un compte.
     */
    public function test_attachment_goes_by_email(): void {
        global $CFG;
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id,
        ]);
        $path = make_request_directory() . '/convocation.txt';
        file_put_contents($path, 'Convocation');
        $vars = ['name' => fullname($this->student), 'student' => fullname($this->student), 'title' => $dossier->title,
            'date' => 'demain', 'location' => 'Amphi'];
        $sent = these_send_email($this->these, 'convocation', [$this->student], $vars, $path, 'convocation.txt', $dossier);
        $this->assertSame(1, $sent);
        $this->assertCount(0, $sink->get_notifications());
        $emails = $sink->get_emails();
        $this->assertCount(1, $emails);
        $this->assertSame($this->student->email, $emails[0]->to);
        $sink->close();
    }

    /**
     * Chaque courriel du module a un fournisseur, déclaré dans db/messages.php.
     */
    public function test_every_email_has_a_declared_provider(): void {
        global $CFG;
        $messageproviders = [];
        include($CFG->dirroot . '/mod/these/db/messages.php');
        $providers = messaging::providers();
        $this->assertSame([], array_diff_key(these_get_email_definitions(), $providers));
        foreach ($providers as $emailkey => $provider) {
            $this->assertArrayHasKey($provider, $messageproviders, $emailkey);
            $this->assertSame($provider, messaging::provider_for($emailkey));
        }
        $this->assertArrayHasKey(messaging::DEFAULT_PROVIDER, $messageproviders);
        foreach (array_keys($messageproviders) as $provider) {
            $this->assertTrue(get_string_manager()->string_exists('messageprovider:' . $provider, 'mod_these'), $provider);
        }
    }
}
