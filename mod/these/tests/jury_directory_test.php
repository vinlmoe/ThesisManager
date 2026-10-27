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

        // Matière : colonne facultative, gardée quand le fichier n'en a pas.
        jurors::import_csv("title;lastname;firstname;email;status;institution;discipline\n"
            . "Pr;Martin;Jean;martin@example.org;pr;VetAgro Sup;Anatomie\n");
        $this->assertSame('Anatomie', jurors::get($pr)->discipline);
        jurors::import_csv("title;lastname;firstname;email;status;institution\nPr;Martin;Jean;martin@example.org;pr;VetAgro Sup\n");
        $this->assertSame('Anatomie', jurors::get($pr)->discipline);
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
        $this->assertNull(these_validate_jury($members, 2));
        $this->assertSame('ok', these_jury_eligibility($members[0]));
        // Le directeur siège sans voter : deux membres votants, président compris.
        $this->assertSame('ok', these_jury_eligibility($members[1]));
        $this->assertNotNull(these_validate_jury($members, 3));
        $this->assertSame('tocheck', these_jury_eligibility($members[2]));
        $this->assertSame('ok', these_jury_eligibility($members[3]));

        // Un maître de conférences ne préside pas.
        $data['jurorid'] = [0 => $mcf, 1 => $pr, 2 => 0, 3 => 0];
        $this->assertStringContainsString('Jean BERNARD', these_validate_jury(these_extract_submitted_jury($data), 2));
        // Un membre extérieur votant doit déclarer son statut.
        $data['jurorid'] = [0 => $pr, 1 => $mcf, 2 => 0, 3 => 0];
        $data['jurystatus'][2] = '';
        $this->assertStringContainsString('Claire Dupont', these_validate_jury(these_extract_submitted_jury($data), 2));
        // Pas deux fois le même enseignant.
        $data['jurystatus'][2] = 'other';
        $data['jurorid'][1] = $pr;
        $this->assertSame(
            get_string('juryduplicate', 'mod_these', 'Jean MARTIN'),
            these_validate_jury(these_extract_submitted_jury($data), 2)
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

    /**
     * Préremplissage : seuls les enseignants non éditeurs inscrits au cours et absents de l'annuaire
     * sont ajoutés ; ni la DEVE (enseignant éditeur), ni la bibliothèque, ni les étudiants.
     */
    public function test_prefill_from_course(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['institution' => 'VetAgro Sup']);
        $known = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher', ['email' => 'connu@example.org']);
        $this->juror('Connu', 'pr');

        $missing = jurors::course_teachers_missing($this->context);
        $this->assertSame([(int) $this->director->id], array_map('intval', array_keys($missing)));
        $this->assertArrayNotHasKey($known->id, $missing);

        $this->assertSame(1, jurors::prefill_from_course($this->context));
        $juror = jurors::find_by_email($this->director->email);
        $this->assertNotNull($juror);
        $this->assertSame($this->director->lastname, $juror->lastname);
        $this->assertSame('Dr', $juror->title);
        $this->assertSame('mcf', $juror->status);
        $this->assertSame('VetAgro Sup', $juror->institution);
        $this->assertEquals(1, $juror->active);
        $this->assertNull(jurors::find_by_email($this->deve->email));
        $this->assertNull(jurors::find_by_email($this->librarian->email));
        $this->assertNull(jurors::find_by_email($this->student->email));
        $this->assertSame('pr', jurors::find_by_email('connu@example.org')->status);

        // Une seconde fois : rien à ajouter.
        $this->assertSame([], jurors::course_teachers_missing($this->context));
        $this->assertSame(0, jurors::prefill_from_course($this->context));
    }

    /**
     * Conflit de jury : un membre (même enseignant de l'annuaire, même adresse ou même nom) qui siège
     * dans une autre soutenance qui chevauche celle-ci (durée des paramètres du circuit, une heure par
     * défaut) est signalé ; pas au-delà, ni pour un dossier abandonné.
     */
    public function test_jury_conflicts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_activity();
        $pr = $this->juror('Martin', 'pr');
        $time = make_timestamp(2026, 11, 4, 14);
        $make = function (int $defensedate, array $jury) {
            $studentid = $this->getDataGenerator()->create_and_enrol($this->course)->id;
            $dossier = $this->generator->create_dossier($this->these, $studentid, [
                'status' => THESE_STATUS_DEFENSE_VALIDATED, 'defensedate' => $defensedate,
            ]);
            these_save_jury($dossier->id, these_extract_submitted_jury($jury));
            return $this->reload($dossier->id);
        };
        $first = $make($time, ['juryrole' => [0 => 'president', 1 => 'member'], 'jurorid' => [0 => $pr],
            'juryname' => [1 => 'Claire Externe'], 'juryemail' => [1 => 'claire@example.org'], 'jurytitle' => [1 => 'Dr'],
            'juryinstitution' => [1 => 'Ailleurs'], 'jurystatus' => [1 => 'mcf']]);
        $second = $make($time + 30 * MINSECS, ['juryrole' => [0 => 'president'], 'jurorid' => [0 => $pr]]);
        $third = $make($time + 3 * HOURSECS, ['juryrole' => [0 => 'president'], 'jurorid' => [0 => $pr]]);

        $conflicts = these_jury_conflicts($first);
        $this->assertCount(1, $conflicts);
        $this->assertSame('Pr Jean MARTIN', $conflicts[0]->member);
        $this->assertEquals($time + 30 * MINSECS, $conflicts[0]->time);
        $this->assertCount(1, these_jury_conflicts($second));
        $this->assertSame([], these_jury_conflicts($third));
        $this->assertStringContainsString('Pr Jean MARTIN', these_render_jury_conflicts($first));
        $this->assertSame('', these_render_jury_conflicts($third));

        // Soutenances de quatre heures : la troisième chevauche les deux autres.
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['defenseduration' => 240]);
        $this->assertCount(2, these_jury_conflicts($third));
        \mod_these\local\scopesettings::set(these_local_scope($this->these), ['defenseduration' => 0]);
        $this->assertSame(60, \mod_these\local\scopesettings::for_these($this->these)['defenseduration']);
        $this->assertSame([], these_jury_conflicts($third));

        // Même adresse, hors annuaire.
        $other = (object) ['jurorid' => 0, 'fullname' => 'C. Externe', 'email' => 'Claire@example.org', 'jurytitle' => ''];
        $this->assertCount(1, these_jury_conflicts($second, [$other]));

        // Dossier abandonné : plus de conflit.
        $DB->set_field('these_dossier', 'status', THESE_STATUS_CANCELLED, ['id' => $second->id]);
        $this->assertSame([], these_jury_conflicts($first));
    }

    /**
     * Salles de soutenance : une par ligne des paramètres du circuit, sans doublon ; à défaut, le lieu
     * par défaut de l'ancien réglage de l'activité.
     */
    public function test_defense_rooms(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        $this->these->defaultlocation = 'Amphithéâtre';
        $this->assertSame(['Amphithéâtre'], these_defense_rooms($this->these));
        \mod_these\local\scopesettings::set(these_settings_scope($this->these), [
            'defenserooms' => "Salle du conseil\r\n\n  Salle 12  \nSalle du conseil",
        ]);
        $this->assertSame(['Salle du conseil', 'Salle 12'], these_defense_rooms($this->these));
        $this->these->defaultlocation = '';
        \mod_these\local\scopesettings::set(these_settings_scope($this->these), ['defenserooms' => '']);
        $this->assertSame([], these_defense_rooms($this->these));
    }

    /**
     * Liste du corps enseignant : enseignants actifs de l'établissement des paramètres, groupés par
     * statut (professeurs d'abord), avec leur matière, en trois colonnes (première colonne remplie
     * d'abord), rendue en PDF.
     */
    public function test_teaching_list(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_activity();
        $scope = these_settings_scope($this->these);
        \mod_these\local\scopesettings::set($scope, ['institution' => 'École vétérinaire']);
        $mcf = $this->juror('Bernard', 'mcf', 'Dr');
        $this->juror('Martin', 'pr');
        $external = $this->juror('Curie', 'pr');
        $inactive = $this->juror('Ancien', 'pr');
        $DB->set_field('these_juror', 'institution', 'École Vétérinaire  de la ville', ['id' => $mcf]);
        $DB->set_field('these_juror', 'institution', 'Oniris', ['id' => $external]);
        $DB->set_field('these_juror', 'active', 0, ['id' => $inactive]);
        $DB->set_field_select('these_juror', 'institution', 'École vétérinaire', 'id NOT IN (?, ?)', [$mcf, $external]);

        $data = jurors::teaching_list_context($scope, $this->these);
        $this->assertSame('École vétérinaire', $data['establishment']['name']);
        $this->assertSame(2, $data['count']);
        $labels = array_column($data['groups'], 'label');
        $this->assertSame([get_string('teachinggrouppr', 'mod_these'), get_string('teachinggroupmcf', 'mod_these')], $labels);
        $this->assertSame('Pr Jean MARTIN', $data['groups'][0]['members'][0]['name']);
        $this->assertSame('', $data['groups'][0]['members'][0]['discipline']);
        $this->assertStringStartsWith('%PDF', these_render_scope_doctemplate($scope, 'teachinglist', $data));

        // Quatre professeurs : deux lignes de trois cellules, la première colonne remplie d'abord.
        $DB->set_field('these_juror', 'discipline', 'Anatomie', ['id' => $mcf]);
        $this->juror('Albert', 'pr');
        $this->juror('Petit', 'pr');
        $this->juror('Zola', 'pr');
        $DB->set_field_select('these_juror', 'institution', 'École vétérinaire', 'id NOT IN (?, ?)', [$mcf, $external]);
        $data = jurors::teaching_list_context($scope, $this->these);
        $rows = $data['groups'][0]['rows'];
        $this->assertCount(2, $rows);
        $names = function (array $row) {
            return array_map(function ($cell) {
                return $cell['member']['name'] ?? null;
            }, $row['cells']);
        };
        $this->assertSame(['Pr Jean ALBERT', 'Pr Jean PETIT', 'Pr Jean ZOLA'], $names($rows[0]));
        $this->assertSame(['Pr Jean MARTIN', null, null], $names($rows[1]));
        $this->assertSame('Anatomie', $data['groups'][1]['members'][0]['discipline']);
        $body = \mod_these\pdf\html_document::parse(these_get_doctemplate($scope, 'teachinglist'))['body'];
        $html = \mod_these\pdf\html_document::mustache()->render($body, $data);
        $this->assertStringContainsString('Anatomie', $html);
    }

    /**
     * Annuaire en lecture seule : enseignants actifs internes et extérieurs, recherche sans accents
     * dans le nom, la matière et l'établissement, limitation à ceux qui peuvent présider ; ouvert aux
     * étudiants, sans adresse.
     */
    public function test_read_only_directory(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_activity();
        $internal = $this->juror('Martin', 'pr');
        $external = $this->juror('Curie', 'pr');
        $mcf = $this->juror('Bernard', 'mcf', 'Dr');
        $retired = $this->juror('Ancien', 'pr');
        $DB->set_field('these_juror', 'institution', 'Faculté de médecine de Lyon', ['id' => $external]);
        $DB->set_field('these_juror', 'discipline', 'Pharmacologie vétérinaire', ['id' => $internal]);
        $DB->set_field('these_juror', 'active', 0, ['id' => $retired]);

        $ids = function (array $jurors) {
            return array_map('intval', array_column($jurors, 'id'));
        };
        $this->assertSame([$mcf, $external, $internal], $ids(jurors::directory()));
        $this->assertSame([$external, $internal], $ids(jurors::directory('', true)));
        $this->assertSame([$external], $ids(jurors::directory('faculte MEDECINE', true)));
        $this->assertSame([$internal], $ids(jurors::directory('pharmacologie')));
        $this->assertSame([], $ids(jurors::directory('Ancien')));

        $url = new \moodle_url('/mod/these/directory.php', ['id' => $this->cm->id]);
        $data = (new \mod_these\output\juror_directory($url, jurors::directory('', true), '', true))
            ->export_for_template($GLOBALS['PAGE']->get_renderer('mod_these'));
        $this->assertTrue($data['presidents']);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Faculté de médecine de Lyon', $json);
        $this->assertStringNotContainsString('@example.org', $json);

        // Ouvert aux étudiants : lien dans la barre de navigation, sauf pour la DEVE qui tient l'annuaire.
        $this->setUser($this->student);
        $this->assertNotEmpty(these_require_view($this->context));
        $links = (new \mod_these\output\navlinks($this->cm, $this->context))->get_links();
        $this->assertArrayHasKey(get_string('jurordirectory', 'mod_these'), $links);
        $this->setUser($this->deve);
        $links = (new \mod_these\output\navlinks($this->cm, $this->context))->get_links();
        $this->assertArrayNotHasKey(get_string('jurordirectory', 'mod_these'), $links);
    }
}
