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
 * Validation du dépôt du sujet et du changement de sujet : brouillon libre ; à la soumission,
 * champs, mots-clés, directeur (interne, ou extérieur suppléé par un examinateur), engagements et
 * signature.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\subject_form
 */
final class subject_form_test extends form_testcase {
    /**
     * Formulaire du dépôt du sujet.
     *
     * @param array $customdata Données à surcharger.
     * @return subject_form
     */
    private function form(array $customdata = []): subject_form {
        return new subject_form(null, $customdata + [
            'directors' => [0 => '', $this->director->id => fullname($this->director)],
            'submitlabel' => 'submitsubject',
            'allowdraft' => true,
            'signername' => fullname($this->student),
        ]);
    }

    /**
     * Sujet complet, soumis, avec un directeur interne.
     *
     * @return array
     */
    private function complete(): array {
        return [
            'submitsubject' => 1,
            'studyyear' => 'A5',
            'title' => 'Maladie de Carré chez le furet',
            'keywords' => 'furet ; Carré ; virologie',
            'externaldirector' => 0,
            'directorid' => $this->director->id,
            'commitguide' => 1,
            'commitduplicate' => 1,
            'commitethics' => 0,
        ] + $this->signature_data();
    }

    /**
     * Un brouillon reste incomplet ; la soumission complète est acceptée.
     */
    public function test_draft_and_complete(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();

        $this->assertSame([], $form->validation(['savedraft' => 1], []));
        $this->assertSame([], $form->validation($this->complete(), []));
    }

    /**
     * Soumission vide : chaque champ obligatoire, le directeur, les deux engagements requis et la
     * signature sont signalés ; l'engagement éthique reste facultatif.
     */
    public function test_required_fields(): void {
        $this->setup_form_page($this->student);
        $errors = $this->form()->validation(['submitsubject' => 1], []);

        $this->assert_error_fields(['studyyeargroup', 'title', 'keywords', 'directorid', 'commitguide', 'commitduplicate',
            'signername', 'signatureconsent'], $errors);
        $this->assertSame(get_string('commitrequired', 'mod_these'), $errors['commitguide']);
    }

    /**
     * De trois à cinq mots-clés, séparés par des points-virgules ou des virgules.
     */
    public function test_keywords_count(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();
        $expected = ['keywords' => get_string('keywordscount', 'mod_these')];

        $this->assertSame($expected, $form->validation(['keywords' => 'furet ; Carré'] + $this->complete(), []));
        $this->assertSame($expected, $form->validation(['keywords' => 'a;b;c;d;e;f'] + $this->complete(), []));
        // Les éléments vides ne comptent pas.
        $this->assertSame($expected, $form->validation(['keywords' => 'furet ; ; Carré ;'] + $this->complete(), []));
        $this->assertSame([], $form->validation(['keywords' => 'a, b, c'] + $this->complete(), []));
        $this->assertSame([], $form->validation(['keywords' => 'a;b;c;d;e'] + $this->complete(), []));
    }

    /**
     * Directeur extérieur : ses coordonnées complètes, une adresse valide, et un examinateur interne ;
     * le directeur interne n'est alors plus exigé.
     */
    public function test_external_director(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();
        $external = ['externaldirector' => 1, 'directorid' => 0, 'examinerid' => $this->director->id,
            'extdirectorname' => 'Jean Dupont', 'extdirectorgrade' => 'Professeur', 'extdirectorinstitution' => 'ENVA',
            'extdirectoremail' => 'jean.dupont@example.org'] + $this->complete();

        $this->assertSame([], $form->validation($external, []));
        $this->assert_error_fields(['examinerid'], $form->validation(['examinerid' => 0] + $external, []));
        $this->assertSame(
            ['extdirectoremail' => get_string('invalidemail')],
            $form->validation(['extdirectoremail' => 'jean.dupont'] + $external, [])
        );
        $errors = $form->validation(['extdirectorname' => '', 'extdirectorgrade' => ' ', 'extdirectorinstitution' => '',
            'extdirectoremail' => ''] + $external, []);
        $this->assert_error_fields(['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'], $errors);
        $this->assertSame(get_string('required'), $errors['extdirectoremail']);
    }

    /**
     * Changement de sujet : le motif est obligatoire à la soumission.
     */
    public function test_subject_change_reason(): void {
        $this->setup_form_page($this->student);
        $form = $this->form(['subjectchange' => 1, 'allowdraft' => false]);

        $this->assertSame(
            ['changereason' => get_string('required')],
            $form->validation(['changereason' => ' '] + $this->complete(), [])
        );
        $this->assertSame([], $form->validation(['changereason' => 'Nouveau terrain'] + $this->complete(), []));
        // Hors changement de sujet, pas de motif demandé.
        $this->assertSame([], $this->form()->validation($this->complete(), []));
    }
}
