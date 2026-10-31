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
 * Validation des formulaires de gestion d'un dossier par la DEVE : création, validation de l'A6,
 * demande et proposition d'une nouvelle date, modification du dossier.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\create_form
 * @covers     \mod_these\form\a6_form
 * @covers     \mod_these\form\daterequest_form
 * @covers     \mod_these\form\newdate_form
 * @covers     \mod_these\form\dossier_edit_form
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\create_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\a6_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\daterequest_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\newdate_form::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\form\dossier_edit_form::class)]
final class dossier_forms_test extends form_testcase {
    /**
     * Création : l'étudiant doit être choisi parmi ceux proposés.
     */
    public function test_create_form(): void {
        $this->setup_form_page();
        $form = new create_form(null, ['students' => [$this->student->id => fullname($this->student)]]);

        $this->assertSame([], $form->validation(['userid' => $this->student->id], []));
        $this->assert_error_fields(['userid'], $form->validation(['userid' => 0], []));
        $this->assert_error_fields(['userid'], $form->validation([], []));
        // Un étudiant qui n'est pas proposé (il a déjà un dossier, ou n'est pas inscrit) est refusé.
        $this->assert_error_fields(['userid'], $form->validation(['userid' => $this->director->id], []));
    }

    /**
     * A6 : une date de validation, ou la case « pas encore validée ».
     */
    public function test_a6_form(): void {
        $this->setup_form_page();
        $form = new a6_form(null, ['studentname' => fullname($this->student)]);

        $errors = $form->validation(['aga6notyet' => 0, 'aga6date' => 0], []);
        $this->assertSame(['aga6date' => get_string('a6required', 'mod_these')], $errors);
        $this->assertSame([], $form->validation(['aga6notyet' => 0, 'aga6date' => make_timestamp(2026, 6, 1)], []));
        $this->assertSame([], $form->validation(['aga6notyet' => 1, 'aga6date' => 0], []));
    }

    /**
     * Demande d'une nouvelle date : motif obligatoire, des espaces ne suffisent pas.
     */
    public function test_daterequest_form(): void {
        $this->setup_form_page();
        $form = new daterequest_form(null, ['currentdate' => '3 décembre 2026']);

        $this->assert_error_fields(['reason'], $form->validation([], []));
        $this->assert_error_fields(['reason'], $form->validation(['reason' => "  \n "], []));
        $this->assertSame([], $form->validation(['reason' => 'Salle indisponible'], []));
    }

    /**
     * Nouvelle date proposée par l'étudiant : à venir, différente de la date refusée, après le délai
     * minimal.
     */
    public function test_newdate_form(): void {
        $this->setup_form_page($this->student);
        $refused = time() + 60 * DAYSECS;
        $earliest = time() + 28 * DAYSECS;
        $customdata = ['refuseddate' => $refused, 'mindelayweeks' => 4, 'earliestdate' => $earliest];
        $form = new newdate_form(null, $customdata);

        $this->assertSame(
            ['proposeddate' => get_string('datemustbefuture', 'mod_these')],
            $form->validation(['proposeddate' => time() - HOURSECS], [])
        );
        $this->assertSame(
            ['proposeddate' => get_string('datemustbefuture', 'mod_these')],
            $form->validation([], [])
        );
        $this->assertSame(
            ['proposeddate' => get_string('newdatesame', 'mod_these')],
            $form->validation(['proposeddate' => $refused], [])
        );
        $this->assertSame(
            ['proposeddate' => defense_form::too_soon_error($customdata)],
            $form->validation(['proposeddate' => $earliest - HOURSECS], [])
        );
        $this->assertSame([], $form->validation(['proposeddate' => $earliest], []));
        $this->assertSame([], $form->validation(['proposeddate' => $refused + DAYSECS], []));

        // Sans délai minimal, toute date à venir convient.
        $form = new newdate_form(null, ['refuseddate' => $refused]);
        $this->assertSame([], $form->validation(['proposeddate' => time() + HOURSECS], []));
    }

    /**
     * Modification du dossier : numéro DEVE unique dans l'activité (le sien excepté), jury contrôlé
     * seulement s'il est renseigné.
     */
    public function test_dossier_edit_form(): void {
        $this->setup_form_page();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['devenumber' => '2026-001',
            'status' => THESE_STATUS_ACTIVE, 'directorid' => $this->director->id]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->create_dossier($this->these, $other->id, ['devenumber' => '2026-002']);
        $form = new dossier_edit_form(null, [
            'directors' => [$this->director->id => fullname($this->director)],
            'theseid' => $this->these->id,
            'minjury' => $this->these->minjury,
            'jurycount' => 0,
        ]);
        $base = ['id' => $this->cm->id, 'dossierid' => $dossier->id];

        $this->assertSame([], $form->validation($base + ['devenumber' => ''], []));
        $this->assertSame([], $form->validation($base + ['devenumber' => '2026-001'], []));
        $this->assertSame([], $form->validation($base + ['devenumber' => '2026-003'], []));
        $this->assertSame(
            ['devenumber' => get_string('devenumbertaken', 'mod_these')],
            $form->validation($base + ['devenumber' => ' 2026-002 '], [])
        );

        // Jury complet : valide ; jury renseigné sans président : refusé.
        $this->assertSame([], $form->validation($base + ['devenumber' => ''] + $this->jury_data(), []));
        $nopresident = $this->jury_data($this->jury_with(0, ['juryrole' => 'member']));
        $errors = $form->validation($base + ['devenumber' => ''] + $nopresident, []);
        $this->assertSame(
            ['juryhelp' => get_string('juryproblem', 'mod_these', get_string('juryonepresident', 'mod_these'))],
            $errors
        );
    }
}
