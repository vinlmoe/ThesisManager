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

use mod_these\local\settingstransfer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/form_testcase.php');

/**
 * Validation des formulaires de paramétrage par la DEVE : paramètres du circuit, questions du
 * questionnaire et réponses, fiche de l'annuaire du jury, modèles de documents, transfert des
 * paramètres entre activités.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\form\scopesettings_form
 * @covers     \mod_these\form\question_form
 * @covers     \mod_these\form\questionnaire_form
 * @covers     \mod_these\form\juror_form
 * @covers     \mod_these\form\doctemplate_form
 * @covers     \mod_these\form\settings_transfer_form
 */
final class settings_forms_test extends form_testcase {
    /**
     * Paramètres du circuit : durée d'une soutenance d'au moins une minute, délai minimal positif ou nul.
     */
    public function test_scopesettings_form(): void {
        $this->setup_form_page();
        $form = new scopesettings_form(null, ['fileoptions' => ['maxfiles' => 1], 'agents' => []]);

        $this->assertSame([], $form->validation(['defenseduration' => 60, 'mindelayweeks' => 4], []));
        $this->assertSame([], $form->validation(['defenseduration' => 1, 'mindelayweeks' => 0], []));
        $this->assertSame(
            ['defenseduration' => get_string('settingsdefensedurationinvalid', 'mod_these')],
            $form->validation(['defenseduration' => 0, 'mindelayweeks' => 4], [])
        );
        $this->assertSame(
            ['mindelayweeks' => get_string('settingsmindelayweeksinvalid', 'mod_these')],
            $form->validation(['defenseduration' => 60, 'mindelayweeks' => -1], [])
        );
        $this->assert_error_fields(['defenseduration'], $form->validation([], []));
    }

    /**
     * Question : libellé obligatoire ; une liste ou un QCM propose au moins deux choix distincts.
     */
    public function test_question_form(): void {
        $this->setup_form_page();
        $form = new question_form();

        $this->assertSame([], $form->validation(['qtype' => 'text', 'label' => 'Laboratoire', 'options' => ''], []));
        $this->assert_error_fields(['label'], $form->validation(['qtype' => 'textarea', 'label' => '  ', 'options' => ''], []));
        foreach (['list', 'multi'] as $qtype) {
            $this->assertSame(
                ['options' => get_string('questionoptionsrequired', 'mod_these')],
                $form->validation(['qtype' => $qtype, 'label' => 'Espèce', 'options' => "Chien\n\n"], []),
                $qtype
            );
            // Doublons et lignes vides ne comptent pas.
            $this->assert_error_fields(
                ['options'],
                $form->validation(['qtype' => $qtype, 'label' => 'Espèce', 'options' => "Chien\n Chien \n"], [])
            );
            $this->assertSame([], $form->validation(['qtype' => $qtype, 'label' => 'Espèce', 'options' => "Chien\r\nChat"], []));
        }
    }

