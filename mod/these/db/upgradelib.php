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

/**
 * Fonctions des étapes de mise à jour, partagées avec les scripts de cli/.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Index du numéro DEVE (these_dossier.devenumber) : les numéros vides deviennent NULL, puis l'index est
 * créé unique s'il n'y a pas de numéro attribué à plusieurs dossiers du site, non unique sinon (la mise
 * à jour ne doit pas échouer sur des doublons anciens, à corriger par la DEVE). Rejouable : un index
 * non unique devient unique une fois les doublons corrigés.
 *
 * @return array Doublons : liste de {number, dossiers: [{id, theseid, activity}]} ; vide si l'index est unique.
 */
function these_upgrade_devenumber_index() {
    global $DB;

    $dbman = $DB->get_manager();
    $DB->execute("UPDATE {these_dossier} SET devenumber = NULL WHERE devenumber IS NOT NULL AND TRIM(devenumber) = ''");

    $duplicates = [];
    $rows = $DB->get_recordset_sql(
        "SELECT d.id, d.devenumber, d.theseid, t.name
           FROM {these_dossier} d
           JOIN (SELECT devenumber
                   FROM {these_dossier}
                  WHERE devenumber IS NOT NULL
               GROUP BY devenumber
                 HAVING COUNT(1) > 1) dup ON dup.devenumber = d.devenumber
      LEFT JOIN {these} t ON t.id = d.theseid
       ORDER BY d.devenumber, d.id"
    );
    foreach ($rows as $row) {
        $duplicates[$row->devenumber] = $duplicates[$row->devenumber] ?? (object) ['number' => $row->devenumber, 'dossiers' => []];
        $duplicates[$row->devenumber]->dossiers[] = (object) [
            'id' => (int) $row->id,
            'theseid' => (int) $row->theseid,
            'activity' => format_string((string) $row->name),
        ];
    }
    $rows->close();

    $table = new xmldb_table('these_dossier');
    $wanted = new xmldb_index('devenumber', $duplicates ? XMLDB_INDEX_NOTUNIQUE : XMLDB_INDEX_UNIQUE, ['devenumber']);
    $indexes = $DB->get_indexes('these_dossier');
    $current = null;
    foreach ($indexes as $index) {
        if ($index['columns'] === ['devenumber']) {
            $current = $index;
        }
    }
    if ($current !== null && (bool) $current['unique'] !== empty($duplicates)) {
        $dbman->drop_index($table, new xmldb_index('devenumber', XMLDB_INDEX_NOTUNIQUE, ['devenumber']));
        $current = null;
    }
    if ($current === null) {
        $dbman->add_index($table, $wanted);
    }
    return array_values($duplicates);
}

/**
 * Liste des numéros DEVE en double, pour l'administrateur (voir these_upgrade_devenumber_index()).
 *
 * @param array $duplicates
 * @return string Texte brut, une ligne par numéro.
 */
function these_upgrade_devenumber_report(array $duplicates) {
    $lines = [get_string('upgradedevenumberduplicates', 'mod_these')];
    foreach ($duplicates as $duplicate) {
        $dossiers = array_map(function ($dossier) {
            return get_string('upgradedevenumberdossier', 'mod_these', (object) [
                'id' => $dossier->id,
                'activity' => $dossier->activity !== '' ? $dossier->activity : $dossier->theseid,
            ]);
        }, $duplicate->dossiers);
        $lines[] = '- ' . get_string('upgradedevenumberduplicate', 'mod_these', (object) [
            'number' => $duplicate->number,
            'dossiers' => implode(', ', $dossiers),
        ]);
    }
    return implode("\n", $lines);
}
