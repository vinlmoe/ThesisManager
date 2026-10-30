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
     * Complète le dossier de make_dossier() : signature du directeur vérifiée sur place par la DEVE,
     * président du jury déclaré éligible par un autre compte DEVE, ancien sujet changé par la DEVE.
     *
     * @param \stdClass $dossier
     * @param \stdClass $validator Compte qui a validé l'éligibilité.
     * @return int Identifiant de la signature.
     */
    private function cite_staff(\stdClass $dossier, \stdClass $validator): int {
        global $DB;

        $now = time();
        $signatureid = $DB->insert_record('these_signature', (object) [
            'dossierid' => $dossier->id, 'doctype' => 'subject', 'slot' => 'director', 'userid' => $this->director->id,
            'signername' => 'Dora Directrice', 'signeremail' => '', 'token' => null, 'requesttime' => 0,
            'timesigned' => $now, 'contenthash' => str_repeat('c', 64), 'templatehash' => str_repeat('t', 64),
            'ipaddress' => '127.0.0.1', 'method' => 'inperson', 'delegated' => 0, 'verifiedby' => $this->deve->id,
            'validatedby' => 0, 'validatedtime' => 0, 'timecreated' => $now,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_these', 'filearea' => 'signature',
            'itemid' => $signatureid, 'filepath' => '/', 'filename' => 'signature.png',
        ], 'PNG');
        $jury = $this->generator->default_jury();
        $jury[0]->eligible = 1;
        these_save_jury($dossier->id, $jury, $validator->id);
        $DB->insert_record('these_subjecthistory', (object) [
            'dossierid' => $dossier->id, 'title' => 'Ancien sujet', 'titleen' => '', 'summary' => '', 'keywords' => '',
            'studyyear' => '', 'confidential' => 0, 'directorid' => $this->director->id, 'directorname' => 'Dora Directrice',
            'libraryref' => '', 'reason' => 'Changement', 'changedby' => $this->deve->id, 'timecreated' => $now,
        ]);
        return $signatureid;
    }

    /**
     * Un compte seulement cité (validation d'éligibilité) est trouvé par ses contextes comme dans la
     * liste des utilisateurs du contexte.
     */
    public function test_cited_staff_found(): void {
        $this->setup_activity();
        $dossier = $this->make_dossier();
        $validator = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->cite_staff($dossier, $validator);

        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($validator->id)->get_contextids());
        $userlist = new userlist($this->context, 'mod_these');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $validator->id, array_map('intval', $userlist->get_userids()));
        $this->assertNotContains((int) $this->librarian->id, array_map('intval', $userlist->get_userids()));
    }

    /**
     * Compte rattaché à un membre du jury : contexte et liste d'utilisateurs, export de ses jurys,
     * suppression qui détache le compte sans retirer le membre du jury.
     */
    public function test_jury_member_account(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->make_dossier();
        $juror = $this->getDataGenerator()->create_user(['email' => 'membre@example.com']);
        $jury = $this->generator->default_jury();
        foreach ($jury as $member) {
            $member->eligible = true;
        }
        these_save_jury($dossier->id, $jury, $this->deve->id);
        $member = $DB->get_record('these_jury', ['dossierid' => $dossier->id, 'juryrole' => 'member'], '*', MUST_EXIST);
        $this->assertEquals($juror->id, $member->userid);

        $this->assertEquals([$this->context->id], provider::get_contexts_for_userid($juror->id)->get_contextids());
        $userlist = new userlist($this->context, 'mod_these');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $juror->id, array_map('intval', $userlist->get_userids()));

        provider::export_user_data(new approved_contextlist($juror, 'mod_these', [$this->context->id]));
        $data = writer::with_context($this->context)->get_data([get_string('staffactions', 'mod_these')]);
        $this->assertEquals('Sujet de test', $data->juries[0]->dossier);
        $this->assertEquals('member', $data->juries[0]->role);

        provider::delete_data_for_user(new approved_contextlist($juror, 'mod_these', [$this->context->id]));
        $member = $DB->get_record('these_jury', ['id' => $member->id], '*', MUST_EXIST);
        $this->assertEquals(0, $member->userid);
        $this->assertSame('Albert Membre', $member->fullname);
        $this->assertEmpty(provider::get_contexts_for_userid($juror->id)->get_contextids());
    }

    /**
     * Signature enregistrée : contexte utilisateur remonté, exporté, puis effacé.
     */
    public function test_saved_signature_user_context(): void {
        $this->setup_activity();
        these_save_signature_image($this->director->id, 'PNG');
        $usercontext = \context_user::instance($this->director->id);

        $this->assertEquals([$usercontext->id], provider::get_contexts_for_userid($this->director->id)->get_contextids());
        $userlist = new userlist($usercontext, 'mod_these');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->director->id], $userlist->get_userids());
        $this->assertEmpty(provider::get_contexts_for_userid($this->deve->id)->get_contextids());

        provider::export_user_data(new approved_contextlist($this->director, 'mod_these', [$usercontext->id]));
        $this->assertNotEmpty(writer::with_context($usercontext)->get_files([get_string('signaturesaved', 'mod_these')]));

        provider::delete_data_for_user(new approved_contextlist($this->director, 'mod_these', [$usercontext->id]));
        $this->assertNull(these_get_saved_signature($this->director->id));
        $this->assertEmpty(provider::get_contexts_for_userid($this->director->id)->get_contextids());
    }

    /**
     * Suppression du personnel cité : signature anonymisée et image effacée, comptes de vérification,
     * d'éligibilité et de changement de sujet remis à zéro ; le dossier et sa date de signature restent.
     */
    public function test_delete_cited_staff(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->make_dossier();
        $validator = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $signatureid = $this->cite_staff($dossier, $validator);

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_these', [
            $this->director->id, $this->deve->id, $validator->id,
        ]));

        $this->assertTrue($DB->record_exists('these_dossier', ['id' => $dossier->id]));
        $signature = $DB->get_record('these_signature', ['id' => $signatureid], '*', MUST_EXIST);
        $this->assertEquals(0, $signature->userid);
        $this->assertSame('', $signature->signername);
        $this->assertEquals(0, $signature->verifiedby);
        $this->assertGreaterThan(0, $signature->timesigned);
        $this->assertNull(these_get_signature_image($this->context->id, $signatureid));
        $president = $DB->get_record('these_jury', ['dossierid' => $dossier->id, 'juryrole' => 'president'], '*', MUST_EXIST);
        $this->assertEquals(1, $president->eligible);
        $this->assertEquals(0, $president->eligibleby);
        $history = $DB->get_record('these_subjecthistory', ['dossierid' => $dossier->id], '*', MUST_EXIST);
        $this->assertEquals(0, $history->directorid);
        $this->assertSame('', $history->directorname);
        $this->assertEquals(0, $history->changedby);
        $this->assertSame('Ancien sujet', $history->title);
        foreach ([$this->director, $this->deve, $validator] as $user) {
            $this->assertEmpty(provider::get_contexts_for_userid($user->id)->get_contextids());
        }
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

    /**
     * Dossier de make_dossier() dont le procès-verbal est enregistré : PV scanné et PV d'ajournement
     * archivé, décisions du jury, plus une signature du directeur avec son image, un document scellé,
     * une réponse au questionnaire et un lien d'accès du jury.
     *
     * @return \stdClass Dossier.
     */
    private function make_dossier_with_minutes(): \stdClass {
        global $DB;

        $dossier = $this->make_dossier();
        $DB->update_record('these_dossier', (object) ['id' => $dossier->id, 'status' => THESE_STATUS_CLOSED,
            'title' => 'Sujet de test', 'summary' => 'Résumé personnel', 'devenumber' => '2026-042',
            'defensedate' => make_timestamp(2026, 6, 15, 14), 'defenseresult' => THESE_RESULT_ADMITTED,
            'mention' => 'honorable', 'bordereau' => json_encode(['birthname' => 'MARTIN'])]);
        $fs = get_file_storage();
        $record = ['contextid' => $this->context->id, 'component' => 'mod_these', 'itemid' => $dossier->id, 'filepath' => '/'];
        $fs->create_file_from_string($record + ['filearea' => 'pv', 'filename' => 'pv.pdf'], '%PDF-pv-signe');
        $fs->create_file_from_string($record + ['filearea' => 'pvadjourned', 'filename' => 'ajournement-pv.pdf'], '%PDF-ajourne');
        $fs->create_file_from_string($record + ['filearea' => 'sealed', 'filename' => 'conclusions.pdf'], '%PDF-scelle');
        $this->cite_staff($dossier, $this->deve);
        $DB->set_field('these_jury', 'accesstoken', str_repeat('a', 64), ['dossierid' => $dossier->id]);
        $DB->insert_record('these_answer', (object) ['dossierid' => $dossier->id, 'questionid' => 1, 'value' => 'Réponse',
            'timemodified' => time()]);
        return $this->reload($dossier->id);
    }

    /**
     * Empreintes des fichiers d'une zone du dossier.
     *
     * @param int $dossierid
     * @param string $filearea
     * @return string[] nom => empreinte SHA-1
     */
    private function area_hashes(int $dossierid, string $filearea): array {
        $hashes = [];
        foreach (get_file_storage()->get_area_files($this->context->id, 'mod_these', $filearea, $dossierid, 'id', false) as $file) {
            $hashes[$file->get_filename()] = $file->get_contenthash();
        }
        return $hashes;
    }

    /**
     * Suppression d'un étudiant dont le procès-verbal est enregistré, par chacun des trois chemins :
     * le PV (fichiers intacts, champs du dossier, jury) est conservé ; le reste est effacé.
     */
    public function test_delete_student_keeps_minutes(): void {
        global $DB;
        $this->setup_activity();
        foreach (['user', 'users', 'context'] as $path) {
            $dossier = $this->make_dossier_with_minutes();
            $pv = $this->area_hashes($dossier->id, 'pv');
            $adjourned = $this->area_hashes($dossier->id, 'pvadjourned');
            $this->assertCount(1, $pv);

            if ($path === 'user') {
                provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_these', [$this->context->id]));
            } else if ($path === 'users') {
                provider::delete_data_for_users(new approved_userlist($this->context, 'mod_these', [$this->student->id]));
            } else {
                provider::delete_data_for_all_users_in_context($this->context);
            }

            // Le procès-verbal : fichiers inchangés, champs du PV et jury conservés.
            $this->assertSame($pv, $this->area_hashes($dossier->id, 'pv'), $path);
            $this->assertSame($adjourned, $this->area_hashes($dossier->id, 'pvadjourned'), $path);
            $kept = $this->reload($dossier->id);
            $this->assertEquals($this->student->id, $kept->userid);
            $this->assertSame('Sujet de test', $kept->title);
            $this->assertSame('2026-042', $kept->devenumber);
            $this->assertEquals($dossier->defensedate, $kept->defensedate);
            $this->assertEquals(THESE_RESULT_ADMITTED, $kept->defenseresult);
            $this->assertSame('honorable', $kept->mention);
            $this->assertEquals(THESE_STATUS_CLOSED, $kept->status);
            $this->assertEquals(4, $DB->count_records('these_jury', ['dossierid' => $dossier->id]), $path);

            // Le reste est effacé.
            $this->assertSame('', (string) $kept->summary);
            $this->assertEmpty($kept->bordereau);
            $this->assertSame('', (string) $kept->returncomment);
            $this->assertEquals(0, $kept->returnedby);
            foreach (['manuscript', 'sealed'] as $filearea) {
                $this->assertSame([], $this->area_hashes($dossier->id, $filearea), "$path $filearea");
            }
            foreach (['these_signature', 'these_log', 'these_answer', 'these_subjecthistory'] as $table) {
                $this->assertEquals(0, $DB->count_records($table, ['dossierid' => $dossier->id]), "$path $table");
            }
            $tokens = $DB->count_records_select('these_jury', 'dossierid = ? AND accesstoken IS NOT NULL', [$dossier->id]);
            $this->assertEquals(0, $tokens, $path);
            $this->assertTrue(get_file_storage()->is_area_empty($this->context->id, 'mod_these', 'signature'));

            // Rejouée : rien de plus ne disparaît.
            provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_these', [$this->context->id]));
            $this->assertSame($pv, $this->area_hashes($dossier->id, 'pv'));
            $DB->delete_records('these_jury', ['dossierid' => $dossier->id]);
            $DB->delete_records('these_dossier', ['id' => $dossier->id]);
            get_file_storage()->delete_area_files($this->context->id, 'mod_these', 'pv', $dossier->id);
            get_file_storage()->delete_area_files($this->context->id, 'mod_these', 'pvadjourned', $dossier->id);
        }
    }

    /**
     * Une simple décision du jury reportée suffit à conserver le dossier ; sans aucun procès-verbal,
     * le dossier est effacé en entier.
     */
    public function test_delete_student_minutes_by_decision_only(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->make_dossier();
        $DB->set_field('these_dossier', 'defenseresult', THESE_RESULT_ADJOURNED, ['id' => $dossier->id]);
        provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_these', [$this->context->id]));
        $this->assertTrue($DB->record_exists('these_dossier', ['id' => $dossier->id]));
        $this->assertTrue(get_file_storage()->is_area_empty($this->context->id, 'mod_these', 'manuscript', $dossier->id));

        $DB->set_field('these_dossier', 'defenseresult', 0, ['id' => $dossier->id]);
        provider::delete_data_for_user(new approved_contextlist($this->student, 'mod_these', [$this->context->id]));
        $this->assertFalse($DB->record_exists('these_dossier', ['id' => $dossier->id]));
        $this->assertFalse($DB->record_exists('these_jury', ['dossierid' => $dossier->id]));
    }

    /**
     * Suppression d'un membre du personnel signataire, d'un dossier dont le procès-verbal est
     * enregistré : le PV et les décisions sont inchangés ; ses références ailleurs sont dissociées.
     */
    public function test_delete_staff_keeps_minutes(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->make_dossier_with_minutes();
        $pv = $this->area_hashes($dossier->id, 'pv');
        $adjourned = $this->area_hashes($dossier->id, 'pvadjourned');

        provider::delete_data_for_users(new approved_userlist($this->context, 'mod_these', [
            $this->director->id, $this->deve->id,
        ]));

        $this->assertSame($pv, $this->area_hashes($dossier->id, 'pv'));
        $this->assertSame($adjourned, $this->area_hashes($dossier->id, 'pvadjourned'));
        $kept = $this->reload($dossier->id);
        $this->assertEquals(THESE_RESULT_ADMITTED, $kept->defenseresult);
        $this->assertSame('honorable', $kept->mention);
        $this->assertEquals(4, $DB->count_records('these_jury', ['dossierid' => $dossier->id]));
        $this->assertEquals(0, $kept->directorid);
        $signatures = ['dossierid' => $dossier->id, 'userid' => $this->director->id];
        $this->assertEquals(0, $DB->count_records('these_signature', $signatures));
    }
}
