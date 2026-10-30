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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/these/tests/these_testcase.php');
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/mod/these/db/upgrade.php');

/**
 * Étapes de mise à jour du module.
 *
 * @package    mod_these
 * @category   test
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::xmldb_these_upgrade
 */
final class upgrade_test extends these_testcase {
    /**
     * 2026102504 : les documents réservés au personnel déposés dans la zone « generated » par les
     * versions précédentes en sont retirés ; les autres fichiers, et les mêmes noms dans d'autres
     * zones, sont conservés.
     */
    public function test_staff_only_documents_removed_from_generated_area(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['directorid' => $this->director->id]);

        $fs = get_file_storage();
        $record = ['contextid' => $this->context->id, 'component' => 'mod_these', 'itemid' => $dossier->id, 'filepath' => '/'];
        foreach (['report', 'grid', 'subject'] as $doctype) {
            $filename = these_document_filename($doctype);
            $fs->create_file_from_string($record + ['filearea' => 'generated', 'filename' => $filename], '%PDF');
        }
        $fs->create_file_from_string($record + ['filearea' => 'generated', 'filename' => 'convocation-etudiant.pdf'], '%PDF');
        $fs->create_file_from_string($record + ['filearea' => 'pv', 'filename' => these_document_filename('report')], '%PDF');

        $previous = 2026102503;
        set_config('version', $previous, 'mod_these');
        $this->assertTrue(xmldb_these_upgrade($previous));

        $names = array_map(function ($file) {
            return $file->get_filename();
        }, these_get_files($this->context, 'generated', $dossier->id));
        sort($names);
        $this->assertSame(['convocation-etudiant.pdf', these_document_filename('subject')], $names);
        $this->assertCount(1, these_get_files($this->context, 'pv', $dossier->id));
        $this->assertGreaterThan($previous, (int) get_config('mod_these', 'version'));
    }

    /**
     * 2026103001 : index et clé étrangère ajoutés, adresse de l'annuaire élargie, sur un schéma qui
     * en est dépourvu comme sur un schéma déjà conforme (étape rejouable).
     */
    public function test_indexes_and_juror_email_length(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $indexes = [
            ['these_log', 'userid', ['userid']],
            ['these_signature', 'userid', ['userid']],
            ['these_dossier', 'defensedate', ['defensedate']],
            ['these_subjecthistory', 'directorid', ['directorid']],
            ['these_subjecthistory', 'changedby', ['changedby']],
            ['these_question', 'theseid', ['theseid']],
        ];
        $exists = function () use ($dbman, $indexes) {
            $missing = [];
            foreach ($indexes as [$tablename, $name, $fields]) {
                if (!$dbman->index_exists(new \xmldb_table($tablename), new \xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields))) {
                    $missing[] = "$tablename.$name";
                }
            }
            return $missing;
        };
        $this->assertSame([], $exists(), 'install.xml');

        // Schéma de la version précédente : sans ces index, adresse à 100 caractères.
        foreach ($indexes as [$tablename, $name, $fields]) {
            $table = new \xmldb_table($tablename);
            if ($tablename === 'these_question') {
                $dbman->drop_key($table, new \xmldb_key($name, XMLDB_KEY_FOREIGN, $fields, 'these', ['id']));
            } else {
                $dbman->drop_index($table, new \xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, $fields));
            }
        }
        $table = new \xmldb_table('these_juror');
        $emailindex = new \xmldb_index('email', XMLDB_INDEX_NOTUNIQUE, ['email']);
        $dbman->drop_index($table, $emailindex);
        $dbman->change_field_precision($table, new \xmldb_field('email', XMLDB_TYPE_CHAR, '100', null, null, null, null));
        $dbman->add_index($table, $emailindex);
        $this->assertCount(6, $exists());
        $this->assertEquals(100, $DB->get_columns('these_juror')['email']->max_length);

        $previous = 2026103000;
        set_config('version', $previous, 'mod_these');
        $this->assertTrue(xmldb_these_upgrade($previous));
        $this->assertSame([], $exists(), 'après mise à jour');
        $this->assertEquals(255, $DB->get_columns('these_juror')['email']->max_length);
        $this->assertTrue($dbman->index_exists($table, $emailindex));
        $this->assertGreaterThan($previous, (int) get_config('mod_these', 'version'));

        // Rejouée sur un schéma déjà conforme : rien ne casse.
        set_config('version', $previous, 'mod_these');
        $this->assertTrue(xmldb_these_upgrade($previous));
        $this->assertSame([], $exists(), 'rejouée');
    }
}
