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
 * Bouton seul dans un bloc : le bouton est le composant \single_button du cœur, rendu par son gabarit
 * core/single_button.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class single_button_block implements \renderable, \templatable {
    /** @var \single_button */
    protected $button;
    /** @var string */
    protected $divclass;

    /**
     * Constructeur.
     *
     * @param moodle_url $url
     * @param string $label
     * @param string $method get ou post.
     * @param string $divclass Classes CSS du bloc.
     */
    public function __construct(moodle_url $url, $label, $method = 'post', $divclass = 'mb-3') {
        $this->button = new \single_button($url, $label, $method);
        $this->divclass = (string) $divclass;
    }

    /**
     * Données du gabarit mod_these/single_button_block.
     *
     * @param \renderer_base $output
     * @return array divclass, button (contexte de core/single_button)
     */
    public function export_for_template(\renderer_base $output): array {
        return ['divclass' => $this->divclass, 'button' => $this->button->export_for_template($output)];
    }
}
