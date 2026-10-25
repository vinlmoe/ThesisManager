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
 * Liens rendus en boutons, côte à côte.
 *
 * Les liens reçus sont déjà filtrés selon les droits de l'utilisateur (voir these_get_dossier_actions()).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class actions implements \renderable, \templatable {
    /** @var array libellé => moodle_url|null */
    protected $links;
    /** @var string */
    protected $class;

    /**
     * Constructeur.
     *
     * @param array $links libellé => moodle_url (null : ignoré)
     * @param string $class Classes CSS des boutons.
     */
    public function __construct(array $links, $class = 'btn btn-sm btn-secondary mr-1 mb-1') {
        $this->links = $links;
        $this->class = (string) $class;
    }

    /**
     * Indique s'il n'y a aucun bouton à afficher.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return !array_filter($this->links, function ($url) {
            return $url !== null;
        });
    }

    /**
     * Données du gabarit mod_these/actions.
     *
     * @param \renderer_base $output
     * @return array actions (label, url, class)
     */
    public function export_for_template(\renderer_base $output): array {
        $actions = [];
        foreach ($this->links as $label => $url) {
            if ($url instanceof moodle_url) {
                $actions[] = ['label' => (string) $label, 'url' => $url->out(false), 'class' => $this->class];
            }
        }
        return ['actions' => $actions];
    }
}
