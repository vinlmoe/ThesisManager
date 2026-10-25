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
 * Liens vers les fichiers d'une zone : contexte et rendu.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\file_links
 */
final class file_links_test extends output_testcase {
    /**
     * Contexte : un lien par fichier, saut de ligne avant chaque lien sauf le premier ; zone vide.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        get_file_storage()->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_these',
            'filearea' => 'manuscript', 'itemid' => $dossier->id, 'filepath' => '/', 'filename' => 'annexe (1).pdf'], 'x');
        $links = new file_links($this->context, 'manuscript', $dossier->id);
        $this->assertFalse($links->is_empty());
        $files = $links->export_for_template($this->renderer())['files'];
        $this->assertSame(['annexe (1).pdf', 'manuscript.pdf'], array_column($files, 'filename'));
        $this->assertSame([false, true], array_column($files, 'separator'));
        $this->assertStringContainsString('/mod_these/manuscript/' . $dossier->id . '/manuscript.pdf', $files[1]['url']);
        $this->assertTrue((new file_links($this->context, 'pv', $dossier->id))->is_empty());
    }

    /**
     * Rendu : liens échappés séparés par un saut de ligne ; rien pour une zone vide.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        get_file_storage()->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_these',
            'filearea' => 'manuscript', 'itemid' => $dossier->id, 'filepath' => '/', 'filename' => 'annexe (1).pdf'], 'x');
        $html = $this->renderer()->render(new file_links($this->context, 'manuscript', $dossier->id));
        $this->assertMatchesRegularExpression(
            '#^<a href="[^"]+">annexe \(1\)\.pdf</a><br /><a href="[^"]+">manuscript\.pdf</a>$#',
            $html
        );
        $this->assertSame('', $this->renderer()->render(new file_links($this->context, 'pv', $dossier->id)));
    }
}
