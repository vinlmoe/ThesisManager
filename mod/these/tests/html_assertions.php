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

/**
 * Vérifications structurelles d'un fragment HTML rendu, par XPath : ce qu'un test exige d'un rendu
 * (un tableau de telle classe, un lien vers telle page, tel texte dans telle cellule) sans dépendre
 * de l'ordre des attributs, des espaces ni des classes de présentation.
 *
 * Les expressions sont évaluées depuis un élément qui enveloppe le fragment : « .//table » cherche
 * dans tout le fragment, « ./*[1][self::h4] » désigne son premier élément.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait html_assertions {
    /**
     * Condition XPath : l'élément porte la classe donnée (parmi d'autres).
     *
     * @param string $class
     * @return string
     */
    protected static function xclass(string $class): string {
        return "contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')";
    }

    /**
     * Évalue une expression XPath sur un fragment HTML.
     *
     * @param string $html Fragment HTML, en UTF-8.
     * @param string $xpath Expression relative au fragment.
     * @return \DOMNodeList
     */
    protected function html_query(string $html, string $xpath): \DOMNodeList {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // La déclaration d'encodage évite que libxml lise le fragment en ISO-8859-1 ; les éléments
        // HTML5 qu'il ne connaît pas (details, summary...) sont gardés, leurs avertissements ignorés.
        $document->loadHTML('<?xml encoding="UTF-8"?><div id="these-html-root">' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xp = new \DOMXPath($document);
        $root = $xp->query('//div[@id="these-html-root"]')->item(0);
        $nodes = $xp->query($xpath, $root);
        $this->assertNotFalse($nodes, 'Expression XPath invalide : ' . $xpath);
        return $nodes;
    }

    /**
     * Vérifie qu'un fragment contient au moins un élément (ou exactement $count) répondant à l'expression.
     *
     * @param string $html
     * @param string $xpath Expression relative au fragment, p. ex. './/table[' . self::xclass('generaltable') . ']'.
     * @param int|null $count Nombre exact attendu, ou null pour « au moins un ».
     * @param string $message
     * @return \DOMNode Premier élément trouvé.
     */
    protected function assert_html_has(string $html, string $xpath, ?int $count = null, string $message = ''): \DOMNode {
        $nodes = $this->html_query($html, $xpath);
        $message = trim($message . ' ' . $xpath);
        if ($count === null) {
            $this->assertGreaterThan(0, $nodes->length, $message);
        } else {
            $this->assertSame($count, $nodes->length, $message);
        }
        return $nodes->item(0) ?? new \DOMText();
    }

    /**
     * Vérifie qu'aucun élément du fragment ne répond à l'expression.
     *
     * @param string $html
     * @param string $xpath
     * @param string $message
     * @return void
     */
    protected function assert_html_lacks(string $html, string $xpath, string $message = ''): void {
        $this->assertSame(0, $this->html_query($html, $xpath)->length, trim($message . ' ' . $xpath));
    }

    /**
     * Textes des éléments répondant à l'expression, dans l'ordre du document, espaces normalisés.
     *
     * @param string $html
     * @param string $xpath
     * @return string[]
     */
    protected function html_texts(string $html, string $xpath): array {
        $texts = [];
        foreach ($this->html_query($html, $xpath) as $node) {
            $texts[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        }
        return $texts;
    }

    /**
     * Vérifie qu'un des éléments répondant à l'expression contient le texte donné.
     *
     * @param string $html
     * @param string $xpath
     * @param string $text Texte attendu (décodé : « a & b », pas « a &amp; b »).
     * @param string $message
     * @return void
     */
    protected function assert_html_text_contains(string $html, string $xpath, string $text, string $message = ''): void {
        $texts = $this->html_texts($html, $xpath);
        $found = array_filter($texts, function ($candidate) use ($text) {
            return strpos($candidate, $text) !== false;
        });
        $this->assertNotEmpty($found, trim($message . ' ' . $xpath) . ' : « ' . $text . ' » absent de ' . json_encode(
            $texts,
            JSON_UNESCAPED_UNICODE
        ));
    }
}
