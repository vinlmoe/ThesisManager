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

use mod_these\local\backlinks;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Liens de retour : page d'origine mémorisée (tableau de bord, suivi des thèses...), retenue pour un
 * dossier de l'activité qu'elle présente, accueil de l'activité à défaut.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\backlinks
 */
final class backlinks_test extends these_testcase {
    /**
     * Sans page d'origine, un dossier revient au tableau de bord pour le personnel et au dossier de
     * l'étudiant pour l'étudiant ; une page commune revient au tableau de bord Moodle.
     */
    public function test_defaults(): void {
        global $SESSION;
        $this->setup_activity();
        unset($SESSION->{backlinks::SESSIONKEY});

        $this->setUser($this->deve);
        $target = backlinks::from_dossier($this->cm, $this->context);
        $this->assertStringStartsWith('/mod/these/dashboard.php', $target->url->out_as_local_url(false));
        $this->assertSame(get_string('dashboard', 'mod_these'), $target->label);

        $this->setUser($this->student);
        $target = backlinks::from_dossier($this->cm, $this->context);
        $this->assertStringStartsWith('/mod/these/view.php', $target->url->out_as_local_url(false));

        $this->assertStringStartsWith('/my/', backlinks::from_global()->url->out_as_local_url(false));
    }

    /**
     * La page d'origine mémorisée vaut pour les dossiers des activités qu'elle présente seulement.
     */
    public function test_origin_is_kept_for_its_activities(): void {
        $this->setup_activity();
        $this->setUser($this->deve);
        $origin = new \moodle_url('/mod/thesesynthesis/search.php', ['id' => 42, 'q' => 'chien']);
        backlinks::remember($origin, 'Recherche', [$this->these->id]);

        $target = backlinks::from_dossier($this->cm, $this->context);
        $this->assertSame($origin->out(false), $target->url->out(false));
        $this->assertSame('Recherche', $target->label);
        $this->assertSame($origin->out(false), backlinks::from_global()->url->out(false));

        // Origine qui ne présente pas cette activité : retour au tableau de bord.
        backlinks::remember($origin, 'Recherche', [$this->these->id + 1]);
        $target = backlinks::from_dossier($this->cm, $this->context);
        $this->assertStringStartsWith('/mod/these/dashboard.php', $target->url->out_as_local_url(false));
    }

    /**
     * Les pages d'un dossier reviennent au dossier, ou au dossier de l'étudiant sur la page d'accueil.
     */
    public function test_to_dossier(): void {
        $this->setup_activity();
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, ['status' => THESE_STATUS_ACTIVE]);

        $target = backlinks::to_dossier($this->cm, $dossier);
        $this->assertStringStartsWith('/mod/these/dossier.php', $target->url->out_as_local_url(false));
        $this->assertEquals($dossier->id, $target->url->get_param('dossierid'));

        $rights = these_get_rights($this->context, $dossier, $this->student->id);
        $target = backlinks::to_dossier($this->cm, $dossier, $rights);
        $this->assertStringStartsWith('/mod/these/view.php', $target->url->out_as_local_url(false));
    }
}
