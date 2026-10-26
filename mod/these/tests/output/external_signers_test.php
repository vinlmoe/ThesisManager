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

namespace mod_these\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/output_testcase.php');

/**
 * Signataires sans compte d'un dossier.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\external_signers
 */
final class external_signers_test extends output_testcase {
    /**
     * Dossier programmé avec bordereau : le président et la direction ont des documents à signer.
     *
     * @return \stdClass
     */
    private function dossier_with_bordereau(): \stdClass {
        global $DB;
        $dossier = $this->confidential_dossier();
        $DB->set_field(
            'these_dossier',
            'bordereau',
            json_encode(['diffusion' => 'internet', 'embargo' => 0]),
            ['id' => $dossier->id]
        );
        \mod_these\local\scopesettings::set(
            these_local_scope($this->these),
            ['dgname' => 'Directrice & co', 'dgemail' => 'dg@example.org']
        );
        return $this->reload($dossier->id);
    }

    /**
     * Contexte : une ligne par signataire en attente, relance seulement avec une adresse.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $dossier = $this->dossier_with_bordereau();
        $this->setUser($this->deve);
        $url = new \moodle_url('/mod/these/signatures.php', ['id' => $this->cm->id, 'dossierid' => $dossier->id]);
        $signers = new external_signers($this->cm, $dossier, $url);
        $this->assertFalse($signers->is_empty());
        $data = $signers->export_for_template($this->renderer());
        $this->assertTrue($data['hasrows']);
        $this->assertSame(get_string('externalsigners', 'mod_these'), $data['heading']);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Directrice & co', $json);
        $this->assertStringContainsString('dg@example.org', $json);
        $this->assertStringContainsString('resend=dg', $json);
        $this->assertStringContainsString('sign.php', $json);
    }

    /**
     * Rendu : titre et tableau ; titre seul sans signataire en attente.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->dossier_with_bordereau();
        $this->setUser($this->deve);
        $url = new \moodle_url('/mod/these/signatures.php');
        $html = $this->renderer()->render(new external_signers($this->cm, $dossier, $url));
        $this->assertStringStartsWith('<h4 class="mt-4">' . get_string('externalsigners', 'mod_these') . '</h4>', $html);
        $this->assertStringContainsString('class="btn btn-sm btn-primary">' . get_string('signinperson', 'mod_these'), $html);
        $plain = $this->generator->create_dossier($this->these, $this->getDataGenerator()->create_and_enrol($this->course)->id);
        $signers = new external_signers($this->cm, $plain, $url);
        $this->assertTrue($signers->is_empty());
        $heading = '<h4 class="mt-4">' . get_string('externalsigners', 'mod_these') . '</h4>';
        $this->assertSame($heading, $this->renderer()->render($signers));
    }
}
