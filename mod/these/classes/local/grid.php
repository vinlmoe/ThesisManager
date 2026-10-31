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
     * Éléments d'origine de la grille (grid_items()), sous forme d'enregistrements d'une portée.
     *
     * @param stdClass|null $scope
     * @return stdClass[] Dans l'ordre, sans identifiant.
     */
    protected static function default_records(?stdClass $scope) {
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
        return $records;
    }

    /**
     * Enregistre les éléments d'origine de la grille d'une portée qui n'en a aucun : à la création
     * d'une activité (« Gestion des thèses » ou « Suivi des thèses »), à la restauration d'une
     * sauvegarde qui n'en contient pas et à la mise à jour 2026103100. La lecture de la grille
     * (get_grid_items()) n'écrit jamais : deux premières lectures simultanées se heurtaient à l'index
     * unique de la portée.
     *
     * @param stdClass $scope Voir doctemplates::local_scope() ; theseid ou synthesisid renseigné.
     * @return int Nombre d'éléments enregistrés (0 si la portée en avait déjà).
     */
    public static function create_default_items(stdClass $scope) {
        global $DB;

        if ($DB->record_exists('these_griditem', doctemplates::scope_conditions($scope))) {
            return 0;
        }
        $records = self::default_records($scope);
        $DB->insert_records('these_griditem', $records);
        return count($records);
    }

    /**
     * Observateur de la création d'un module de cours : une activité « Suivi des thèses » porte la
     * portée de paramètres partagée par les promotions qu'elle suit ; sa grille reçoit les éléments
     * d'origine dès sa création, comme celle d'une activité « Gestion des thèses » (these_add_instance()).
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        if (($event->other['modulename'] ?? '') !== 'thesesynthesis' || empty($event->other['instanceid'])) {
            return;
        }
        self::create_default_items((object) ['theseid' => 0, 'synthesisid' => (int) $event->other['instanceid']]);
    }

    /**
     * Éléments de la grille de conformité d'une portée de paramètres (voir these_settings_scope()),
     * modifiables par la DEVE. Lecture seule : une portée sans élément enregistré (activité antérieure
     * à la mise à jour, grille vidée par la DEVE) présente les éléments d'origine sans les enregistrer.
     *
     * @param stdClass|null $scope null : éléments d'origine (aperçus).
     * @return array rubrique => [clé => {itemkey, section, label, conditional, sortorder}]
     */
    public static function get_grid_items(?stdClass $scope) {
        global $DB;

        $records = $scope
            ? $DB->get_records('these_griditem', doctemplates::scope_conditions($scope), 'sortorder ASC, id ASC') : [];
        if (!$records) {
            $records = self::default_records($scope);
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
