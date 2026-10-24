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

use html_writer;
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
        global $OUTPUT;

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

        if ($action === 'edit') {
            $form = new juror_form(new moodle_url($baseurl, ['action' => 'edit', 'jurorid' => $jurorid]));
            if ($form->is_cancelled()) {
                redirect($baseurl);
            }
            $juror = jurors::get($jurorid);
            $form->set_data(($juror ? (array) $juror : ['institution' => (string) get_config('mod_these', 'institution')])
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

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('jurors', 'mod_these'));
        echo $navigation;
        echo html_writer::div(get_string('jurors_desc', 'mod_these'), 'mb-3');
        if ($result) {
            echo $OUTPUT->notification(get_string('jurorimportdone', 'mod_these', $result), 'success');
            if ($result->errors) {
                echo $OUTPUT->notification(html_writer::alist(array_map('s', $result->errors)), 'warning');
            }
        }
        echo html_writer::div($OUTPUT->single_button(
            new moodle_url($baseurl, ['action' => 'edit']),
            get_string('juroradd', 'mod_these'),
            'get'
        ), 'mb-3');

        $table = new \html_table();
        $table->head = [
            get_string('juryname', 'mod_these'),
            get_string('jurystatus', 'mod_these'),
            get_string('jurorpresident', 'mod_these'),
            get_string('juryinstitution', 'mod_these'),
            get_string('email'),
            get_string('status'),
            get_string('actions'),
        ];
        $table->attributes['class'] = 'generaltable these-jurors';
        foreach (jurors::get_all() as $juror) {
            $table->data[] = [
                s(jurors::name($juror)),
                s(jurors::status_label($juror->status)),
                jurors::can_preside($juror->status) ? get_string('yes') : get_string('no'),
                s($juror->institution),
                s($juror->email),
                $juror->active ? get_string('juroractiveyes', 'mod_these') : get_string('juroractiveno', 'mod_these'),
                html_writer::link(new moodle_url($baseurl, ['action' => 'edit', 'jurorid' => $juror->id]), get_string('edit'))
                    . ' | ' . html_writer::link(
                        new moodle_url($baseurl, ['action' => 'delete', 'jurorid' => $juror->id]),
                        get_string('delete')
                    ),
            ];
        }
        if ($table->data) {
            echo html_writer::table($table);
        } else {
            echo $OUTPUT->notification(get_string('jurorsnone', 'mod_these'), 'info');
        }
        echo $OUTPUT->heading(get_string('jurorimport', 'mod_these'), 3, 'mt-4');
        $importform->display();
        echo $OUTPUT->footer();
    }
}
