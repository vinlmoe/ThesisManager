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
 * Transfert des paramètres entre activités et par fichier : paramètres de l'activité,
 * courriels, modèles et images des documents.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_export_settings
 * @covers     ::these_import_settings
 * @covers     ::these_settings_transfer_sources
 */
final class settings_transfer_test extends these_testcase {
    /**
     * Seconde activité, dans un autre cours où la DEVE est aussi enseignante.
     *
     * @return array [instance, contexte]
     */
    private function other_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $these = $this->getDataGenerator()->create_module('these', ['course' => $course->id, 'establishmentname' => 'Autre',
            'minjury' => 3, 'reminderdays' => 14]);
        $this->getDataGenerator()->enrol_user($this->deve->id, $course->id, 'editingteacher');
        return [$these, \context_module::instance($these->cmid)];
    }

    /**
     * Personnalise la source : paramètres, courriel, modèle, image.
     */
    private function customise_source(): void {
        global $DB;
        $DB->update_record('these', (object) ['id' => $this->these->id, 'establishmentname' => 'VetAgro Sup',
            'establishmentcity' => "Marcy-l'Étoile", 'minjury' => 4, 'reminderdays' => 21, 'deveemail' => 'deve@example.org']);
        $this->these = $DB->get_record('these', ['id' => $this->these->id]);
        these_save_email_template($this->these->id, 'returned', 'Dossier renvoyé', 'Corps {student}');
        these_set_doctemplate(these_local_scope($this->these), 'conclusions', '<p>Conclusions de {{student.lastname}}</p>');
        get_file_storage()->create_file_from_string([
            'contextid' => $this->context->id, 'component' => 'mod_these', 'filearea' => 'docimages', 'itemid' => 0,
            'filepath' => '/', 'filename' => 'logo.png',
        ], these_sample_signature_image());
    }

    /**
     * Tout reprendre d'une autre activité, en remplaçant ce qui existait.
     */
    public function test_transfer_between_activities(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_activity();
        $this->customise_source();
        [$target, $targetcontext] = $this->other_activity();
        these_set_doctemplate(these_local_scope($target), 'grid', '<p>Grille locale</p>');
        these_save_email_template($target->id, 'convocation', 'Local', 'Local');

        $data = these_export_settings($this->these, $this->context, these_settings_groups());
        $result = these_import_settings($target, $targetcontext, $data, these_settings_groups());

        $this->assertTrue($result->activity);
        $this->assertSame(1, $result->emails);
        $this->assertSame(1, $result->doctemplates);
        $this->assertSame(1, $result->docimages);
        $this->assertSame([], $result->skipped);
        $saved = $DB->get_record('these', ['id' => $target->id]);
        $this->assertSame('VetAgro Sup', $saved->establishmentname);
        $this->assertEquals(4, $saved->minjury);
        $this->assertEquals(21, $saved->reminderdays);
        $this->assertSame('deve@example.org', $saved->deveemail);
        $this->assertSame('Dossier renvoyé', these_get_custom_email_template($target->id, 'returned')->subject);
        // Les personnalisations propres à la destination sont remplacées.
        $this->assertNull(these_get_custom_email_template($target->id, 'convocation'));
        $this->assertSame(
            '<p>Conclusions de {{student.lastname}}</p>',
            these_get_doctemplate(these_local_scope($target), 'conclusions')
        );
        $this->assertFalse(these_doctemplate_customised(these_local_scope($target), 'grid'));
        $this->assertArrayHasKey('logo', these_doctemplate_images(these_local_scope($target)));
        // La source ne change pas.
        $this->assertTrue(these_doctemplate_customised(these_local_scope($this->these), 'conclusions'));
    }

    /**
     * Seuls les groupes choisis changent.
     */
    public function test_partial_transfer(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setup_activity();
        $this->customise_source();
        [$target, $targetcontext] = $this->other_activity();

        $data = these_export_settings($this->these, $this->context, these_settings_groups());
        $result = these_import_settings($target, $targetcontext, $data, ['doctemplates']);

        $this->assertFalse($result->activity);
        $this->assertSame('Autre', $DB->get_field('these', 'establishmentname', ['id' => $target->id]));
        $this->assertNull(these_get_custom_email_template($target->id, 'returned'));
        $this->assertTrue(these_doctemplate_customised(these_local_scope($target), 'conclusions'));
        $this->assertArrayNotHasKey('logo', these_doctemplate_images(these_local_scope($target)));
    }

    /**
     * Fichier exporté : relecture, refus d'un fichier étranger, éléments invalides ignorés.
     */
    public function test_file_roundtrip(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        $this->customise_source();
        [$target, $targetcontext] = $this->other_activity();

        $json = json_encode(these_export_settings($this->these, $this->context, ['emails', 'doctemplates', 'docimages']));
        $this->assertNull(these_parse_settings_file('{"format":"autre"}'));
        $this->assertNull(these_parse_settings_file('pas du json'));
        $data = these_parse_settings_file($json);
        $this->assertArrayNotHasKey('activity', $data['groups']);

        $data['groups']['emails'][] = ['emailkey' => 'inconnu', 'subject' => 'x', 'body' => 'y'];
        $data['groups']['doctemplates'][] = ['doctype' => 'grid', 'content' => '<p>{{#student}}sans fin</p>'];
        $data['groups']['docimages'][] = ['filename' => 'faux.png', 'content' => base64_encode('pas une image')];
        $result = these_import_settings($target, $targetcontext, $data, these_settings_groups());

        $this->assertFalse($result->activity);
        $this->assertSame(1, $result->emails);
        $this->assertSame(1, $result->doctemplates);
        $this->assertSame(1, $result->docimages);
        $this->assertCount(3, $result->skipped);
        $this->assertFalse(these_doctemplate_customised(these_local_scope($target), 'grid'));
    }

    /**
     * Seules les activités dont l'utilisateur est la DEVE sont proposées comme source.
     */
    public function test_sources(): void {
        $this->resetAfterTest();
        $this->setup_activity();
        [$other] = $this->other_activity();
        $course = $this->getDataGenerator()->create_course();
        $foreign = $this->getDataGenerator()->create_module('these', ['course' => $course->id]);

        $sources = these_settings_transfer_sources($this->these, $this->deve->id);
        $this->assertArrayHasKey($other->id, $sources);
        $this->assertArrayNotHasKey($foreign->id, $sources);
        $this->assertArrayNotHasKey($this->these->id, $sources);
        $this->assertSame([], these_settings_transfer_sources($this->these, $this->student->id));
    }
}
