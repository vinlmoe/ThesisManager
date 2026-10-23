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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Sauvegarde et restauration d'un cours : paramétrage, dossiers, jury, historique et fichiers
 * sont recopiés, et la copie pointe vers ses propres lignes.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_these_activity_structure_step
 * @covers     \restore_these_activity_structure_step
 */
final class backup_restore_test extends these_testcase {
    /**
     * Sauvegarde un cours puis le restaure dans un nouveau cours, données utilisateur comprises.
     *
     * @param \stdClass $course
     * @return int Identifiant du cours restauré.
     */
    private function backup_and_restore(\stdClass $course): int {
        global $CFG, $USER;

        $CFG->keeptempdirectoriesonbackup = true;
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course($course->fullname, $course->shortname . '_copie', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Copie complète d'une activité avec un dossier en cours.
     */
    public function test_backup_restore_with_userdata(): void {
        global $DB;
        $this->setup_activity(['universityemail' => 'scolarite@univ.example.com']);
        $this->setAdminUser();
        these_save_email_template($this->these->id, 'reminder', 'Relance', 'Corps');
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id, 'devenumber' => '2026-010',
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        these_log($dossier->id, $this->student->id, 'defensesubmitted', THESE_STATUS_ACTIVE, THESE_STATUS_DEFENSE_SUBMITTED);
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_these', 'filearea' => 'manuscript',
            'itemid' => $dossier->id, 'filepath' => '/', 'filename' => 'manuscrit.pdf',
        ], '%PDF-test');

        $newcourseid = $this->backup_and_restore($this->course);

        $newthese = $DB->get_record('these', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals('scolarite@univ.example.com', $newthese->universityemail);
        $this->assertTrue($DB->record_exists('these_email_template', ['theseid' => $newthese->id, 'emailkey' => 'reminder']));

        $newdossier = $DB->get_record('these_dossier', ['theseid' => $newthese->id], '*', MUST_EXIST);
        $this->assertNotEquals($dossier->id, $newdossier->id);
        $this->assertEquals($this->student->id, $newdossier->userid);
        $this->assertEquals($this->director->id, $newdossier->directorid);
        $this->assertEquals(THESE_STATUS_DEFENSE_SUBMITTED, $newdossier->status);
        $this->assertEquals('2026-010', $newdossier->devenumber);
        $this->assertEquals(4, $DB->count_records('these_jury', ['dossierid' => $newdossier->id]));
        $this->assertEquals(1, $DB->count_records('these_log', ['dossierid' => $newdossier->id]));

        $newcm = get_coursemodule_from_instance('these', $newthese->id);
        $newcontext = \context_module::instance($newcm->id);
        $files = these_get_files($newcontext, 'manuscript', $newdossier->id);
        $this->assertCount(1, $files);
        $this->assertEquals('manuscrit.pdf', $files[0]->get_filename());
    }
}
