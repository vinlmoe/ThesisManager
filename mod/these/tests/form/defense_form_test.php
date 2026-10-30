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

use mod_these\local\bordereau;
use mod_these\local\doctemplates;
use mod_these\local\documentrenderer;
use mod_these\local\grid;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/form_testcase.php');

/**
 * Validation du dépôt de la soutenance par l'étudiant : brouillon libre ; à la soumission,
 * manuscrit, date (à venir, après le délai minimal), grille de conformité, composition du jury,
 * identité et bordereau, conclusions, embargo motivé, questionnaire et signature.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\defense_form
 * @covers     \mod_these\local\jury
 * @covers     \mod_these\local\juryform
 */
final class defense_form_test extends form_testcase {
    /**
     * Formulaire du dépôt de la soutenance.
     *
     * @param array $customdata Données à surcharger.
     * @return defense_form
     */
    private function form(array $customdata = []): defense_form {
        return new defense_form(null, $customdata + [
            'minjury' => $this->these->minjury,
            'these' => $this->these,
            'context' => $this->context,
            'submitlabel' => 'submitdefense',
            'signername' => fullname($this->student),
            'jurycount' => 4,
        ]);
    }

    /**
     * Dépôt complet, soumis.
     *
     * @return array
     */
    private function complete(): array {
        $data = [
            'submitdefense' => 1,
            'manuscript' => $this->draft('manuscrit.pdf'),
            'proposeddate' => time() + 60 * DAYSECS,
            'gridconfirm' => 1,
            'bbirthname' => 'Durand',
            'bfirstnames' => 'Hélène Marie',
            'bnationality' => 'française',
            'bstreet' => '1 avenue Bourgelat',
            'bpostcode' => '69280',
            'bcity' => 'Marcy-l\'Étoile',
            'bcountry' => 'France',
            'bpersonalemail' => 'helene.durand@example.org',
            'bnbpages' => 120,
            'bnbrefs' => 80,
            'bsummaryfr' => 'Résumé en français.',
            'bsummaryen' => 'English abstract.',
            'titleen' => 'Canine distemper in ferrets',
            'conclusions' => 'Le furet est sensible au virus.',
            'bdiffusion' => 'internet',
            'bkeyword' => ['furet', '', ''],
            'bembargo' => 0,
            'bembargoreason' => '',
        ];
        foreach (grid::get_grid_items(doctemplates::settings_scope($this->these)) as $items) {
            foreach (array_keys($items) as $key) {
                $data['grid_' . $key] = 1;
            }
        }
        return $data + $this->jury_data() + $this->signature_data();
    }

    /**
     * Message d'erreur du jury, tel qu'affiché en tête de sa rubrique.
     *
     * @param string $identifier Chaîne de mod_these.
     * @param mixed $a
     * @return array
     */
    private function jury_error(string $identifier, $a = null): array {
        return ['juryhelp' => get_string('juryproblem', 'mod_these', get_string($identifier, 'mod_these', $a))];
    }

    /**
     * Un brouillon n'est pas contrôlé ; le dépôt complet est accepté.
     */
    public function test_draft_and_complete(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();

        $this->assertSame([], $form->validation(['savedraft' => 1, 'manuscript' => 0], []));
        $this->assertSame([], $form->validation($this->complete(), []));
    }

    /**
     * Soumission vide : chaque contrôle signale son champ.
     */
    public function test_required_fields(): void {
        $this->setup_form_page($this->student);
        $errors = $this->form()->validation(['submitdefense' => 1, 'manuscript' => $this->draft(null)], []);

        $expected = ['manuscript', 'proposeddate', 'gridconfirm', 'juryhelp', 'bbirthname', 'bfirstnames', 'bnationality',
            'bstreet', 'bpostcode', 'bcity', 'bcountry', 'bpersonalemail', 'bnbpages', 'bnbrefs', 'bsummaryfr', 'titleen',
            'conclusions', 'bdiffusion', 'bkeywordgroup', 'signername', 'signatureconsent'];
        foreach (grid::get_grid_items(doctemplates::settings_scope($this->these)) as $items) {
            foreach ($items as $key => $item) {
                if (!$item->conditional) {
                    $expected[] = 'grid_' . $key;
                }
            }
        }
        $this->assert_error_fields($expected, $errors);
        $this->assertSame(get_string('manuscriptrequired', 'mod_these'), $errors['manuscript']);
        $this->assertSame(get_string('proposeddaterequired', 'mod_these'), $errors['proposeddate']);
        $this->assertSame(get_string('gridstudentconfirmrequired', 'mod_these'), $errors['gridconfirm']);
    }

