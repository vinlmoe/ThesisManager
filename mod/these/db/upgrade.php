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

    return true;
}
