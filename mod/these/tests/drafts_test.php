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
require_once($CFG->dirroot . '/mod/these/tests/these_testcase.php');

/**
 * Fichiers déposés par les formulaires (manuscrit, rapport, PV scanné, corrections) : enregistrés dans
 * l'opération sur le dossier, sous son verrou. Un double envoi du formulaire n'échoue pas et laisse un
 * seul jeu de fichiers ; un envoi refusé n'enregistre aucun fichier.
 *
 * @package    mod_these
 * @category   test
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\files
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\files::class)]
final class drafts_test extends these_testcase {
    /**
     * Brouillon d'un gestionnaire de fichiers de l'utilisateur courant, contenant un fichier.
     *
     * @param string $filename
     * @param string $content
     * @return int draftitemid
     */
    private function draft(string $filename, string $content): int {
        global $USER;
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * Contenus des fichiers d'une zone du dossier.
     *
     * @param int $dossierid
     * @param string $filearea
     * @return string[] nom => contenu
     */
    private function contents(int $dossierid, string $filearea): array {
        $contents = [];
        foreach (these_get_files($this->context, $filearea, $dossierid) as $file) {
            $contents[$file->get_filename()] = $file->get_content();
        }
        return $contents;
    }

    /**
     * Rapport du directeur envoyé deux fois (même brouillon) : les deux envois aboutissent, un seul
     * fichier ; un envoi sur un dossier qui a changé de statut est refusé sans toucher au rapport.
     */
    public function test_report_sent_twice(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $this->setUser($this->director);
        $draftitemid = $this->draft('rapport.pdf', '%PDF-rapport');

        these_save_report($dossier, $this->director->id, true, ['directorreport' => $draftitemid]);
        these_save_report($dossier, $this->director->id, true, ['directorreport' => $draftitemid]);
        $this->assertSame(['rapport.pdf' => '%PDF-rapport'], $this->contents($dossier->id, 'directorreport'));
        $this->assertEquals(1, $this->reload($dossier->id)->reportfavorable);

        $stale = clone $dossier;
        $stale->status = THESE_STATUS_ACTIVE;
        try {
            these_save_report($stale, $this->director->id, false, ['directorreport' => $this->draft('autre.pdf', '%PDF-autre')]);
            $this->fail('Rapport enregistré sur un dossier changé');
        } catch (\moodle_exception $e) {
            $this->assertEquals('dossierchanged', $e->errorcode);
        }
        $this->assertSame(['rapport.pdf' => '%PDF-rapport'], $this->contents($dossier->id, 'directorreport'));
    }

    /**
     * Dépôt de la soutenance : brouillon enregistré deux fois, puis soumission envoyée deux fois. Le
     * second envoi de la soumission est refusé proprement (dossierchanged) ; un seul manuscrit.
     */
    public function test_defense_submitted_twice(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id,
        ]);
        $this->setUser($this->student);
        $draftitemid = $this->draft('manuscrit.pdf', '%PDF-manuscrit');
        $drafts = ['manuscript' => $draftitemid];

        foreach ([false, false, true] as $submit) {
            $data = $this->defense_data();
            these_save_defense($this->these, $this->cm, $dossier, $data, $submit, $this->student->id, null, $drafts);
        }
        try {
            these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), true, $this->student->id, null, $drafts);
            $this->fail('Soumission enregistrée deux fois');
        } catch (\moodle_exception $e) {
            $this->assertEquals('dossierchanged', $e->errorcode);
        }
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $this->reload($dossier->id)->status);
        $this->assertSame(['manuscrit.pdf' => '%PDF-manuscrit'], $this->contents($dossier->id, 'manuscript'));
    }

    /**
     * Procès-verbal et corrections : le second envoi est refusé, un seul jeu de fichiers.
     */
    public function test_minutes_and_corrections_sent_twice(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id, 'defensedate' => time() - DAYSECS,
        ]);
        $this->setUser($this->deve);
        $drafts = ['pv' => $this->draft('pv.pdf', '%PDF-pv')];
        $decision = (object) ['result' => THESE_RESULT_ADMITTED, 'mention' => 'honorable', 'prizeproposal' => false,
            'correctionsrequested' => true, 'correctionsnote' => 'Reprendre la conclusion.'];
        $dossier = these_record_minutes($this->these, $this->cm, $dossier, $this->deve->id, $decision, '', $drafts);
        $this->assertEquals(THESE_STATUS_CORRECTIONS, $dossier->status);
        $seen = clone $dossier;
        $seen->status = THESE_STATUS_SCHEDULED;
        try {
            these_record_minutes($this->these, $this->cm, $seen, $this->deve->id, $decision, '', $drafts);
            $this->fail('Procès-verbal enregistré deux fois');
        } catch (\moodle_exception $e) {
            $this->assertEquals('dossierchanged', $e->errorcode);
        }
        $this->assertSame(['pv.pdf' => '%PDF-pv'], $this->contents($dossier->id, 'pv'));

        $this->setUser($this->student);
        $drafts = ['corrections' => $this->draft('corrige.pdf', '%PDF-corrige')];
        // Chaque envoi part du dossier tel que la page l'a lu.
        $seen = $this->reload($dossier->id);
        these_submit_corrections($this->these, $this->cm, clone $seen, $this->student->id, 'Fait.', $drafts);
        try {
            these_submit_corrections($this->these, $this->cm, clone $seen, $this->student->id, 'Fait.', $drafts);
            $this->fail('Corrections déposées deux fois');
        } catch (\moodle_exception $e) {
            $this->assertEquals('dossierchanged', $e->errorcode);
        }
        $this->assertSame(['corrige.pdf' => '%PDF-corrige'], $this->contents($dossier->id, 'corrections'));
    }
}
