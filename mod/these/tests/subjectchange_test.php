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
 */
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
            'externaldirector' => 0, 'codirectorname' => '', 'commitguide' => 1, 'commitduplicate' => 1, 'commitethics' => 0];
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
     * atteint lors de l'abandon ne vaut plus.
     */
    public function test_change_subject_of_cancelled_dossier(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id, 'title' => 'Sujet abandonné',
        ]);
        $dossier = these_cancel_dossier($dossier, $this->deve->id, 'Abandon.');
        $this->assertTrue(these_subject_changeable($dossier));
        $student = these_get_rights($this->context, $dossier, $this->student->id);
        $this->assertArrayHasKey(
            get_string('subjectchange', 'mod_these'),
            these_get_dossier_actions($this->cm, $dossier, $student)
        );

        $data = (object) ['title' => 'Nouveau sujet', 'titleen' => 'New subject', 'summary' => 'Résumé',
            'keywords' => 'x ; y ; z', 'studyyear' => 'A5', 'directorid' => $this->director->id, 'confidential' => 0,
            'externaldirector' => 0, 'codirectorname' => ''];
        these_change_subject($this->these, $this->cm, $dossier, $data, $this->student->id, 'Nouveau départ.');
        $dossier = $this->reload($dossier->id);

        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $this->assertEquals(0, $dossier->cancelledfromstatus);
        $this->assertSame('Nouveau sujet', $dossier->title);
        $history = array_values(these_get_subject_history($dossier->id));
        $this->assertCount(1, $history);
        $this->assertSame('Sujet abandonné', $history[0]->title);
    }
}
