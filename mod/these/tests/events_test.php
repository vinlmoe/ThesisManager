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

use mod_these\event\defense_scheduled;
use mod_these\event\document_signed;
use mod_these\event\dossier_created;
use mod_these\event\dossier_status_changed;
use mod_these\event\minutes_recorded;
use mod_these\event\subject_submitted;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/these_testcase.php');

/**
 * Événements Moodle déclenchés par le cycle de vie d'un dossier.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\event\dossier_event
 * @covers     \mod_these\event\dossier_created
 * @covers     \mod_these\event\subject_submitted
 * @covers     \mod_these\event\dossier_status_changed
 * @covers     \mod_these\event\document_signed
 * @covers     \mod_these\event\defense_scheduled
 * @covers     \mod_these\event\minutes_recorded
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\dossier_event::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\dossier_created::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\subject_submitted::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\dossier_status_changed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\document_signed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\defense_scheduled::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\event\minutes_recorded::class)]
final class events_test extends these_testcase {
    /**
     * Événements d'une classe parmi ceux interceptés.
     *
     * @param \phpunit_event_sink $sink
     * @param string $class
     * @return \core\event\base[]
     */
    private function events_of(\phpunit_event_sink $sink, string $class): array {
        return array_values(array_filter($sink->get_events(), function ($event) use ($class) {
            return $event instanceof $class;
        }));
    }

    /**
     * Vérifications communes : objet, contexte, étudiant lié, adresse du dossier, nom et description.
     *
     * @param \core\event\base $event
     * @param \stdClass $dossier
     * @param int $userid
     * @return void
     */
    private function assert_dossier_event(\core\event\base $event, \stdClass $dossier, int $userid): void {
        $this->assertEquals($dossier->id, $event->objectid);
        $this->assertSame('these_dossier', $event->objecttable);
        $this->assertEquals($this->context->id, $event->contextid);
        $this->assertEquals($dossier->userid, $event->relateduserid);
        $this->assertEquals($userid, $event->userid);
        $this->assertEquals($this->course->id, $event->courseid);
        $expected = new \moodle_url('/mod/these/dossier.php', ['id' => $this->cm->id, 'dossierid' => $dossier->id]);
        $this->assertSame($expected->out(false), $event->get_url()->out(false));
        $this->assertNotEmpty($event::get_name());
        $this->assertStringContainsString("'" . $dossier->id . "'", $event->get_description());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Création d'un dossier.
     */
    public function test_dossier_created(): void {
        $this->setup_activity();
        $sink = $this->redirectEvents();
        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->deve->id);
        $events = $this->events_of($sink, dossier_created::class);
        $this->assertCount(1, $events);
        $this->assert_dossier_event($events[0], $dossier, $this->deve->id);
        $this->assertSame('c', $events[0]->crud);
        $this->assertCount(0, $this->events_of($sink, dossier_status_changed::class));
        $sink->close();
    }

    /**
     * Soumission du sujet : un seul événement de soumission, en plus du changement de statut ; un
     * simple enregistrement n'en déclenche aucun.
     */
    public function test_subject_submitted(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->student->id);
        $data = (object) ['title' => 'Leptospirose canine', 'summary' => 'Résumé', 'keywords' => 'chien',
            'directorid' => $this->director->id];
        $sink = $this->redirectEvents();
        these_save_subject($this->these, $this->cm, $dossier, $data, false, $this->student->id);
        $this->assertCount(0, $this->events_of($sink, subject_submitted::class));

        these_save_subject($this->these, $this->cm, $dossier, $data, true, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $events = $this->events_of($sink, subject_submitted::class);
        $this->assertCount(1, $events);
        $this->assert_dossier_event($events[0], $dossier, $this->student->id);
        $this->assertFalse($events[0]->other['resubmitted']);
        $sink->close();
    }

    /**
     * Changement de statut : statuts de départ et d'arrivée et action ; un enregistrement sans
     * changement de statut ne déclenche rien.
     */
    public function test_dossier_status_changed(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $sink = $this->redirectEvents();
        these_set_status($dossier, $dossier->status, $this->deve->id, 'edited');
        $this->assertCount(0, $this->events_of($sink, dossier_status_changed::class));

        these_validate_step($this->these, $this->cm, $dossier, $this->director->id);
        $dossier = $this->reload($dossier->id);
        $events = $this->events_of($sink, dossier_status_changed::class);
        $this->assertCount(1, $events);
        $this->assert_dossier_event($events[0], $dossier, $this->director->id);
        $this->assertSame([
            'from' => THESE_STATUS_SUBJECT_SUBMITTED, 'to' => THESE_STATUS_SUBJECT_APPROVED, 'action' => 'validateddirectorsubject',
        ], $events[0]->other);
        $sink->close();
    }

    /**
     * Signature d'un document : document, emplacement, méthode et enregistrement de la signature.
     */
    public function test_document_signed(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $sink = $this->redirectEvents();
        $signature = these_sign_document(
            $this->context,
            $dossier,
            'subject',
            'student',
            $this->student->id,
            'Étudiant',
            these_sample_signature_image()
        );
        $events = $this->events_of($sink, document_signed::class);
        $this->assertCount(1, $events);
        $this->assert_dossier_event($events[0], $dossier, $this->student->id);
        $this->assertSame([
            'doctype' => 'subject', 'slot' => 'student', 'method' => 'online', 'signatureid' => (int) $signature->id,
        ], $events[0]->other);
        $sink->close();
    }

    /**
     * Date de soutenance arrêtée par la DEVE à la validation du dossier de soutenance ; la validation
     * du directeur, qui précède, ne la fixe pas.
     */
    public function test_defense_scheduled(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id,
        ]);
        these_save_defense($this->these, $this->cm, $dossier, $this->defense_data(), true, $this->student->id);
        $dossier = $this->reload($dossier->id);
        $sink = $this->redirectEvents();
        these_validate_step($this->these, $this->cm, $dossier, $this->director->id);
        $dossier = $this->reload($dossier->id);
        $this->assertCount(0, $this->events_of($sink, defense_scheduled::class));

        $date = time() + 40 * DAYSECS;
        these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [
            'defensedate' => $date, 'devenumber' => these_suggest_devenumber(),
        ]);
        $dossier = $this->reload($dossier->id);
        $events = $this->events_of($sink, defense_scheduled::class);
        $this->assertCount(1, $events);
        $this->assert_dossier_event($events[0], $dossier, $this->deve->id);
        $this->assertSame(['defensedate' => $date], $events[0]->other);
        $sink->close();
    }

    /**
     * Procès-verbal enregistré : résultat et corrections, pour une admission comme pour un ajournement.
     */
    public function test_minutes_recorded(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id, 'defensedate' => time() - DAYSECS,
        ]);
        $sink = $this->redirectEvents();
        these_record_minutes($this->these, $this->cm, $dossier, $this->deve->id, (object) [
            'result' => THESE_RESULT_ADMITTED, 'mention' => 'honorable', 'correctionsrequested' => true,
            'correctionsnote' => 'Bibliographie',
        ]);
        $dossier = $this->reload($dossier->id);
        $this->assertEquals(THESE_STATUS_CORRECTIONS, $dossier->status);
        $events = $this->events_of($sink, minutes_recorded::class);
        $this->assertCount(1, $events);
        $this->assert_dossier_event($events[0], $dossier, $this->deve->id);
        $this->assertSame(['result' => THESE_RESULT_ADMITTED, 'corrections' => true], $events[0]->other);
        $this->assertStringContainsString('with corrections', $events[0]->get_description());
        $sink->clear();

        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $adjourned = $this->generator->create_dossier($this->these, $other->id, [
            'status' => THESE_STATUS_SCHEDULED, 'directorid' => $this->director->id, 'defensedate' => time() - DAYSECS,
        ]);
        these_record_minutes($this->these, $this->cm, $adjourned, $this->deve->id, (object) [
            'result' => THESE_RESULT_ADJOURNED,
        ]);
        $events = $this->events_of($sink, minutes_recorded::class);
        $this->assertCount(1, $events);
        $this->assertEquals($adjourned->id, $events[0]->objectid);
        $this->assertSame(['result' => THESE_RESULT_ADJOURNED, 'corrections' => false], $events[0]->other);
        $this->assertStringContainsString('adjourned', $events[0]->get_description());
        $sink->close();
    }
}
