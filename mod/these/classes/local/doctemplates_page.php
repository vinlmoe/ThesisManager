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
use mod_these\form\doctemplate_form;
use mod_these\form\doctemplate_images_form;

/**
 * Page des modèles des documents d'une portée de paramètres (voir these_settings_scope()) :
 * activité « Gestion des thèses » qui n'est suivie par aucune activité de suivi, ou activité
 * « Suivi des thèses », dont les modèles valent pour toutes les promotions suivies. Liste des
 * documents et images, modification, aperçu PDF avec des données fictives, retour à l'origine.
 *
 * Chaque page appelante vérifie ses droits et prépare $PAGE avant d'appeler output().
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doctemplates_page {
    /**
     * Traite l'action demandée et affiche la page.
     *
     * @param \stdClass $scope Portée dont les modèles sont modifiés.
     * @param moodle_url $listurl Adresse de la liste (avec ses paramètres d'accès).
     * @param \stdClass|null $activity Activité dont l'établissement sert aux aperçus.
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(\stdClass $scope, moodle_url $listurl, ?\stdClass $activity, $navigation = '') {
        global $OUTPUT, $PAGE;

        $type = optional_param('type', '', PARAM_ALPHA);
        $action = optional_param('action', '', PARAM_ALPHA);
        $types = these_doctemplate_types();
        if ($type !== '' && !array_key_exists($type, $types)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        $sample = function () use ($scope, $activity, $type) {
            return these_doctemplate_sample_context($scope, $type, $activity);
        };

        // Aperçu du modèle en vigueur.
        if ($type !== '' && $action === 'preview') {
            send_file(
                these_render_scope_doctemplate($scope, $type, $sample()),
                $type . '.pdf',
                0,
                0,
                true,
                false,
                'application/pdf'
            );
        }

        // Retour au modèle d'origine.
        if ($type !== '' && $action === 'reset') {
            if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
                these_set_doctemplate($scope, $type, null);
                redirect(
                    $listurl,
                    get_string('doctplresetdone', 'mod_these', these_doctemplate_label($type)),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('doctplresetconfirm', 'mod_these', these_doctemplate_label($type)),
                new moodle_url($listurl, ['type' => $type, 'action' => 'reset', 'confirm' => 1, 'sesskey' => sesskey()]),
                $listurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        // Modification d'un modèle.
        if ($type !== '') {
            $pageurl = new moodle_url($listurl, ['type' => $type]);
            $mform = new doctemplate_form($pageurl, ['scope' => $scope, 'activity' => $activity]);
            if ($mform->is_cancelled()) {
                redirect($listurl);
            }
            $mform->set_data(['id' => $listurl->get_param('id'), 'type' => $type,
                'content' => these_get_doctemplate($scope, $type)]);
            if ($data = $mform->get_data()) {
                if (!empty($data->previewbutton)) {
                    // Aperçu du modèle en cours de modification, sans l'enregistrer.
                    send_file(
                        these_render_scope_doctemplate($scope, $type, $sample(), $data->content),
                        $type . '.pdf',
                        0,
                        0,
                        true,
                        false,
                        'application/pdf'
                    );
                }
                these_set_doctemplate($scope, $type, $data->content);
                redirect(
                    $listurl,
                    get_string('doctplsaved', 'mod_these', these_doctemplate_label($type)),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            }

            $renderer = $PAGE->get_renderer('mod_these');
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('doctemplates', 'mod_these') . ' : ' . these_doctemplate_label($type));
            echo backlinks::render(backlinks::target($listurl, get_string('doctemplates', 'mod_these')));
            echo $renderer->render(new output\message(get_string('doctplintro', 'mod_these'), 'my-3'));
            $mform->display();

            // Variables disponibles, avec un exemple de valeur.
            echo $OUTPUT->heading(get_string('doctplvariables', 'mod_these'), 3, 'mt-4');
            // La chaîne cite une balise sous forme d'entités (&lt;img ...&gt;) : décodée ici, elle est
            // échappée par le gabarit et s'affiche comme avant.
            $variablesdesc = html_entity_decode(get_string('doctplvariables_desc', 'mod_these'), ENT_QUOTES | ENT_HTML401, 'UTF-8');
            echo $renderer->render(new output\message($variablesdesc, 'mb-2'));
            echo $renderer->render(new output\doctemplate_variables($sample()));
            echo $OUTPUT->footer();
            exit;
        }

        // Images des modèles.
        $imageoptions = ['subdirs' => 0, 'maxfiles' => 20, 'accepted_types' => ['.png', '.jpg', '.jpeg']];
        $imagesform = new doctemplate_images_form($listurl, ['options' => $imageoptions]);
        $draftitemid = file_get_submitted_draft_itemid('docimages');
        file_prepare_draft_area($draftitemid, $scope->contextid, $scope->component, 'docimages', 0, $imageoptions);
        $imagesform->set_data(['id' => $listurl->get_param('id'), 'docimages' => $draftitemid]);
        if ($data = $imagesform->get_data()) {
            file_save_draft_area_files($data->docimages, $scope->contextid, $scope->component, 'docimages', 0, $imageoptions);
            redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        }

        // Liste des modèles.
        $renderer = $PAGE->get_renderer('mod_these');
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('doctemplates', 'mod_these'));
        echo $navigation;
        $intro = get_string($scope->shared ? 'doctemplatesshared_desc' : 'doctemplates_desc', 'mod_these');
        echo $renderer->render(output\message::help($intro));
        echo $renderer->render(new output\doctemplate_list($scope, $listurl));
        echo $OUTPUT->heading(get_string('doctplimages', 'mod_these'), 3, 'mt-4');
        // Images appelées par les modèles d'origine : le module n'en fournit aucune.
        $images = doctemplates::doctemplate_images($scope);
        $slots = [];
        foreach (doctemplates::doctemplate_image_slots() as $slot) {
            $slots[] = get_string('doctplimageslot', 'mod_these', (object) [
                'file' => $slot . '.png',
                'role' => get_string('doctplimageslot' . $slot, 'mod_these'),
                'state' => get_string(isset($images[$slot]) ? 'doctplimagepresent' : 'doctplimagemissing', 'mod_these'),
            ]);
        }
        echo $renderer->render(new output\message(get_string('doctplimageslots', 'mod_these'), 'mb-1'));
        echo $renderer->render(new output\bullet_list($slots));
        $imagesform->display();
        echo $OUTPUT->footer();
    }

    /**
     * Avis affiché dans une activité « Gestion des thèses » suivie par une activité de suivi : ses
     * paramètres partagés se modifient dans cette dernière.
     *
     * @param \stdClass $scope Portée partagée.
     * @param string $what Libellé de ce qui est partagé (modèles, grille, questionnaire).
     * @return string HTML
     */
    public static function shared_notice(\stdClass $scope, $what) {
        global $OUTPUT, $PAGE;

        $url = new moodle_url('/mod/thesesynthesis/view.php', ['id' => $scope->cmid]);
        $link = new output\link($url, format_string($scope->name, true, ['escape' => false]));
        return $OUTPUT->notification(get_string('sharedsettings', 'mod_these', (object) [
            'what' => $what,
            'name' => $PAGE->get_renderer('mod_these')->render($link),
        ]), 'info');
    }
}
