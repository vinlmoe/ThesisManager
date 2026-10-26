<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Étapes de mise à jour de mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Mise à jour du module.
 *
 * @param int $oldversion Version installée.
 * @return bool
 */
function xmldb_these_upgrade($oldversion) {
    global $CFG, $DB;

    if ($oldversion < 2026102504) {
        // Les documents réservés au personnel (rapport du directeur, grille de conformité) ne sont
        // plus rangés dans la zone « generated », consultable par l'étudiant : retrait de ceux que
        // les générations précédentes y ont déposés. L'archive transmise à l'université les rend
        // désormais à la demande.
        require_once($CFG->dirroot . '/mod/these/locallib.php');
        $filenames = [];
        foreach (these_document_definitions() as $doctype => $definition) {
            if ($definition['staffonly']) {
                $filenames[] = these_document_filename($doctype);
            }
        }
        [$insql, $params] = $DB->get_in_or_equal($filenames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_these';
        $params['filearea'] = 'generated';
        $records = $DB->get_recordset_select(
            'files',
            "component = :component AND filearea = :filearea AND filename $insql",
            $params
        );
        $fs = get_file_storage();
        foreach ($records as $record) {
            $fs->get_file_instance($record)->delete();
        }
        $records->close();

        upgrade_mod_savepoint(true, 2026102504, 'these');
    }

    if ($oldversion < 2026102602) {
        // Plus aucun réglage du site : rôles des services, agents DEVE signataires, établissement,
        // signataires des documents et contrat type deviennent des paramètres de chaque activité
        // « Suivi des thèses » et de chaque activité « Gestion des thèses » (utilisés quand aucune
        // activité de suivi ne la suit). Les valeurs du site y sont recopiées, puis supprimées.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_setting');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('theseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('synthesisid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('owner_name', XMLDB_INDEX_UNIQUE, ['theseid', 'synthesisid', 'name']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $names = ['deverole', 'libraryrole', 'deveagent', 'institution', 'university', 'hetopurl', 'devename', 'devetitle',
            'dgtitle', 'dgname', 'dgemail', 'visaplace', 'ccemname', 'ccememail', 'ucbltitle', 'ucblname', 'ucblemail'];
        $config = (array) get_config('mod_these');
        $values = array_intersect_key($config, array_flip($names));
        $owners = [];
        foreach ($DB->get_records('these', null, '', 'id') as $these) {
            $cm = get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING);
            if ($cm) {
                $owners[] = ['theseid' => (int) $these->id, 'synthesisid' => 0, 'component' => 'mod_these',
                    'contextid' => context_module::instance($cm->id)->id];
            }
        }
        if ($dbman->table_exists('thesesynthesis')) {
            foreach ($DB->get_records('thesesynthesis', null, '', 'id') as $synthesis) {
                $cm = get_coursemodule_from_instance('thesesynthesis', $synthesis->id, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $owners[] = ['theseid' => 0, 'synthesisid' => (int) $synthesis->id, 'component' => 'mod_thesesynthesis',
                        'contextid' => context_module::instance($cm->id)->id];
                }
            }
        }
        $fs = get_file_storage();
        $contracts = $fs->get_area_files(context_system::instance()->id, 'mod_these', 'contracttemplate', 0, 'id', false);
        $contract = reset($contracts);
        foreach ($owners as $owner) {
            $conditions = ['theseid' => $owner['theseid'], 'synthesisid' => $owner['synthesisid']];
            foreach ($values as $name => $value) {
                if (!$DB->record_exists('these_setting', $conditions + ['name' => $name])) {
                    $DB->insert_record('these_setting', (object) ($conditions + ['name' => $name, 'value' => (string) $value]));
                }
            }
            if ($contract && !$fs->get_area_files($owner['contextid'], $owner['component'], 'contracttemplate', 0, 'id', false)) {
                $fs->create_file_from_storedfile([
                    'contextid' => $owner['contextid'],
                    'component' => $owner['component'],
                    'filearea' => 'contracttemplate',
                    'itemid' => 0,
                    'filepath' => '/',
                ], $contract);
            }
        }
        foreach ($names as $name) {
            unset_config($name, 'mod_these');
        }
        unset_config('contracttemplate', 'mod_these');
        $fs->delete_area_files(context_system::instance()->id, 'mod_these', 'contracttemplate');

        upgrade_mod_savepoint(true, 2026102602, 'these');
    }

    return true;
}
