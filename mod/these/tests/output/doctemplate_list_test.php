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
 * Liste des modèles de documents.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\doctemplate_list
 */
final class doctemplate_list_test extends output_testcase {
    /**
     * Contexte : un modèle par type ; badge et bouton de retour à l'origine pour un modèle modifié.
     */
    public function test_export_for_template(): void {
        $this->setup_activity();
        $scope = these_settings_scope($this->these);
        these_set_doctemplate($scope, 'subject', '<p>Perso</p>');
        $url = new \moodle_url('/mod/these/doctemplates.php', ['id' => $this->cm->id]);
        $data = (new doctemplate_list($scope, $url))->export_for_template($this->renderer());
        $this->assertCount(count(these_doctemplate_types()), $data['rows']);
        $labels = array_map(function ($row) {
            return $row['cells'][0]['parts'][0]['ptext']['value'];
        }, $data['rows']);
        $row = $data['rows'][array_search(these_doctemplate_label('subject'), $labels)];
        $this->assertStringContainsString('badge-warning', $row['cells'][1]['parts'][0]['pspan']['class']);
        $this->assertCount(5, $row['cells'][2]['parts']);
        $this->assertSame('_blank', $row['cells'][2]['parts'][2]['plink']['target']);
    }

    /**
     * Rendu : boutons séparés par une espace, aperçu dans un nouvel onglet.
     */
    public function test_render(): void {
        $this->setup_activity();
        $url = new \moodle_url('/mod/these/doctemplates.php', ['id' => $this->cm->id]);
        $html = $this->renderer()->render(new doctemplate_list(these_settings_scope($this->these), $url));
        $this->assertStringContainsString('class="btn btn-sm btn-primary">' . get_string('edit') . '</a> <a href=', $html);
        $this->assertStringContainsString('class="btn btn-sm btn-secondary" target="_blank">', $html);
        $this->assertStringNotContainsString('btn-outline-danger', $html);
    }
}
