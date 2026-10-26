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

use context;
use stdClass;

/**
 * Paramètres du circuit : rôles des services, agents DEVE signataires, durée et salles des soutenances, établissement, université,
 * signataires et contrat type de diffusion. Aucun n'est un réglage du site : comme les modèles des
 * documents, ils appartiennent à une portée (voir these_settings_scope()), l'activité « Suivi des
 * thèses » qui suit l'activité, sinon l'activité « Gestion des thèses » elle-même (table
 * these_setting, zone de fichiers « contracttemplate » de la portée).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scopesettings {
    /** @var string[] Paramètres contenant une adresse de courriel. */
    const EMAILS = ['dgemail', 'ccememail', 'ucblemail'];

    /**
     * Cache des paramètres lus pendant la requête, par portée et par contexte (vidé entre deux tests).
     *
     * @return \cache
     */
    protected static function cache() {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'mod_these', 'scopesettings');
    }

    /**
     * Paramètres texte et leur valeur par défaut, dans l'ordre du formulaire.
     *
     * @return array nom => valeur par défaut
     */
    public static function text_defaults() {
        return [
            'institution' => '',
            'university' => '',
            'hetopurl' => 'https://www.hetop.eu/hetop/',
            'devename' => '',
            'devetitle' => 'Directrice des Etudes et de la Vie Etudiante',
            'dgtitle' => '',
            'dgname' => '',
            'dgemail' => '',
            'visaplace' => '',
            'ccemname' => '',
            'ccememail' => '',
            'ucbltitle' => '',
            'ucblname' => '',
            'ucblemail' => '',
        ];
    }

    /**
     * Tous les paramètres et leur valeur par défaut. Rôles des services : « Enseignant » pour la DEVE,
     * le rôle interne « Bibliothèque (thèses) » pour la bibliothèque.
     *
     * @return array nom => valeur par défaut
     */
    public static function defaults() {
        global $DB;
        return [
            'deverole' => (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
            'libraryrole' => (int) $DB->get_field('role', 'id', ['shortname' => 'theselibrary']),
            'deveagent' => '',
            'defenseduration' => 60,
            'defenserooms' => '',
        ] + self::text_defaults();
    }

    /**
     * Salles de soutenance proposées dans une portée, une par ligne du paramètre « defenserooms ».
     * Sans salle, le lieu par défaut de l'ancien réglage de l'activité, s'il existe.
     *
     * @param array $settings Paramètres de la portée (voir get_all()).
     * @param string $fallback Lieu par défaut de l'activité « Gestion des thèses ».
     * @return string[] Salles, la première étant proposée par défaut.
     */
    public static function rooms(array $settings, $fallback = '') {
        $rooms = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/\R/', (string) ($settings['defenserooms'] ?? ''))),
            'strlen'
        )));
        if (!$rooms && trim((string) $fallback) !== '') {
            $rooms = [trim((string) $fallback)];
        }
        return $rooms;
    }

    /**
     * Champ « Lieu de soutenance » d'un formulaire : texte libre, avec la liste des salles de la portée
     * proposée à la saisie.
     *
     * @param \MoodleQuickForm $mform
     * @param string[] $rooms Voir rooms().
     * @return void
     */
    public static function add_location_field(\MoodleQuickForm $mform, array $rooms) {
        $attributes = ['size' => 64];
        if ($rooms) {
            $attributes['list'] = 'these-defense-rooms';
        }
        $mform->addElement('text', 'defenselocation', get_string('defenselocation', 'mod_these'), $attributes);
        $mform->setType('defenselocation', PARAM_TEXT);
        if ($rooms) {
            $options = implode('', array_map(function ($room) {
                return \html_writer::tag('option', '', ['value' => $room]);
            }, $rooms));
            $mform->addElement(
                'static',
                'defenseroomshelp',
                '',
                \html_writer::tag('datalist', $options, ['id' => 'these-defense-rooms'])
                    . get_string('defenseroomshelp', 'mod_these', implode(' ; ', array_map('s', $rooms)))
            );
        }
    }

    /**
     * Conditions désignant les paramètres d'une portée.
     *
     * @param stdClass $scope
     * @return array
     */
    protected static function conditions(stdClass $scope) {
        return ['theseid' => (int) $scope->theseid, 'synthesisid' => (int) $scope->synthesisid];
    }

    /**
     * Paramètres d'une portée, valeurs par défaut comprises.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @return array nom => valeur
     */
    public static function get_all(stdClass $scope) {
        global $DB;

        $key = 'scope_' . (int) $scope->theseid . '_' . (int) $scope->synthesisid;
        $values = self::cache()->get($key);
        if ($values === false) {
            $values = self::defaults();
            foreach ($DB->get_records('these_setting', self::conditions($scope)) as $record) {
                if (array_key_exists($record->name, $values)) {
                    $values[$record->name] = is_int($values[$record->name]) ? (int) $record->value : (string) $record->value;
                }
            }
            self::cache()->set($key, $values);
        }
        return $values;
    }

    /**
     * Un paramètre d'une portée.
     *
     * @param stdClass $scope
     * @param string $name
     * @return mixed
     */
    public static function get(stdClass $scope, $name) {
        return self::get_all($scope)[$name];
    }

    /**
     * Paramètres en vigueur pour une activité « Gestion des thèses ».
     *
     * @param stdClass $these
     * @return array
     */
    public static function for_these(stdClass $these) {
        return self::get_all(these_settings_scope($these));
    }

    /**
     * Paramètres en vigueur pour un dossier.
     *
     * @param stdClass $dossier
     * @return array
     */
    public static function for_dossier(stdClass $dossier) {
        global $DB;
        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        return self::for_these($these);
    }

    /**
     * Paramètres en vigueur dans un contexte : celui d'une activité « Gestion des thèses » ou
     * « Suivi des thèses ». Ailleurs (cours, catégorie, site), les valeurs par défaut.
     *
     * @param context $context
     * @return array
     */
    public static function for_context(context $context) {
        global $DB;

        $key = 'context_' . $context->id;
        $values = self::cache()->get($key);
        if ($values === false) {
            $values = null;
            if ($context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
                if ($cm && $cm->modname === 'these' && ($these = $DB->get_record('these', ['id' => $cm->instance]))) {
                    $values = self::for_these($these);
                } else if ($cm && $cm->modname === 'thesesynthesis' && ($scope = these_synthesis_scope($cm->instance))) {
                    $values = self::get_all($scope);
                }
            }
            $values = $values ?? self::defaults();
            self::cache()->set($key, $values);
        }
        return $values;
    }

    /**
     * Enregistre des paramètres d'une portée ; les autres restent inchangés.
     *
     * @param stdClass $scope
     * @param array $values nom => valeur
     * @return void
     */
    public static function set(stdClass $scope, array $values) {
        global $DB;

        $defaults = self::defaults();
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, $defaults)) {
                continue;
            }
            $value = is_int($defaults[$name]) ? (string) (int) $value : trim((string) $value);
            if ($name === 'defenseduration' && (int) $value < 1) {
                $value = (string) $defaults[$name];
            }
            $conditions = self::conditions($scope) + ['name' => $name];
            if ($id = $DB->get_field('these_setting', 'id', $conditions)) {
                $DB->set_field('these_setting', 'value', $value, ['id' => $id]);
            } else {
                $DB->insert_record('these_setting', (object) ($conditions + ['value' => $value]));
            }
        }
        self::reset_cache();
    }

    /**
     * Supprime les paramètres d'une portée (suppression de l'activité).
     *
     * @param stdClass $scope
     * @return void
     */
    public static function delete(stdClass $scope) {
        global $DB;
        $DB->delete_records('these_setting', self::conditions($scope));
        self::reset_cache();
    }

    /**
     * Oublie les paramètres lus (après un enregistrement, et entre deux tests).
     *
     * @return void
     */
    public static function reset_cache() {
        self::cache()->purge();
    }

    /**
     * Contrat type de diffusion électronique (PDF officiel) déposé dans la portée.
     *
     * @param stdClass $scope
     * @return string|null Contenu du PDF.
     */
    public static function contract_template(stdClass $scope) {
        if (empty($scope->contextid)) {
            return null;
        }
        $files = get_file_storage()->get_area_files($scope->contextid, $scope->component, 'contracttemplate', 0, 'id', false);
        $file = reset($files);
        return $file ? $file->get_content() : null;
    }
}
