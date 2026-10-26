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

/**
 * Bloc de texte : introduction d'une page, alerte, paragraphe. Le contenu est un texte brut ou une
 * liste de fragments (voir parts) ; les décisions sur ce qui y figure sont prises par l'appelant.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message implements \renderable, \templatable {
    /** @var array[] */
    protected $parts;
    /** @var string */
    protected $class;
    /** @var string */
    protected $tag;

    /**
     * Constructeur.
     *
     * @param string|array $content Texte brut, ou liste de fragments.
     * @param string $class Classes CSS du bloc (vide : sans attribut class).
     * @param string $tag Balise : div ou p.
     */
    public function __construct($content, $class = 'alert alert-info', $tag = 'div') {
        $this->parts = is_array($content) ? array_values($content) : [parts::text((string) $content)];
        $this->class = (string) $class;
        $this->tag = $tag === 'p' ? 'p' : 'div';
    }

    /**
     * Explication repliée sous « Aide » : les pages de la DEVE restent courtes, l'explication est à un
     * clic.
     *
     * @param string $text Texte brut.
     * @return self
     */
    public static function help(string $text): self {
        return new self([parts::details([parts::text(get_string('help'))], [parts::text($text)])], 'these-help mb-3');
    }

    /**
     * Données du gabarit mod_these/message.
     *
     * @param \renderer_base $output
     * @return array parts, class, isparagraph
     */
    public function export_for_template(\renderer_base $output): array {
        return ['parts' => $this->parts, 'class' => $this->class, 'isparagraph' => $this->tag === 'p'];
    }
}
