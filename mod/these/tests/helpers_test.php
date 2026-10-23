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
 * Fonctions utilitaires : composition du jury, numérotation DEVE, rendu d'un dossier à chaque
 * étape du circuit et suppression de l'instance.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_validate_jury
 * @covers     ::these_suggest_devenumber
 * @covers     ::these_render_dossier
 */
final class helpers_test extends these_testcase {
    /**
     * Un et un seul président, un minimum de membres votants, des adresses valides.
     */
    public function test_validate_jury(): void {
        $this->resetAfterTest();
        $jury = $this->getDataGenerator()->get_plugin_generator('mod_these')->default_jury();
        $this->assertNull(these_validate_jury($jury, 3));
        $this->assertNotNull(these_validate_jury($jury, 4));

        $nopresident = $jury;
        $nopresident[0] = clone $jury[0];
        $nopresident[0]->juryrole = 'member';
        $this->assertEquals(get_string('juryonepresident', 'mod_these'), these_validate_jury($nopresident, 3));

        $bademail = $jury;
        $bademail[2] = clone $jury[2];
        $bademail[2]->email = 'pas-une-adresse';
        $this->assertStringContainsString('Albert Assesseur', these_validate_jury($bademail, 3));
    }

    /**
     * Les lignes de jury au nom vide sont ignorées, les rôles inconnus ramenés à « assesseur ».
     */
    public function test_extract_submitted_jury(): void {
        $members = these_extract_submitted_jury([
            'juryrole' => ['president', 'bogus', 'member'],
            'juryname' => ['  Paul  ', '', 'Albert'],
            'jurytitle' => ['Pr', '', ''],
            'juryinstitution' => ['', '', ''],
            'juryemail' => ['', '', ''],
        ]);
        $this->assertCount(2, $members);
        $this->assertEquals('Paul', $members[0]->fullname);
        $this->assertEquals('member', $members[1]->juryrole);
    }

    /**
     * Numérotation AAAA-NNN : premier numéro libre de l'année, unicité contrôlée.
     */
    public function test_devenumber(): void {
        $this->setup_activity();
        $year = userdate(time(), '%Y');
        $this->assertEquals($year . '-001', these_suggest_devenumber($this->these->id));

        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['devenumber' => $year . '-007']);
        $this->generator->create_dossier($this->these, $other->id, ['devenumber' => 'ANCIEN-12']);
        $this->assertEquals($year . '-008', these_suggest_devenumber($this->these->id));
        $this->assertTrue(these_devenumber_taken($this->these->id, $year . '-007'));
        $this->assertFalse(these_devenumber_taken($this->these->id, $year . '-007', $dossier->id));
    }

    /**
     * Le dossier se rend à chaque statut, pour chaque acteur, sans erreur ni chaîne manquante,
     * et ne propose à chacun que ses propres actions.
     */
    public function test_render_dossier_at_every_status(): void {
        global $PAGE;
        $this->setup_activity();
        $PAGE->set_url(new \moodle_url('/mod/these/dossier.php', ['id' => $this->cm->id]));
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'directorid' => $this->director->id, 'proposeddate' => time(), 'devenumber' => '2026-001',
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        these_log($dossier->id, $this->deve->id, 'returnedtodirector', 0, 0, 'Motif');

        foreach (these_all_statuses() as $status) {
            $dossier->status = $status;
            $dossier->cancelledfromstatus = THESE_STATUS_ACTIVE;
            foreach ([$this->student, $this->director, $this->deve, $this->librarian] as $user) {
                $rights = these_get_rights($this->context, $dossier, $user->id);
                $html = these_render_dossier($this->these, $this->cm, $this->context, $dossier, $rights);
                $this->assertStringNotContainsString('[[', $html, "Chaîne manquante au statut $status");
                $actions = these_get_dossier_actions($this->cm, $dossier, $rights);
                $steps = these_review_steps();
                if (isset($steps[$status])) {
                    $this->assertSame(
                        these_user_can_act($dossier, $rights),
                        array_key_exists(get_string('review' . $steps[$status]['key'], 'mod_these'), $actions)
                    );
                }
            }
        }
        $this->assertDebuggingNotCalled();
    }

    /**
     * La suppression de l'instance emporte dossiers, jury, historique et modèles de courriels.
     */
    public function test_delete_instance(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id);
        these_save_jury($dossier->id, $this->generator->default_jury());
        these_log($dossier->id, $this->student->id, 'created', 0, 0);
        these_save_email_template($this->these->id, 'reminder', 'Sujet', 'Corps');

        course_delete_module($this->cm->id);
        $this->assertFalse($DB->record_exists('these_dossier', ['id' => $dossier->id]));
        $this->assertFalse($DB->record_exists('these_jury', ['dossierid' => $dossier->id]));
        $this->assertFalse($DB->record_exists('these_log', ['dossierid' => $dossier->id]));
        $this->assertFalse($DB->record_exists('these_email_template', ['theseid' => $this->these->id]));
    }

    /**
     * La réinitialisation du cours ne supprime les dossiers que si l'option est cochée.
     */
    public function test_reset_userdata(): void {
        global $DB;
        $this->setup_activity();
        $this->generator->create_dossier($this->these, $this->student->id);

        $this->assertEquals([], these_reset_userdata((object) ['courseid' => $this->course->id, 'reset_these_dossiers' => 0]));
        $this->assertEquals(1, $DB->count_records('these_dossier', ['theseid' => $this->these->id]));

        these_reset_userdata((object) ['courseid' => $this->course->id, 'reset_these_dossiers' => 1]);
        $this->assertEquals(0, $DB->count_records('these_dossier', ['theseid' => $this->these->id]));
    }

    /**
     * Les documents à la demande (fiche de sujet, attestation) sont des PDF valides.
     */
    public function test_on_demand_documents(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_CONFORM, 'directorid' => $this->director->id, 'devenumber' => '2026-003',
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        $data = these_document_data($this->these, $dossier);
        $this->assertStringStartsWith('%PDF', \mod_these\pdf\document_pdf::subject_sheet($data));
        $data['librarian'] = 'Bibliothécaire';
        $this->assertStringStartsWith('%PDF', \mod_these\pdf\document_pdf::conformity($data));
    }
    /**
     * Le formulaire rechargé conserve le rôle enregistré de chaque membre du jury (régression :
     * une valeur par défaut posée sur les lignes répétées écrasait le président).
     */
    public function test_jury_form_keeps_saved_roles(): void {
        $this->setup_activity();
        $jury = $this->generator->default_jury();
        $form = new \mod_these\form\dossier_edit_form(null, [
            'directors' => [0 => ''],
            'theseid' => $this->these->id,
            'minjury' => 3,
            'jurycount' => count($jury),
        ]);
        $form->set_data(these_jury_form_data($jury));
        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);
        foreach ($jury as $i => $member) {
            $this->assertEquals([$member->juryrole], $mform->getElement("juryrole[$i]")->getValue());
        }
        // Ligne vide supplémentaire : sans valeur, elle affiche la première option, « Assesseur ».
        $this->assertNull($mform->getElement('juryrole[' . count($jury) . ']')->getValue());
        $this->assertEquals('member', array_key_first(these_jury_role_options()));
    }
}
