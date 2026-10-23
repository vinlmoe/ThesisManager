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
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Modèles modifiables des documents : modèles d'origine, modification et retour à l'origine,
 * contrôle d'un modèle invalide, découpage en sections, données insérées.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_render_doctemplate
 * @covers     ::these_set_doctemplate
 * @covers     ::these_build_document_context
 * @covers     \mod_these\pdf\html_document
 */
final class doctemplate_test extends \advanced_testcase {
    /**
     * Chaque modèle d'origine produit un PDF avec les données d'exemple.
     */
    public function test_default_templates_render(): void {
        $this->resetAfterTest();
        $data = these_sample_document_context();
        foreach (array_keys(these_doctemplate_types()) as $type) {
            $this->assertNull(these_doctemplate_error($type, these_doctemplate_default($type)), $type);
            $this->assertStringStartsWith('%PDF', these_render_doctemplate($type, $data), $type);
        }
    }

    /**
     * Un modèle modifié remplace celui d'origine, jusqu'à ce qu'il soit rétabli ; enregistrer le
     * modèle d'origine revient à le rétablir.
     */
    public function test_customise_and_reset(): void {
        $this->resetAfterTest();
        $this->assertFalse(these_doctemplate_customised('report'));

        these_set_doctemplate('report', "<p>Rapport de {{student.lastname}}</p>\r\n");
        $this->assertTrue(these_doctemplate_customised('report'));
        $this->assertSame("<p>Rapport de {{student.lastname}}</p>\n", these_get_doctemplate('report'));

        these_set_doctemplate('report', null);
        $this->assertFalse(these_doctemplate_customised('report'));
        $this->assertSame(these_doctemplate_default('report'), these_get_doctemplate('report'));

        these_set_doctemplate('grid', "  " . these_doctemplate_default('grid'));
        $this->assertFalse(these_doctemplate_customised('grid'));
    }

    /**
     * Un modèle dont les balises sont mal formées est refusé avec un message.
     */
    public function test_invalid_template(): void {
        $this->resetAfterTest();
        $this->assertNotNull(these_doctemplate_error('report', '<p>{{#student}}sans fin</p>'));
        $this->assertNull(these_doctemplate_error('report', '<p>{{#student}}{{lastname}}{{/student}}</p>'));
    }

    /**
     * Directive de mise en page et sections.
     */
    public function test_parse_sections(): void {
        $parsed = \mod_these\pdf\html_document::parse('<!--@page margins="10,11,12,13" font="freeserif" size="11"-->'
            . "\n<!--@header-->EN-TÊTE<!--@body-->CONTENU<!--@footer-->PIED {{pagenumber}}");
        $this->assertSame([10.0, 11.0, 12.0, 13.0], $parsed['layout']['margins']);
        $this->assertSame('freeserif', $parsed['layout']['font']);
        $this->assertSame(11.0, $parsed['layout']['size']);
        $this->assertSame('EN-TÊTE', $parsed['header']);
        $this->assertSame('CONTENU', $parsed['body']);
        $this->assertSame('PIED {{pagenumber}}', $parsed['footer']);

        $plain = \mod_these\pdf\html_document::parse('<p>Seul contenu</p>');
        $this->assertSame('<p>Seul contenu</p>', $plain['body']);
        $this->assertSame('', $plain['header']);
    }

    /**
     * Données insérées : valeurs échappées, cases à cocher, signatures, directeur de thèse
     * extérieur.
     */
    public function test_context_values(): void {
        $this->resetAfterTest();
        $data = these_sample_document_context();
        $this->assertSame('MARTIN', $data['student']['lastname']);
        $this->assertStringContainsString('&#9746;', $data['box']['A5']);
        $this->assertStringContainsString('&#9744;', $data['box']['A6']);
        $this->assertStringContainsString('<img src="@', $data['sig']['director']);
        $this->assertSame('Pr DURAND Anne', $data['thesisdirector']['name']);
        $this->assertSame('Pr Jean DUPONT', $data['jurypresident']['name']);
        $this->assertArrayHasKey('student.lastname', these_doctemplate_variables($data));

        $filled = \mod_these\pdf\html_document::fill('<p>{{thesis.title}}</p>', ['thesis' => ['title' => 'A < B & C']]);
        $this->assertSame('<p>A &lt; B &amp; C</p>', $filled['body']);
    }
}
