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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

use mod_these\local\jurors;

/**
 * Annuaire du jury, composition et éligibilité du jury, notification et accès des membres.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\jurors
 * @covers     ::these_extract_submitted_jury
 * @covers     ::these_validate_jury
 * @covers     ::these_save_jury
 * @covers     ::these_notify_jury
 */
final class jury_directory_test extends these_testcase {
    /**
     * Enseignant de l'annuaire.
     *
     * @param string $lastname
     * @param string $status
     * @param string $title
     * @return int
     */
    private function juror(string $lastname, string $status, string $title = 'Pr'): int {
        return jurors::save((object) ['title' => $title, 'lastname' => $lastname, 'firstname' => 'Jean',
            'email' => strtolower($lastname) . '@example.org', 'status' => $status, 'institution' => 'VetAgro Sup', 'active' => 1]);
    }

    /**
     * Statuts, présidence, options de l'annuaire, import CSV.
     */
    public function test_directory(): void {
        $this->resetAfterTest();
        $this->assertCount(8, jurors::statuses());
        foreach (['mcfhdr', 'pr', 'premerite', 'mcfemerite'] as $status) {
            $this->assertTrue(jurors::can_preside($status), $status);
        }
        foreach (['ir', 'mcf', 'mcfstag', 'prstag', 'other', ''] as $status) {
            $this->assertFalse(jurors::can_preside($status), $status);
        }
        $pr = $this->juror('Martin', 'pr');
        $mcf = $this->juror('Bernard', 'mcf', 'Dr');
        $retired = jurors::save((object) ['title' => 'Pr', 'lastname' => 'Ancien', 'firstname' => 'Paul', 'email' => '',
            'status' => 'premerite', 'institution' => '', 'active' => 0]);
        $this->assertArrayHasKey($pr, jurors::options());
        $this->assertArrayHasKey($mcf, jurors::options());
        $this->assertArrayNotHasKey($mcf, jurors::options(true));
        $this->assertArrayNotHasKey($retired, jurors::options());
        $this->assertArrayHasKey($retired, jurors::options(false, [$retired]));
        $this->assertSame('Pr MARTIN Jean', jurors::name(jurors::get($pr)));

        $result = jurors::import_csv("title;lastname;firstname;email;status;institution\n"
            . "Pr;Martin;Jean;martin@example.org;" . get_string('jurystatuspremerite', 'mod_these') . ";VetAgro Sup\n"
            . "Dr;Petit;Anne;anne.petit@example.org;mcfhdr;ENVT\n"
            . "Xx;Faux;Nom;;pr;\n");
        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertCount(1, $result->errors);
        $this->assertSame('premerite', jurors::get($pr)->status);
    }

    /**
     * Saisie du jury depuis l'annuaire, membres extérieurs, règles de présidence.
     */
    public function test_jury_composition(): void {
        $this->resetAfterTest();
        $pr = $this->juror('Martin', 'pr');
        $mcf = $this->juror('Bernard', 'mcf', 'Dr');
        $data = [
            'juryrole' => [0 => 'president', 1 => 'director', 2 => 'member', 3 => 'guest'],
            'jurorid' => [0 => $pr, 1 => $mcf, 2 => 0, 3 => 0],
            'juryname' => [2 => 'Claire Dupont', 3 => 'Luc Invité'],
            'jurytitle' => [2 => 'Dr', 3 => 'Dr'],
            'jurystatus' => [2 => 'other', 3 => ''],
            'juryinstitution' => [2 => 'Clinique', 3 => ''],
            'juryemail' => [2 => 'claire@example.org', 3 => ''],
        ];
        $members = these_extract_submitted_jury($data);
        $this->assertCount(4, $members);
        $this->assertSame('Jean MARTIN', $members[0]->fullname);
        $this->assertSame('pr', $members[0]->jurystatus);
        $this->assertSame('martin@example.org', $members[0]->email);
        $this->assertSame((int) $pr, $members[0]->jurorid);
        $this->assertNull(these_validate_jury($members, 3));
        $this->assertSame('ok', these_jury_eligibility($members[0]));
        $this->assertSame('tocheck', these_jury_eligibility($members[2]));
        $this->assertSame('ok', these_jury_eligibility($members[3]));

        // Un maître de conférences ne préside pas.
        $data['jurorid'] = [0 => $mcf, 1 => $pr, 2 => 0, 3 => 0];
        $this->assertStringContainsString('Jean BERNARD', these_validate_jury(these_extract_submitted_jury($data), 3));
        // Un membre extérieur votant doit déclarer son statut.
        $data['jurorid'] = [0 => $pr, 1 => $mcf, 2 => 0, 3 => 0];
        $data['jurystatus'][2] = '';
        $this->assertStringContainsString('Claire Dupont', these_validate_jury(these_extract_submitted_jury($data), 3));
        // Pas deux fois le même enseignant.
        $data['jurystatus'][2] = 'other';
        $data['jurorid'][1] = $pr;
        $this->assertSame(
            get_string('juryduplicate', 'mod_these', 'Jean MARTIN'),
            these_validate_jury(these_extract_submitted_jury($data), 3)
        );

        // Validation de l'éligibilité par la DEVE : chaque membre votant coché.
        $data['jurorid'][1] = $mcf;
        $data['juryeligible'] = [0 => 1, 1 => 1, 2 => 0, 3 => 0];
        $members = these_extract_submitted_jury($data, true);
        $this->assertStringContainsString('Claire Dupont', these_validate_jury_eligibility($members));
        $data['juryeligible'][2] = 1;
        $this->assertNull(these_validate_jury_eligibility(these_extract_submitted_jury($data, true)));
    }

