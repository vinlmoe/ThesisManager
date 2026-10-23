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
 * Relances : un acteur dont une étape attend depuis plus que le délai de l'activité est relancé,
 * une fois par délai écoulé ; les brouillons et les dossiers actifs ne le sont jamais.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_get_dossiers_needing_reminder
 * @covers     \mod_these\task\send_reminders
 */
final class reminder_test extends these_testcase {
    /**
     * Sélection des dossiers à relancer.
     */
    public function test_selection(): void {
        $this->setup_activity();
        $gen = $this->getDataGenerator();
        $old = time() - 20 * DAYSECS;
        $waiting = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'statustime' => $old, 'directorid' => $this->director->id,
        ]);
        $this->generator->create_dossier($this->these, $gen->create_and_enrol($this->course, 'student')->id, [
            'status' => THESE_STATUS_ACTIVE, 'statustime' => $old,
        ]);
        $this->generator->create_dossier($this->these, $gen->create_and_enrol($this->course, 'student')->id, [
            'status' => THESE_STATUS_DRAFT, 'statustime' => $old,
        ]);
        $this->generator->create_dossier($this->these, $gen->create_and_enrol($this->course, 'student')->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'statustime' => time() - 2 * DAYSECS,
        ]);

        $this->assertEquals([$waiting->id], array_keys(these_get_dossiers_needing_reminder()));
    }

    /**
     * La tâche relance le directeur une seule fois par délai écoulé.
     */
    public function test_task_sends_once_per_delay(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'statustime' => time() - 20 * DAYSECS,
            'directorid' => $this->director->id,
        ]);
        $sink = $this->redirectEmails();
        $task = new \mod_these\task\send_reminders();
        ob_start();
        $task->execute();
        $task->execute();
        ob_end_clean();

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($this->director->email, $messages[0]->to);
        $this->assertNotEmpty($DB->get_field('these_dossier', 'remindertime', ['id' => $dossier->id]));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'logaction' => 'reminded']));

        // Un nouveau délai écoulé depuis la relance : nouvelle relance.
        $DB->set_field('these_dossier', 'remindertime', time() - 15 * DAYSECS, ['id' => $dossier->id]);
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertCount(2, $sink->get_messages());
    }

    /**
     * Un délai nul désactive les relances de l'activité.
     */
    public function test_disabled(): void {
        $this->setup_activity(['reminderdays' => 0]);
        $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'statustime' => time() - 200 * DAYSECS,
        ]);
        $this->assertEmpty(these_get_dossiers_needing_reminder());
    }
}
