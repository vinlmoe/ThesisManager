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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_these\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Fournisseur de données personnelles : l'étudiant retrouve et peut faire effacer son dossier ;
 * le personnel cité dans le dossier d'un autre en est dissocié sans que ce dossier disparaisse.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\privacy\provider
 */
final class privacy_provider_test extends these_testcase {
    /**
     * Dossier complet : directeur, jury, historique, renvoi par la DEVE, fichier.
     *
     * @return \stdClass
     */
    private function make_dossier(): \stdClass {
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id,
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        these_log($dossier->id, $this->student->id, 'defensesubmitted', 0, 0);
        these_log($dossier->id, $this->director->id, 'validateddirectordefense', 0, 0, 'Très bon travail');
        $this->redirectEmails();
        these_return_dossier($this->these, $this->cm, $dossier, $this->deve->id, 'student', 'Pièce manquante');
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_these', 'filearea' => 'manuscript',
            'itemid' => $dossier->id, 'filepath' => '/', 'filename' => 'manuscrit.pdf',
        ], '%PDF-test');
        return $this->reload($dossier->id);
    }

    /**
     * Contextes et utilisateurs remontés, quel que soit leur rôle.
     */
    public function test_contexts_and_users(): void {
        $this->setup_activity();
        $this->make_dossier();

        foreach ([$this->student, $this->director, $this->deve] as $user) {
            $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        }
        $this->assertEmpty(provider::get_contexts_for_userid($this->librarian->id)->get_contextids());

        $userlist = new userlist($this->context, 'mod_these');
        provider::get_users_in_context($userlist);
        $expected = [$this->student->id, $this->director->id, $this->deve->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Export : le dossier de l'étudiant, sans le rapport du directeur ; les interventions du
     * directeur dans le dossier d'autrui.
     */
    public function test_export(): void {
        $this->setup_activity();
        $this->make_dossier();

        provider::export_user_data(new approved_contextlist($this->student, 'mod_these', [$this->context->id]));
        $writer = writer::with_context($this->context);
        $data = $writer->get_data([get_string('mydossier', 'mod_these')]);
        $this->assertEquals('Sujet de test', $data->title);
        $this->assertEquals('Pièce manquante', $data->returncomment);
        $this->assertNotEmpty($writer->get_files([get_string('mydossier', 'mod_these')]));

        writer::reset();
        provider::export_user_data(new approved_contextlist($this->director, 'mod_these', [$this->context->id]));
        $data = writer::with_context($this->context)->get_data([get_string('staffactions', 'mod_these')]);
        $this->assertEquals(['Sujet de test'], $data->directed);
        $this->assertEquals('Très bon travail', $data->actions[0]->remark);
    }

    /**
     * Suppression de l'étudiant : tout son dossier disparaît.
     */
    public function test_delete_student(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->make_dossier();

        provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_these', [$this->context->id]));
        $this->assertFalse($DB->record_exists('these_dossier', ['id' => $dossier->id]));
        $this->assertFalse($DB->record_exists('these_jury', ['dossierid' => $dossier->id]));
        $this->assertFalse($DB->record_exists('these_log', ['dossierid' => $dossier->id]));
        $this->assertTrue(get_file_storage()->is_area_empty($this->context->id, 'mod_these', 'manuscript', $dossier->id));
    }

    /**
     * Suppression du personnel : le dossier de l'étudiant reste, les références sont dissociées.
     */
    public function test_delete_staff(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->make_dossier();

        $userlist = new approved_userlist($this->context, 'mod_these', [$this->director->id, $this->deve->id]);
        provider::delete_data_for_users($userlist);

        $dossier = $this->reload($dossier->id);
        $this->assertEquals(0, $dossier->directorid);
        $this->assertEquals(0, $dossier->returnedby);
        $this->assertEquals('', $dossier->returncomment);
        $this->assertFalse($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'userid' => $this->director->id]));
        $this->assertTrue($DB->record_exists('these_log', ['dossierid' => $dossier->id, 'userid' => $this->student->id]));
        $this->assertEquals(4, $DB->count_records('these_jury', ['dossierid' => $dossier->id]));
    }

    /**
     * Suppression de tout le contexte.
     */
    public function test_delete_all(): void {
        global $DB;
        $this->setup_activity();
        $this->make_dossier();
        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertEquals(0, $DB->count_records('these_dossier', ['theseid' => $this->these->id]));
    }
}
