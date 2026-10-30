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
 * Planning des soutenances : bornes des semaines et des mois, soutenances retenues selon le statut
 * et les droits, planning de plusieurs promotions, planning imprimable.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_get_defenses
 * @covers     ::these_planning_week_start
 * @covers     ::these_planning_month_start
 * @covers     ::these_planning_document_context
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('these_get_defenses')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_planning_week_start')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_planning_month_start')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_planning_document_context')]
final class planning_test extends these_testcase {
    /**
     * Semaines du lundi au dimanche et mois civils, y compris autour d'un changement d'heure.
     */
    public function test_period_bounds(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');

        // Mercredi 4 novembre 2026 : semaine du lundi 2.
        $monday = these_planning_week_start(make_timestamp(2026, 11, 4, 10));
        $this->assertSame(make_timestamp(2026, 11, 2), $monday);
        $this->assertSame($monday, these_planning_week_start(make_timestamp(2026, 11, 8, 23, 59)));
        $this->assertSame(make_timestamp(2026, 11, 9), these_planning_shift_week($monday, 1));
        $this->assertSame(make_timestamp(2026, 10, 26), these_planning_shift_week($monday, -1));

        // Semaine du passage à l'heure d'hiver (dimanche 25 octobre 2026).
        $this->assertSame(make_timestamp(2026, 10, 19), these_planning_week_start(make_timestamp(2026, 10, 25, 20)));
        $this->assertSame(make_timestamp(2026, 10, 26), these_planning_shift_week(make_timestamp(2026, 10, 19), 1));

        $month = these_planning_month_start(make_timestamp(2026, 11, 30, 18));
        $this->assertSame(make_timestamp(2026, 11, 1), $month);
        $this->assertSame(make_timestamp(2026, 12, 1), these_planning_shift_month($month, 1));
        $this->assertSame(make_timestamp(2027, 1, 1), these_planning_shift_month($month, 2));
        $this->assertSame(make_timestamp(2026, 10, 1), these_planning_shift_month($month, -1));
    }

    /**
     * Soutenances d'une semaine : date proposée ou arrêtée, sans les dossiers abandonnés ni les
     * thèses ajournées ; un directeur ne voit que celles de ses dossiers.
     */
    public function test_week_defenses_and_rights(): void {
        $this->setTimezone('Europe/Paris');
        $this->setup_activity();
        $monday = make_timestamp(2026, 11, 2);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $third = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $create = function ($user, $status, $time, $directorid = 0) {
            return $this->generator->create_dossier($this->these, $user->id, [
                'status' => $status, 'defensedate' => $time, 'proposeddate' => $time, 'directorid' => $directorid,
                'defenselocation' => 'Amphithéâtre A',
            ]);
        };
        $scheduled = $create($this->student, THESE_STATUS_SCHEDULED, $monday + 2 * DAYSECS + 14 * HOURSECS, $this->director->id);
        these_save_jury($scheduled->id, $this->generator->default_jury());
        $proposed = $create($other, THESE_STATUS_DEFENSE_SUBMITTED, $monday + 2 * DAYSECS + 9 * HOURSECS);
        $create($third, THESE_STATUS_CANCELLED, $monday + DAYSECS);
        $create($this->getDataGenerator()->create_and_enrol($this->course, 'student'), THESE_STATUS_ACTIVE, $monday + DAYSECS);
        $create($this->getDataGenerator()->create_and_enrol($this->course, 'student'), THESE_STATUS_CLOSED, $monday + 8 * DAYSECS);

        $sources = [these_planning_source($this->these, $this->cm, $this->context, $this->deve->id)];
        $defenses = these_get_defenses($sources, $monday, these_planning_shift_week($monday, 1), $this->deve->id);
        $this->assertSame([(int) $proposed->id, (int) $scheduled->id], array_map(function ($defense) {
            return (int) $defense->dossier->id;
        }, $defenses));
        $this->assertFalse($defenses[0]->confirmed);
        $this->assertTrue($defenses[1]->confirmed);

        $row = these_defense_row($defenses[1]);
        $this->assertSame(userdate($scheduled->defensedate, get_string('strftimetime', 'langconfig')), $row['time']);
        $this->assertSame('Professeur Paul Président', $row['president']);
        $this->assertStringContainsString('Albert Membre', $row['jury']);
        $this->assertStringNotContainsString('Paul Président', $row['jury']);
        $this->assertSame('Amphithéâtre A', $row['location']);

        // La semaine suivante : la thèse soutenue et close reste au planning.
        $nextweek = these_planning_shift_week($monday, 1);
        $next = these_get_defenses($sources, $nextweek, these_planning_shift_week($monday, 2), $this->deve->id);
        $this->assertCount(1, $next);
        $this->assertSame(
            [$monday => 2, these_planning_shift_week($monday, 1) => 1],
            these_count_defenses_by_week($sources, $monday, 4, $this->deve->id)
        );

        // Le directeur : seulement son dossier ; l'étudiant : rien.
        $sources = [these_planning_source($this->these, $this->cm, $this->context, $this->director->id)];
        $mine = these_get_defenses($sources, $monday, these_planning_shift_week($monday, 1), $this->director->id);
        $this->assertSame([(int) $scheduled->id], array_map(function ($defense) {
            return (int) $defense->dossier->id;
        }, $mine));
        $sources = [these_planning_source($this->these, $this->cm, $this->context, $this->student->id)];
        $this->assertSame([], these_get_defenses($sources, $monday, these_planning_shift_week($monday, 1), $this->student->id));
    }

