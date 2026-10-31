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

namespace mod_thesesynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/thesesynthesis/locallib.php');

/**
 * Suivi des thèses sur plusieurs promotions : liens, dossiers suivis selon les droits à la source,
 * jurys de l'utilisateur.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::thesesynthesis_get_dossiers
 * @covers     ::thesesynthesis_count_dossiers
 * @covers     ::thesesynthesis_get_juries
 * @covers     ::thesesynthesis_get_available_activities
 * @covers     \mod_these\page\planning_page
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_get_dossiers')]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_count_dossiers')]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_get_juries')]
#[\PHPUnit\Framework\Attributes\CoversFunction('thesesynthesis_get_available_activities')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\page\planning_page::class)]
final class synthesis_test extends \advanced_testcase {
    /**
     * Deux promotions (deux cours), un directeur dans les deux, une DEVE dans la première seulement,
     * et une activité de suivi qui les lie.
     *
     * @return \stdClass
     */
    private function setup_promotions(): \stdClass {
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        $env = new \stdClass();
        $env->director = $gen->create_user(['email' => 'directeur@example.org']);
        $env->deve = $gen->create_user();
        $env->juror = $gen->create_user(['email' => 'jury@example.org']);
        $env->activities = [];
        foreach (['Promo 2026', 'Promo 2027'] as $i => $name) {
            $course = $gen->create_course(['fullname' => $name, 'shortname' => 'P' . $i]);
            $these = $gen->create_module('these', ['course' => $course->id]);
            $gen->enrol_user($env->director->id, $course->id, 'teacher');
            if ($i === 0) {
                $gen->enrol_user($env->deve->id, $course->id, 'editingteacher');
            }
            $student = $gen->create_and_enrol($course, 'student');
            $dossier = $thesegen->create_dossier($these, $student->id, ['directorid' => $env->director->id,
                'status' => $i === 0 ? THESE_STATUS_SUBJECT_SUBMITTED : THESE_STATUS_SUBJECT_APPROVED]);
            $env->activities[] = (object) ['course' => $course, 'these' => $these, 'dossier' => $dossier];
        }
        $synthcourse = $gen->create_course();
        $env->synthesis = $gen->create_module('thesesynthesis', ['course' => $synthcourse->id]);
        thesesynthesis_set_links($env->synthesis->id, array_map(function ($activity) {
            return $activity->these->cmid;
        }, $env->activities));
        return $env;
    }

    /**
     * Le directeur voit ses thèses des deux promotions, la DEVE tous les dossiers de la sienne ;
     * « à traiter » selon l'acteur attendu.
     */
    public function test_followed_dossiers(): void {
        $this->resetAfterTest();
        $env = $this->setup_promotions();

        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->director->id);
        $rows = thesesynthesis_get_dossiers($links, $env->director->id);
        $this->assertCount(2, $rows);
        $todo = array_values(array_filter($rows, function ($row) {
            return $row->todo;
        }));
        $this->assertCount(1, $todo);
        $this->assertEquals($env->activities[0]->dossier->id, $todo[0]->dossier->id);

        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->deve->id);
        $rows = thesesynthesis_get_dossiers($links, $env->deve->id);
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->todo);

        // Filtre de statut et de promotion.
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->director->id);
        $this->assertCount(1, thesesynthesis_get_dossiers($links, $env->director->id, ['status' => THESE_STATUS_SUBJECT_APPROVED]));
        $this->assertCount(1, thesesynthesis_get_dossiers(
            $links,
            $env->director->id,
            ['cmid' => $env->activities[1]->these->cmid]
        ));

        // Activités proposées au lien : celles où l'on voit tous les dossiers.
        $available = thesesynthesis_get_available_activities($env->deve->id);
        $this->assertSame([(int) $env->activities[0]->these->cmid], array_keys($available));
        $this->assertSame([], thesesynthesis_get_available_activities($env->director->id));
    }

    /**
     * Jurys de l'utilisateur : membre choisi dans l'annuaire ou rattaché à la validation de son
     * éligibilité par la DEVE ; ni un autre compte portant la même adresse, ni un compte suspendu.
     */
    public function test_juries(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $jurorid = \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => 'Jury', 'firstname' => 'Paul',
            'email' => 'JURY@example.org', 'status' => 'pr', 'institution' => '', 'active' => 1]);
        these_save_jury($env->activities[0]->dossier->id, [
            (object) ['juryrole' => 'president', 'fullname' => 'Paul JURY', 'jurytitle' => 'Pr', 'institution' => '',
                'email' => '', 'jurorid' => $jurorid, 'jurystatus' => 'pr'],
        ]);
        these_save_jury($env->activities[1]->dossier->id, [
            (object) ['juryrole' => 'member', 'fullname' => 'Paul Jury', 'jurytitle' => 'Pr', 'institution' => '',
                'email' => 'jury@example.org', 'jurorid' => 0, 'jurystatus' => 'pr', 'eligible' => true],
        ], $env->deve->id);
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->juror->id);
        $juries = thesesynthesis_get_juries($links, $env->juror);
        $this->assertCount(2, $juries);
        $this->assertSame([], thesesynthesis_get_juries($links, $env->director));

        // Un autre compte à la même adresse n'est pas reconnu ; un compte suspendu ne l'est plus.
        $homonym = $this->getDataGenerator()->create_user(['email' => 'jury@example.org']);
        $this->assertSame([], thesesynthesis_get_juries($links, $homonym));
        $DB->set_field('user', 'suspended', 1, ['id' => $env->juror->id]);
        $this->assertSame([], thesesynthesis_get_juries($links, $env->juror));
    }

    /**
     * Le nombre de lectures en base ne croît pas avec le nombre de dossiers suivis : étudiants et
     * directeurs sont chargés en une requête par activité, et la connaissance du sujet des thèses
     * confidentielles est calculée une fois par activité, y compris pour qui n'est reconnu que par
     * les jurys (un détenteur de viewall sans rôle de service).
     */
    public function test_reads_do_not_grow_with_dossiers(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        $activity = $env->activities[0];
        $roleid = $gen->create_role(['shortname' => 'theseviewall']);
        assign_capability('mod/these:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        assign_capability('mod/these:viewall', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $viewall = $gen->create_user(['email' => 'viewall@example.org']);
        $gen->enrol_user($viewall->id, $activity->course->id, $roleid);
        accesslib_clear_all_caches_for_unit_testing();

        $add = function (int $count) use ($gen, $thesegen, $env, $activity, $DB) {
            for ($i = 0; $i < $count; $i++) {
                $student = $gen->create_and_enrol($activity->course, 'student');
                $dossier = $thesegen->create_dossier($activity->these, $student->id, ['directorid' => $env->director->id,
                    'status' => THESE_STATUS_ACTIVE, 'confidential' => $i % 2]);
                if ($i % 2) {
                    these_save_jury($dossier->id, [(object) ['juryrole' => 'member', 'fullname' => 'Vue Totale',
                        'jurytitle' => 'Pr', 'institution' => '', 'email' => 'viewall@example.org', 'jurorid' => 0,
                        'jurystatus' => 'pr']]);
                }
                // Une réponse au questionnaire et une signature du directeur attendue par dossier.
                $DB->insert_record('these_answer', (object) ['dossierid' => $dossier->id, 'questionid' => 1,
                    'value' => 'Réponse ' . $i, 'timemodified' => time()]);
                $DB->insert_record('these_signature', (object) ['dossierid' => $dossier->id, 'doctype' => 'subject',
                    'slot' => 'director', 'userid' => 0, 'signername' => '', 'signeremail' => '', 'token' => null,
                    'requesttime' => time() - DAYSECS, 'timesigned' => 0, 'timecreated' => time()]);
            }
        };
        // Lectures de chaque page (liste des dossiers, recherche par texte, signatures en attente)
        // et nombre de lignes obtenues, après une première exécution qui remplit les caches.
        $reads = function (\stdClass $user) use ($DB, $env) {
            $links = thesesynthesis_get_active_links($env->synthesis->id, $user->id);
            $pages = [
                'dossiers' => function () use ($links, $user) {
                    return thesesynthesis_get_dossiers($links, $user->id);
                },
                'search' => function () use ($links, $user) {
                    return thesesynthesis_search($links, $user->id, ['text' => 'totale']);
                },
                'signatures' => function () use ($links, $user) {
                    return thesesynthesis_get_signatures($links, $user)['awaited'];
                },
            ];
            $result = [];
            foreach ($pages as $page => $load) {
                $load();
                $before = $DB->perf_get_reads();
                $rows = $load();
                $result[$page] = [$DB->perf_get_reads() - $before, count($rows)];
            }
            return $result;
        };

        $add(2);
        $fewdeve = $reads($env->deve);
        $this->assertSame([3, 1, 2], array_column($fewdeve, 1));
        $fewviewall = $reads($viewall);
        $this->assertSame([3, 1, 0], array_column($fewviewall, 1));

        $add(6);
        $manydeve = $reads($env->deve);
        $this->assertSame([9, 4, 8], array_column($manydeve, 1));
        $manyviewall = $reads($viewall);
        $this->assertSame([9, 4, 0], array_column($manyviewall, 1));

        $this->assertSame(array_column($fewdeve, 0), array_column($manydeve, 0));
        $this->assertSame(array_column($fewviewall, 0), array_column($manyviewall, 0));
    }

    /**
     * Pagination des dossiers suivis avec des droits mixtes : DEVE dans la première promotion, simple
     * directeur dans la seconde. Les droits et les filtres sont appliqués dans la requête : chaque page
     * est pleine (sauf la dernière), les pages se suivent sans doublon ni manque, dans un ordre stable
     * (dernière modification, puis dossier), et le total compté en base est celui de la liste complète.
     */
    public function test_pagination_with_mixed_rights(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        // La DEVE de la première promotion dirige aussi des thèses de la seconde.
        $gen->enrol_user($env->deve->id, $env->activities[1]->course->id, 'teacher');
        $other = $gen->create_user();
        $statuses = [THESE_STATUS_SUBJECT_APPROVED, THESE_STATUS_ACTIVE, THESE_STATUS_SUBJECT_SUBMITTED];
        $expected = [(int) $env->activities[0]->dossier->id];
        foreach ($env->activities as $i => $activity) {
            for ($n = 0; $n < 9; $n++) {
                $student = $gen->create_and_enrol($activity->course, 'student', ['lastname' => 'Élève' . $i . $n]);
                $mine = $n % 3 === 0;
                $dossier = $thesegen->create_dossier($activity->these, $student->id, [
                    'directorid' => $mine ? $env->deve->id : $other->id,
                    'status' => $statuses[$n % 3],
                    'title' => 'Thèse ' . $i . '-' . $n,
                    // Dates égales deux à deux : l'ordre repose alors sur le numéro du dossier.
                    'timemodified' => 1000 + intdiv($n, 2),
                ]);
                if ($i === 0 || $mine) {
                    $expected[] = (int) $dossier->id;
                }
            }
        }
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->deve->id);
        $ids = function (array $rows) {
            return array_map(function ($row) {
                return (int) $row->dossier->id;
            }, $rows);
        };

        // Liste complète : les neuf dossiers de la promotion de la DEVE, plus le sien, et les trois qu'elle
        // dirige dans l'autre, du plus récent au plus ancien.
        $all = thesesynthesis_get_dossiers($links, $env->deve->id);
        sort($expected);
        $sorted = $ids($all);
        sort($sorted);
        $this->assertSame($expected, $sorted);
        $order = array_map(function ($row) {
            return [-(int) $row->dossier->timemodified, -(int) $row->dossier->id];
        }, $all);
        $sortedorder = $order;
        sort($sortedorder);
        $this->assertSame($sortedorder, $order);
        $this->assertSame(count($all), thesesynthesis_count_dossiers($links, $env->deve->id));

        // Pages de quatre : pleines sauf la dernière, et bout à bout égales à la liste complète.
        $paged = [];
        for ($page = 0; $page * 4 < count($all); $page++) {
            $rows = thesesynthesis_get_dossiers($links, $env->deve->id, [], $page * 4, 4);
            $this->assertCount(min(4, count($all) - $page * 4), $rows);
            $paged = array_merge($paged, $ids($rows));
        }
        $this->assertSame($ids($all), $paged);

        // Filtres conservés d'une page à l'autre : statut, promotion, texte.
        foreach (
            [
                ['status' => THESE_STATUS_SUBJECT_SUBMITTED],
                ['cmid' => $env->activities[1]->these->cmid],
                ['search' => 'eleve1'],
                ['search' => 'Thèse 0-'],
                ['status' => THESE_STATUS_ACTIVE, 'cmid' => $env->activities[0]->these->cmid, 'search' => 'élève0'],
            ] as $filters
        ) {
            $all = thesesynthesis_get_dossiers($links, $env->deve->id, $filters);
            $this->assertNotEmpty($all, json_encode($filters));
            $this->assertSame(count($all), thesesynthesis_count_dossiers($links, $env->deve->id, $filters));
            $paged = [];
            for ($page = 0; $page * 2 < count($all); $page++) {
                $paged = array_merge($paged, $ids(thesesynthesis_get_dossiers($links, $env->deve->id, $filters, $page * 2, 2)));
            }
            $this->assertSame($ids($all), $paged, json_encode($filters));
            foreach ($all as $row) {
                if (isset($filters['status'])) {
                    $this->assertEquals($filters['status'], $row->dossier->status);
                }
                if (isset($filters['cmid'])) {
                    $this->assertEquals($filters['cmid'], $row->link->cm->id);
                }
            }
        }
        $this->assertSame(3, thesesynthesis_count_dossiers($links, $env->deve->id, ['search' => 'eleve1']));
        // Le numéro DEVE se cherche aussi.
        $DB->set_field('these_dossier', 'devenumber', 'DEVE-2026-042', ['id' => $expected[3]]);
        $this->assertSame([$expected[3]], $ids(thesesynthesis_get_dossiers($links, $env->deve->id, ['search' => '2026-042'])));

        // File « À traiter » : mêmes dossiers que ceux marqués à traiter dans la liste complète.
        $all = thesesynthesis_get_dossiers($links, $env->deve->id);
        $todo = array_values(array_filter($all, function ($row) {
            return $row->todo;
        }));
        $this->assertNotEmpty($todo);
        $this->assertSame($ids($todo), $ids(thesesynthesis_get_dossiers($links, $env->deve->id, ['todo' => true])));
        $this->assertSame(count($todo), thesesynthesis_count_dossiers($links, $env->deve->id, ['todo' => true]));
        $this->assertSame(
            array_slice($ids($todo), 2, 2),
            $ids(thesesynthesis_get_dossiers($links, $env->deve->id, ['todo' => true], 2, 2))
        );
        // Le directeur seul : les thèses qu'il dirige, et parmi elles celles à son étape.
        $links = thesesynthesis_get_active_links($env->synthesis->id, $env->director->id);
        $this->assertSame(2, thesesynthesis_count_dossiers($links, $env->director->id));
        $this->assertSame(
            [(int) $env->activities[0]->dossier->id],
            $ids(thesesynthesis_get_dossiers($links, $env->director->id, ['todo' => true]))
        );
    }

    /**
     * Thèse confidentielle dans la liste paginée : un détenteur de mod/these:viewall sans rôle de service
     * ne la trouve pas par son titre (ni dans la page, ni dans le total), mais par le nom de l'étudiant,
     * titre masqué ; un membre du jury par son compte rattaché, lui, connaît le sujet.
     */
    public function test_pagination_confidential_title(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $gen = $this->getDataGenerator();
        $activity = $env->activities[0];
        $DB->update_record('these_dossier', (object) ['id' => $activity->dossier->id, 'confidential' => 1,
            'title' => 'Sujet SECRETISSIME']);
        $student = $DB->get_record('user', ['id' => $activity->dossier->userid]);
        $roleid = $gen->create_role(['shortname' => 'theseviewall']);
        assign_capability('mod/these:view', CAP_ALLOW, $roleid, \context_system::instance()->id);
        assign_capability('mod/these:viewall', CAP_ALLOW, $roleid, \context_system::instance()->id);
        $viewall = $gen->create_user();
        $gen->enrol_user($viewall->id, $activity->course->id, $roleid);
        accesslib_clear_all_caches_for_unit_testing();

        $links = thesesynthesis_get_active_links($env->synthesis->id, $viewall->id);
        $this->assertSame(0, thesesynthesis_count_dossiers($links, $viewall->id, ['search' => 'secretissime']));
        $this->assertSame([], thesesynthesis_get_dossiers($links, $viewall->id, ['search' => 'secretissime'], 0, 10));
        $rows = thesesynthesis_get_dossiers($links, $viewall->id, ['search' => $student->lastname], 0, 10);
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->knowssubject);

        // Membre du jury par son compte : il connaît le sujet et le trouve par son titre.
        these_save_jury($activity->dossier->id, [(object) ['juryrole' => 'member', 'fullname' => 'Vue Totale',
            'jurytitle' => 'Pr', 'institution' => '', 'email' => '', 'jurorid' => 0, 'jurystatus' => 'pr']]);
        $DB->set_field('these_jury', 'userid', $viewall->id, ['dossierid' => $activity->dossier->id]);
        $this->assertSame(1, thesesynthesis_count_dossiers($links, $viewall->id, ['search' => 'secretissime']));
        $rows = thesesynthesis_get_dossiers($links, $viewall->id, ['search' => 'secretissime'], 0, 10);
        $this->assertTrue($rows[0]->knowssubject);
        // Compte suspendu : plus reconnu.
        $DB->set_field('user', 'suspended', 1, ['id' => $viewall->id]);
        $this->assertSame(0, thesesynthesis_count_dossiers($links, $viewall->id, ['search' => 'secretissime']));
    }

    /**
     * Le nombre de lectures des listes paginées (page, total, file « À traiter ») ne dépend ni du nombre
     * de dossiers ni de la page demandée.
     */
    public function test_paged_reads_do_not_grow_with_dossiers(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $gen = $this->getDataGenerator();
        $thesegen = $gen->get_plugin_generator('mod_these');
        $activity = $env->activities[0];
        $add = function (int $count) use ($gen, $thesegen, $env, $activity) {
            for ($i = 0; $i < $count; $i++) {
                $student = $gen->create_and_enrol($activity->course, 'student', ['lastname' => 'Paginé']);
                $thesegen->create_dossier($activity->these, $student->id, ['directorid' => $env->director->id,
                    'status' => THESE_STATUS_SUBJECT_APPROVED, 'confidential' => $i % 2, 'title' => 'Sujet paginé']);
            }
        };
        $reads = function (\stdClass $user, int $page) use ($DB, $env) {
            $links = thesesynthesis_get_active_links($env->synthesis->id, $user->id);
            $lists = [
                'page' => function () use ($links, $user, $page) {
                    return count(thesesynthesis_get_dossiers($links, $user->id, [], $page * 3, 3));
                },
                'search' => function () use ($links, $user, $page) {
                    return count(thesesynthesis_get_dossiers($links, $user->id, ['search' => 'pagine'], $page * 3, 3));
                },
                'count' => function () use ($links, $user) {
                    return thesesynthesis_count_dossiers($links, $user->id, ['search' => 'pagine']);
                },
                'todo' => function () use ($links, $user, $page) {
                    return count(thesesynthesis_get_dossiers($links, $user->id, ['todo' => true], $page * 3, 3));
                },
            ];
            $result = [];
            foreach ($lists as $name => $load) {
                $load();
                $before = $DB->perf_get_reads();
                $value = $load();
                $result[$name] = [$DB->perf_get_reads() - $before, $value];
            }
            return $result;
        };

        $add(4);
        $few = $reads($env->deve, 0);
        $this->assertSame([3, 3, 4, 3], array_column($few, 1));
        $fewdirector = $reads($env->director, 0);
        $this->assertSame([3, 3, 4, 1], array_column($fewdirector, 1));
        $add(16);
        $many = $reads($env->deve, 2);
        $this->assertSame([3, 3, 20, 3], array_column($many, 1));
        $manydirector = $reads($env->director, 2);
        $this->assertSame([3, 3, 20, 0], array_column($manydirector, 1));
        $this->assertSame(array_column($few, 0), array_column($many, 0));
        // La file vide du directeur, page 2, ne lit pas les étudiants : seules les listes non vides se comparent.
        $this->assertSame(array_slice(array_column($fewdirector, 0), 0, 3), array_slice(array_column($manydirector, 0), 0, 3));
    }

    /**
     * Planning des soutenances des promotions suivies : chacun n'y voit que ce qu'il voit à la source,
     * avec la promotion de chaque étudiant.
     */
    public function test_planning_page(): void {
        global $DB;
        $this->resetAfterTest();
        $env = $this->setup_promotions();
        $monday = these_planning_week_start(time());
        foreach ($env->activities as $i => $activity) {
            $DB->update_record('these_dossier', (object) ['id' => $activity->dossier->id, 'status' => THESE_STATUS_SCHEDULED,
                'title' => 'Thèse ' . $i, 'defensedate' => $monday + DAYSECS + $i * HOURSECS, 'defenselocation' => 'Salle ' . $i]);
        }
        $cm = get_coursemodule_from_instance('thesesynthesis', $env->synthesis->id);
        $context = \context_module::instance($cm->id);
        $render = function ($user) use ($env, $cm, $context) {
            global $PAGE;
            $this->setUser($user);
            $PAGE = new \moodle_page();
            $PAGE->set_context($context);
            $PAGE->set_url(new \moodle_url('/mod/thesesynthesis/planning.php', ['id' => $cm->id]));
            ob_start();
            \mod_these\page\planning_page::output(
                thesesynthesis_get_active_links($env->synthesis->id, $user->id),
                new \moodle_url('/mod/thesesynthesis/planning.php', ['id' => $cm->id]),
                \mod_thesesynthesis\local\scope::for_synthesis($env->synthesis->id),
                $env->activities[0]->these,
                true,
                'Planning'
            );
            return ob_get_clean();
        };

        $html = $render($env->director);
        $this->assertStringContainsString('Thèse 0', $html);
        $this->assertStringContainsString('Thèse 1', $html);
        $this->assertStringContainsString('P1', $html);
        $this->assertStringContainsString(get_string('planningcount', 'mod_these', 2), $html);

        // La DEVE de la première promotion seulement.
        $html = $render($env->deve);
        $this->assertStringContainsString('Thèse 0', $html);
        $this->assertStringNotContainsString('Thèse 1', $html);
    }
}
