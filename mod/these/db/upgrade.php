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
 * Upgrade steps for mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Exécute les étapes de mise à jour de la base depuis la version installée.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_these_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102400) {
        // Documents de l'établissement remplis et signés dans le module : formulaire de dépôt de
        // sujet, bordereau, rapport et grille de conformité du directeur, fiche d'agréments, fiche
        // signalétique, contrat de diffusion, conclusions.
        $table = new xmldb_table('these_dossier');
        $fields = [
            new xmldb_field('studyyear', XMLDB_TYPE_CHAR, '4', null, null, null, null, 'codirectorname'),
            new xmldb_field('confidential', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'studyyear'),
            new xmldb_field('directortitle', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'confidential'),
            new xmldb_field('externaldirector', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'directortitle'),
            new xmldb_field('extdirectorname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'externaldirector'),
            new xmldb_field('extdirectorgrade', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'extdirectorname'),
            new xmldb_field('extdirectorinstitution', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'extdirectorgrade'),
            new xmldb_field('extdirectoremail', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'extdirectorinstitution'),
            new xmldb_field('commitments', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'extdirectoremail'),
            new xmldb_field('ethicsnumber', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'commitments'),
            new xmldb_field('bordereau', XMLDB_TYPE_TEXT, null, null, null, null, null, 'ethicsnumber'),
            new xmldb_field('conclusions', XMLDB_TYPE_TEXT, null, null, null, null, null, 'bordereau'),
            new xmldb_field('reporttext', XMLDB_TYPE_TEXT, null, null, null, null, null, 'conclusions'),
            new xmldb_field('reportfavorable', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'reporttext'),
            new xmldb_field('conformitygrid', XMLDB_TYPE_TEXT, null, null, null, null, null, 'reportfavorable'),
            new xmldb_field('agreement', XMLDB_TYPE_TEXT, null, null, null, null, null, 'conformitygrid'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('these_signature');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('dossierid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('doctype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('slot', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('signername', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('signeremail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('token', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('requesttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timesigned', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('dossierid', XMLDB_KEY_FOREIGN, ['dossierid'], 'these_dossier', ['id']);
        $table->add_index('dossier_doc_slot', XMLDB_INDEX_UNIQUE, ['dossierid', 'doctype', 'slot']);
        $table->add_index('token', XMLDB_INDEX_NOTUNIQUE, ['token']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Rôle interne « Bibliothèque (thèses) », proposé par défaut dans les réglages du site.
        require_once($CFG->dirroot . '/mod/these/locallib.php');
        these_setup_default_roles();

        upgrade_mod_savepoint(true, 2026102400, 'these');
    }

    if ($oldversion < 2026102500) {
        // Documents composés à partir de modèles modifiables : les logos de l'ancien réglage sont
        // remplacés par les images des modèles (doctemplates/images, réglage « docimages »).
        get_file_storage()->delete_area_files(context_system::instance()->id, 'mod_these', 'logo');
        unset_config('logo', 'mod_these');

        upgrade_mod_savepoint(true, 2026102500, 'these');
    }

    if ($oldversion < 2026102600) {
        // Modèles des documents propres à chaque activité, modifiés par la DEVE.
        $table = new xmldb_table('these_doctemplate');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('theseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('doctype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('theseid', XMLDB_KEY_FOREIGN, ['theseid'], 'these', ['id']);
        $table->add_index('theseid_doctype', XMLDB_INDEX_UNIQUE, ['theseid', 'doctype']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Les modèles et images modifiés auparavant pour tout le site passent dans chaque activité.
        $config = (array) get_config('mod_these');
        $fs = get_file_storage();
        $systemcontext = context_system::instance();
        $images = $fs->get_area_files($systemcontext->id, 'mod_these', 'docimages', 0, 'filename', false);
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'these']);
        foreach ($DB->get_records('these', null, 'id', 'id') as $these) {
            foreach ($config as $name => $content) {
                if (strpos($name, 'doctpl_') === 0 && trim((string) $content) !== '') {
                    $doctype = substr($name, strlen('doctpl_'));
                    if (!$DB->record_exists('these_doctemplate', ['theseid' => $these->id, 'doctype' => $doctype])) {
                        $DB->insert_record('these_doctemplate', (object) [
                            'theseid' => $these->id, 'doctype' => $doctype, 'content' => $content, 'timemodified' => time(),
                        ]);
                    }
                }
            }
            $cmid = $DB->get_field('course_modules', 'id', ['module' => $moduleid, 'instance' => $these->id]);
            if ($images && $cmid) {
                $context = context_module::instance($cmid);
                foreach ($images as $image) {
                    $fs->create_file_from_storedfile(['contextid' => $context->id, 'itemid' => 0], $image);
                }
            }
        }
        foreach (array_keys($config) as $name) {
            if (strpos($name, 'doctpl_') === 0) {
                unset_config($name, 'mod_these');
            }
        }
        $fs->delete_area_files($systemcontext->id, 'mod_these', 'docimages');
        unset_config('docimages', 'mod_these');

        upgrade_mod_savepoint(true, 2026102600, 'these');
    }

    if ($oldversion < 2026102800) {
        // Annuaire du jury, éligibilité et accès des membres du jury, grille de conformité modifiable.
        $table = new xmldb_table('these_jury');
        $fields = [
            new xmldb_field('jurorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'),
            new xmldb_field('jurystatus', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'jurorid'),
            new xmldb_field('eligible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'jurystatus'),
            new xmldb_field('eligibleby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'eligible'),
            new xmldb_field('eligibletime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'eligibleby'),
            new xmldb_field('accesstoken', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'eligibletime'),
            new xmldb_field('notifiedtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'accesstoken'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach (
            [
            new xmldb_index('accesstoken', XMLDB_INDEX_NOTUNIQUE, ['accesstoken']),
            new xmldb_index('jurorid', XMLDB_INDEX_NOTUNIQUE, ['jurorid']),
            ] as $index
        ) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        $table = new xmldb_table('these_griditem');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('theseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('itemkey', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('section', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('conditional', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('theseid', XMLDB_KEY_FOREIGN, ['theseid'], 'these', ['id']);
        $table->add_index('theseid_itemkey', XMLDB_INDEX_UNIQUE, ['theseid', 'itemkey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('these_juror');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('institution', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('email', XMLDB_INDEX_NOTUNIQUE, ['email']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026102800, 'these');
    }

    return true;
}
