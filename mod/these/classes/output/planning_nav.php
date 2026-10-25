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
 * Navigation du planning : choix de la vue (semaine ou mois), période précédente, courante et suivante,
 * et formulaire d'accès à une date.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planning_nav implements \renderable, \templatable {
    /** @var moodle_url */
    protected $baseurl;
    /** @var string */
    protected $view;
    /** @var int[] */
    protected $dates;

    /**
     * Constructeur.
     *
     * @param moodle_url $baseurl Adresse de la page (avec ses paramètres d'accès).
     * @param string $view week ou month.
     * @param int $from Début de la période affichée.
     * @param int $previous Début de la période précédente.
     * @param int $current Début de la période en cours.
     * @param int $next Début de la période suivante.
     * @param int $date Date demandée (valeur du formulaire d'accès à une date).
     */
    public function __construct(moodle_url $baseurl, $view, $from, $previous, $current, $next, $date) {
        $this->baseurl = $baseurl;
        $this->view = $view === 'month' ? 'month' : 'week';
        $this->dates = ['from' => (int) $from, 'previous' => (int) $previous, 'current' => (int) $current,
            'next' => (int) $next, 'date' => (int) $date];
    }

    /**
     * Adresse d'une période dans la vue courante.
     *
     * @param int $date
     * @return string
     */
    protected function url($date): string {
        return (new moodle_url($this->baseurl, ['view' => $this->view, 'date' => $date]))->out(false);
    }

    /**
     * Données du gabarit mod_these/planning_nav.
     *
     * @param \renderer_base $output
     * @return array views, periods (label, url, class), action, hidden (name, value), gotolabel, gotovalue, submitlabel
     */
    public function export_for_template(\renderer_base $output): array {
        $dates = $this->dates;
        $views = [];
        foreach (['week' => 'planningviewweek', 'month' => 'planningviewmonth'] as $name => $string) {
            $views[] = [
                'label' => get_string($string, 'mod_these'),
                'url' => (new moodle_url($this->baseurl, ['view' => $name, 'date' => $dates['from']]))->out(false),
                'class' => 'btn btn-sm ' . ($name === $this->view ? 'btn-primary' : 'btn-outline-primary') . ' mr-1',
            ];
        }
        $suffix = $this->view;
        $button = 'btn btn-secondary mb-1 ';
        $periods = [
            ['label' => '« ' . get_string('planningprev' . $suffix, 'mod_these'), 'url' => $this->url($dates['previous']),
                'class' => $button . 'mr-1'],
            ['label' => get_string('planningthis' . $suffix, 'mod_these'), 'url' => $this->url($dates['current']),
                'class' => $button . 'mr-1' . ($dates['current'] === $dates['from'] ? ' active' : '')],
            ['label' => get_string('planningnext' . $suffix, 'mod_these') . ' »', 'url' => $this->url($dates['next']),
                'class' => $button . 'mr-3'],
        ];
        $hidden = [];
        foreach ($this->baseurl->params() as $name => $value) {
            $hidden[] = ['name' => $name, 'value' => $value];
        }
        $hidden[] = ['name' => 'view', 'value' => $this->view];
        return [
            'views' => $views,
            'periods' => $periods,
            'action' => $this->baseurl->out_omit_querystring(),
            'hidden' => $hidden,
            'gotolabel' => get_string('planninggoto', 'mod_these'),
            // Sans le zéro initial du jour que userdate() retire par défaut ($fixday), « 2026-11-4 » n'est
            // pas une valeur valide pour un champ de date, qui s'afficherait vide du 1er au 9 du mois.
            'gotovalue' => userdate($dates['date'], '%Y-%m-%d', 99, false),
            'submitlabel' => get_string('go'),
        ];
    }
}
