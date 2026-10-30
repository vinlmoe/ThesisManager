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

use coding_exception;
use context_module;
use core_text;
use stdClass;

/**
 * Modèles des documents : registre des types, modèle d'origine et modèle en vigueur d'une portée,
 * portées des paramètres partagés (activité ou suivi), images des modèles, contrat type.
 *
 * Voir aussi documentcontext (données des modèles) et documentrenderer (production des PDF).
 *
 * Les fonctions these_*() correspondantes de locallib/doctemplates.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doctemplates {
    /**
     * Documents produits à partir d'un modèle modifiable (doctemplates/, page d'administration
     * « Modèles des documents »), dans l'ordre de présentation.
     *
     * @return array type de modèle => document signé dont il reprend les signatures (null : aucun).
     */
    public static function doctemplate_types() {
        return [
            'subject' => 'subject',
            'agreement' => 'agreement',
            'signaletique' => 'signaletique',
            'bordereau' => 'bordereau',
            'grid' => 'grid',
            'conclusions' => 'conclusions',
            'contractdata' => 'contract',
            'contractsign' => 'contract',
            'conformity' => 'conformity',
            'convocation' => null,
            'jurysheet' => null,
            'minutes' => null,
            'planning' => null,
            'teachinglist' => null,
        ];
    }

    /**
     * Libellé d'un modèle de document.
     *
     * @param string $type
     * @return string
     */
    public static function doctemplate_label($type) {
        return get_string('doctpl' . $type, 'mod_these');
    }

    /**
     * Modèle d'origine d'un document, fourni avec le module (doctemplates/<type>.html).
     *
     * @param string $type
     * @return string
     */
    public static function doctemplate_default($type) {
        global $CFG;

        if (!array_key_exists($type, self::doctemplate_types())) {
            throw new coding_exception('Modèle de document inconnu : ' . $type);
        }
        return (string) file_get_contents($CFG->dirroot . '/mod/these/doctemplates/' . $type . '.html');
    }

    /**
     * Portée propre à une activité « Gestion des thèses » : ses propres modèles, images, grille et
     * questionnaire.
     *
     * @param stdClass $these
     * @return stdClass {theseid, synthesisid, shared, component, contextid, cmid, name}
     */
    public static function local_scope(stdClass $these) {
        $cm = !empty($these->id) ? get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING) : null;
        return (object) [
            'theseid' => (int) ($these->id ?? 0),
            'synthesisid' => 0,
            'shared' => false,
            'component' => 'mod_these',
            'contextid' => $cm ? context_module::instance($cm->id)->id : 0,
            'cmid' => $cm ? (int) $cm->id : 0,
            'name' => (string) ($these->name ?? ''),
        ];
    }

    /**
     * Portée des paramètres d'un module de cours : celle de l'activité « Gestion des thèses »
     * elle-même, ou celle qu'un autre module (une activité de suivi) tient pour lui-même (rappel
     * <module>_these_settings_scope_for_cm(), voir settings_scope()).
     *
     * @param stdClass $cm
     * @return stdClass|null Voir local_scope() ; null si le module de cours n'a pas de portée.
     */
    public static function scope_for_cm(stdClass $cm) {
        global $DB;

        if ($cm->modname === 'these') {
            $these = $DB->get_record('these', ['id' => $cm->instance]);
            return $these ? self::local_scope($these) : null;
        }
        $scope = component_callback('mod_' . $cm->modname, 'these_settings_scope_for_cm', [$cm], null);
        return $scope instanceof stdClass ? $scope : null;
    }

    /**
     * Portée des paramètres en vigueur pour une activité : celle qu'un autre module fournit (une
     * activité de suivi de plusieurs promotions, par le rappel <module>_these_settings_scope() de son
     * lib.php : le premier module qui en renvoie une l'emporte), sinon l'activité elle-même. Les
     * modèles des documents, leurs images, la grille de conformité et le questionnaire préalable au
     * jury sont ainsi définis une fois pour toutes les promotions suivies.
     *
     * @param stdClass $these
     * @return stdClass Voir these_local_scope().
     */
    public static function settings_scope(stdClass $these) {
        if (!empty($these->id)) {
            foreach (get_plugins_with_function('these_settings_scope') as $plugins) {
                foreach ($plugins as $function) {
                    $scope = $function($these);
                    if ($scope instanceof stdClass) {
                        return $scope;
                    }
                }
            }
        }
        return self::local_scope($these);
    }

    /**
     * Conditions de base de données désignant une portée.
     *
     * @param stdClass $scope
     * @return array
     */
    public static function scope_conditions(stdClass $scope) {
        return ['theseid' => (int) $scope->theseid, 'synthesisid' => (int) $scope->synthesisid];
    }

    /**
     * Modèle en vigueur d'un document dans une portée : celui modifié par la DEVE, sinon celui
     * d'origine.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param string $type
     * @return string
     */
    public static function get_doctemplate(stdClass $scope, $type) {
        global $DB;

        $custom = $DB->get_field('these_doctemplate', 'content', self::scope_conditions($scope) + ['doctype' => $type]);
        return is_string($custom) && trim($custom) !== '' ? $custom : self::doctemplate_default($type);
    }

    /**
     * Le modèle d'un document a-t-il été modifié dans la portée ?
     *
     * @param stdClass $scope
     * @param string $type
     * @return bool
     */
    public static function doctemplate_customised(stdClass $scope, $type) {
        global $DB;

        return $DB->record_exists('these_doctemplate', self::scope_conditions($scope) + ['doctype' => $type]);
    }

    /**
     * Enregistre le modèle d'un document d'une portée ; null, ou le modèle d'origine, rétablit celui-ci.
     *
     * @param stdClass $scope
     * @param string $type
     * @param string|null $content
     * @return void
     */
    public static function set_doctemplate(stdClass $scope, $type, $content) {
        global $DB;

        $normalise = function ($text) {
            return trim(str_replace("\r\n", "\n", (string) $text));
        };
        $conditions = self::scope_conditions($scope) + ['doctype' => $type];
        $existing = $DB->get_record('these_doctemplate', $conditions);
        if ($content === null || $normalise($content) === $normalise(self::doctemplate_default($type))) {
            if ($existing) {
                $DB->delete_records('these_doctemplate', ['id' => $existing->id]);
            }
            return;
        }
        $record = (object) ($conditions + ['content' => str_replace("\r\n", "\n", $content), 'timemodified' => time()]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('these_doctemplate', $record);
        } else {
            $DB->insert_record('these_doctemplate', $record);
        }
    }

    /**
     * Images appelées par les modèles d'origine, à déposer par la DEVE sous ces noms de fichier
     * (logo.png...). Leur rôle est décrit par les chaînes doctplimageslot{nom}.
     *
     * @return string[]
     */
    public static function doctemplate_image_slots() {
        return ['logo', 'ministere', 'universite', 'bibliotheque', 'abes'];
    }

    /**
     * Nom sous lequel une image est appelée dans les modèles ({{img.nom}}).
     *
     * @param string $filename
     * @return string
     */
    public static function doctemplate_image_name($filename) {
        return preg_replace('/[^a-z0-9]/', '', core_text::strtolower(pathinfo($filename, PATHINFO_FILENAME)));
    }

    /**
     * Images utilisables dans les modèles : celles déposées par la DEVE dans la portée. Le module n'en
     * fournit aucune ; les modèles d'origine appellent logo, ministere, universite, bibliotheque et
     * abes (voir doctemplate_image_slots()), et une image absente est omise du document.
     *
     * @param stdClass|null $scope null : aucune image.
     * @return array nom => source pour TCPDF ('@' suivi du contenu en base64)
     */
    public static function doctemplate_images(?stdClass $scope) {
        $images = [];
        if ($scope && $scope->contextid) {
            $fs = get_file_storage();
            foreach ($fs->get_area_files($scope->contextid, $scope->component, 'docimages', 0, 'filename', false) as $file) {
                if (in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                    $images[self::doctemplate_image_name($file->get_filename())] = '@' . base64_encode($file->get_content());
                }
            }
        }
        return $images;
    }

    /**
     * Contrat type de diffusion électronique (PDF officiel), déposé dans les paramètres de l'activité
     * « Suivi des thèses » qui suit l'activité, sinon de l'activité elle-même.
     *
     * @param stdClass $these
     * @return string|null Contenu du PDF.
     */
    public static function get_contract_template(stdClass $these) {
        return scopesettings::contract_template(self::settings_scope($these));
    }
}
