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

/**
 * Dossiers déjà réduits à leur procès-verbal par l'effacement des données de leur étudiant (versions
 * antérieures à 2026103100). L'effacement supprimait tout l'historique, alors que tout dossier créé ou
 * importé commence par l'entrée « created » ou « imported » : un dossier qui n'en a pas et dont le
 * procès-verbal est enregistré (décision du jury, PV scanné ou d'ajournement) a été effacé. Les entrées
 * qu'une opération concurrente a pu y inscrire après l'effacement (fait alors hors du verrou du
 * dossier) sont retirées ; le dossier est clos et marqué archivé, comme le fait désormais l'effacement
 * (voir \mod_these\privacy\provider).
 *
 * @return int Nombre de dossiers marqués.
 */
function these_upgrade_mark_erased_dossiers() {
    global $DB;

    $ids = $DB->get_fieldset_sql(
        "SELECT d.id
           FROM {these_dossier} d
          WHERE d.erasedtime = 0
            AND NOT EXISTS (SELECT 1 FROM {these_log} l
                             WHERE l.dossierid = d.id AND l.logaction IN ('created', 'imported'))
            AND (d.defenseresult <> 0
                 OR EXISTS (SELECT 1 FROM {files} f
                             WHERE f.component = 'mod_these' AND f.filearea IN ('pv', 'pvadjourned')
                               AND f.itemid = d.id AND f.filename <> '.'))"
    );
    $now = time();
    $closed = 100; // THESE_STATUS_CLOSED, figé.
    foreach ($ids as $id) {
        $DB->delete_records('these_log', ['dossierid' => $id]);
        $DB->update_record('these_dossier', (object) ['id' => $id, 'status' => $closed, 'erasedtime' => $now,
            'cancelledfromstatus' => 0, 'timemodified' => $now]);
        $DB->insert_record('these_log', (object) ['dossierid' => $id, 'userid' => 0, 'logaction' => 'erased',
            'fromstatus' => $closed, 'tostatus' => $closed, 'remark' => '', 'timecreated' => $now]);
        // Soutenance retirée du calendrier (l'événement porte le nom de l'étudiant).
        $DB->delete_records('event', ['modulename' => 'these', 'uuid' => 'these-dossier-' . $id]);
    }
    return count($ids);
}

/**
 * Éléments d'origine de la grille de conformité des activités « Gestion des thèses » et « Suivi des
 * thèses » qui n'en ont pas (voir \mod_these\local\grid::create_default_items()).
 *
 * @return int Nombre de portées complétées.
 */
function these_upgrade_create_grid_items() {
    global $DB;

    $scopes = [];
    $theseids = $DB->get_fieldset_sql(
        "SELECT t.id FROM {these} t
          WHERE NOT EXISTS (SELECT 1 FROM {these_griditem} g WHERE g.theseid = t.id AND g.synthesisid = 0)"
    );
    foreach ($theseids as $theseid) {
        $scopes[] = (object) ['theseid' => (int) $theseid, 'synthesisid' => 0];
    }
    if ($DB->get_manager()->table_exists('thesesynthesis')) {
        $synthesisids = $DB->get_fieldset_sql(
            "SELECT s.id FROM {thesesynthesis} s
              WHERE NOT EXISTS (SELECT 1 FROM {these_griditem} g WHERE g.theseid = 0 AND g.synthesisid = s.id)"
        );
        foreach ($synthesisids as $synthesisid) {
            $scopes[] = (object) ['theseid' => 0, 'synthesisid' => (int) $synthesisid];
        }
    }
    foreach ($scopes as $scope) {
        \mod_these\local\grid::create_default_items($scope);
    }
    return count($scopes);
}
