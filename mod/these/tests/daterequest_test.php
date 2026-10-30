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

use mod_these\local\daterequest;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Nouvelle date de soutenance demandée par la DEVE : le dossier garde son statut et ses signatures,
 * l'étudiant est prévenu, et la validation de la DEVE attend sa nouvelle proposition.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\daterequest
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\daterequest::class)]
final class daterequest_test extends these_testcase {
    /**
     * Demande, puis nouvelle proposition de l'étudiant.
     */
    public function test_request_and_propose(): void {
        global $DB;
        $this->setup_activity(['deveemail' => 'deve@example.org']);
        $sink = $this->redirectEmails();
        $date = time() + 3 * WEEKSECS;
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id,
            'proposeddate' => $date, 'defensedate' => $date,
        ]);
        these_sign_document(
            $this->context,
            $dossier,
            'conclusions',
            'director',
            $this->director->id,
            'Directeur',
            these_sample_signature_image()
        );
        $this->assertTrue(daterequest::allowed($dossier));
        $this->assertFalse(daterequest::pending($dossier));

        $dossier = daterequest::request($this->these, $this->cm, $dossier, $this->deve->id, 'Aucune salle libre.');
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_APPROVED, $dossier->status);
        $this->assertTrue(daterequest::pending($dossier));
        $this->assertFalse(daterequest::allowed($dossier));
        $this->assertSame('Aucune salle libre.', $dossier->daterequestcomment);
        $this->assertArrayHasKey('conclusions:director', these_get_signatures($dossier->id));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'daterequested']));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame($this->student->email, $messages[0]->to);
        $sink->clear();

        $newdate = $date + DAYSECS;
        $dossier = daterequest::propose($this->these, $this->cm, $dossier, $this->student->id, $newdate);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_DEFENSE_APPROVED, $dossier->status);
        $this->assertFalse(daterequest::pending($dossier));
        $this->assertEquals($newdate, $dossier->proposeddate);
        $this->assertEquals($newdate, $dossier->defensedate);
        $this->assertArrayHasKey('conclusions:director', these_get_signatures($dossier->id));
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('deve@example.org', $messages[0]->to);
    }

    /**
     * Demande possible seulement entre le dépôt du dossier de soutenance et sa validation par la DEVE.
     */
    public function test_allowed_statuses(): void {
        $this->resetAfterTest();
        foreach (these_all_statuses() as $status) {
            $expected = in_array($status, [THESE_STATUS_DEFENSE_SUBMITTED, THESE_STATUS_DEFENSE_APPROVED], true);
            $this->assertSame($expected, daterequest::allowed((object) ['status' => $status, 'daterequested' => 0]), "$status");
        }
        // Une demande restée en attente après la validation ne bloque plus rien.
        $this->assertFalse(daterequest::pending((object) ['status' => THESE_STATUS_DEFENSE_VALIDATED, 'daterequested' => time()]));
    }
}
