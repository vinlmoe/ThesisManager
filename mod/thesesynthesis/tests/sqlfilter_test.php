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

use mod_thesesynthesis\local\sqlfilter;

/**
 * Conditions de recherche du suivi : variantes des ligatures pour les bases dont la comparaison
 * LIKE se fait caractère par caractère (MySQL, MariaDB), où « oe » ne vaut jamais « œ ».
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\local\sqlfilter
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_thesesynthesis\local\sqlfilter::class)]
final class sqlfilter_test extends \advanced_testcase {
    /**
     * Chaque suite remplaçable est gardée ou remplacée par sa lettre unique ; le texte d'origine vient
     * en premier, sans doublon.
     */
    public function test_ligature_variants(): void {
        $this->assertSame(['oedeme', 'œdeme'], sqlfilter::ligature_variants('oedeme'));
        $this->assertSame(['etude'], sqlfilter::ligature_variants('etude'));
        $this->assertSame(
            ['coelioscopie aerobie', 'coelioscopie ærobie', 'cœlioscopie aerobie', 'cœlioscopie ærobie'],
            sqlfilter::ligature_variants('coelioscopie aerobie')
        );
        $this->assertContains('straße', sqlfilter::ligature_variants('strasse'));
    }

    /**
     * Le nombre de variantes est borné : au-delà de la limite, les suites restent telles quelles.
     */
    public function test_ligature_variants_bounded(): void {
        $variants = sqlfilter::ligature_variants('oe oe oe oe oe oe', 4);
        $this->assertCount(16, $variants);
        foreach ($variants as $variant) {
            $this->assertStringEndsWith('oe oe', $variant);
        }
    }

    /**
     * Hors PostgreSQL, la condition cherche toutes les variantes ; la recherche trouve « Œdème » par
     * « oedeme » sur la base de test, quelle qu'elle soit.
     */
    public function test_contains_finds_ligature(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Œdème pulmonaire du chien']);
        $filter = new sqlfilter();
        $where = $filter->contains('fullname', 'oedeme');
        $this->assertSame([(int) $course->id], array_map('intval', array_keys(
            $DB->get_records_select('course', $where, $filter->get_params(), '', 'id')
        )));
    }
}
