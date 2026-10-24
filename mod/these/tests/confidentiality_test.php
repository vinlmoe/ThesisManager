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
 * Thèse confidentielle (sujet connu de l'étudiant, de la DEVE, du directeur et du jury seulement) et
 * demande d'embargo de diffusion.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_user_knows_subject
 * @covers     ::these_subject_field
 * @covers     ::these_can_view_filearea
 * @covers     ::these_can_view_document
 * @covers     ::these_send_email
 * @covers     ::these_embargo_until
 * @covers     ::these_extract_bordereau
 */
final class confidentiality_test extends these_testcase {
    /** @var string Titre de la thèse confidentielle des tests. */
    const TITLE = 'Vaccin expérimental contre la maladie de Carré';

    /**
     * Dossier confidentiel au statut voulu, dirigé par le directeur des tests, avec un jury dont un
     * membre a un compte.
     *
     * @param int $status
     * @return array [dossier, membre du jury ayant un compte]
     */
    private function confidential_dossier(int $status): array {
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => $status, 'directorid' => $this->director->id, 'confidential' => 1, 'title' => self::TITLE,
            'titleen' => 'Experimental distemper vaccine', 'keywords' => 'vaccin ; Carré',
        ]);
        $juror = $this->getDataGenerator()->create_user(['email' => 'President@Example.com']);
        these_save_jury($dossier->id, $this->generator->default_jury());
        return [$dossier, $juror];
    }

    /**
     * Qui connaît le sujet : l'étudiant, la DEVE, le directeur et le jury ; ni la bibliothèque ni un
     * autre enseignant.
     */
    public function test_who_knows_the_subject(): void {
        $this->setup_activity();
        [$dossier, $juror] = $this->confidential_dossier(THESE_STATUS_DEFENSE_VALIDATED);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');

        foreach ([$this->student, $this->deve, $this->director, $juror] as $user) {
            $this->assertTrue(these_user_knows_subject($dossier, $this->context, $user->id), $user->email);
            $this->assertSame(self::TITLE, these_subject_field($dossier, 'title', $this->context, $user->id));
        }
        foreach ([$this->librarian, $other] as $user) {
            $this->assertFalse(these_user_knows_subject($dossier, $this->context, $user->id));
            $this->assertSame(
                get_string('confidentialtitle', 'mod_these'),
                these_subject_field($dossier, 'title', $this->context, $user->id)
            );
            $this->assertSame('', these_subject_field($dossier, 'keywords', $this->context, $user->id));
        }

        // Une thèse ordinaire est connue de tous ceux qui voient le dossier.
        $dossier->confidential = 0;
        $this->assertTrue(these_user_knows_subject($dossier, $this->context, $this->librarian->id));
    }

    /**
     * La bibliothèque voit le dossier d'une thèse confidentielle sans son sujet : manuscrit et
     * attestation de conformité seulement, titre masqué dans le dossier, la liste et le planning.
     */
    public function test_library_view_is_masked(): void {
        global $DB, $PAGE;
        $this->setup_activity();
        [$dossier] = $this->confidential_dossier(THESE_STATUS_DEFENSE_VALIDATED);
        $DB->set_field('these_dossier', 'defensedate', time() + DAYSECS, ['id' => $dossier->id]);
        $dossier = $this->reload($dossier->id);

        $library = these_get_rights($this->context, $dossier, $this->librarian->id);
        $this->assertFalse($library->knowssubject);
        $this->assertTrue(these_can_view_filearea('manuscript', $library));
        $this->assertTrue(these_can_view_filearea('corrections', $library));
        $this->assertFalse(these_can_view_filearea('subjectdocs', $library));
        $this->assertFalse(these_can_view_filearea('generated', $library));
        $this->assertTrue(these_can_view_document('conformity', $library));
        $this->assertFalse(these_can_view_document('subject', $library));
        $deve = these_get_rights($this->context, $dossier, $this->deve->id);
        $this->assertTrue($deve->knowssubject);
        $this->assertTrue(these_can_view_document('subject', $deve));

        $PAGE->set_url(new \moodle_url('/mod/these/dossier.php'));
        $this->setUser($this->librarian);
        $html = these_render_dossier($this->these, $this->cm, $this->context, $dossier, $library);
        $this->assertStringNotContainsString(self::TITLE, $html);
        $this->assertStringNotContainsString('Experimental distemper vaccine', $html);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $html);
        $table = these_render_dossier_table([$dossier], $this->cm);
        $this->assertStringNotContainsString(self::TITLE, $table);

        // Recherche du tableau de bord : le titre n'est cherché que pour qui connaît tous les sujets.
        $this->assertCount(0, these_get_dossiers($this->these->id, ['search' => 'Carré']));
        $this->assertCount(1, these_get_dossiers($this->these->id, ['search' => 'Carré', 'confidentialsubjects' => true]));

        // Planning : titre masqué pour la bibliothèque, connu de la DEVE.
        $sources = [these_planning_source($this->these, $this->cm, $this->context, $this->librarian->id)];
        $defenses = these_get_defenses($sources, time(), time() + WEEKSECS, $this->librarian->id);
        $this->assertSame(get_string('confidentialtitle', 'mod_these'), these_defense_row($defenses[0])['title']);
        $this->setUser($this->deve);
        $this->assertSame(self::TITLE, these_defense_row($defenses[0])['title']);
    }

    /**
     * Les courriels ne citent le titre qu'aux destinataires qui connaissent le sujet.
     */
    public function test_emails_mask_the_title(): void {
        $this->setup_activity();
        [$dossier] = $this->confidential_dossier(THESE_STATUS_SUBJECT_APPROVED);
        $sink = $this->redirectEmails();
        these_send_email(
            $this->these,
            'actionrequired',
            [$this->librarian, $this->director],
            these_email_base_vars($this->these, $this->cm, $dossier) + ['status' => 'x'],
            '',
            '',
            $dossier
        );
        $bodies = [];
        foreach ($sink->get_messages() as $message) {
            $bodies[$message->to] = quoted_printable_decode($message->body) . ' ' . $message->subject;
        }
        $sink->close();
        $this->assertStringNotContainsString('maladie de Carr', $bodies[$this->librarian->email]);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $bodies[$this->librarian->email]);
        $this->assertStringContainsString('maladie de Carr', $bodies[$this->director->email]);
    }

    /**
     * Demande d'embargo : 5 ans après la soutenance, motif obligatoire ; une date d'embargo saisie
     * avec l'ancien formulaire n'est reprise que tant que l'étudiant ne réenregistre pas.
     */
    public function test_embargo_request(): void {
        $this->setTimezone('Europe/Paris');
        $this->setup_activity();
        $defense = make_timestamp(2026, 11, 12, 14);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'defensedate' => $defense, 'proposeddate' => $defense,
        ]);
        $this->assertSame(0, these_embargo_until($dossier));

        // Ancienne saisie : embargo avec une date choisie.
        $dossier->bordereau = json_encode(['embargo' => 1, 'embargountil' => make_timestamp(2028, 1, 1)]);
        $this->assertSame(make_timestamp(2028, 1, 1), these_embargo_until($dossier));

        // Nouveau formulaire : demande de 5 ans, motivée.
        $bordereau = these_extract_bordereau(
            (object) ['bembargo' => 1, 'bembargoreason' => ' Brevet en cours ', 'bdiffusion' => 'internet'],
            these_get_bordereau($dossier)
        );
        $this->assertSame(0, $bordereau['embargountil']);
        $this->assertSame('Brevet en cours', $bordereau['embargoreason']);
        $dossier->bordereau = json_encode($bordereau);
        $this->assertSame(make_timestamp(2031, 11, 12), these_embargo_until($dossier));

        // La date de fin suit la date de soutenance arrêtée par la DEVE.
        $dossier->defensedate = make_timestamp(2026, 12, 3, 10);
        $this->assertSame(make_timestamp(2031, 12, 3), these_embargo_until($dossier));
        $data = these_document_context($this->these, $this->context, $dossier, 'contract');
        $this->assertTrue($data['contract']['embargo']);
        $this->assertSame(userdate(make_timestamp(2031, 12, 3), '%d/%m/%Y'), $data['contract']['embargountil']);
        $this->assertSame('Brevet en cours', $data['contract']['embargoreason']);

        // Affichage dans le dossier.
        $rights = these_get_rights($this->context, $dossier, $this->librarian->id);
        $html = these_render_embargo($dossier, $rights);
        $this->assertStringContainsString(userdate(make_timestamp(2031, 12, 3), get_string('strftimedate', 'langconfig')), $html);
        $this->assertStringContainsString('Brevet en cours', $html);

        // Embargo retiré : plus de date, motif effacé.
        $bordereau = these_extract_bordereau((object) ['bembargo' => 0, 'bembargoreason' => 'x'], $bordereau);
        $this->assertArrayNotHasKey('embargoreason', $bordereau);
        $dossier->bordereau = json_encode($bordereau);
        $this->assertSame(0, these_embargo_until($dossier));
    }
}
