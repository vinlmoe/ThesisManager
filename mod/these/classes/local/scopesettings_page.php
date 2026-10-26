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

use mod_these\form\scopesettings_form;
use mod_these\output;
use moodle_url;
use stdClass;

/**
 * Page des paramètres du circuit d'une portée, commune à l'activité « Suivi des thèses » et à une
 * activité « Gestion des thèses » qu'aucune activité de suivi ne suit. Chaque page vérifie ses
 * propres droits et prépare $PAGE avant d'appeler output(). La DEVE, responsable de l'activité, y
 * règle tout, rôles des services compris.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scopesettings_page {
    /**
     * Enregistre le formulaire envoyé et affiche la page.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param moodle_url $baseurl Adresse de la page (avec son paramètre id).
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(stdClass $scope, moodle_url $baseurl, $navigation = '') {
        global $OUTPUT, $PAGE;

        $roles = [0 => get_string('none')];
        foreach (role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT) as $role) {
            $roles[$role->id] = $role->localname;
        }
        $fileoptions = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']];
        $form = new scopesettings_form($baseurl, ['roles' => $roles, 'fileoptions' => $fileoptions]);

        $draftitemid = file_get_submitted_draft_itemid('contracttemplate');
        file_prepare_draft_area($draftitemid, $scope->contextid, $scope->component, 'contracttemplate', 0, $fileoptions);
        $form->set_data(['id' => $baseurl->get_param('id'), 'contracttemplate' => $draftitemid] + scopesettings::get_all($scope));

        if ($data = $form->get_data()) {
            $values = [];
            foreach (array_keys(scopesettings::defaults()) as $name) {
                if (isset($data->$name)) {
                    $values[$name] = $data->$name;
                }
            }
            scopesettings::set($scope, $values);
            file_save_draft_area_files(
                $data->contracttemplate,
                $scope->contextid,
                $scope->component,
                'contracttemplate',
                0,
                $fileoptions
            );
            redirect($baseurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        $renderer = $PAGE->get_renderer('mod_these');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('scopesettings', 'mod_these'));
        echo $navigation;
        $intro = get_string($scope->shared ? 'scopesettingsshared_desc' : 'scopesettings_desc', 'mod_these');
        echo $renderer->render(output\message::help($intro));
        echo $renderer->render(self::checklist_message($scope));
        $form->display();
        echo $OUTPUT->footer();
    }

    /**
     * Encadré « Réglages à compléter » (voir scopesettings::checklist()) : nombre d'éléments manquants,
     * puis chaque élément, complet ou à compléter, avec le lien vers la page où le régler.
     *
     * @param stdClass $scope
     * @return output\message
     */
    public static function checklist_message(stdClass $scope) {
        global $PAGE;

        $checklist = scopesettings::checklist($scope);
        $missing = count(array_filter($checklist, function ($item) {
            return !$item['complete'] && !$item['optional'];
        }));
        $items = array_map(function ($item) {
            $parts = [$item['url'] ? output\parts::link($item['url'], $item['label'], '') : output\parts::text($item['label'])];
            $parts[] = output\parts::text(' ');
            if ($item['complete']) {
                $parts[] = output\parts::badge(get_string('settingscheckdone', 'mod_these'), 'badge badge-success');
            } else if ($item['optional']) {
                $parts[] = output\parts::badge(get_string('settingscheckoptional', 'mod_these'), 'badge badge-secondary');
            } else {
                $parts[] = output\parts::badge(get_string('settingschecktodo', 'mod_these'), 'badge badge-warning');
            }
            return $parts;
        }, $checklist);
        $heading = $missing
            ? get_string('settingschecktitle', 'mod_these', $missing)
            : get_string('settingscheckcomplete', 'mod_these');
        return new output\message([
            output\parts::strong($heading),
            output\parts::html($PAGE->get_renderer('mod_these')->render(new output\bullet_list($items))),
        ], 'alert ' . ($missing ? 'alert-warning' : 'alert-success') . ' these-settings-checklist');
    }
}
