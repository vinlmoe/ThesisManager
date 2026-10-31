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

use mod_these\local\grid;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/form_testcase.php');

/**
 * Validation de l'examen d'une étape du circuit : renvoi motivé vers un destinataire prévu ;
 * contrôles propres à chaque étape à la validation (référence de la bibliothèque, rapport et grille
 * du directeur, numéro, lieu, DEFV et éligibilité du jury pour la DEVE, conformité) ; signature.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\review_form
 * @covers     \mod_these\local\jury
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\review_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\local\jury::class)]
final class review_form_test extends form_testcase {
    /**
     * Formulaire d'examen d'une étape.
     *
     * @param string $stepkey
     * @param array $customdata Données à surcharger.
     * @return review_form
     */
    private function form(string $stepkey, array $customdata = []): review_form {
        return new review_form(null, $customdata + [
            'stepkey' => $stepkey,
            'returnto' => ['student', 'director'],
            'theseid' => $this->these->id,
            'scope' => these_settings_scope($this->these),
            'minjury' => $this->these->minjury,
            'jurycount' => 4,
            'signername' => fullname($this->deve),
            'signs' => '',
        ]);
    }

    /**
     * Renvoi : un commentaire et un destinataire prévu par l'étape ; les contrôles de la validation ne
     * s'appliquent pas.
     */
    public function test_return(): void {
        $this->setup_form_page();
        $form = $this->form('librarysubject');

        $return = ['returndossier' => 1, 'remark' => 'À reprendre', 'returntarget' => 'director'];
        $this->assertSame([], $form->validation($return, []));
        $this->assertSame(
            ['remark' => get_string('returncommentrequired', 'mod_these')],
            $form->validation(['returndossier' => 1, 'remark' => ' ', 'returntarget' => 'student'], [])
        );
        $this->assertSame(
            ['returntarget' => get_string('required')],
            $form->validation(['returndossier' => 1, 'remark' => 'À reprendre', 'returntarget' => 'deve'], [])
        );
        // Ni validation ni renvoi (bouton absent) : rien à contrôler.
        $this->assertSame([], $form->validation(['remark' => ''], []));
    }

    /**
     * Enregistrement du sujet par la bibliothèque : sa référence est obligatoire.
     */
    public function test_library_subject(): void {
        $this->setup_form_page($this->librarian);
        $form = $this->form('librarysubject');

        $this->assertSame(
            ['libraryref' => get_string('required')],
            $form->validation(['validatestep' => 1, 'libraryref' => ' '], [])
        );
        $this->assertSame([], $form->validation(['validatestep' => 1, 'libraryref' => 'BU-2026-12'], []));
    }

    /**
     * Validation du dépôt par le directeur : rapport déposé et favorable, fonction (directeur interne
     * seulement), grille de conformité, signature.
     */
    public function test_director_defense(): void {
        $this->setup_form_page($this->director);
        $favorable = (object) ['deposited' => 1, 'favorable' => 1, 'url' => new \moodle_url('/mod/these/report.php')];
        $data = ['validatestep' => 1, 'directorfunction' => 'Professeur'] + $this->signature_data();
        $first = null;
        foreach (grid::get_grid_items(these_settings_scope($this->these)) as $items) {
            foreach ($items as $key => $item) {
                $data['grid_' . $key] = 1;
                if ($first === null && !$item->conditional) {
                    $first = 'grid_' . $key;
                }
            }
        }
        $form = $this->form('directordefense', ['report' => $favorable, 'signs' => 'Grille']);

        $this->assertSame([], $form->validation($data, []));
        $this->assertSame([$first => get_string('gridrequired', 'mod_these')], $form->validation([$first => 0] + $data, []));
        $this->assertSame(
            ['directorfunction' => get_string('required')],
            $form->validation(['directorfunction' => ''] + $data, [])
        );
        $this->assert_error_fields(['signatureconsent'], $form->validation(['signatureconsent' => 0] + $data, []));

        $form = $this->form('directordefense', ['report' => null, 'signs' => 'Grille']);
        $this->assertSame(['reportstatus' => get_string('directorreportrequired', 'mod_these')], $form->validation($data, []));
        $unfavorable = (object) ['deposited' => 1, 'favorable' => 0];
        $form = $this->form('directordefense', ['report' => $unfavorable, 'signs' => 'Grille']);
        $this->assertSame(['reportstatus' => get_string('reportfavorablerequired', 'mod_these')], $form->validation($data, []));

        // Directeur extérieur : l'examinateur interne n'indique pas de fonction.
        $form = $this->form('directordefense', ['report' => $favorable, 'externaldirector' => 1, 'signs' => 'Grille']);
        $this->assertSame([], $form->validation(['directorfunction' => ''] + $data, []));
    }

    /**
     * Validation de la DEVE : numéro unique, DEFV enregistrée, lieu, jury valide et chaque membre
     * votant reconnu éligible.
     */
    public function test_deve_defense(): void {
        $this->setup_form_page();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_DEFENSE_APPROVED]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->create_dossier($this->these, $other->id, ['devenumber' => '2026-007']);
        $form = $this->form('devedefense', ['defvrecorded' => 1]);
        $data = ['validatestep' => 1, 'dossierid' => $dossier->id, 'devenumber' => '2026-008',
            'defenselocation' => 'Amphithéâtre A'] + $this->jury_data();
        // La DEVE a reconnu chaque membre éligible.
        $data['juryeligible'] = array_fill_keys(array_keys($data['juryname']), 1);

        $this->assertSame([], $form->validation($data, []));
        $this->assertSame(['devenumber' => get_string('required')], $form->validation(['devenumber' => ''] + $data, []));
        $this->assertSame(
            ['devenumber' => get_string('devenumbertaken', 'mod_these')],
            $form->validation(['devenumber' => '2026-007'] + $data, [])
        );
        $this->assertSame(['defenselocation' => get_string('required')], $form->validation(['defenselocation' => ' '] + $data, []));

        // Membre extérieur votant dont l'éligibilité n'est pas cochée.
        $unchecked = $data;
        $unchecked['juryeligible'][2] = 0;
        $notchecked = get_string('juryeligiblerequired', 'mod_these', 'Albert Membre');
        $this->assertSame(
            ['juryhelp' => get_string('juryproblem', 'mod_these', $notchecked)],
            $form->validation($unchecked, [])
        );
        // L'invité ne vote pas : son éligibilité n'est pas demandée.
        $guest = $data;
        $guest['juryeligible'][3] = 0;
        $this->assertSame([], $form->validation($guest, []));
        // La composition est contrôlée avant l'éligibilité.
        $nopresident = ['validatestep' => 1, 'dossierid' => $dossier->id, 'devenumber' => '2026-008',
            'defenselocation' => 'Amphithéâtre A'] + $this->jury_data($this->jury_with(0, ['juryrole' => 'member']));
        $this->assertSame(
            ['juryhelp' => get_string('juryproblem', 'mod_these', get_string('juryonepresident', 'mod_these'))],
            $form->validation($nopresident, [])
        );

        // DEFV non enregistrée : la validation est bloquée.
        $form = $this->form('devedefense', ['defvrecorded' => 0]);
        $this->assertSame(['defvstatus' => get_string('defvrequired', 'mod_these')], $form->validation($data, []));
    }

    /**
     * Conformité par la bibliothèque : la case de vérification est obligatoire.
     */
    public function test_library_conformity(): void {
        $this->setup_form_page($this->librarian);
        $form = $this->form('libraryconformity');

        $this->assertSame(
            ['conformitychecked' => get_string('conformitycheckrequired', 'mod_these')],
            $form->validation(['validatestep' => 1, 'conformitychecked' => 0], [])
        );
        $this->assertSame([], $form->validation(['validatestep' => 1, 'conformitychecked' => 1], []));
    }
}
