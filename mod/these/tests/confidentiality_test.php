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
 * Thèse confidentielle (sujet connu de l'étudiant, de la DEVE, de la bibliothèque, du directeur et
 * du jury seulement) et demande d'embargo de diffusion.
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
     * Qui connaît le sujet : l'étudiant, la DEVE, la bibliothèque, le directeur et le jury ; ni un
     * autre enseignant ni un détenteur de mod/these:viewall sans rôle de service.
     */
    public function test_who_knows_the_subject(): void {
        $this->setup_activity();
        [$dossier, $juror] = $this->confidential_dossier(THESE_STATUS_DEFENSE_VALIDATED);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $viewall = $this->viewall_user();

        foreach ([$this->student, $this->deve, $this->librarian, $this->director, $juror] as $user) {
            $this->assertTrue(these_user_knows_subject($dossier, $this->context, $user->id), $user->email);
            $this->assertSame(self::TITLE, these_subject_field($dossier, 'title', $this->context, $user->id));
        }
        foreach ([$other, $viewall] as $user) {
            $this->assertFalse(these_user_knows_subject($dossier, $this->context, $user->id));
            $this->assertSame(
                get_string('confidentialtitle', 'mod_these'),
                these_subject_field($dossier, 'title', $this->context, $user->id)
            );
            foreach (['titleen', 'summary', 'keywords'] as $field) {
                $this->assertSame(
                    get_string('confidentialtitle', 'mod_these'),
                    these_subject_field($dossier, $field, $this->context, $user->id)
                );
            }
        }

        // Une thèse ordinaire est connue de tous ceux qui voient le dossier.
        $dossier->confidential = 0;
        $this->assertTrue(these_user_knows_subject($dossier, $this->context, $viewall->id));
    }

    /**
     * La bibliothèque voit le dossier d'une thèse confidentielle en entier ; un détenteur de
     * mod/these:viewall le voit sans son sujet : manuscrit seulement, titre masqué dans le dossier, la
     * liste et le planning.
     */
    public function test_library_sees_viewall_does_not(): void {
        global $DB, $PAGE;
        $this->setup_activity();
        [$dossier] = $this->confidential_dossier(THESE_STATUS_DEFENSE_VALIDATED);
        $DB->set_field('these_dossier', 'defensedate', time() + DAYSECS, ['id' => $dossier->id]);
        $dossier = $this->reload($dossier->id);
        $viewall = $this->viewall_user();
        $PAGE->set_url(new \moodle_url('/mod/these/dossier.php'));

        // Bibliothèque : tout.
        $library = these_get_rights($this->context, $dossier, $this->librarian->id);
        $this->assertTrue($library->knowssubject);
        $this->assertTrue(these_can_view_filearea('subjectdocs', $library));
        $this->assertTrue(these_can_view_document('subject', $library));
        $this->assertTrue(these_can_view_document('conformity', $library));
        $this->setUser($this->librarian);
        $html = these_render_dossier($this->these, $this->cm, $this->context, $dossier, $library);
        $this->assertStringContainsString(self::TITLE, $html);

        // Détenteur de viewall : manuscrit seulement, sujet masqué.
        $rights = these_get_rights($this->context, $dossier, $viewall->id);
        $this->assertFalse($rights->knowssubject);
        $this->assertTrue(these_can_view_filearea('manuscript', $rights));
        $this->assertTrue(these_can_view_filearea('corrections', $rights));
        $this->assertFalse(these_can_view_filearea('subjectdocs', $rights));
        $this->assertFalse(these_can_view_filearea('generated', $rights));
        $this->assertFalse(these_can_view_document('conformity', $rights));
        $this->assertFalse(these_can_view_document('subject', $rights));
        $this->setUser($viewall);
        $html = these_render_dossier($this->these, $this->cm, $this->context, $dossier, $rights);
        $this->assertStringNotContainsString(self::TITLE, $html);
        $this->assertStringNotContainsString('Experimental distemper vaccine', $html);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $html);
        $this->assertStringNotContainsString(self::TITLE, these_render_dossier_table([$dossier], $this->cm));

        // Recherche du tableau de bord : le titre n'est cherché que pour qui connaît tous les sujets.
        $this->assertCount(0, these_get_dossiers($this->these->id, ['search' => 'Carré']));
        $this->assertCount(1, these_get_dossiers($this->these->id, ['search' => 'Carré', 'confidentialsubjects' => true]));

        // Planning : titre masqué pour viewall, connu de la bibliothèque.
        $sources = [these_planning_source($this->these, $this->cm, $this->context, $viewall->id)];
        $defenses = these_get_defenses($sources, time(), time() + WEEKSECS, $viewall->id);
        $this->assertSame(get_string('confidentialtitle', 'mod_these'), these_defense_row($defenses[0])['title']);
        $this->setUser($this->librarian);
        $this->assertSame(self::TITLE, these_defense_row($defenses[0])['title']);
    }

    /**
     * Les courriels ne citent le titre qu'aux destinataires qui connaissent le sujet.
     */
    public function test_emails_mask_the_title(): void {
        $this->setup_activity();
        [$dossier] = $this->confidential_dossier(THESE_STATUS_SUBJECT_APPROVED);
        $viewall = $this->viewall_user();
        $sink = $this->redirectEmails();
        these_send_email(
            $this->these,
            'actionrequired',
            [$viewall, $this->librarian, $this->director],
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
        $this->assertStringNotContainsString('maladie de Carr', $bodies[$viewall->email]);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $bodies[$viewall->email]);
        $this->assertStringContainsString('maladie de Carr', $bodies[$this->librarian->email]);
        $this->assertStringContainsString('maladie de Carr', $bodies[$this->director->email]);
    }

    /**
     * Destinataires sans compte : l'université, les membres du jury et les signataires invités du
     * dossier connaissent le sujet ; toute autre adresse reçoit « Thèse confidentielle ». Les envois
     * de convocation, de désignation du jury et de demande de signature passent par ce masquage.
     */
    public function test_external_recipients_mask_the_title(): void {
        set_config('dgemail', 'dg@example.org', 'mod_these');
        $this->setup_activity(['universityemail' => 'scolarite@univ.example.org']);
        [$dossier] = $this->confidential_dossier(THESE_STATUS_CONFORM);
        $sink = $this->redirectEmails();
        $recipients = [
            these_make_external_user('scolarite@univ.example.org'),
            these_make_external_user('president@example.com', 'Paul Président'),
            these_make_external_user('dg@example.org'),
            these_make_external_user('inconnu@example.org'),
        ];
        these_send_email(
            $this->these,
            'actionrequired',
            $recipients,
            these_email_base_vars($this->these, $this->cm, $dossier) + ['status' => 'x'],
            '',
            '',
            $dossier
        );
        $bodies = [];
        foreach ($sink->get_messages() as $message) {
            $bodies[$message->to] = quoted_printable_decode($message->body) . ' ' . $message->subject;
        }
        foreach (['scolarite@univ.example.org', 'president@example.com', 'dg@example.org'] as $email) {
            $this->assertStringContainsString('maladie de Carr', $bodies[$email], $email);
        }
        $this->assertStringNotContainsString('maladie de Carr', $bodies['inconnu@example.org']);
        $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $bodies['inconnu@example.org']);
        $sink->clear();

        // Désignation du jury : le titre est cité au membre (qui connaît le sujet).
        these_notify_jury($this->these, $this->cm, $dossier);
        $messages = $sink->get_messages();
        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertStringContainsString('maladie de Carr', quoted_printable_decode($message->body), $message->to);
        }
        $sink->close();
    }

    /**
     * Les jetons d'un courriel personnalisé sont remplacés en une seule passe : une valeur contenant
     * « {{title}} » (commentaire d'un étudiant) ne fait pas apparaître le titre, et un jeton inconnu
     * est laissé tel quel.
     */
    public function test_placeholders_single_pass(): void {
        $this->resetAfterTest();
        $vars = ['comment' => 'Voir {{title}} et {{url}}', 'title' => 'Secret', 'url' => 'https://x.example'];
        $this->assertSame(
            'Voir {{title}} et {{url}} / Secret / {{inconnu}}',
            these_render_email_placeholders('{{comment}} / {{title}} / {{inconnu}}', $vars)
        );
        $this->assertSame('Secret', these_render_email_placeholders('{{title}}', ['title' => 'Secret', 'comment' => '{{title}}']));
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
