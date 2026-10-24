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

namespace mod_these\pdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

/**
 * Document PDF composé à partir d'un modèle modifiable (voir doctemplates/ et la page
 * d'administration « Modèles des documents ») : du HTML, tel que l'interprète TCPDF, dans lequel
 * les données du dossier sont insérées par des balises Mustache ({{student.lastname}}...).
 *
 * Un modèle peut commencer par une directive de mise en page et se découper en sections :
 *
 *     <!--@page margins="12,15,15,15" header="8" footer="10" font="freesans" size="10"-->
 *     <!--@header--> ... répété en haut de chaque page ...
 *     <!--@body--> ... contenu ...
 *     <!--@footer--> ... répété en bas de chaque page ...
 *
 * margins : haut, droite, bas, gauche (mm) ; header : position de l'en-tête depuis le haut de la
 * page ; footer : position du pied depuis le bas ; font et size : police et taille par défaut ;
 * lineheight : interligne. Sans section, tout le modèle est le contenu.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_document extends \pdf {
    /** @var string En-tête rendu, répété sur chaque page. */
    protected $headerhtml = '';

    /** @var string Pied de page rendu, répété sur chaque page. */
    protected $footerhtml = '';

    /** @var array Mise en page (voir parse()). */
    protected $layout = [];

    /**
     * Mise en page par défaut.
     *
     * @return array
     */
    public static function default_layout() {
        return [
            'margins' => [12, 15, 15, 15],
            'header' => 8,
            'footer' => 12,
            'font' => 'freesans',
            'size' => 10,
            'lineheight' => 1.25,
        ];
    }

    /**
     * Découpe un modèle en directive de mise en page et sections.
     *
     * @param string $template
     * @return array {layout, header, body, footer}
     */
    public static function parse($template) {
        $layout = self::default_layout();
        if (preg_match('/<!--\s*@page\b(.*?)-->/s', $template, $match)) {
            preg_match_all('/([a-z]+)\s*=\s*"([^"]*)"/i', $match[1], $attributes, PREG_SET_ORDER);
            foreach ($attributes as [, $name, $value]) {
                $name = strtolower($name);
                if ($name === 'margins') {
                    $values = array_map('floatval', array_map('trim', explode(',', $value)));
                    if (count($values) === 4) {
                        $layout['margins'] = $values;
                    }
                } else if (in_array($name, ['header', 'footer', 'size', 'lineheight'], true)) {
                    $layout[$name] = (float) $value;
                } else if ($name === 'font') {
                    $layout['font'] = preg_replace('/[^a-z0-9]/', '', strtolower($value));
                }
            }
            $template = str_replace($match[0], '', $template);
        }

        $sections = ['header' => '', 'body' => '', 'footer' => ''];
        $parts = preg_split('/<!--\s*@(header|body|footer)\s*-->/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
        $sections['body'] = $parts[0];
        for ($i = 1; $i < count($parts); $i += 2) {
            $sections[$parts[$i]] .= $parts[$i + 1];
        }
        return ['layout' => $layout] + array_map('trim', $sections);
    }

    /**
     * Moteur Mustache : pas de partiel ni d'appel de fonction, échappement HTML par défaut.
     *
     * @return \Mustache_Engine
     */
    public static function mustache() {
        global $CFG;
        if (!class_exists('\Mustache_Engine')) {
            require_once($CFG->libdir . '/mustache/src/Mustache/Autoloader.php');
            \Mustache_Autoloader::register();
        }
        return new \Mustache_Engine([
            'strict_callables' => true,
            'escape' => function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
        ]);
    }

    /**
     * Insère les données dans un modèle.
     *
     * @param string $template Modèle complet (directive et sections comprises).
     * @param array $context Données (voir these_document_context()).
     * @return array {layout, header, body, footer} rendus.
     */
    public static function fill($template, array $context) {
        $parsed = self::parse($template);
        $mustache = self::mustache();
        foreach (['header', 'body', 'footer'] as $section) {
            $parsed[$section] = $parsed[$section] === '' ? '' : $mustache->render($parsed[$section], $context);
        }
        return $parsed;
    }

    /**
     * Produit le PDF d'un modèle.
     *
     * @param string $template
     * @param array $context
     * @param string $title Titre (métadonnées).
     * @return string Contenu PDF.
     */
    public static function render($template, array $context, $title = '') {
        $pdf = new self('P', 'mm', 'A4', true, 'UTF-8');
        // Numéros de page : remplacés par TCPDF à la fin du document.
        $context['pagenumber'] = $pdf->getAliasNumPage();
        $context['pagecount'] = $pdf->getAliasNbPages();
        $filled = self::fill($template, $context);
        $layout = $filled['layout'];

        $pdf->layout = $layout;
        $pdf->headerhtml = $filled['header'];
        $pdf->footerhtml = $filled['footer'];
        $pdf->SetCreator('Moodle mod_these');
        $pdf->SetTitle($title);
        [$top, $right, $bottom, $left] = $layout['margins'];
        $pdf->SetMargins($left, $top, $right);
        $pdf->SetHeaderMargin($layout['header']);
        $pdf->SetFooterMargin($layout['footer']);
        $pdf->setPrintHeader($filled['header'] !== '');
        $pdf->setPrintFooter($filled['footer'] !== '');
        $pdf->SetAutoPageBreak(true, $bottom);
        $pdf->setCellHeightRatio($layout['lineheight']);
        $pdf->setImageScale(1.53);
        $pdf->SetFont($layout['font'], '', $layout['size']);
        $pdf->AddPage();
        $pdf->writeHTML($filled['body'], true, false, true, false, '');
        return $pdf->Output('', 'S');
    }

    /**
     * Nombre de pages d'un PDF produit par render().
     *
     * @param string $pdf
     * @return int
     */
    public static function page_count($pdf) {
        return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
    }

    /**
     * En-tête de page : section « header » du modèle.
     *
     * @return void
     */
    public function Header() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        if ($this->headerhtml === '') {
            return;
        }
        [, $right, , $left] = $this->layout['margins'];
        $this->SetFont($this->layout['font'], '', $this->layout['size']);
        $this->writeHTMLCell(
            $this->getPageWidth() - $left - $right,
            0,
            $left,
            $this->layout['header'],
            $this->headerhtml,
            0,
            1,
            false,
            true,
            '',
            true
        );
    }

    /**
     * Pied de page : section « footer » du modèle.
     *
     * @return void
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        if ($this->footerhtml === '') {
            return;
        }
        [, $right, , $left] = $this->layout['margins'];
        $this->SetFont($this->layout['font'], '', $this->layout['size']);
        $this->writeHTMLCell(
            $this->getPageWidth() - $left - $right,
            0,
            $left,
            $this->getPageHeight() - $this->layout['footer'],
            $this->footerhtml,
            0,
            1,
            false,
            true,
            '',
            true
        );
    }
}
