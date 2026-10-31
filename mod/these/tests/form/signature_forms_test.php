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

namespace mod_these\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/form_testcase.php');

/**
 * Validation des formulaires de signature : signature par lien, signature groupée de « Mes
 * signatures », refus de signer, vérification d'identité avant une signature sur place.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\sign_form
 * @covers     \mod_these\form\mysignatures_form
 * @covers     \mod_these\form\refuse_form
 * @covers     \mod_these\form\identity_form
 * @covers     \mod_these\local\signaturefields
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\sign_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\mysignatures_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\refuse_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\identity_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\signaturefields::class)]
final class signature_forms_test extends form_testcase {
    /**
     * Signature par lien : tracé lisible, nom et certification obligatoires.
     */
    public function test_sign_form(): void {
        $this->setup_form_page();
        $form = new sign_form(null, ['signername' => 'Hélène Durand']);
        $signature = $this->signature_data();

        $this->assertSame([], $form->validation($signature, []));
        $this->assertSame(
            ['signername' => get_string('signaturemissing', 'mod_these')],
            $form->validation(['signaturedata' => ''] + $signature, [])
        );
        // Une image qui n'est pas un PNG, ou mal encodée, vaut une signature absente.
        $notpng = 'data:image/png;base64,' . base64_encode(str_repeat('x', 200));
        $this->assertSame(
            ['signername' => get_string('signaturemissing', 'mod_these')],
            $form->validation(['signaturedata' => $notpng] + $signature, [])
        );
        $this->assertSame(
            ['signername' => get_string('signaturemissing', 'mod_these')],
            $form->validation(['signaturedata' => 'data:image/jpeg;base64,AAAA'] + $signature, [])
        );
        $this->assertSame(['signername' => get_string('required')], $form->validation(['signername' => ' '] + $signature, []));
        $this->assertSame(
            ['signatureconsent' => get_string('signatureconsentrequired', 'mod_these')],
            $form->validation(['signatureconsent' => 0] + $signature, [])
        );
        // « Utiliser ma signature enregistrée » sans signature enregistrée : le tracé reste exigé.
        $this->assert_error_fields(['signername'], $form->validation(['usesavedsignature' => 1, 'signername' => 'H',
            'signatureconsent' => 1], []));
    }

    /**
     * Mes signatures : au moins un document coché, en plus de la signature.
     */
    public function test_mysignatures_form(): void {
        $this->setup_form_page($this->director);
        $form = new mysignatures_form(null, [
            'documents' => [11 => 'Conclusions', 12 => 'Grille de conformité'],
            'groups' => [['heading' => 'Dossier de test', 'ids' => [11, 12]]],
            'signername' => fullname($this->director),
        ]);
        $signature = $this->signature_data();

        $this->assertSame(
            ['documentsgroup' => get_string('signnodocument', 'mod_these')],
            $form->validation(['sig11' => 0, 'sig12' => 0] + $signature, [])
        );
        $this->assertSame([], $form->validation(['sig11' => 0, 'sig12' => 1] + $signature, []));
        // Un identifiant qui n'est pas proposé ne compte pas.
        $this->assert_error_fields(['documentsgroup'], $form->validation(['sig99' => 1] + $signature, []));
        $this->assert_error_fields(['documentsgroup', 'signername', 'signatureconsent'], $form->validation([], []));
    }

    /**
     * Refus de signer : au moins un document et un motif.
     */
    public function test_refuse_form(): void {
        $this->setup_form_page($this->director);
        $form = new refuse_form(null, ['documents' => [21 => 'Conclusions', 22 => 'Accord'], 'heading' => 'Dossier']);

        $this->assertSame([], $form->validation(['sig21' => 1, 'sig22' => 0, 'reason' => 'Erreur de date'], []));
        $this->assertSame(
            ['refusedocuments' => get_string('signnodocument', 'mod_these')],
            $form->validation(['sig21' => 0, 'sig22' => 0, 'reason' => 'Erreur de date'], [])
        );
        $this->assertSame(['reason' => get_string('required')], $form->validation(['sig22' => 1, 'reason' => "\t"], []));
        $this->assert_error_fields(['refusedocuments', 'reason'], $form->validation([], []));
    }

    /**
     * Signature sur place : la vérification d'identité est obligatoire.
     */
    public function test_identity_form(): void {
        $this->setup_form_page();
        $form = new identity_form();

        $this->assertSame(
            ['identityverified' => get_string('signidentityrequired', 'mod_these')],
            $form->validation(['identityverified' => 0], [])
        );
        $this->assertSame([], $form->validation(['identityverified' => 1], []));
    }
}