    /**
     * Le même membre garde son accès et sa validation ; la notification donne l'accès aux
     * documents, une seule fois sauf renvoi.
     */
    public function test_notification_and_access(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED,
            'title' => 'Leptospirose',
        ]);
        $pr = $this->juror('Martin', 'pr');
        $members = [
            (object) ['juryrole' => 'president', 'fullname' => 'Jean MARTIN', 'jurytitle' => 'Pr', 'institution' => 'VetAgro Sup',
                'email' => 'martin@example.org', 'jurorid' => $pr, 'jurystatus' => 'pr', 'eligible' => true],
            (object) ['juryrole' => 'member', 'fullname' => 'Claire Dupont', 'jurytitle' => 'Dr', 'institution' => 'Clinique',
                'email' => 'claire@example.org', 'jurorid' => 0, 'jurystatus' => 'other', 'eligible' => true],
            (object) ['juryrole' => 'guest', 'fullname' => 'Luc Invité', 'jurytitle' => '', 'institution' => '',
                'email' => '', 'jurorid' => 0, 'jurystatus' => ''],
        ];
        these_save_jury($dossier->id, $members, $this->deve->id);
        $jury = these_get_jury($dossier->id);
        $this->assertEquals(1, $jury[0]->eligible);
        $this->assertEquals($this->deve->id, $jury[0]->eligibleby);

        $sink = $this->redirectEmails();
        $result = these_notify_jury($this->these, $this->cm, $dossier);
        $this->assertSame(2, $result->sent);
        $this->assertSame(['Luc Invité'], $result->missing);
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('jury.php?token=', quoted_printable_decode($messages[0]->body));
        $sink->clear();

        $jury = these_get_jury($dossier->id);
        $first = $this->token_sent_to($messages, 'martin@example.org', 'jury.php');
        $this->assertSame(these_token_hash($first), $jury[0]->accesstoken);
        $this->assertNotEmpty($jury[0]->notifiedtime);
        $access = these_get_jury_access($first);
        $this->assertSame((int) $dossier->id, (int) $access->dossier->id);
        $this->assertNull(these_get_jury_access('inconnu'));
        $this->assertNull(these_get_jury_access($jury[0]->accesstoken));

        // Déjà prévenus : rien de plus, sauf renvoi, qui remplace le lien.
        $this->assertSame(0, these_notify_jury($this->these, $this->cm, $dossier)->sent);
        $this->assertSame(2, these_notify_jury($this->these, $this->cm, $dossier, true)->sent);
        $token = $this->token_sent_to($sink->get_messages(), 'martin@example.org', 'jury.php');
        $this->assertNull(these_get_jury_access($first));
        $this->assertNotNull(these_get_jury_access($token));
        $hash = these_get_jury($dossier->id)[0]->accesstoken;

        // Jury modifié par l'étudiant : le même membre garde son lien et sa validation ; un
        // changement de rôle demande une nouvelle validation.
        $members[1]->juryrole = 'director';
        unset($members[0]->eligible, $members[1]->eligible);
        these_save_jury($dossier->id, $members);
        $jury = these_get_jury($dossier->id);
        $this->assertSame($hash, $jury[0]->accesstoken);
        $this->assertEquals(1, $jury[0]->eligible);
        $this->assertEquals(0, $jury[1]->eligible);

        // Dossier abandonné : plus d'accès.
        $DB->set_field('these_dossier', 'status', THESE_STATUS_CANCELLED, ['id' => $dossier->id]);
        $this->assertNull(these_get_jury_access($token));
        $sink->close();
    }

    /**
     * Un enseignant retiré de l'annuaire reste dans les jurys, détaché de l'annuaire.
     */
    public function test_delete_juror_keeps_jury(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, []);
        $pr = $this->juror('Martin', 'pr');
        these_save_jury($dossier->id, these_extract_submitted_jury(['juryrole' => [0 => 'president'], 'jurorid' => [0 => $pr]]));
        jurors::delete($pr);
        $jury = these_get_jury($dossier->id);
        $this->assertSame('Jean MARTIN', $jury[0]->fullname);
        $this->assertEquals(0, $jury[0]->jurorid);
        $this->assertSame('pr', $jury[0]->jurystatus);
    }
}
