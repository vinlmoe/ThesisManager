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
 * Parcours complet du circuit, du dépôt du sujet au procès-verbal, tel que décrit dans le
 * document de travail : chaque étape fait passer le dossier au statut suivant, prévient l'acteur
 * suivant et s'inscrit à l'historique.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_save_subject
 * @covers     ::these_validate_step
 * @covers     ::these_save_defense
 * @covers     ::these_generate_documents
 * @covers     ::these_transmit_documents
 * @covers     ::these_close_dossier
 */
final class workflow_test extends these_testcase {
    /**
     * Phase 1 : sujet déposé, validé par le directeur, enregistré par la DEVE puis par la
     * bibliothèque ; le dossier devient actif.
     */
    public function test_subject_phase(): void {
        global $DB;
        $this->setup_activity();
        $sink = $this->redirectEmails();

        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->student->id);
        $this->assertEquals(THESE_STATUS_DRAFT, $dossier->status);

        // Brouillon : aucun changement de statut, aucun courriel.
        $data = (object) ['title' => 'Leptospirose canine', 'summary' => 'Résumé', 'keywords' => 'chien',
            'directorid' => $this->director->id];
        these_save_subject($this->these, $this->cm, $dossier, $data, false, $this->student->id);
        $this->assertEquals(THESE_STATUS_DRAFT, $this->reload($dossier->id)->status);
        $this->assertCount(0, $sink->get_messages());

        // Étape 1 : soumission au directeur, qui est prévenu.
        these_save_subject($this->these, $this->cm, $dossier, $data, true, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->director->email, $messages[0]->to);
        $sink->clear();

        // Étape 2 : validation par le directeur, la DEVE est prévenue.
        these_validate_step($this->these, $this->cm, $dossier, $this->director->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_APPROVED, $dossier->status);
        $this->assertContains($this->deve->email, array_column($sink->get_messages(), 'to'));
        $sink->clear();

        // Étape 3 : enregistrement DEVE avec numéro.
        $number = these_suggest_devenumber($this->these->id);
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, ['devenumber' => $number]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_REGISTERED, $dossier->status);
        $this->assertEquals($number, $dossier->devenumber);
        $this->assertContains($this->librarian->email, array_column($sink->get_messages(), 'to'));
        $sink->clear();

        // Étape 4 : enregistrement bibliothèque, dossier actif, étudiant et directeur prévenus.
        these_validate_step($this->these, $this->cm, $dossier, $this->librarian->id, ['libraryref' => 'NOT-42']);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_ACTIVE, $dossier->status);
        $this->assertEquals('NOT-42', $dossier->libraryref);
        $recipients = array_column($sink->get_messages(), 'to');
        sort($recipients);
        $expected = [$this->student->email, $this->director->email];
        sort($expected);
        $this->assertEquals($expected, $recipients);

        $this->assertEquals(6, $DB->count_records('these_log', ['dossierid' => $dossier->id]));
    }

    /**
     * Phase 2 : dépôt, validation du directeur avec rapport, validation DEVE, conformité,
     * génération et transmission des documents, procès-verbal.
     */
    public function test_defense_phase(): void {
        global $DB;
        $this->setup_activity(['universityemail' => 'scolarite@univ.example.com']);
        $sink = $this->redirectEmails();

        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id, 'devenumber' => '2026-001',
        ]);

        // Étape 1 : dépôt et soumission, avec le jury proposé.
        these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), true, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $dossier->status);
        $this->assertEquals($dossier->proposeddate, $dossier->defensedate);
        $this->assertCount(4, these_get_jury($dossier->id));
        $this->assertEquals($this->director->email, $sink->get_messages()[0]->to);
        $sink->clear();

        // Étapes 2 à 4.
        these_validate_step($this->these, $this->cm, $dossier, $this->director->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_APPROVED, $dossier->status);
        $date = time() + 40 * DAYSECS;
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, ['defensedate' => $date]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_VALIDATED, $dossier->status);
        $this->assertEquals($date, $dossier->defensedate);
        these_validate_step($this->these, $this->cm, $dossier, $this->librarian->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CONFORM, $dossier->status);
        $sink->clear();

        // Étape 5 : convocation étudiant, une par membre du jury, feuille de jury, PV.
        $files = these_generate_documents($this->these, $this->context, $dossier, $this->deve->id);
        $this->assertCount(7, $files);
        foreach ($files as $file) {
            $this->assertStringStartsWith('%PDF', $file->get_content());
        }
        $dossier = $this->reload($dossier->id);
        $this->assertNotEmpty($dossier->documentstime);

        $result = these_transmit_documents($this->these, $this->cm, $this->context, $dossier, $this->deve->id);
        // Étudiant, trois membres du jury avec adresse, université ; l'invitée n'a pas d'adresse.
        $this->assertEquals(5, $result->sent);
        $this->assertEquals(['Iris Invitée'], $result->missing);
        $recipients = array_column($sink->get_messages(), 'to');
        $this->assertContains('president@example.com', $recipients);
        $this->assertContains('scolarite@univ.example.com', $recipients);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SCHEDULED, $dossier->status);
        $sink->clear();

        // Étape 6 : procès-verbal, dossier clôturé.
        these_close_dossier($this->these, $this->cm, $dossier, $this->deve->id, THESE_RESULT_ADMITTED, 'Félicitations');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
        $this->assertEquals(THESE_RESULT_ADMITTED, $dossier->defenseresult);
        $this->assertCount(2, $sink->get_messages());
        $this->assertNull(these_step_actor($dossier->status));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'closed']));
    }

    /**
     * Un enregistrement du dépôt de soutenance sans soumission conserve le statut actif et la
     * date proposée sert de date retenue tant que la DEVE ne l'a pas arrêtée.
     */
    public function test_defense_draft_keeps_status(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id,
        ]);
        these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), false, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_ACTIVE, $dossier->status);
        $this->assertEquals($dossier->proposeddate, $dossier->defensedate);
    }

    /**
     * Les adresses de service, si elles sont renseignées, remplacent la notification individuelle
     * de chaque membre de la DEVE ou de la bibliothèque.
     */
    public function test_service_address_replaces_individual_notifications(): void {
        $this->setup_activity(['deveemail' => 'deve@example.com']);
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        these_validate_step($this->these, $this->cm, $dossier, $this->director->id);
        $this->assertEquals(['deve@example.com'], array_column($sink->get_messages(), 'to'));
    }

    /**
     * Un courriel personnalisé par la DEVE remplace le texte par défaut, variables comprises.
     */
    public function test_custom_email_template(): void {
        $this->setup_activity();
        these_save_email_template($this->these->id, 'actionrequired', 'À traiter : {{student}}', 'Statut {{status}}');
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'directorid' => $this->director->id,
        ]);
        these_save_subject($this->these, $this->cm, $dossier, $dossier, true, $this->student->id);
        $message = $sink->get_messages()[0];
        $this->assertStringContainsString(fullname($this->student), $message->subject);
        $body = quoted_printable_decode($message->body);
        $this->assertStringContainsString(these_status_label(THESE_STATUS_SUBJECT_SUBMITTED), $body);
    }
}
