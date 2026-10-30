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
            ['dgname' => 'Directrice & co', 'ccememail' => 'ccem@example.org', 'universitypaper' => 0]
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
        $this->assertStringContainsString('ccem@example.org', $json);
        $this->assertStringContainsString('resend=ccem', $json);
        // Direction générale : jamais de lien, elle ne signe pas elle-même.
        $this->assertStringNotContainsString('resend=dg', $json);
        $this->assertStringContainsString('sign.php', $json);
    }

    /**
     * Étudiant et directeur : proposés à la signature sur place pour un document dont la signature a
     * commencé, sans relance par courriel ; pas pour un document dont aucune signature n'existe.
     */
    public function test_student_and_director_in_person(): void {
        $this->setup_activity();
        $this->setUser($this->deve);
        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SUBJECT_SUBMITTED, 'directorid' => $this->director->id,
        ]);
        $url = new \moodle_url('/mod/these/signatures.php', ['id' => $this->cm->id, 'dossierid' => $dossier->id]);
        $this->assertStringNotContainsString('slot=director', json_encode((new external_signers($this->cm, $dossier, $url))
            ->export_for_template($this->renderer()), JSON_UNESCAPED_SLASHES));

        these_sign_document(
            $this->context,
            $dossier,
            'subject',
            'student',
            $this->student->id,
            'Étudiant',
            these_sample_signature_image()
        );
        $json = json_encode(
            (new external_signers($this->cm, $dossier, $url))->export_for_template($this->renderer()),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $this->assertStringContainsString('slot=director', $json);
        $this->assertStringContainsString(fullname($this->director), $json);
        $this->assertStringNotContainsString('resend=director', $json);
        $this->assertStringNotContainsString('slot=student', $json);
        $this->assertSame((int) $this->director->id, these_inperson_signer($dossier, 'director')['userid']);
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
        $heading = './*[1][self::h4][normalize-space() = "' . get_string('externalsigners', 'mod_these') . '"]';
        $this->assert_html_has($html, $heading);
        $this->assert_html_has($html, './/a[@href][' . self::xclass('btn-primary') . '][starts-with(normalize-space(), "'
            . get_string('signinperson', 'mod_these') . '")]');
        $plain = $this->generator->create_dossier($this->these, $this->getDataGenerator()->create_and_enrol($this->course)->id);
        $signers = new external_signers($this->cm, $plain, $url);
        $this->assertTrue($signers->is_empty());
        // Sans signataire extérieur : le titre seul.
        $empty = $this->renderer()->render($signers);
        $this->assert_html_has($empty, $heading);
        $this->assert_html_has($empty, './*', 1);
    }
}