    /**
     * Date proposée : à venir, et pas avant la date au plus tôt (délai minimal après le dépôt).
     */
    public function test_proposed_date(): void {
        $this->setup_form_page($this->student);
        $earliest = time() + 28 * DAYSECS;
        $customdata = ['mindelayweeks' => 4, 'earliestdate' => $earliest];
        $form = $this->form($customdata);

        $this->assertSame(
            ['proposeddate' => get_string('datemustbefuture', 'mod_these')],
            $form->validation(['proposeddate' => time() - MINSECS] + $this->complete(), [])
        );
        $this->assertSame(
            ['proposeddate' => defense_form::too_soon_error($customdata)],
            $form->validation(['proposeddate' => $earliest - MINSECS] + $this->complete(), [])
        );
        $this->assertSame([], $form->validation(['proposeddate' => $earliest] + $this->complete(), []));
    }

    /**
     * Éléments conditionnels de la grille : facultatifs ; les autres sont exigés un à un.
     */
    public function test_grid_items(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();
        $data = $this->complete();
        $required = null;
        foreach (grid::get_grid_items(doctemplates::settings_scope($this->these)) as $items) {
            foreach ($items as $key => $item) {
                if ($item->conditional) {
                    $data['grid_' . $key] = 0;
                } else if ($required === null) {
                    $required = 'grid_' . $key;
                }
            }
        }
        $this->assertSame([], $form->validation($data, []));
        $this->assertNotNull($required);
        $this->assertSame([$required => get_string('gridrequired', 'mod_these')], $form->validation([$required => 0] + $data, []));
    }

    /**
     * Bordereau : nombres strictement positifs, adresse valide, résumés limités, au moins un mot-clé.
     */
    public function test_bordereau_fields(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();

        $this->assertSame(['bnbpages' => get_string('required')], $form->validation(['bnbpages' => 0] + $this->complete(), []));
        $this->assertSame(['bnbrefs' => get_string('required')], $form->validation(['bnbrefs' => -3] + $this->complete(), []));
        $this->assertSame(
            ['bpersonalemail' => get_string('invalidemail')],
            $form->validation(['bpersonalemail' => 'helene@'] + $this->complete(), [])
        );
        $this->assertSame(
            ['bkeywordgroup' => get_string('required')],
            $form->validation(['bkeyword' => [' ', '']] + $this->complete(), [])
        );

        // Résumés : la limite se compte en caractères, pas en octets.
        $max = bordereau::BORDEREAU_SUMMARY_MAX;
        $this->assertSame([], $form->validation(['bsummaryfr' => str_repeat('é', $max)] + $this->complete(), []));
        $toolong = get_string('summarytoolong', 'mod_these', $max);
        $this->assertSame(
            ['bsummaryfr' => $toolong],
            $form->validation(['bsummaryfr' => str_repeat('é', $max + 1)] + $this->complete(), [])
        );
        $this->assertSame(
            ['bsummaryen' => $toolong],
            $form->validation(['bsummaryen' => str_repeat('a', $max + 1)] + $this->complete(), [])
        );
    }

    /**
     * Conclusions : longueur maximale.
     */
    public function test_conclusions_too_long(): void {
        $this->setup_form_page($this->student);
        $text = str_repeat('a', documentrenderer::CONCLUSIONS_MAX + 1);
        $errors = $this->form()->validation(['conclusions' => $text] + $this->complete(), []);

        $this->assertSame(['conclusions' => get_string('conclusionstoolong', 'mod_these', (object) [
            'max' => documentrenderer::CONCLUSIONS_MAX, 'length' => documentrenderer::CONCLUSIONS_MAX + 1])], $errors);
    }

