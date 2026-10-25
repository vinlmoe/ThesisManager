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
     * 2026102505 : les membres du jury et les signataires invités sont rattachés à leur compte
     * d'après leur adresse (ou celle de leur fiche de l'annuaire), quand il est unique et actif.
     */
    public function test_jury_and_signers_linked_to_accounts(): void {
        global $DB;
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'directorid' => $this->director->id, 'status' => THESE_STATUS_DEFENSE_VALIDATED,
        ]);
        $gen = $this->getDataGenerator();
        $president = $gen->create_user(['email' => 'president@example.org']);
        $directory = $gen->create_user(['email' => 'annuaire@example.org']);
        $gen->create_user(['email' => 'double@example.org']);
        $gen->create_user(['email' => 'double@example.org']);
        $jurorid = \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => 'Annuaire', 'firstname' => 'Anne',
            'email' => 'annuaire@example.org', 'status' => 'pr', 'active' => 1]);
        $rows = [
            ['juryrole' => 'president', 'fullname' => 'Paul Président', 'email' => 'president@example.org', 'jurorid' => 0],
            ['juryrole' => 'member', 'fullname' => 'Anne Annuaire', 'email' => '', 'jurorid' => $jurorid],
            ['juryrole' => 'member', 'fullname' => 'Deux Comptes', 'email' => 'double@example.org', 'jurorid' => 0],
            ['juryrole' => 'guest', 'fullname' => 'Sans Compte', 'email' => 'personne@example.org', 'jurorid' => 0],
        ];
        foreach ($rows as $i => $row) {
            $DB->insert_record('these_jury', (object) ($row + ['dossierid' => $dossier->id, 'jurytitle' => '',
                'institution' => '', 'sortorder' => $i, 'timecreated' => time(), 'userid' => 0]));
        }
        $DB->insert_record('these_signature', (object) ['dossierid' => $dossier->id, 'doctype' => 'signaletique',
            'slot' => 'president', 'userid' => 0, 'signername' => 'Paul Président', 'signeremail' => 'president@example.org',
            'token' => these_token_hash('x'), 'requesttime' => time(), 'timesigned' => 0, 'timecreated' => time()]);

        $previous = 2026102504;
        set_config('version', $previous, 'mod_these');
        $this->assertTrue(xmldb_these_upgrade($previous));

        $members = these_get_jury($dossier->id);
        $this->assertSame((int) $president->id, (int) $members[0]->userid);
        $this->assertSame((int) $directory->id, (int) $members[1]->userid);
        $this->assertSame(0, (int) $members[2]->userid);
        $this->assertSame(0, (int) $members[3]->userid);
        $signature = these_get_signatures($dossier->id)['signaletique:president'];
        $this->assertSame((int) $president->id, (int) $signature->userid);
    }
}
