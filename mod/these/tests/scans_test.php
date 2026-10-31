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

use mod_these\local\scans;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Scans des documents signés hors module : période de dépôt, fichiers joints au dossier, empreinte
 * à l'historique, visibilité des autres pièces du dossier.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\scans
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\scans::class)]
final class scans_test extends these_testcase {
    /**
     * Brouillon de l'utilisateur contenant un fichier.
     *
     * @param int $userid
     * @param string $filename
     * @param string $content
     * @return int Identifiant du brouillon.
     */
    private function draft(int $userid, string $filename, string $content): int {
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
        return $draftitemid;
    }

    /**
     * Un scan se dépose une fois le dossier validé par la DEVE, tant qu'il n'est pas abandonné.
     */
    public function test_allowed(): void {
        $this->setup_activity();
        $expected = [
            THESE_STATUS_ACTIVE => false,
            THESE_STATUS_DEFENSE_APPROVED => false,
            THESE_STATUS_DEFENSE_VALIDATED => true,
            THESE_STATUS_SCHEDULED => true,
            THESE_STATUS_CORRECTIONS => true,
            THESE_STATUS_CLOSED => true,
            THESE_STATUS_CANCELLED => false,
        ];
        foreach ($expected as $status => $allowed) {
            $this->assertSame($allowed, scans::allowed((object) ['status' => $status]), 'Statut ' . $status);
        }
    }

