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

/**
 * Outils de la DEVE : conflits de salle, action directe de la file « À traiter », actions groupées,
 * réglages du circuit à compléter ; agents de la bibliothèque.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\jury::room_conflicts
 * @covers     \mod_these\local\rendering::primary_action
 * @covers     \mod_these\local\bulk
 * @covers     \mod_these\local\scopesettings::checklist
 * @covers     \mod_these\local\scopesettings::library_agents
 * @covers     \mod_these\local\rights
 * @covers     \mod_these\local\calendar
 * @covers     \mod_these\local\notifications::digest_content
 * @covers     \mod_these\local\notifications::send_weekly_digests
 */
final class deve_tools_test extends these_testcase {
    /**
     * Même salle (casse et espaces ignorés) sur des soutenances qui se chevauchent ; une autre salle,
     * un créneau disjoint ou un dossier abandonné ne comptent pas.
     */
    public function test_room_conflicts(): void {
        $this->setup_activity();
        $time = make_timestamp(2026, 11, 4, 14);
        $make = function (int $date, string $location, int $status = THESE_STATUS_DEFENSE_VALIDATED) {
            $studentid = $this->getDataGenerator()->create_and_enrol($this->course)->id;
            return $this->generator->create_dossier($this->these, $studentid, [
                'status' => $status, 'defensedate' => $date, 'defenselocation' => $location,
            ]);
        };
        $first = $make($time, 'Salle du conseil');
        $this->assertSame([], these_room_conflicts($first));
        $make($time + 30 * MINSECS, '  salle  DU conseil ');
        $make($time + 15 * MINSECS, 'Amphithéâtre');
        $make($time + 2 * HOURSECS, 'Salle du conseil');
        $make($time, 'Salle du conseil', THESE_STATUS_CANCELLED);
        $conflicts = these_room_conflicts($first);
        $this->assertCount(1, $conflicts);
        $this->assertSame($time + 30 * MINSECS, $conflicts[0]->time);
        $this->assertSame([], these_room_conflicts($first, 'Salle 12'));
        $this->assertSame([], these_room_conflicts($make($time, '')));
        $this->assertStringContainsString(get_string('roomconflicts', 'mod_these'), these_render_room_conflicts($first));
    }

