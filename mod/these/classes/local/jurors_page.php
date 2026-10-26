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

namespace mod_these\local;

use mod_these\output;
use moodle_url;
use mod_these\form\juror_form;
use mod_these\form\juror_import_form;

/**
 * Page de tenue de l'annuaire du jury, commune à l'activité « Gestion des thèses » (DEVE) et à
 * l'activité « Suivi des thèses » : liste, ajout, modification, suppression, import CSV. Chaque
 * page vérifie ses propres droits et prépare $PAGE avant d'appeler output().
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jurors_page {
    /**
     * Traite l'action demandée et affiche la page.
     *
     * @param moodle_url $baseurl Adresse de la page (avec ses paramètres d'accès).
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(moodle_url $baseurl, $navigation = '') {
        global $OUTPUT, $PAGE;

        $action = optional_param('action', '', PARAM_ALPHA);
        $jurorid = optional_param('jurorid', 0, PARAM_INT);

        if ($action === 'delete' && $jurorid) {
            $juror = jurors::get($jurorid);
            if ($juror && optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
                jurors::delete($jurorid);
                redirect($baseurl, get_string('jurordeleted', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            echo $OUTPUT->header();
            if ($juror) {
                echo $OUTPUT->confirm(
                    get_string('jurordeleteconfirm', 'mod_these', s(jurors::name($juror))),
                    new moodle_url($baseurl, ['action' => 'delete', 'jurorid' => $jurorid, 'confirm' => 1, 'sesskey' => sesskey()]),
                    $baseurl
                );
            }
            echo $OUTPUT->footer();
            exit;
        }

        if ($action === 'prefill') {
            if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
                $count = jurors::prefill_from_course($PAGE->context);
                redirect(
                    $baseurl,
                    get_string('jurorprefilldone', 'mod_these', $count),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            $missing = jurors::course_teachers_missing($PAGE->context);
            if (!$missing) {
                redirect($baseurl, get_string('jurorprefillnone', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('jurorprefill', 'mod_these'));
            echo $navigation;
            echo $OUTPUT->confirm(
                get_string('jurorprefillconfirm', 'mod_these', count($missing))
                    . $PAGE->get_renderer('mod_these')->render(new output\bullet_list(array_map('fullname', $missing))),
                new moodle_url($baseurl, ['action' => 'prefill', 'confirm' => 1, 'sesskey' => sesskey()]),
                $baseurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        if ($action === 'edit') {
            $form = new juror_form(new moodle_url($baseurl, ['action' => 'edit', 'jurorid' => $jurorid]));
            if ($form->is_cancelled()) {
                redirect($baseurl);
            }
            $juror = jurors::get($jurorid);
            $institution = (string) scopesettings::for_context($PAGE->context)['institution'];
            $form->set_data(($juror ? (array) $juror : ['institution' => $institution])
                + ['jurorid' => $jurorid, 'action' => 'edit']);
            if ($data = $form->get_data()) {
                $data->id = $data->jurorid;
                jurors::save($data);
                redirect($baseurl, get_string('jurorsaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string($juror ? 'juroredit' : 'juroradd', 'mod_these'));
            echo $navigation;
            $form->display();
            echo $OUTPUT->footer();
            exit;
        }

        $importform = new juror_import_form(new moodle_url($baseurl));
        $result = null;
        if ($data = $importform->get_data()) {
            $result = jurors::import_csv($importform->get_file_content('csvfile'));
        }

        $renderer = $PAGE->get_renderer('mod_these');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('jurors', 'mod_these'));
        echo $navigation;
        echo $renderer->render(new output\message(get_string('jurors_desc', 'mod_these'), 'mb-3'));
        if ($result) {
            echo $OUTPUT->notification(get_string('jurorimportdone', 'mod_these', $result), 'success');
            if ($result->errors) {
                echo $OUTPUT->notification($renderer->render(new output\bullet_list($result->errors)), 'warning');
            }
        }
        echo $renderer->render(new output\single_button_block(
            new moodle_url($baseurl, ['action' => 'edit']),
            get_string('juroradd', 'mod_these'),
            'get'
        ));
        echo $renderer->render(new output\single_button_block(
            new moodle_url($baseurl, ['action' => 'prefill']),
            get_string('jurorprefill', 'mod_these'),
            'get'
        ));

        $list = new output\juror_list(jurors::get_all(), $baseurl);
        if (!$list->is_empty()) {
            echo $renderer->render($list);
        } else {
            echo $OUTPUT->notification(get_string('jurorsnone', 'mod_these'), 'info');
        }
        echo $OUTPUT->heading(get_string('jurorimport', 'mod_these'), 3, 'mt-4');
        $importform->display();
        echo $OUTPUT->footer();
    }
}