    /**
     * Questionnaire : chaque question obligatoire reçoit une réponse ; pour un QCM, au moins une case.
     */
    public function test_questionnaire_form(): void {
        $this->setup_form_page($this->student);
        $scope = these_settings_scope($this->these);
        $text = these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 1]);
        $multi = these_save_question($scope, (object) ['qtype' => 'multi', 'label' => 'Espèces', 'options' => "Chien\nChat",
            'required' => 1]);
        $optional = these_save_question($scope, (object) ['qtype' => 'list', 'label' => 'Option', 'options' => "A\nB",
            'required' => 0]);
        $form = new questionnaire_form(null, ['questions' => these_get_questions($scope)]);

        $errors = $form->validation(['q' . $text => ' ', 'q' . $multi . '_0' => 0, 'q' . $multi . '_1' => 0], []);
        $this->assert_error_fields(['q' . $text, 'q' . $multi], $errors);
        $this->assertSame(get_string('required'), $errors['q' . $text]);
        $this->assertSame([], $form->validation(['q' . $text => 'Virologie', 'q' . $multi . '_1' => 1, 'q' . $optional => ''], []));
    }

    /**
     * Fiche de l'annuaire : adresse facultative, mais valide si elle est donnée.
     */
    public function test_juror_form(): void {
        $this->setup_form_page();
        $form = new juror_form();
        $data = ['title' => 'Pr', 'lastname' => 'Martin', 'firstname' => 'Anne', 'status' => 'pr'];

        $this->assertSame([], $form->validation($data + ['email' => ''], []));
        $this->assertSame([], $form->validation($data + ['email' => 'anne.martin@example.org'], []));
        $this->assertSame(['email' => get_string('invalidemail')], $form->validation($data + ['email' => 'anne.martin@'], []));
    }

    /**
     * Modèle de document : il doit produire un document avec les données d'exemple.
     */
    public function test_doctemplate_form(): void {
        $this->setup_form_page();
        $scope = these_local_scope($this->these);
        $form = new doctemplate_form(null, ['scope' => $scope, 'activity' => $this->these]);

        $this->assertSame([], $form->validation(['type' => 'grid',
            'content' => '<p>{{#student}}{{lastname}}{{/student}}</p>'], []));
        $errors = $form->validation(['type' => 'grid', 'content' => '<p>{{#student}}sans fin</p>'], []);
        $this->assert_error_fields(['content'], $errors);
        $this->assertStringStartsWith(
            strstr(get_string('doctplerror', 'mod_these', '#'), '#', true),
            $errors['content']
        );
    }

    /**
     * Transfert des paramètres, export : au moins un groupe choisi.
     */
    public function test_settings_transfer_form_export(): void {
        $this->setup_form_page();
        $form = new settings_transfer_form(null, ['mode' => 'export', 'sources' => []]);

        $this->assertSame([], $form->validation(['exportemails' => 1], []));
        $none = array_fill_keys(array_map(function ($group) {
            return 'export' . $group;
        }, settingstransfer::settings_groups()), 0);
        $this->assertSame(['exportgroups' => get_string('transfernogroup', 'mod_these')], $form->validation($none, []));
        // Les cases de l'import (sans préfixe) ne comptent pas pour l'export.
        $this->assert_error_fields(['exportgroups'], $form->validation(['emails' => 1], []));
    }

    /**
     * Transfert des paramètres, import : un groupe, la confirmation du remplacement, et une source
     * lisible (activité proposée, ou fichier JSON au bon format).
     */
    public function test_settings_transfer_form_import(): void {
        $this->setup_form_page();
        $sourceid = 4242;
        $form = new settings_transfer_form(null, ['mode' => 'import', 'sources' => [$sourceid => 'Autre activité']]);
        $base = ['emails' => 1, 'confirmreplace' => 1];

        $this->assertSame([], $form->validation($base + ['source' => 'activity', 'sourceid' => $sourceid], []));
        $this->assert_error_fields(['sourceid'], $form->validation($base + ['source' => 'activity', 'sourceid' => 1], []));
        $this->assertSame(
            ['confirmreplace' => get_string('transferconfirmrequired', 'mod_these')],
            $form->validation(['confirmreplace' => 0] + $base + ['source' => 'activity', 'sourceid' => $sourceid], [])
        );
        $this->assert_error_fields(['groups'], $form->validation(['emails' => 0] + $base + ['source' => 'activity',
            'sourceid' => $sourceid], []));

        // Fichier : absent, illisible, puis valide.
        $this->assertSame(
            ['settingsfile' => get_string('required')],
            $form->validation($base + ['source' => 'file', 'settingsfile' => $this->draft(null)], [])
        );
        $this->assertSame(
            ['settingsfile' => get_string('transferbadfile', 'mod_these')],
            $form->validation($base + ['source' => 'file', 'settingsfile' => $this->draft('p.json', '{"format":"autre"}')], [])
        );
        $this->assertSame(
            ['settingsfile' => get_string('transferbadfile', 'mod_these')],
            $form->validation($base + ['source' => 'file', 'settingsfile' => $this->draft('p.json', 'pas du JSON')], [])
        );
        $json = json_encode(settingstransfer::export_settings($this->these, $this->context, ['emails']));
        $this->assertSame([], $form->validation($base + ['source' => 'file', 'settingsfile' => $this->draft('p.json', $json)], []));
    }
}
