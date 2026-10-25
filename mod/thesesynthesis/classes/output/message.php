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

namespace mod_thesesynthesis\output;

/**
 * Message en bloc : introduction d'une page, ou alerte d'information (aucun résultat).
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message implements \renderable, \templatable {
    /** @var string */
    protected $text;
    /** @var string */
    protected $class;

    /**
     * Constructeur.
     *
     * @param string $text Texte brut.
     * @param string $class Classes CSS du bloc.
     */
    public function __construct($text, $class = 'alert alert-info') {
        $this->text = (string) $text;
        $this->class = (string) $class;
    }

    /**
     * Données du gabarit mod_thesesynthesis/message.
     *
     * @param \renderer_base $output
     * @return array text, class
     */
    public function export_for_template(\renderer_base $output): array {
        return ['text' => $this->text, 'class' => $this->class];
    }
}
