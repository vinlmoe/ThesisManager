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
    global $DB, $OUTPUT;

    if ($oldversion < 2026102504) {
        // Les documents réservés au personnel (rapport du directeur, grille de conformité) ne sont
        // plus rangés dans la zone « generated », consultable par l'étudiant : retrait de ceux que
        // les générations précédentes y ont déposés. L'archive transmise à l'université les rend
        // désormais à la demande.
        // Noms des documents réservés au personnel à cette version (rapport du directeur et grille de
        // conformité, these_document_filename() de l'époque), figés : le code courant ne les produit
        // plus sous ces noms.
        $filenames = ['report.pdf', 'grid.pdf'];
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

    if ($oldversion < 2026102603) {
        // Manière dont chaque signature a été donnée (en ligne, sur place auprès de la DEVE, à la main
        // avec scan joint au dossier), délégation de la direction générale et compte DEVE qui a
        // vérifié l'identité du signataire ou enregistré le scan.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_signature');
        $fields = [
            new xmldb_field('method', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'online', 'ipaddress'),
            new xmldb_field('delegated', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'method'),
            new xmldb_field('verifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'delegated'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026102603, 'these');
    }

    if ($oldversion < 2026102700) {
        // Le directeur de thèse siège au jury sans voter : il faut au moins deux membres votants,
        // président compris. Les activités restées au minimum d'origine (trois, directeur compris)
        // passent à deux.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these');
        $field = new xmldb_field('minjury', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '2', 'defaultlocation');
        $dbman->change_field_default($table, $field);
        $DB->set_field('these', 'minjury', 2, ['minjury' => 3]);
        upgrade_mod_savepoint(true, 2026102700, 'these');
    }

    if ($oldversion < 2026102703) {
        // Validation de l'A6 : liée à l'étudiant, plus au dossier. Les saisies de la DEVE déjà faites
        // dans l'agrément d'un dossier sont reprises (la plus récente par étudiant).
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_a6');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('a6date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('a6school', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $rs = $DB->get_recordset_select(
            'these_dossier',
            'agreement IS NOT NULL',
            null,
            'timemodified ASC',
            'id, userid, agreement, timemodified'
        );
        foreach ($rs as $dossier) {
            $agreement = json_decode((string) $dossier->agreement, true);
            if (!is_array($agreement) || !(array_key_exists('a6date', $agreement) || array_key_exists('a6notyet', $agreement))) {
                continue;
            }
            $record = (object) [
                'userid' => (int) $dossier->userid,
                'a6date' => empty($agreement['a6notyet']) ? (int) ($agreement['a6date'] ?? 0) : 0,
                'a6school' => (string) ($agreement['a6school'] ?? ''),
                'usermodified' => 0,
                'timemodified' => (int) $dossier->timemodified,
            ];
            if ($id = $DB->get_field('these_a6', 'id', ['userid' => $record->userid])) {
                $record->id = $id;
                $DB->update_record('these_a6', $record);
            } else {
                $DB->insert_record('these_a6', $record);
            }
        }
        $rs->close();
        upgrade_mod_savepoint(true, 2026102703, 'these');
    }

    if ($oldversion < 2026102707) {
        // Plus de rôles des services : la DEVE est reconnue à sa capacité (rôle « Enseignant »), la
        // bibliothèque à la liste de ses agents. Les détenteurs du rôle choisi pour la bibliothèque sont
        // ajoutés à cette liste, pour garder leurs droits et leurs courriels ; le rôle n'est pas supprimé.
        foreach ($DB->get_records('these_setting', ['name' => 'libraryrole']) as $setting) {
            if (!(int) $setting->value) {
                continue;
            }
            $usernames = $DB->get_fieldset_sql(
                "SELECT DISTINCT u.username
                   FROM {role_assignments} ra
                   JOIN {user} u ON u.id = ra.userid
                  WHERE ra.roleid = :roleid AND u.deleted = 0",
                ['roleid' => (int) $setting->value]
            );
            if (!$usernames) {
                continue;
            }
            $conditions = ['theseid' => $setting->theseid, 'synthesisid' => $setting->synthesisid, 'name' => 'libraryagent'];
            $agents = $DB->get_record('these_setting', $conditions);
            $current = $agents ? preg_split('/[\s,;]+/', (string) $agents->value, -1, PREG_SPLIT_NO_EMPTY) : [];
            $value = implode("\n", array_unique(array_merge($current, $usernames)));
            if ($agents) {
                $DB->set_field('these_setting', 'value', $value, ['id' => $agents->id]);
            } else {
                $DB->insert_record('these_setting', (object) ($conditions + ['value' => $value]));
            }
        }
        $DB->delete_records_list('these_setting', 'name', ['deverole', 'libraryrole']);
        upgrade_mod_savepoint(true, 2026102707, 'these');
    }

    if ($oldversion < 2026102708) {
        // Annuaire du jury : matière enseignée, reprise dans la liste du corps enseignant.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_juror');
        $field = new xmldb_field('discipline', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'institution');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102708, 'these');
    }

    if ($oldversion < 2026102800) {
        // Co-directeur interne choisi dans l'annuaire des enseignants.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_dossier');
        $field = new xmldb_field(
            'codirectorjurorid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'codirectorname'
        );
        // Co-directeur supprimé depuis (2026102901) : rien à ajouter sans la colonne du nom, après
        // laquelle le champ se place.
        if ($dbman->field_exists($table, 'codirectorname') && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102800, 'these');
    }

    if ($oldversion < 2026102802) {
        // Signatures des agents DEVE (et de la direction générale par délégation) : compte qui a
        // validé l'étape, et donc les documents qu'ils signent.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_signature');
        $field = new xmldb_field('validatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'verifiedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102802, 'these');
    }

    if ($oldversion < 2026102803) {
        // Date de la validation de l'équipe DEVE, avec le compte validateur.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_signature');
        $field = new xmldb_field('validatedtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'validatedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102803, 'these');
    }

    if ($oldversion < 2026102901) {
        $dbman = $DB->get_manager();
        // Plus de co-directeur : un directeur extérieur est suppléé par un examinateur interne,
        // enregistré comme directeur du dossier. Plus de rapport rédigé dans le module : il est déposé.
        $table = new xmldb_table('these_dossier');
        foreach (['codirectorname', 'codirectorjurorid', 'reporttext'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        // Nouvelle date de soutenance demandée par la DEVE, sans renvoyer le dossier.
        $fields = [
            new xmldb_field('daterequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'remindertime'),
            new xmldb_field('daterequestcomment', XMLDB_TYPE_TEXT, null, null, null, null, null, 'daterequested'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $table = new xmldb_table('these_subjecthistory');
        $field = new xmldb_field('codirectorname');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        // Validation des études : le DEFV est saisi par la DEVE avec l'A6.
        $table = new xmldb_table('these_a6');
        $fields = [
            new xmldb_field('defvdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'a6school'),
            new xmldb_field('defvschool', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'defvdate'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026102901, 'these');
    }

    if ($oldversion < 2026103001) {
        $dbman = $DB->get_manager();
        // Index des colonnes interrogées par la confidentialité, le planning, le récapitulatif et
        // l'historique de sujet.
        $indexes = [
            ['these_log', 'userid', ['userid']],
            ['these_signature', 'userid', ['userid']],
            ['these_dossier', 'defensedate', ['defensedate']],
            ['these_subjecthistory', 'directorid', ['directorid']],
            ['these_subjecthistory', 'changedby', ['changedby']],
        ];
        foreach ($indexes as [$tablename, $name, $fields]) {
            $table = new xmldb_table($tablename);
            $index = new xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        // Clé étrangère de la question vers son activité « Gestion des thèses » (0 pour une question
        // partagée) : un index simple sur theseid, comme pour les autres tables de paramètres.
        $table = new xmldb_table('these_question');
        $key = new xmldb_key('theseid', XMLDB_KEY_FOREIGN, ['theseid'], 'these', ['id']);
        if (!$dbman->index_exists($table, new xmldb_index('theseid', XMLDB_INDEX_NOTUNIQUE, ['theseid']))) {
            $dbman->add_key($table, $key);
        }
        // Adresse de l'annuaire du jury à 255 caractères, comme celle d'un membre du jury ; l'index
        // est retiré le temps du changement.
        $table = new xmldb_table('these_juror');
        $index = new xmldb_index('email', XMLDB_INDEX_NOTUNIQUE, ['email']);
        $field = new xmldb_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'firstname');
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $dbman->change_field_precision($table, $field);
        $dbman->add_index($table, $index);
        upgrade_mod_savepoint(true, 2026103001, 'these');
    }

    if ($oldversion < 2026103003) {
        $dbman = $DB->get_manager();
        // Compte Moodle rattaché au membre du jury : les droits (sujet d'une thèse confidentielle,
        // jurys du suivi) ne reconnaissent plus un membre à l'égalité de son adresse avec celle d'un
        // compte.
        $table = new xmldb_table('these_jury');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'notifiedtime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // Rattachement des membres existants : le compte de leur fiche de l'annuaire, sinon l'unique
        // compte confirmé, actif et non supprimé portant leur adresse ; aucun si l'adresse est
        // inconnue ou partagée par plusieurs comptes.
        $accounts = [];
        $account = function ($email) use ($DB, &$accounts) {
            $email = core_text::strtolower(trim((string) $email));
            if ($email === '') {
                return 0;
            }
            if (!array_key_exists($email, $accounts)) {
                $users = $DB->get_records_select(
                    'user',
                    'LOWER(email) = :email AND confirmed = 1 AND suspended = 0 AND deleted = 0',
                    ['email' => $email],
                    '',
                    'id',
                    0,
                    2
                );
                $accounts[$email] = count($users) === 1 ? (int) key($users) : 0;
            }
            return $accounts[$email];
        };
        $members = $DB->get_recordset_sql(
            "SELECT j.id, j.email, r.email AS juroremail
               FROM {these_jury} j
          LEFT JOIN {these_juror} r ON r.id = j.jurorid
              WHERE j.userid = 0"
        );
        foreach ($members as $member) {
            $userid = $account($member->juroremail) ?: $account($member->email);
            if ($userid) {
                $DB->set_field('these_jury', 'userid', $userid, ['id' => $member->id]);
            }
        }
        $members->close();
        upgrade_mod_savepoint(true, 2026103003, 'these');
    }

    if ($oldversion < 2026103100) {
        // Numéro DEVE unique sur le site, garanti par un index unique (NULL pour un numéro vide). Des
        // doublons enregistrés auparavant (unicité contrôlée par le seul formulaire, et par activité)
        // ne font pas échouer la mise à jour : l'index est alors non unique et les doublons sont
        // listés pour être corrigés, avant cli/devenumber_index.php (voir INSTALL.md).
        require_once(__DIR__ . '/upgradelib.php');
        // Dossier réduit à son procès-verbal par l'effacement des données de l'étudiant : marqué, clos et
        // sorti du circuit.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('these_dossier');
        $field = new xmldb_field('erasedtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'daterequestcomment');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        these_upgrade_mark_erased_dossiers();
        $duplicates = these_upgrade_devenumber_index();
        if ($duplicates) {
            $report = these_upgrade_devenumber_report($duplicates);
            if (CLI_SCRIPT) {
                mtrace($report);
            } else {
                echo $OUTPUT->notification(nl2br(s($report)), \core\output\notification::NOTIFY_WARNING);
            }
        }
        // Grille de conformité enregistrée à la création de l'activité et plus à la première lecture :
        // éléments d'origine des activités (et des activités de suivi) qui n'en ont pas encore.
        these_upgrade_create_grid_items();
        upgrade_mod_savepoint(true, 2026103100, 'these');
    }

    return true;
}