    /**
     * Action directe : la validation de l'étape pour son acteur, les documents puis le procès-verbal
     * pour la DEVE ; rien pour qui n'est pas attendu.
     */
    public function test_primary_action(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'directorid' => $this->director->id,
        ]);
        $rights = function ($user, $dossier) {
            return these_get_rights($this->context, $dossier, $user->id);
        };
        [$label, $url] = these_primary_action($this->cm, $dossier, $rights($this->deve, $dossier));
        $this->assertSame(get_string('reviewdevedefense', 'mod_these'), $label);
        $this->assertStringContainsString('/mod/these/review.php', $url->out(false));
        $this->assertNull(these_primary_action($this->cm, $dossier, $rights($this->director, $dossier)));

        $dossier->status = THESE_STATUS_CONFORM;
        $url = these_primary_action($this->cm, $dossier, $rights($this->deve, $dossier))[1];
        $this->assertStringContainsString('/mod/these/documents.php', $url->out(false));
        $dossier->status = THESE_STATUS_SCHEDULED;
        $url = these_primary_action($this->cm, $dossier, $rights($this->deve, $dossier))[1];
        $this->assertStringContainsString('/mod/these/minutes.php', $url->out(false));
    }

    /**
     * Actions groupées : chacune ne porte que sur les dossiers dont l'étape s'y prête.
     */
    public function test_bulk(): void {
        $this->setup_activity();
        $this->assertSame(['remind', 'generate', 'notifyjury'], array_keys(\mod_these\local\bulk::actions()));
        $validated = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED,
        ]);
        these_save_jury($validated->id, [
            (object) ['juryrole' => 'president', 'fullname' => 'Jean MARTIN', 'jurytitle' => 'Pr', 'institution' => 'VetAgro Sup',
                'email' => 'martin@example.org', 'jurorid' => 0, 'jurystatus' => 'pr', 'eligible' => true],
        ], $this->deve->id);
        $other = $this->getDataGenerator()->create_and_enrol($this->course)->id;
        $active = $this->generator->create_dossier($this->these, $other, ['status' => THESE_STATUS_ACTIVE]);

        $apply = function ($dossier, $action) {
            return \mod_these\local\bulk::apply($this->these, $this->cm, $this->context, $dossier, $action, $this->deve->id);
        };
        $sink = $this->redirectEmails();
        $this->assertTrue($apply($validated, 'notifyjury'));
        $this->assertFalse($apply($validated, 'notifyjury'));
        $this->assertFalse($apply($active, 'notifyjury'));
        $this->assertFalse($apply($active, 'generate'));
        $this->assertFalse($apply($active, 'unknown'));
        $this->assertCount(1, $sink->get_messages());
    }

    /**
     * Réglages à compléter : les éléments manquants, puis complets une fois renseignés ; les agents
     * DEVE et le questionnaire sont facultatifs.
     */
    public function test_settings_checklist(): void {
        $this->setup_activity();
        $scope = these_settings_scope($this->these);
        $checklist = \mod_these\local\scopesettings::checklist($scope);
        $required = array_filter($checklist, function ($item) {
            return !$item['optional'];
        });
        $this->assertNotEmpty($required);
        $this->assertCount(3, array_filter($checklist, function ($item) {
            return $item['optional'];
        }));
        $rooms = array_values(array_filter($checklist, function ($item) {
            return $item['label'] === get_string('settingscheckrooms', 'mod_these');
        }))[0];
        $this->assertFalse($rooms['complete']);

        \mod_these\local\scopesettings::set($scope, ['defenserooms' => 'Salle du conseil', 'institution' => 'École',
            'university' => "l'Université"]);
        $complete = array_column(\mod_these\local\scopesettings::checklist($scope), 'complete', 'label');
        $this->assertTrue($complete[get_string('settingscheckrooms', 'mod_these')]);
        $this->assertTrue($complete[get_string('settingscheckinstitution', 'mod_these')]);
        $this->assertFalse($complete[get_string('settingscheckcontract', 'mod_these')]);
    }

    /**
     * Agents de la bibliothèque désignés dans les paramètres : droits de la bibliothèque sans rôle,
     * jamais ceux de la DEVE ni la direction de thèse, destinataires des courriels de la bibliothèque.
     */
    public function test_library_agents(): void {
        $this->setup_activity();
        $gen = $this->getDataGenerator();
        $editor = $gen->create_and_enrol($this->course, 'editingteacher', ['username' => 'biblio1']);
        $teacher = $gen->create_and_enrol($this->course, 'teacher', ['email' => 'biblio2@example.org']);
        $this->assertTrue(these_get_rights($this->context, null, $editor->id)->deve);

        \mod_these\local\scopesettings::set(these_settings_scope($this->these), ['libraryagent' => "biblio1\nBIBLIO2@example.org"]);
        $agents = array_keys(\mod_these\local\scopesettings::library_agents($this->context));
        $this->assertSame([(int) $editor->id, (int) $teacher->id], $agents);
        foreach ([$editor, $teacher] as $agent) {
            $rights = these_get_rights($this->context, null, $agent->id);
            $this->assertFalse($rights->deve);
            $this->assertTrue($rights->library);
            $this->assertTrue($rights->viewall);
        }
        $this->assertArrayNotHasKey($teacher->id, these_get_potential_directors($this->context));
        $this->assertArrayHasKey($this->director->id, these_get_potential_directors($this->context));
        $this->assertFalse(\mod_these\local\rights::can_manage($this->context, 'mod/these:managedeve', $editor->id));
        $this->assertTrue(\mod_these\local\rights::can_manage($this->context, 'mod/these:managedeve', $this->deve->id));

        $recipients = \mod_these\local\notifications::get_service_recipients($this->these, $this->context, 'library');
        $library = array_column($recipients, 'id');
        $this->assertContains($editor->id, $library);
        $this->assertContains($teacher->id, $library);
        $deve = array_column(\mod_these\local\notifications::get_service_recipients($this->these, $this->context, 'deve'), 'id');
        $this->assertNotContains($editor->id, $deve);
        $this->assertContains($this->deve->id, $deve);

        $this->setUser($editor);
        $this->expectException(\required_capability_exception::class);
        these_require_service($this->context, 'deve');
    }

    /**
     * Calendrier : un événement de cours quand la DEVE arrête la date, déplacé avec la soutenance,
     * supprimé à l'abandon ; le titre d'une thèse confidentielle n'y figure pas.
     */
    public function test_calendar_events(): void {
        global $DB;
        $this->setup_activity();
        $time = make_timestamp(2026, 11, 4, 14);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_DEFENSE_APPROVED, 'defensedate' => $time, 'defenselocation' => 'Salle du conseil',
            'title' => 'Sujet secret', 'confidential' => 1,
        ]);
        $events = function () use ($dossier) {
            global $DB;
            return array_values($DB->get_records('event', ['modulename' => 'these', 'uuid' => 'these-dossier-' . $dossier->id]));
        };
        $this->assertSame([], $events());

        $dossier = these_set_status($dossier, THESE_STATUS_DEFENSE_VALIDATED, $this->deve->id, 'validated');
        $event = $events()[0];
        $this->assertSame($time, (int) $event->timestart);
        $this->assertSame(HOURSECS, (int) $event->timeduration);
        $this->assertSame((int) $this->course->id, (int) $event->courseid);
        $this->assertStringContainsString(fullname($this->student), $event->name);
        $this->assertStringContainsString('Salle du conseil', $event->description);
        $this->assertStringNotContainsString('Sujet secret', $event->description);

        $dossier = these_set_status(
            $dossier,
            THESE_STATUS_DEFENSE_VALIDATED,
            $this->deve->id,
            'edited',
            '',
            ['defensedate' => $time + DAYSECS]
        );
        $this->assertCount(1, $events());
        $this->assertSame($time + DAYSECS, (int) $events()[0]->timestart);

        these_set_status($dossier, THESE_STATUS_CANCELLED, $this->deve->id, 'cancelled');
        $this->assertSame([], $events());
        // La synchronisation quotidienne ne le recrée pas.
        \mod_these\local\calendar::sync_all();
        $this->assertSame([], $events());
    }

    /**
     * Récapitulatif hebdomadaire : dossiers qui attendent la DEVE et soutenances de la semaine, envoyé
     * à la DEVE ; rien quand il est désactivé.
     */
    public function test_weekly_digest(): void {
        $this->setup_activity();
        $now = time();
        $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_SUBJECT_APPROVED]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course)->id;
        $this->generator->create_dossier($this->these, $other, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED, 'defensedate' => $now + 2 * DAYSECS, 'defenselocation' => 'Salle 12',
        ]);
        $content = \mod_these\local\notifications::digest_content($this->these, $now);
        $this->assertCount(1, $content['todo']);
        $this->assertCount(1, $content['defenses']);
        $this->assertStringContainsString('Salle 12', $content['defenses'][0]);

        $sink = $this->redirectEmails();
        $this->assertSame(1, \mod_these\local\notifications::send_weekly_digests($now));
        $this->assertContains($this->deve->email, array_column($sink->get_messages(), 'to'));
        \mod_these\local\scopesettings::set(these_settings_scope($this->these), ['weeklydigest' => 0]);
        $this->assertSame(0, \mod_these\local\notifications::send_weekly_digests($now));
    }
}
