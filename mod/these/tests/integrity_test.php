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
 * Intégrité des dossiers et des documents : scellé des documents signés, mise en forme couverte par
 * les signatures, liens à jeton (empreinte, expiration, révocation), opérations atomiques sur un
 * dossier, images des modèles.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_seal_document
 * @covers     ::these_signature_template_changed
 * @covers     ::these_get_signatures_by_token
 * @covers     ::these_jury_access_open
 * @covers     ::these_get_jury_member_access
 * @covers     ::these_dossier_operation
 * @covers     \mod_these\pdf\html_document::restrict_images
 */
final class integrity_test extends these_testcase {
    /**
     * Modèle d'origine d'un document, complété d'un paragraphe.
     *
     * @param string $type
     * @param string $text
     * @return string
     */
    private function changed_template(string $type, string $text): string {
        return str_replace('<!--@body-->', '<!--@body--><p>' . $text . '</p>', these_doctemplate_default($type));
    }

    /**
     * Un document entièrement signé est scellé : son PDF ne change plus, même si le modèle change,
     * et son empreinte figure à l'historique. Une signature retirée lève le scellé.
     */
    public function test_fully_signed_document_is_sealed(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id,
            'conformitygrid' => json_encode(['fondlist' => 1]), 'ethicsnumber' => '2526',
        ]);
        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'grid', 'director', $this->director->id, 'Pr Durand', $png);

        $sealed = these_get_sealed_document($this->context, $dossier->id, 'grid');
        $this->assertNotNull($sealed);
        $pdf = $sealed->get_content();
        $log = $DB->get_record('these_log', ['dossierid' => $dossier->id, 'logaction' => 'documentsealed']);
        $this->assertStringContainsString(hash('sha256', $pdf), $log->remark);
        // Le nom du document scellé porte la date du scellement.
        $this->assertSame(
            'grid-' . userdate($sealed->get_timecreated(), '%Y-%m-%d', 99, false) . '.pdf',
            \mod_these\local\documents::signed_document_filename($this->context, $dossier, 'grid')
        );

        // Modèle modifié après la signature : le document signé reste celui qui a été signé.
        these_set_doctemplate(these_local_scope($this->these), 'grid', $this->changed_template('grid', 'Ajout'));
        $this->assertSame($pdf, these_signed_document_pdf($this->these, $this->context, $dossier, 'grid'));
        $this->assertSame([], these_refresh_signatures($this->context, $dossier));

        // Contenu modifié : signature invalidée, scellé levé.
        $dossier->ethicsnumber = '2527';
        $this->assertSame(['grid:director'], these_refresh_signatures($this->context, $dossier));
        $this->assertNull(these_get_sealed_document($this->context, $dossier->id, 'grid'));
        $this->assertSame('grid.pdf', \mod_these\local\documents::signed_document_filename($this->context, $dossier, 'grid'));
    }

    /**
     * Tant qu'un document attend des signatures, un changement de sa mise en forme invalide celles
     * déjà apposées : les signataires suivants ne signent pas un autre texte.
     */
    public function test_template_change_invalidates_partial_signatures(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'subject', 'student', $this->student->id, 'Étudiant', $png);
        $this->assertNull(these_get_sealed_document($this->context, $dossier->id, 'subject'));
        $this->assertSame([], these_refresh_signatures($this->context, $dossier));

        these_set_doctemplate(these_local_scope($this->these), 'subject', $this->changed_template('subject', 'Clause ajoutée'));
        $this->assertSame(['subject:student'], these_refresh_signatures($this->context, $dossier));
    }

    /**
     * Sans contrat type lisible, le contrat de diffusion n'est pas scellé.
     */
    public function test_contract_without_template_is_not_sealed(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id,
            'bordereau' => json_encode(['birthname' => 'MARTIN', 'diffusion' => 'restricted']),
        ]);
        $this->assertSame('contracttemplatemissing', these_contract_template_problem($this->these));
        $png = these_sample_signature_image();
        foreach (these_document_slots($dossier, 'contract') as $slot) {
            these_sign_document($this->context, $dossier, 'contract', $slot, 0, 'Signataire', $png);
        }
        $this->assertTrue(these_document_fully_signed($dossier, 'contract'));
        $this->assertNull(these_get_sealed_document($this->context, $dossier->id, 'contract'));

        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_these', 'filearea' => 'contracttemplate',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'contrat.pdf',
        ], 'Ceci n\'est pas un PDF');
        $this->assertSame('contracttemplateunreadable', these_contract_template_problem($this->these));
        these_signed_document_pdf($this->these, $this->context, $dossier, 'contract');
        $this->assertDebuggingCalled();
        $this->assertNull(these_get_sealed_document($this->context, $dossier->id, 'contract'));
    }

    /**
     * Un lien de signature expire après THESE_TOKEN_LIFETIME.
     */
    public function test_signature_token_expires(): void {
        global $DB;
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id, 'externaldirector' => 1,
            'extdirectorname' => 'Dr Externe', 'extdirectoremail' => 'externe@example.com',
        ]);
        these_request_signature($this->these, $this->cm, $dossier, 'extdirector', ['subject']);
        $token = $this->token_sent_to($sink->get_messages(), 'externe@example.com');
        $this->assertCount(1, these_get_signatures_by_token($token));

        $DB->set_field('these_signature', 'requesttime', time() - THESE_TOKEN_LIFETIME - 1, ['dossierid' => $dossier->id]);
        $this->assertEmpty(these_get_signatures_by_token($token));
        $sink->close();
    }

    /**
     * L'accès du jury expire après la soutenance et cesse à la clôture du dossier ; un membre ayant
     * un compte y accède sans lien, reconnu à son adresse.
     */
    public function test_jury_access_lifetime(): void {
        global $DB;
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id, 'defensedate' => time() + DAYSECS,
        ]);
        $juror = $this->getDataGenerator()->create_user(['email' => 'juror@example.org']);
        these_save_jury($dossier->id, [(object) ['juryrole' => 'president', 'fullname' => 'Jean MARTIN', 'jurytitle' => 'Pr',
            'institution' => 'VetAgro Sup', 'email' => 'juror@example.org']]);
        these_notify_jury($this->these, $this->cm, $dossier);
        $token = $this->token_sent_to($sink->get_messages(), 'juror@example.org', 'jury.php');
        $member = these_get_jury($dossier->id)[0];

        $this->assertNotNull(these_get_jury_access($token));
        $this->assertNotNull(these_get_jury_member_access($member->id, $juror));
        $this->assertNull(these_get_jury_member_access($member->id, $this->student));

        // Soutenance passée depuis plus longtemps que la durée de validité.
        $DB->set_field('these_dossier', 'defensedate', time() - THESE_TOKEN_LIFETIME - DAYSECS, ['id' => $dossier->id]);
        $DB->set_field('these_jury', 'notifiedtime', time() - THESE_TOKEN_LIFETIME - DAYSECS, ['id' => $member->id]);
        $this->assertNull(these_get_jury_access($token));
        $this->assertNull(these_get_jury_member_access($member->id, $juror));

        // Clôture : le lien est révoqué.
        $DB->set_field('these_dossier', 'defensedate', time(), ['id' => $dossier->id]);
        $this->assertNotNull(these_get_jury_access($token));
        these_set_status($this->reload($dossier->id), THESE_STATUS_CLOSED, $this->deve->id, 'closed');
        $this->assertNull(these_get_jury_access($token));
        $this->assertNull($DB->get_field('these_jury', 'accesstoken', ['id' => $member->id]));
        $sink->close();
    }

    /**
     * Une opération sur un dossier qui a changé de statut entre-temps (double envoi, autre
     * utilisateur) est refusée, et rien de ce qu'elle a commencé n'est conservé.
     */
    public function test_stale_operation_is_refused(): void {
        global $DB;
        // Le test ne doit pas tourner dans la transaction du cadre de test : il vérifie l'annulation
        // de la transaction de l'opération.
        $this->preventResetByRollback();
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id,
        ]);
        $seen = clone $dossier;
        these_cancel_dossier($dossier, $this->deve->id, 'Abandon');
        $logs = $DB->count_records('these_log', ['dossierid' => $dossier->id]);

        try {
            these_cancel_dossier($seen, $this->deve->id, 'Abandon (doublon)');
            $this->fail('Opération sur un dossier périmé acceptée');
        } catch (\moodle_exception $e) {
            $this->assertSame('dossierchanged', $e->errorcode);
        }
        $this->assertSame($logs, $DB->count_records('these_log', ['dossierid' => $dossier->id]));

        // Opération qui échoue en cours de route : ses écritures sont annulées.
        $dossier = $this->reload($dossier->id);
        try {
            these_dossier_operation($dossier, function () use ($dossier) {
                these_set_status($dossier, THESE_STATUS_ACTIVE, $this->deve->id, 'reopened');
                throw new \coding_exception('Échec');
            });
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Échec', $e->getMessage());
        }
        $this->assertEquals(THESE_STATUS_CANCELLED, $this->reload($dossier->id)->status);
        $this->assertSame($logs, $DB->count_records('these_log', ['dossierid' => $dossier->id]));
    }

    /**
     * Les modèles ne peuvent faire lire à TCPDF que les images du module ou des images en ligne.
     */
    public function test_template_images_are_restricted(): void {
        $html = '<p><img src="@QUJD" width="10"/><img src="/etc/passwd"/><img width="3" src=\'http://intranet/x.png\'/>'
            . '<img src="data:image/png;base64,QUJD"/><img src=file:///etc/hosts></p>';
        $this->assertSame(
            '<p><img src="@QUJD" width="10"/><img src="data:image/png;base64,QUJD"/></p>',
            \mod_these\pdf\html_document::restrict_images($html)
        );
    }
}
