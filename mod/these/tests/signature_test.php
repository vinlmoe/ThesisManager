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
 * Documents de l'établissement signés dans le module : apposition des signatures au fil du
 * circuit, invalidation quand le contenu signé change, invitations des signataires sans compte,
 * production des PDF, et rôles des services choisis dans les réglages du site.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_sign_document
 * @covers     ::these_refresh_signatures
 * @covers     ::these_request_signature
 * @covers     ::these_signed_document_pdf
 */
final class signature_test extends these_testcase {
    /**
     * Signature tracée factice : une image PNG.
     *
     * @param string $name Nom du signataire.
     * @return \stdClass {name, png}
     */
    private function signature(string $name = 'Signataire'): \stdClass {
        $image = imagecreatetruecolor(200, 60);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 10, 50, 190, 10, imagecolorallocate($image, 10, 40, 110));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        return (object) ['name' => $name, 'png' => $png];
    }

    /**
     * Données du formulaire de sujet.
     *
     * @return \stdClass
     */
    private function subject_data(): \stdClass {
        return (object) [
            'title' => 'Leptospirose canine', 'titleen' => '', 'summary' => 'Résumé', 'keywords' => 'chien ; rein ; zoonose',
            'directorid' => $this->director->id, 'directortitle' => 'Pr', 'codirectorname' => '', 'studyyear' => 'A4',
            'confidential' => 0, 'externaldirector' => 0, 'commitguide' => 1, 'commitduplicate' => 1, 'commitethics' => 0,
        ];
    }

    /**
     * Seules les images PNG plausibles sont acceptées comme signature.
     */
    public function test_decode_signature_image(): void {
        $this->resetAfterTest();
        $png = $this->signature()->png;
        $this->assertSame($png, these_decode_signature_image('data:image/png;base64,' . base64_encode($png)));
        $this->assertNull(these_decode_signature_image(''));
        $this->assertNull(these_decode_signature_image('data:image/png;base64,' . base64_encode('pas une image')));
        $this->assertNull(these_decode_signature_image('data:image/jpeg;base64,' . base64_encode($png)));
    }

    /**
     * Phase 1 : l'étudiant signe à la soumission, le directeur et la DEVE à la validation ; le
     * formulaire de dépôt de sujet porte les trois signatures.
     */
    public function test_subject_signatures(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->student->id);
        $dossier = these_save_subject(
            $this->these,
            $this->cm,
            $dossier,
            $this->subject_data(),
            true,
            $this->student->id,
            $this->signature('Camille Martin')
        );
        $director = $this->signature('Pr Durand');
        $dossier = these_validate_step($this->these, $this->cm, $dossier, $this->director->id, [], '', $director);
        $dossier = these_validate_step(
            $this->these,
            $this->cm,
            $dossier,
            $this->deve->id,
            ['devenumber' => '2026-001'],
            '',
            $this->signature('DEVE')
        );

        $signatures = these_get_signatures($dossier->id);
        foreach (['subject:student', 'subject:director', 'subject:deve'] as $key) {
            $this->assertArrayHasKey($key, $signatures);
            $this->assertNotEmpty($signatures[$key]->timesigned);
            $this->assertSame(these_document_hash($dossier, 'subject'), $signatures[$key]->contenthash);
            $this->assertNotNull(these_get_signature_image($this->context->id, $signatures[$key]->id));
        }
        $this->assertSame('Pr Durand', $signatures['subject:director']->signername);

        $pdf = these_signed_document_pdf($this->these, $this->context, $dossier, 'subject');
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * Un sujet modifié après signature perd ses signatures, et, renvoyé par la DEVE puis
     * corrigé, il repart chez le directeur pour être signé de nouveau.
     */
    public function test_changed_subject_invalidates_and_goes_back_to_director(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->student->id);
        $dossier = these_save_subject(
            $this->these,
            $this->cm,
            $dossier,
            $this->subject_data(),
            true,
            $this->student->id,
            $this->signature()
        );
        $dossier = these_validate_step($this->these, $this->cm, $dossier, $this->director->id, [], '', $this->signature());
        $dossier = these_return_dossier($this->these, $this->cm, $dossier, $this->deve->id, 'student', 'Titre à préciser');

        $data = $this->subject_data();
        $data->title = 'Leptospirose canine en zone périurbaine';
        $dossier = these_save_subject($this->these, $this->cm, $dossier, $data, true, $this->student->id, $this->signature());

        $this->assertEquals(THESE_STATUS_SUBJECT_SUBMITTED, $dossier->status);
        $signatures = these_get_signatures($dossier->id);
        $this->assertArrayNotHasKey('subject:director', $signatures);
        $this->assertArrayHasKey('subject:student', $signatures);
        $this->assertSame(these_document_hash($dossier, 'subject'), $signatures['subject:student']->contenthash);
    }

    /**
     * Un renvoi sans modification du contenu signé revient directement à l'étape qui l'a renvoyé.
     */
    public function test_unchanged_subject_returns_to_returning_step(): void {
        $this->setup_activity();
        $this->redirectEmails();
        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->student->id);
        $dossier = these_save_subject(
            $this->these,
            $this->cm,
            $dossier,
            $this->subject_data(),
            true,
            $this->student->id,
            $this->signature()
        );
        $dossier = these_validate_step($this->these, $this->cm, $dossier, $this->director->id, [], '', $this->signature());
        $dossier = these_return_dossier($this->these, $this->cm, $dossier, $this->deve->id, 'student', 'Pièce jointe manquante');
        $dossier = these_save_subject(
            $this->these,
            $this->cm,
            $dossier,
            $this->subject_data(),
            true,
            $this->student->id,
            $this->signature()
        );
        $this->assertEquals(THESE_STATUS_SUBJECT_APPROVED, $dossier->status);
        $this->assertArrayHasKey('subject:director', these_get_signatures($dossier->id));
    }

    /**
     * Directeur extérieur : un lien de signature lui est envoyé à la soumission du sujet, et sa
     * signature par le lien efface le jeton.
     */
    public function test_external_director_token(): void {
        $this->setup_activity();
        $sink = $this->redirectEmails();
        $dossier = these_create_dossier($this->these->id, $this->student->id, $this->student->id);
        $data = $this->subject_data();
        $data->externaldirector = 1;
        $data->extdirectorname = 'Dr Jean Externe';
        $data->extdirectorgrade = 'Docteur vétérinaire';
        $data->extdirectorinstitution = 'Clinique du Lac';
        $data->extdirectoremail = 'externe@example.com';
        $dossier = these_save_subject($this->these, $this->cm, $dossier, $data, true, $this->student->id, $this->signature());

        $this->assertContains('externe@example.com', array_column($sink->get_messages(), 'to'));
        $signature = these_get_signatures($dossier->id)['subject:extdirector'];
        $this->assertEmpty($signature->timesigned);
        $token = $this->token_sent_to($sink->get_messages(), 'externe@example.com');
        // Seule l'empreinte du jeton est en base.
        $this->assertSame(these_token_hash($token), $signature->token);
        $this->assertEmpty(these_get_signatures_by_token($signature->token));
        $pending = these_get_signatures_by_token($token);
        $this->assertCount(1, $pending);

        these_sign_document(
            $this->context,
            $dossier,
            'subject',
            'extdirector',
            0,
            'Dr Jean Externe',
            $this->signature()->png,
            'externe@example.com'
        );
        $this->assertEmpty(these_get_signatures_by_token($token));
        $this->assertEmpty(these_get_signatures_by_token('court'));
    }

    /**
     * Phase 2 : le dépôt signe le bordereau et le contrat, le directeur signe rapport, grille,
     * agrément, visa du contrat et conclusions ; la validation DEVE signe l'agrément et invite
     * une seule fois chaque signataire extérieur pour tous ses documents.
     */
    public function test_defense_signatures_and_invitations(): void {
        $this->setup_activity();
        \mod_these\local\scopesettings::set(these_local_scope($this->these), [
            'dgemail' => 'dg@example.com', 'ccememail' => 'ccem@example.com', 'ucblemail' => 'ucbl@example.com',
        ]);
        $sink = $this->redirectEmails();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id, 'directortitle' => 'Pr',
        ]);

        $data = $this->defense_data();
        $data->titleen = 'Canine leptospirosis';
        $data->conclusions = 'Conclusions de la thèse.';
        $data->bbirthname = 'MARTIN';
        $data->bfirstnames = 'Camille';
        $data->bpersonalemail = 'camille@example.com';
        $data->bkeyword = ['chien', 'rein'];
        $data->bdiffusion = 'internet';
        $dossier = these_save_defense($this->these, $this->cm, $dossier, $data, true, $this->student->id, $this->signature());
        $signatures = these_get_signatures($dossier->id);
        $this->assertArrayHasKey('bordereau:student', $signatures);
        $this->assertArrayHasKey('contract:student', $signatures);

        $grid = array_fill_keys(array_merge(...array_map('array_keys', array_values(these_grid_items()))), 1);
        $dossier = these_validate_step($this->these, $this->cm, $dossier, $this->director->id, [
            'reporttext' => 'Travail remarquable.', 'reportfavorable' => 1, 'conformitygrid' => json_encode($grid),
        ], '', $this->signature());
        $signatures = these_get_signatures($dossier->id);
        foreach (['report', 'grid', 'agreement', 'contract', 'conclusions'] as $doctype) {
            $this->assertNotEmpty($signatures[$doctype . ':director']->timesigned, $doctype);
        }

        $sink->clear();
        $dossier = these_validate_step($this->these, $this->cm, $dossier, $this->deve->id, [
            'agreement' => json_encode(['defvdate' => time(), 'mobility' => 1, 'a6notyet' => 1, 'a6date' => 0]),
        ], '', $this->signature());
        $signatures = these_get_signatures($dossier->id);
        $this->assertNotEmpty($signatures['agreement:deve']->timesigned);
        $this->assertNotEmpty($signatures['agreement:dgrep']->timesigned);
        // L'agrément administratif saisi par la DEVE n'invalide pas l'agrément scientifique.
        $this->assertNotEmpty($signatures['agreement:director']->timesigned);

        // Une invitation par signataire : président, direction générale, CCEM, université.
        $recipients = array_count_values(array_column($sink->get_messages(), 'to'));
        foreach (['president@example.com', 'dg@example.com', 'ccem@example.com', 'ucbl@example.com'] as $email) {
            $this->assertSame(1, $recipients[$email] ?? 0, $email);
        }
        // Le même lien couvre tous les documents de la direction générale.
        $token = $this->token_sent_to($sink->get_messages(), 'dg@example.com');
        $this->assertEqualsCanonicalizing(
            ['signaletique', 'contract', 'conclusions'],
            array_column(these_get_signatures_by_token($token), 'doctype')
        );

        // Tous les documents se produisent en PDF.
        foreach (array_keys(these_document_definitions()) as $doctype) {
            if (these_document_available($dossier, $doctype)) {
                $this->assertStringStartsWith(
                    '%PDF',
                    these_signed_document_pdf($this->these, $this->context, $dossier, $doctype),
                    $doctype
                );
            }
        }
    }

    /**
     * Le contrat de diffusion assemble la page de données, le contrat type déposé dans les
     * réglages et la page de signatures.
     */
    public function test_contract_includes_template(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_SUBMITTED, 'directorid' => $this->director->id,
            'bordereau' => json_encode(['birthname' => 'MARTIN', 'diffusion' => 'restricted']),
        ]);

        $pagecount = function (string $pdf): int {
            return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
        };
        $without = these_signed_document_pdf($this->these, $this->context, $dossier, 'contract');
        $this->assertSame(2, $pagecount($without));

        // Contrat type de trois pages.
        $template = new \pdf();
        for ($i = 0; $i < 3; $i++) {
            $template->AddPage();
            $template->Write(5, 'Article ' . ($i + 1));
        }
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_these', 'filearea' => 'contracttemplate',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'contrat.pdf',
        ], $template->Output('', 'S'));
        $with = these_signed_document_pdf($this->these, $this->context, $dossier, 'contract');
        $this->assertSame(5, $pagecount($with));
    }

    /**
     * Les rôles des services se choisissent dans les paramètres de l'activité : un rôle attribué au
     * niveau du site, sans inscription au cours, donne les droits du service.
     */
    public function test_service_roles_from_settings(): void {
        $this->setup_activity();
        $user = $this->getDataGenerator()->create_user();
        $rights = these_get_rights($this->context, null, $user->id);
        $this->assertFalse($rights->library);

        $roleid = these_setup_default_roles();
        role_assign($roleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $rights = these_get_rights($this->context, null, $user->id);
        $this->assertTrue($rights->library);
        $this->assertFalse($rights->deve);

        // Un rôle personnalisé choisi pour la DEVE, sans aucune capacité du module.
        $deverole = $this->getDataGenerator()->create_role(['shortname' => 'devecustom']);
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['deverole' => $deverole]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        role_assign($deverole, $other->id, \context_course::instance($this->course->id));
        $this->assertTrue(these_get_rights($this->context, null, $other->id)->deve);

        // Destinataires des notifications de la bibliothèque : le détenteur du rôle au niveau du site.
        $recipients = these_get_service_recipients($this->these, $this->context, 'library');
        $this->assertContains($user->id, array_column($recipients, 'id'));
    }
}
