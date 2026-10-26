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
use mod_these\form\griditems_form;

/**
 * Page des éléments de la grille de conformité d'une portée de paramètres (activité « Gestion des
 * thèses » non suivie, ou activité « Suivi des thèses » pour toutes les promotions suivies).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class griditems_page {
    /**
     * Traite le formulaire et affiche la page.
     *
     * @param \stdClass $scope
     * @param moodle_url $pageurl Adresse de la page (avec ses paramètres d'accès).
     * @param moodle_url $previewurl Aperçu PDF de la grille.
     * @param moodle_url $cancelurl Retour.
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(
        \stdClass $scope,
        moodle_url $pageurl,
        moodle_url $previewurl,
        moodle_url $cancelurl,
        $navigation = ''
    ) {
        global $DB, $OUTPUT, $PAGE;

        $items = [];
        foreach (these_get_grid_items($scope) as $sectionitems) {
            foreach ($sectionitems as $item) {
                $items[] = $item;
            }
        }
        $mform = new griditems_form($pageurl, ['count' => count($items)]);
        $formdata = ['id' => $pageurl->get_param('id'), 'itemkey' => [], 'section' => [], 'label' => [], 'conditional' => []];
        foreach ($items as $i => $item) {
            $formdata['itemkey'][$i] = $item->itemkey;
            $formdata['section'][$i] = $item->section;
            $formdata['label'][$i] = $item->label;
            $formdata['conditional'][$i] = $item->conditional;
        }
        $mform->set_data($formdata);

        if ($mform->is_cancelled()) {
            redirect($cancelurl);
        } else if ($data = $mform->get_data()) {
            if (!empty($data->resetbutton)) {
                $DB->delete_records('these_griditem', these_scope_conditions($scope));
            } else {
                $submitted = [];
                foreach ((array) $data->label as $i => $label) {
                    $submitted[] = (object) [
                        'itemkey' => $data->itemkey[$i] ?? '',
                        'section' => $data->section[$i] ?? 'forme',
                        'label' => $label,
                        'conditional' => $data->conditional[$i] ?? 0,
                    ];
                }
                these_save_grid_items($scope, $submitted);
            }
            redirect($pageurl, get_string('griditemssaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        $renderer = $PAGE->get_renderer('mod_these');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('griditems', 'mod_these'));
        echo $navigation;
        $intro = get_string($scope->shared ? 'griditemsshared_desc' : 'griditems_desc', 'mod_these');
        echo $renderer->render(output\message::help($intro));
        $preview = new output\link($previewurl, get_string('doctplpreview', 'mod_these'), 'btn btn-secondary', '_blank', 'mb-3');
        echo $renderer->render($preview);
        $mform->display();
        echo $OUTPUT->footer();
    }
}
