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

use moodle_url;

/**
 * Lien seul (retour, bouton), éventuellement dans un bloc.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class link implements \renderable, \templatable {
    /** @var moodle_url */
    protected $url;
    /** @var string */
    protected $label;
    /** @var array */
    protected $options;

    /**
     * Constructeur.
     *
     * @param moodle_url $url
     * @param string $label Texte brut.
     * @param string $class Classes CSS du lien (vide : sans attribut class).
     * @param string $target Cible (_blank), vide par défaut.
     * @param string $divclass Classes CSS du bloc qui entoure le lien (vide : pas de bloc).
     */
    public function __construct(moodle_url $url, $label, $class = '', $target = '', $divclass = '') {
        $this->url = $url;
        $this->label = (string) $label;
        $this->options = ['class' => (string) $class, 'target' => (string) $target, 'divclass' => (string) $divclass];
    }

    /**
     * Données du gabarit mod_these/link.
     *
     * @param \renderer_base $output
     * @return array url, label, class, target, divclass
     */
    public function export_for_template(\renderer_base $output): array {
        return ['url' => $this->url->out(false), 'label' => $this->label] + $this->options;
    }
}
