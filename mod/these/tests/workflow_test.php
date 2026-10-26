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
 * @covers     ::these_record_minutes
 * @covers     ::these_submit_corrections
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

        // Étape 3 : enregistrement DEVE, sans numéro (attribué après le dépôt de la thèse).
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_REGISTERED, $dossier->status);
        $this->assertEmpty($dossier->devenumber);
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
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id,
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
        $number = these_suggest_devenumber($this->these->id);
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, ['defensedate' => $date,
            'devenumber' => $number]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_VALIDATED, $dossier->status);
        $this->assertEquals($number, $dossier->devenumber);
        $this->assertEquals($date, $dossier->defensedate);
        these_validate_step($this->these, $this->cm, $dossier, $this->librarian->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CONFORM, $dossier->status);
        $sink->clear();

        // Étape 5 : convocation étudiant, une par membre du jury, feuille de jury, PV, et les
        // documents de l'établissement disponibles (ici le dépôt de sujet et l'attestation de
        // conformité, les autres n'ayant pas été renseignés par ce raccourci de test).
        $files = these_generate_documents($this->these, $this->context, $dossier, $this->deve->id);
        $this->assertCount(9, $files);
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

        // Étape 6 : procès-verbal sans corrections, dossier clôturé.
        these_record_minutes($this->these, $this->cm, $dossier, $this->deve->id, (object) [
            'result' => THESE_RESULT_ADMITTED, 'mention' => 'felicitations', 'prizeproposal' => true,
            'correctionsrequested' => false, 'correctionsnote' => 'ignoré',
        ]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
        $this->assertEquals(THESE_RESULT_ADMITTED, $dossier->defenseresult);
        $this->assertSame('felicitations', $dossier->mention);
        $this->assertEquals(1, $dossier->prizeproposal);
        $this->assertSame('', (string) $dossier->correctionsnote);
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $body = preg_replace('/\s+/', ' ', quoted_printable_decode($messages[0]->body));
        $this->assertStringContainsString(\core_text::strtolower(get_string('mentionfelicitations', 'mod_these')), $body);
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

    /**
     * Procès-verbal avec corrections : dépôt du manuscrit corrigé, renvoi par la bibliothèque,
     * nouveau dépôt revenant à la bibliothèque, puis clôture par la DEVE.
     */
    public function test_corrections_circuit(): void {
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id,
        ]);

        $dossier = these_record_minutes($this->these, $this->cm, $dossier, $this->deve->id, (object) [
            'result' => THESE_RESULT_ADMITTED, 'mention' => 'treshonorable', 'prizeproposal' => false,
            'correctionsrequested' => true, 'correctionsnote' => 'Reprendre la discussion.',
        ]);
        $this->assertEquals(THESE_STATUS_CORRECTIONS, $dossier->status);
        $this->assertEquals('student', these_step_actor($dossier->status));
        $this->assertEquals([$this->student->email], array_column($sink->get_messages(), 'to'));
        $sink->clear();

        // Dépôt des corrections : au directeur.
        $dossier = these_submit_corrections($this->these, $this->cm, $dossier, $this->student->id, 'Discussion reprise.');
        $this->assertEquals(THESE_STATUS_CORRECTIONS_SUBMITTED, $dossier->status);
        $this->assertEquals([$this->director->email], array_column($sink->get_messages(), 'to'));
        these_validate_step($this->these, $this->cm, $dossier, $this->director->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CORRECTIONS_APPROVED, $dossier->status);

        // Renvoi par la bibliothèque, puis nouveau dépôt qui revient directement à elle.
        these_return_dossier($this->these, $this->cm, $dossier, $this->librarian->id, 'student', 'Pagination.');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CORRECTIONS, $dossier->status);
        $dossier = these_submit_corrections($this->these, $this->cm, $dossier, $this->student->id, 'Pagination reprise.');
        $this->assertEquals(THESE_STATUS_CORRECTIONS_APPROVED, $dossier->status);
        these_validate_step($this->these, $this->cm, $dossier, $this->librarian->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CORRECTIONS_CONFORM, $dossier->status);
        $sink->clear();

        // Validation par la DEVE : dossier clos, étudiant et directeur prévenus.
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CLOSED, $dossier->status);
        $this->assertSame('Pagination reprise.', $dossier->correctionsdone);
        $this->assertCount(2, $sink->get_messages());
        $this->assertStringContainsString(get_string('correctionsrequested', 'mod_these'), these_decision_label($dossier));
    }

    /**
     * Rapport du directeur : enregistré sans changer le statut, limité à une page.
     */
    public function test_director_report(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $this->assertTrue(these_report_editable($dossier));
        $dossier = these_save_report($dossier, $this->director->id, "  Travail rigoureux.  ", true);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $dossier->status);
        $this->assertSame('Travail rigoureux.', $dossier->reporttext);
        $this->assertEquals(1, $dossier->reportfavorable);

        $this->assertNull(these_report_error($this->these, str_repeat('Une phrase du rapport. ', 40)));
        $this->assertNotNull(these_report_error($this->these, str_repeat('Une phrase du rapport. ', 150)));
        $this->assertNotNull(these_report_error($this->these, str_repeat("Paragraphe.\n\n", 60)));
        $this->assertFalse(these_report_editable((object) ['status' => THESE_STATUS_DEFENSE_APPROVED]));
    }

    /**
     * Ajournement : retour en phase de soutenance, PV archivé, documents et signatures de la
     * soutenance supprimés (pas ceux du sujet), accès du jury révoqués ; nouveau dépôt possible.
     */
    public function test_adjournment(): void {
        global $DB;
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id, 'devenumber' => '2026-004',
            'reporttext' => 'Rapport.', 'reportfavorable' => 1, 'documentstime' => time(), 'senttime' => time(),
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        $DB->set_field('these_jury', 'notifiedtime', time(), ['dossierid' => $dossier->id]);
        $DB->set_field('these_jury', 'accesstoken', 'jeton', ['dossierid' => $dossier->id]);
        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'subject', 'student', $this->student->id, 'Étudiant', $png);
        these_sign_document($this->context, $dossier, 'report', 'director', $this->director->id, 'Directeur', $png);
        $fs = get_file_storage();
        $file = ['contextid' => $this->context->id, 'component' => 'mod_these', 'itemid' => $dossier->id, 'filepath' => '/'];
        $fs->create_file_from_string($file + ['filearea' => 'pv', 'filename' => 'pv.pdf'], '%PDF-1.4');
        $fs->create_file_from_string($file + ['filearea' => 'generated', 'filename' => 'convocation.pdf'], '%PDF-1.4');

        $dossier = these_record_minutes($this->these, $this->cm, $dossier, $this->deve->id, (object) [
            'result' => THESE_RESULT_ADJOURNED, 'mention' => 'honorable', 'prizeproposal' => true,
            'correctionsrequested' => true, 'correctionsnote' => 'x',
        ]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_ACTIVE, $dossier->status);
        $this->assertTrue(these_is_adjourned($dossier));
        $this->assertTrue(these_defense_editable($dossier));
        $this->assertSame('', (string) $dossier->mention);
        $this->assertEquals(0, $dossier->prizeproposal);
        $this->assertEquals(0, $dossier->correctionsrequested);
        $this->assertEquals(0, $dossier->reportfavorable);
        $this->assertEquals(0, $dossier->documentstime);
        $this->assertSame('2026-004', $dossier->devenumber);
        $this->assertSame('Rapport.', $dossier->reporttext);

        // PV archivé, documents générés supprimés.
        $this->assertCount(0, these_get_files($this->context, 'pv', $dossier->id));
        $this->assertCount(0, these_get_files($this->context, 'generated', $dossier->id));
        $archived = these_get_files($this->context, 'pvadjourned', $dossier->id);
        $this->assertCount(1, $archived);
        $this->assertStringStartsWith('ajournement-', $archived[0]->get_filename());
        // Signature du sujet conservée, celle du rapport supprimée ; jury à prévenir de nouveau.
        $this->assertSame(['subject:student'], array_keys(these_get_signatures($dossier->id)));
        foreach (these_get_jury($dossier->id) as $member) {
            $this->assertEquals(0, $member->notifiedtime);
            $this->assertEmpty($member->accesstoken);
        }
        $recipients = array_column($sink->get_messages(), 'to');
        sort($recipients);
        $expected = [$this->student->email, $this->director->email];
        sort($expected);
        $this->assertEquals($expected, $recipients);
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'adjourned']));

        // Nouveau dépôt de soutenance : de nouveau chez le directeur.
        these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), true, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $dossier->status);
    }

    /**
     * Grille de conformité : remplie et attestée par l'étudiant au dépôt de la soutenance, elle est
     * enregistrée au dossier et préremplit ensuite la validation du directeur.
     */
    public function test_student_fills_conformity_grid(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id,
        ]);
        $data = $this->defense_data();
        $data->ethicsnumber = '2526';
        $keys = [];
        foreach (these_get_grid_items(these_settings_scope($this->these)) as $items) {
            foreach (array_keys($items) as $key) {
                $keys[] = $key;
                $data->{'grid_' . $key} = 1;
            }
        }
        $data->{'grid_' . $keys[0]} = 0;
        these_save_defense($this->these, $this->cm, $dossier, $data, false, $this->student->id);

        $dossier = $this->reload($dossier->id);
        $this->assertSame('2526', $dossier->ethicsnumber);
        $grid = these_get_grid($dossier);
        $this->assertSame(count($keys), count($grid));
        $this->assertSame(0, $grid[$keys[0]]);
        $this->assertSame(1, $grid[$keys[1]]);

        // Un enregistrement sans grille (formulaire d'une autre étape) ne l'efface pas.
        these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), false, $this->student->id);
        $this->assertSame($grid, these_get_grid($this->reload($dossier->id)));
    }

    /**
     * Avancement du dépôt de la soutenance : rubriques à compléter selon les contrôles de la
     * soumission, signature et attestation de la grille exclues.
     */
    public function test_defense_progress(): void {
        global $PAGE;
        $this->setup_activity();
        $this->setUser($this->student);
        $PAGE->set_url('/mod/these/defense.php', ['id' => $this->cm->id]);
        $form = new \mod_these\form\defense_form(null, [
            'minjury' => $this->these->minjury,
            'these' => $this->these,
            'context' => $this->context,
            'submitlabel' => 'submitdefense',
            'signername' => fullname($this->student),
            'rooms' => ['Salle du conseil'],
        ]);
        $missing = function (array $data) use ($form) {
            return array_column($form->section_progress($data), 'missing', 'name');
        };
        $empty = $missing(['proposeddate' => 0, 'defenselocation' => '', 'manuscript' => 0]);
        $this->assertArrayNotHasKey('signatureheader', $empty);
        $this->assertSame(2, $empty['dateheader']);
        $this->assertGreaterThan(0, $empty['gridheader']);

        $data = ['proposeddate' => time() + WEEKSECS, 'defenselocation' => 'Salle du conseil', 'manuscript' => 0,
            'conclusions' => 'Conclusions.'];
        foreach (these_get_grid_items(these_settings_scope($this->these)) as $items) {
            foreach (array_keys($items) as $key) {
                $data['grid_' . $key] = 1;
            }
        }
        $progress = $missing($data);
        $this->assertSame(0, $progress['dateheader']);
        $this->assertSame(0, $progress['gridheader']);
        $this->assertSame(0, $progress['conclusionsheader']);
        $this->assertSame(1, $progress['documentsheader']);
    }
}
