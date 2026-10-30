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

use stdClass;

/**
 * Grille de conformité du directeur : éléments par rubrique, éléments d'une portée de paramètres
 * (modifiables par la DEVE) et grille renseignée d'un dossier.
 *
 * Les fonctions these_*() correspondantes de locallib/signatures.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grid {
    /**
     * Éléments de la grille de conformité du directeur, par rubrique. Les éléments « conditionnels »
     * ne s'appliquent qu'à certaines thèses : ils peuvent rester non cochés.
     *
     * @return array rubrique => [clé => conditionnel (bool)]
     */
    public static function grid_items() {
        return [
            'fond' => [
                'question' => false, 'answer' => false, 'biblio' => false, 'critique' => false, 'ownwork' => false,
                'tool' => true,
            ],
            'forme' => [
                'french' => false, 'language' => false, 'typing' => false, 'thanks' => false, 'toc' => false,
                'staff' => false, 'lists' => false, 'covers' => false, 'citations' => false, 'bibliography' => false,
                'figures' => false, 'introduction' => false, 'conclusion' => false, 'abstract' => false,
                'structure' => true,
            ],
        ];
    }

    /**
     * Rubriques de la grille de conformité, dans l'ordre.
     *
     * @return string[]
     */
    public static function grid_sections() {
        return ['fond', 'forme'];
    }

    /**
     * Éléments de la grille de conformité d'une portée de paramètres (voir these_settings_scope()),
     * modifiables par la DEVE. Les éléments d'origine (these_grid_items()) sont enregistrés à la
     * première consultation.
     *
     * @param stdClass|null $scope null : éléments d'origine, sans enregistrement (aperçus).
     * @return array rubrique => [clé => {itemkey, section, label, conditional, sortorder}]
     */
    public static function get_grid_items(?stdClass $scope) {
        global $DB;

        $records = $scope
            ? $DB->get_records('these_griditem', doctemplates::scope_conditions($scope), 'sortorder ASC, id ASC') : [];
        if (!$records) {
            $records = [];
            $sortorder = 0;
            foreach (self::grid_items() as $section => $items) {
                foreach ($items as $key => $conditional) {
                    $records[] = (object) array_merge($scope ? doctemplates::scope_conditions($scope) : [], [
                        'itemkey' => $key,
                        'section' => $section,
                        'label' => get_string('grid' . $key, 'mod_these'),
                        'conditional' => (int) $conditional,
                        'sortorder' => $sortorder++,
                        'timemodified' => time(),
                    ]);
                }
            }
            if ($scope) {
                foreach ($records as $record) {
                    $record->id = $DB->insert_record('these_griditem', $record);
                }
            }
        }
        $sections = array_fill_keys(self::grid_sections(), []);
        foreach ($records as $record) {
            $section = array_key_exists($record->section, $sections) ? $record->section : 'forme';
            $sections[$section][$record->itemkey] = $record;
        }
        return $sections;
    }

    /**
     * Remplace les éléments de la grille de conformité d'une portée. Un élément gardé conserve sa clé,
     * et donc les réponses déjà données ; un élément supprimé disparaît des grilles et de leurs PDF.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param array $items Liste d'objets {itemkey ('' pour un nouvel élément), section, label, conditional}, dans l'ordre ;
     *                     une clé inconnue de la portée (transfert depuis une autre) est reprise telle quelle.
     * @return void
     */
    public static function save_grid_items(stdClass $scope, array $items) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $existing = $DB->get_records_menu('these_griditem', doctemplates::scope_conditions($scope), '', 'itemkey, id');
        $kept = [];
        $sortorder = 0;
        foreach ($items as $item) {
            $label = trim((string) $item->label);
            if ($label === '') {
                continue;
            }
            $key = (string) ($item->itemkey ?? '');
            if (!preg_match('/^[a-zA-Z0-9]{1,40}$/', $key) || isset($kept[$key])) {
                $key = 'item' . random_string(10);
            }
            $record = (object) array_merge(doctemplates::scope_conditions($scope), [
                'itemkey' => $key,
                'section' => in_array($item->section, self::grid_sections(), true) ? $item->section : 'forme',
                'label' => $label,
                'conditional' => empty($item->conditional) ? 0 : 1,
                'sortorder' => $sortorder++,
                'timemodified' => time(),
            ]);
            if (isset($existing[$key])) {
                $record->id = $existing[$key];
                $DB->update_record('these_griditem', $record);
            } else {
                $DB->insert_record('these_griditem', $record);
            }
            $kept[$key] = true;
        }
        foreach ($existing as $key => $id) {
            if (!isset($kept[$key])) {
                $DB->delete_records('these_griditem', ['id' => $id]);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Grille de conformité d'un dossier.
     *
     * @param stdClass $dossier
     * @return array clé => 0|1 ; tableau vide si elle n'a pas été renseignée.
     */
    public static function get_grid(stdClass $dossier) {
        $data = json_decode((string) ($dossier->conformitygrid ?? ''), true);
        return is_array($data) ? $data : [];
    }
}
