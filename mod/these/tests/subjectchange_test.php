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
 * Changement de sujet : trace de l'ancien sujet, avertissement de l'ancien directeur, de la DEVE et
 * de la bibliothèque, nouvelle phase 1 avec le nouveau directeur.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_change_subject
 * @covers     \mod_these\local\rights
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('these_change_subject')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\rights::class)]
final class subjectchange_test extends these_testcase {
    /**
     * Changement de sujet d'un dossier actif, avec un nouveau directeur.
     */
    public function test_change_subject(): void {
        global $DB;
        $this->setup_activity(['deveemail' => 'deve@example.com', 'libraryemail' => 'biblio@example.com']);
        $newdirector = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id, 'title' => 'Ancien sujet',
            'keywords' => 'a ; b ; c', 'libraryref' => 'NOT-1', 'studyyear' => 'A4',
        ]);
        $png = these_sample_signature_image();
        foreach (['student' => $this->student, 'director' => $this->director, 'deve' => $this->deve] as $slot => $user) {
            these_sign_document($this->context, $dossier, 'subject', $slot, $user->id, fullname($user), $png);
        }
        $this->assertTrue(these_subject_changeable($dossier));
        $sink = $this->redirectEmails();

        $data = (object) ['title' => 'Nouveau sujet', 'titleen' => 'New subject', 'summary' => 'Résumé',
            'keywords' => 'x ; y ; z', 'studyyear' => 'A5', 'directorid' => $newdirector->id, 'confidential' => 0,
            'externaldirector' => 0, 'commitguide' => 1, 'commitduplicate' => 1, 'commitethics' => 0];
        $dossier = these_change_subject(
            $this->these,
            $this->cm,
            $dossier,
            $data,
            $this->student->id,
            'Changement de terrain.',
            (object) ['name' => fullname($this->student), 'png' => $png]
        );
        $dossier = $this->reload($dossier->id);

        // Nouvelle phase 1 avec le nouveau directeur.
        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $this->assertSame('Nouveau sujet', $dossier->title);
        $this->assertEquals($newdirector->id, $dossier->directorid);
        $this->assertSame(['subject:student'], array_keys(these_get_signatures($dossier->id)));

        // Trace : historique, PDF archivé, journal.
        $history = array_values(these_get_subject_history($dossier->id));
        $this->assertCount(1, $history);
        $this->assertSame('Ancien sujet', $history[0]->title);
        $this->assertEquals($this->director->id, $history[0]->directorid);
        $this->assertSame('NOT-1', $history[0]->libraryref);
        $this->assertSame('Changement de terrain.', $history[0]->reason);
        $archived = these_get_files($this->context, 'subjectarchive', $dossier->id);
        $this->assertCount(1, $archived);
        $this->assertStringStartsWith('%PDF', $archived[0]->get_content());
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'subjectchanged']));
        $this->assertStringContainsString('Ancien sujet', these_render_subject_history($this->context, $dossier));

        // Avertissements : ancien directeur, DEVE, bibliothèque ; action attendue du nouveau directeur.
        $recipients = array_column($sink->get_messages(), 'to');
        foreach ([$this->director->email, 'deve@example.com', 'biblio@example.com', $newdirector->email] as $email) {
            $this->assertContains($email, $recipients);
        }
        $this->assertNotContains($this->student->email, $recipients);

        // Plus de changement possible tant que le nouveau sujet n'est pas enregistré.
        $this->assertFalse(these_subject_changeable($dossier));
        $this->expectException(\coding_exception::class);
        these_change_subject($this->these, $this->cm, $dossier, $data, $this->student->id, 'Encore.');
    }

    /**
     * Un dossier abandonné peut changer de sujet : le nouveau sujet le relance en phase 1 et le statut
     * atteint lors de l'abandon ne vaut plus ; tous ses documents signés sont révoqués.
     */
    public function test_change_subject_of_cancelled_dossier(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id, 'title' => 'Sujet abandonné',
        ]);
        $png = these_sample_signature_image();
        these_sign_document($this->context, $dossier, 'subject', 'director', $this->director->id, 'Directeur', $png);
        these_sign_document($this->context, $dossier, 'bordereau', 'student', $this->student->id, 'Étudiant', $png);
        $dossier = these_cancel_dossier($dossier, $this->deve->id, 'Abandon.');
        $this->assertTrue(these_subject_changeable($dossier));
        $student = these_get_rights($this->context, $dossier, $this->student->id);
        $this->assertArrayHasKey(
            get_string('subjectchange', 'mod_these'),
            these_get_dossier_actions($this->cm, $dossier, $student)
        );

        $data = (object) ['title' => 'Nouveau sujet', 'titleen' => 'New subject', 'summary' => 'Résumé',
            'keywords' => 'x ; y ; z', 'studyyear' => 'A5', 'directorid' => $this->director->id, 'confidential' => 0,
            'externaldirector' => 0];
        these_change_subject($this->these, $this->cm, $dossier, $data, $this->student->id, 'Nouveau départ.');
        $dossier = $this->reload($dossier->id);

        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $this->assertEquals(0, $dossier->cancelledfromstatus);
        // Tous les documents signés de l'ancien sujet sont révoqués, soutenance comprise.
        $this->assertSame([], these_get_signatures($dossier->id));
        $this->assertSame('Nouveau sujet', $dossier->title);
        $history = array_values(these_get_subject_history($dossier->id));
        $this->assertCount(1, $history);
        $this->assertSame('Sujet abandonné', $history[0]->title);
    }

    /**
     * Un ancien sujet confidentiel reste confidentiel quand le nouveau sujet ne l'est pas : la scolarité
     * (mod/these:viewall) ne voit ni son titre (sujets précédents, historique) ni son PDF archivé ; la DEVE
     * et le directeur, si. Scénario relevé par le test de charge (tools/stresstest).
     */
    public function test_former_confidential_subject_stays_hidden(): void {
        global $PAGE;
        $this->setup_activity();
        $staff = $this->viewall_user();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id, 'title' => 'Brevet secret XQZ',
            'confidential' => 1,
        ]);
        $data = (object) ['title' => 'Sujet public', 'titleen' => '', 'summary' => 'Résumé', 'keywords' => 'x ; y',
            'studyyear' => 'A5', 'directorid' => $this->director->id, 'confidential' => 0, 'externaldirector' => 0];
        these_change_subject($this->these, $this->cm, $dossier, $data, $this->student->id, 'Projet abandonné.');
        $dossier = $this->reload($dossier->id);
        $entry = current(these_get_subject_history($dossier->id));
        $this->assertCount(1, these_get_files($this->context, 'subjectarchive', $dossier->id));

        $PAGE->set_url(new \moodle_url('/mod/these/dossier.php'));
        $PAGE->set_context($this->context);
        $this->setUser($staff);
        $rights = these_get_rights($this->context, $dossier, $staff->id);
        $this->assertTrue($rights->knowssubject, 'Le sujet en cours est public.');
        $html = these_render_dossier($this->these, $this->cm, $this->context, $dossier, $rights);
        $this->assertStringContainsString('Sujet public', $html);
        $this->assertStringNotContainsString('XQZ', $html);
        $this->assertStringNotContainsString('Projet abandonné', $html);
        $this->assertFalse(\mod_these\local\rights::user_knows_archived_subject($entry, $dossier, $this->context, $staff->id));

        foreach ([$this->deve, $this->director, $this->student] as $user) {
            $this->setUser($user);
            $rights = these_get_rights($this->context, $dossier, $user->id);
            $html = these_render_dossier($this->these, $this->cm, $this->context, $dossier, $rights);
            $this->assertStringContainsString('XQZ', $html);
            $this->assertTrue(\mod_these\local\rights::user_knows_archived_subject($entry, $dossier, $this->context, $user->id));
        }
    }

    /**
     * Changement de sujet d'un dossier dont le directeur a vu son compte supprimé : le changement est
     * enregistré (l'ancien sujet est archivé sans nom de directeur) au lieu d'échouer sur une TypeError.
     * Scénario relevé par le test de charge (tools/stresstest).
     */
    public function test_change_subject_with_deleted_director(): void {
        $this->setup_activity();
        $newdirector = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id, 'title' => 'Ancien sujet',
        ]);
        delete_user($this->director);
        $sink = $this->redirectEmails();

        $data = (object) ['title' => 'Nouveau sujet', 'titleen' => '', 'summary' => 'Résumé', 'keywords' => 'x ; y',
            'studyyear' => 'A5', 'directorid' => $newdirector->id, 'confidential' => 0, 'externaldirector' => 0];
        these_change_subject($this->these, $this->cm, $dossier, $data, $this->student->id, 'Directeur parti.');
        $sink->close();

        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $this->assertEquals($newdirector->id, $dossier->directorid);
        $history = array_values(these_get_subject_history($dossier->id));
        $this->assertCount(1, $history);
        $this->assertSame('Ancien sujet', $history[0]->title);
        $this->assertSame('', $history[0]->directorname);
    }
}
