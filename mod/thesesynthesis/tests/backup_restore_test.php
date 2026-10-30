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

namespace mod_thesesynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Sauvegarde et restauration d'une activité de suivi : les liens suivent les activités « Gestion
 * des thèses » restaurées avec elle, les paramètres partagés sont recopiés sous la nouvelle instance.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_thesesynthesis_activity_structure_step
 * @covers     \restore_thesesynthesis_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Sauvegarde un cours puis le restaure dans un nouveau cours.
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
     * Le lien vers l'activité restaurée dans le même cours est remappé (after_restore) ; celui vers
     * une activité d'un autre cours, non restaurée, est conservé sur le même site. La question et
     * l'élément de grille partagés sont recopiés sous la nouvelle instance.
     */
    public function test_backup_restore_links_and_shared_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $these = $gen->create_module('these', ['course' => $course->id]);
        $othercourse = $gen->create_course();
        $otherthese = $gen->create_module('these', ['course' => $othercourse->id]);
        $synthesis = $gen->create_module('thesesynthesis', ['course' => $course->id, 'name' => 'Suivi 2026']);
        thesesynthesis_set_links($synthesis->id, [$these->cmid, $otherthese->cmid]);

        $scope = \mod_thesesynthesis\local\scope::for_synthesis($synthesis->id);
        $questionid = these_save_question($scope, (object) [
            'qtype' => 'text', 'label' => 'Laboratoire', 'options' => '', 'required' => 1,
        ]);
        these_save_grid_items($scope, [
            (object) ['itemkey' => 'pagination', 'section' => 'forme', 'label' => 'Pages numérotées', 'conditional' => 0],
        ]);

        $newcourseid = $this->backup_and_restore($course);

        $newsynthesis = $DB->get_record('thesesynthesis', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertNotEquals($synthesis->id, $newsynthesis->id);
        $this->assertSame('Suivi 2026', $newsynthesis->name);
        $newthese = $DB->get_record('these', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('these', $newthese->id);

        $links = array_keys(thesesynthesis_get_links($newsynthesis->id));
        sort($links);
        $expected = [(int) $newcm->id, (int) $otherthese->cmid];
        sort($expected);
        $this->assertSame($expected, $links);
        // Les liens d'origine sont intacts.
        $this->assertCount(2, thesesynthesis_get_links($synthesis->id));

        $newscope = \mod_thesesynthesis\local\scope::for_synthesis($newsynthesis->id);
        $questions = these_get_questions($newscope);
        $this->assertCount(1, $questions);
        $this->assertNotEquals($questionid, reset($questions)->id);
        $this->assertSame('Laboratoire', reset($questions)->label);
        $this->assertSame('Pages numérotées', these_get_grid_items($newscope)['forme']['pagination']->label);
        $this->assertSame(0, (int) reset($questions)->theseid);
        // L'activité restaurée est bien suivie par la copie : ses paramètres sont ceux de la copie.
        $this->assertEquals($newsynthesis->id, these_settings_scope($newthese)->synthesisid);
    }
}