    /**
     * Planning de plusieurs promotions et planning mensuel imprimable, en paysage.
     */
    public function test_monthly_document_for_several_cohorts(): void {
        $this->setTimezone('Europe/Paris');
        $this->setup_activity();
        $gen = $this->getDataGenerator();
        $course2 = $gen->create_course(['shortname' => 'Promo2027']);
        $these2 = $gen->create_module('these', ['course' => $course2->id]);
        $cm2 = get_coursemodule_from_instance('these', $these2->id);
        $context2 = \context_module::instance($cm2->id);
        $student2 = $gen->create_and_enrol($course2, 'student');
        $gen->enrol_user($this->deve->id, $course2->id, 'editingteacher');

        $month = make_timestamp(2026, 11, 1);
        $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED, 'defensedate' => make_timestamp(2026, 11, 12, 14), 'title' => 'Leptospirose',
        ]);
        $this->generator->create_dossier($these2, $student2->id, [
            'status' => THESE_STATUS_DEFENSE_VALIDATED, 'defensedate' => make_timestamp(2026, 11, 5, 10), 'title' => 'Arthrose',
        ]);

        $sources = [
            these_planning_source($this->these, $this->cm, $this->context, $this->deve->id),
            these_planning_source($these2, $cm2, $context2, $this->deve->id),
        ];
        $defenses = these_get_defenses($sources, $month, these_planning_shift_month($month, 1), $this->deve->id);
        $this->assertSame(['Arthrose', 'Leptospirose'], array_map(function ($defense) {
            return $defense->dossier->title;
        }, $defenses));
        $this->assertSame('Promo2027', these_defense_row($defenses[0])['promotion']);

        $data = these_planning_document_context($defenses, 'novembre 2026', $this->these, null, true);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['days']);
        $pdf = these_render_scope_doctemplate(null, 'planning', $data);
        $this->assertStringStartsWith('%PDF', $pdf);
        // A4 en paysage : 841,89 × 595,28 points.
        $this->assertMatchesRegularExpression('#/MediaBox \[0\.0+ 0\.0+ 841\.89\d* 595\.27\d*\]#', $pdf);

        // Le modèle d'origine et ses données d'exemple sont valides, et modifiables par la DEVE.
        $scope = these_settings_scope($this->these);
        $this->assertNull(these_doctemplate_error($scope, 'planning', these_doctemplate_default('planning'), $this->these));
        $this->assertNotNull(these_doctemplate_error($scope, 'planning', '{{#days}}', $this->these));

        // Mois sans soutenance.
        $empty = these_planning_document_context([], 'décembre 2026', $this->these, null, false);
        $this->assertFalse($empty['hasdefenses']);
        $this->assertStringStartsWith('%PDF', these_render_scope_doctemplate(null, 'planning', $empty));
    }
}