    /**
     * Le scan est joint au dossier sous un nom lisible et son empreinte est inscrite à l'historique,
     * sans changer le statut ni les signatures du module.
     */
    public function test_add(): void {
        global $DB;
        $this->setup_activity();
        $this->setUser($this->deve);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_SCHEDULED]);
        $content = '%PDF-1.4 contrat signé';
        $draftitemid = $this->draft($this->deve->id, 'contrat.pdf', $content);

        $count = scans::add(
            $this->context,
            $dossier,
            $this->deve->id,
            'contract',
            make_timestamp(2026, 10, 22, 12),
            $draftitemid,
            'Reçu par courrier.'
        );

        $this->assertSame(1, $count);
        $files = these_get_files($this->context, 'signedscan', $dossier->id);
        $this->assertCount(1, $files);
        $this->assertSame(
            '2026-10-22-contract-contrat.pdf',
            $files[0]->get_filename()
        );
        $this->assertSame($content, $files[0]->get_content());

        $log = $DB->get_record('these_log', ['dossierid' => $dossier->id, 'logaction' => 'signedscan'], '*', MUST_EXIST);
        $this->assertStringContainsString(hash('sha256', $content), $log->remark);
        $this->assertStringContainsString(get_string('scandoctypecontract', 'mod_these'), $log->remark);
        $this->assertStringContainsString('Reçu par courrier.', $log->remark);
        $this->assertEquals(THESE_STATUS_SCHEDULED, $log->fromstatus);
        $this->assertEquals(THESE_STATUS_SCHEDULED, $log->tostatus);
        $this->assertEquals(THESE_STATUS_SCHEDULED, $this->reload($dossier->id)->status);
        $this->assertEmpty(these_get_signatures($dossier->id));
    }

    /**
     * Les signataires cochés au dépôt sont inscrits comme ayant signé à la main, à la date du
     * document, par délégation pour la direction générale si demandé, enregistrés par la DEVE : le
     * suivi et la mention imprimée sur le PDF en tiennent compte. Un emplacement déjà signé ou
     * étranger au document est ignoré.
     */
    public function test_add_with_signatories(): void {
        global $DB;
        $this->setup_activity();
        $this->setUser($this->deve);
        \mod_these\local\scopesettings::set(
            these_local_scope($this->these),
            ['dgname' => 'Pr Directrice', 'ucblname' => 'M. Président']
        );
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id,
            'bordereau' => json_encode(['birthname' => 'MARTIN', 'diffusion' => 'restricted']),
        ]);
        $pending = scans::pending_slots($dossier);
        $this->assertSame(['student', 'director', 'dg', 'ucbl'], array_keys($pending['contract']));
        $this->assertSame(['president', 'dg', 'ccem'], array_keys($pending['signaletique']));

        $date = make_timestamp(2026, 10, 22, 12);
        $draftitemid = $this->draft($this->deve->id, 'contrat.pdf', '%PDF-1.4 contrat');
        scans::add(
            $this->context,
            $dossier,
            $this->deve->id,
            'contract',
            $date,
            $draftitemid,
            '',
            ['dg' => true, 'ucbl' => false, 'ccem' => false]
        );

        $signatures = these_get_signatures($dossier->id);
        $dg = $signatures['contract:dg'];
        // Par délégation : la personne qui a signé n'est pas connue, la signature porte l'emplacement.
        $this->assertSame(get_string('slotdg', 'mod_these'), $dg->signername);
        $this->assertEquals($date, $dg->timesigned);
        $this->assertSame('scan', $dg->method);
        $this->assertEquals(1, $dg->delegated);
        $this->assertEquals($this->deve->id, $dg->verifiedby);
        $this->assertSame('M. Président', $signatures['contract:ucbl']->signername);
        $this->assertEquals(0, $signatures['contract:ucbl']->delegated);
        $this->assertArrayNotHasKey('contract:ccem', $signatures);
        $this->assertArrayNotHasKey('contract:student', $signatures);
        $this->assertSame(['student', 'director'], array_keys(scans::pending_slots($dossier)['contract']));

        // Mention du PDF : à la main, par délégation, scan enregistré par la DEVE.
        $data = these_document_context($this->these, $this->context, $dossier, 'contract');
        $this->assertStringContainsString(
            get_string('pdfdelegated', 'mod_these', get_string('slotdg', 'mod_these')),
            $data['sigcaption']['dg']
        );
        $files = these_get_files($this->context, 'signedscan', $dossier->id);
        $this->assertSame('2026-10-22-contract-dg-ucbl-contrat.pdf', $files[0]->get_filename());
        $this->assertStringContainsString(fullname($this->deve), $data['sigcaption']['dg']);
        $this->assertSame('', $data['sigsrc']['dg']);
        $this->assertTrue($data['signed']['dg']);
        $this->assertSame(1, $DB->count_records('these_log', ['dossierid' => $dossier->id, 'logaction' => 'signedscan']));
    }

    /**
     * Signature sur place enregistrée par la DEVE : manière de signer et compte qui a vérifié
     * l'identité conservés avec la signature et imprimés sur le document.
     */
    public function test_in_person_signature(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id,
            'bordereau' => json_encode(['birthname' => 'MARTIN', 'diffusion' => 'restricted']),
        ]);
        $signature = these_sign_document(
            $this->context,
            $dossier,
            'signaletique',
            'dg',
            0,
            'M. Adjoint',
            these_sample_signature_image(),
            '',
            ['method' => 'inperson', 'delegated' => true, 'verifiedby' => $this->deve->id]
        );
        $this->assertSame('inperson', $signature->method);
        $this->assertEquals($this->deve->id, $signature->verifiedby);
        $caption = these_document_context($this->these, $this->context, $dossier, 'signaletique')['sigcaption']['dg'];
        $this->assertStringContainsString(get_string('pdfdelegated', 'mod_these', 'M. Adjoint'), $caption);
        $this->assertStringContainsString(fullname($this->deve), $caption);
    }

    /**
     * Un second dépôt du même fichier le joint de nouveau, sous un autre nom ; un brouillon vide ne
     * joint rien et n'inscrit rien.
     */
    public function test_add_again_and_empty(): void {
        global $DB;
        $this->setup_activity();
        $this->setUser($this->deve);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_CLOSED]);
        $date = make_timestamp(2026, 10, 22, 12);
        foreach ([1, 2] as $unused) {
            $draftitemid = $this->draft($this->deve->id, 'scan.png', 'image');
            $this->assertSame(1, scans::add($this->context, $dossier, $this->deve->id, 'agreement', $date, $draftitemid));
        }
        $this->assertCount(2, these_get_files($this->context, 'signedscan', $dossier->id));

        $this->assertSame(0, scans::add(
            $this->context,
            $dossier,
            $this->deve->id,
            'agreement',
            $date,
            file_get_unused_draft_itemid()
        ));
        $this->assertSame(2, $DB->count_records('these_log', ['dossierid' => $dossier->id, 'logaction' => 'signedscan']));
    }

    /**
     * Les scans se voient comme les autres pièces du dossier : étudiant, directeur, DEVE,
     * bibliothèque ; pas d'un autre étudiant.
     */
    public function test_visibility(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id,
        ]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $expected = ['deve' => $this->deve, 'librarian' => $this->librarian, 'student' => $this->student,
            'director' => $this->director];
        foreach ($expected as $actor => $user) {
            $rights = these_get_rights($this->context, $dossier, $user->id);
            $this->assertTrue(these_can_view_filearea('signedscan', $rights), $actor);
        }
        $rights = these_get_rights($this->context, $dossier, $other->id);
        $this->assertFalse(these_can_view_filearea('signedscan', $rights), 'autre étudiant');
    }
}