    /**
     * Embargo : motif obligatoire quand il est demandé.
     */
    public function test_embargo_reason(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();
        $embargo = ['bembargo' => 1, 'bembargomonths' => 6];

        $this->assertSame(
            ['bembargoreason' => get_string('bembargoreasonrequired', 'mod_these')],
            $form->validation($embargo + ['bembargoreason' => '  '] + $this->complete(), [])
        );
        $this->assertSame([], $form->validation($embargo + ['bembargoreason' => 'Brevet en cours'] + $this->complete(), []));
        // Un motif sans embargo demandé est ignoré.
        $this->assertSame([], $form->validation(['bembargoreason' => 'Brevet'] + $this->complete(), []));
    }

    /**
     * Composition du jury : un président éligible, un directeur, assez de membres votants, un statut
     * pour chaque membre extérieur votant, des adresses valides, pas deux fois le même enseignant.
     */
    public function test_jury_composition(): void {
        $this->setup_form_page($this->student);
        $form = $this->form();
        $check = function (array $members) use ($form): array {
            return $form->validation($this->jury_data($members) + $this->complete(), []);
        };

        $this->assertSame($this->jury_error('juryonepresident'), $check($this->jury_with(0, ['juryrole' => 'member'])));
        $this->assertSame($this->jury_error('juryonedirector'), $check($this->jury_with(1, ['juryrole' => 'guest'])));
        // Le directeur et l'invité ne votent pas : sans le membre, il ne reste qu'un votant pour deux exigés.
        $members = $this->generator->default_jury();
        unset($members[2]);
        $this->assertSame($this->jury_error('juryminmembers', 2), $check(array_values($members)));
        // Un statut de maître de conférences ne permet pas de présider.
        $this->assertSame(
            $this->jury_error('jurypresidentnoteligible', 'Paul Président'),
            $check($this->jury_with(0, ['jurystatus' => 'mcf']))
        );
        $this->assertSame(
            $this->jury_error('jurystatusrequired', 'Albert Membre'),
            $check($this->jury_with(2, ['jurystatus' => '']))
        );
        // Statut inconnu : traité comme absent.
        $this->assertSame(
            $this->jury_error('jurystatusrequired', 'Albert Membre'),
            $check($this->jury_with(2, ['jurystatus' => 'inconnu']))
        );
        $this->assertSame(
            $this->jury_error('juryinvalidemail', 'Iris Invitée'),
            $check($this->jury_with(3, ['email' => 'iris@']))
        );

        // Même enseignant de l'annuaire sur deux lignes.
        $jurorid = \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => 'Annuaire', 'firstname' => 'Anne',
            'email' => 'anne@example.org', 'status' => 'pr', 'institution' => 'ENVT', 'active' => 1]);
        $data = $this->jury_data();
        $data['jurorid'][0] = $jurorid;
        $data['jurorid'][2] = $jurorid;
        $errors = $form->validation($data + $this->complete(), []);
        $this->assertSame(['juryhelp'], array_keys($errors));
        $this->assertStringContainsString(
            strstr(get_string('juryduplicate', 'mod_these', '#'), '#', true),
            $errors['juryhelp']
        );
    }

    /**
     * Le président et le directeur ne peuvent occuper que leurs lignes : plus loin, ils comptent
     * comme membres.
     */
    public function test_jury_fixed_roles(): void {
        $this->setup_form_page($this->student);
        $data = $this->jury_data();
        $data['juryrole'][2] = 'president';
        $this->assertSame([], $this->form()->validation($data + $this->complete(), []));
    }

    /**
     * Questionnaire préalable au jury : ses questions obligatoires conditionnent la soumission.
     */
    public function test_questionnaire(): void {
        $this->setup_form_page($this->student);
        $scope = these_settings_scope($this->these);
        $question = these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 1]);
        $form = $this->form(['questions' => these_get_questions($scope)]);

        $this->assertSame(['q' . $question => get_string('required')], $form->validation($this->complete(), []));
        $this->assertSame([], $form->validation(['q' . $question => 'Virologie'] + $this->complete(), []));
    }
}
