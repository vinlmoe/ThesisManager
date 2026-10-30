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

namespace mod_these\page;

use mod_these\local\backlinks;
use mod_these\local\documentrenderer;
use mod_these\local\planning;
use mod_these\local\rendering;
use mod_these\output;
use moodle_url;
use stdClass;

/**
 * Page « Planning des soutenances », commune à une activité « Gestion des thèses » et à une activité
 * « Suivi des thèses » (toutes les promotions suivies) : soutenances de la semaine ou du mois,
 * navigation d'une période à l'autre, planning imprimable (PDF) et téléchargeable (tableur).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planning_page {
    /** @var int Nombre de semaines à venir proposées dans la navigation. */
    const UPCOMING_WEEKS = 12;

    /**
     * Traite la requête et affiche la page (le contexte, l'adresse et le titre de $PAGE sont déjà
     * définis par l'appelant).
     *
     * @param stdClass[] $sources Activités du planning, voir these_planning_source().
     * @param moodle_url $baseurl Adresse de la page (avec ses paramètres d'accès).
     * @param stdClass|null $scope Portée dont reprendre le modèle du planning et ses images.
     * @param stdClass|null $establishment Activité dont reprendre l'établissement.
     * @param bool $showpromotion Afficher la promotion de chaque soutenance.
     * @param string $heading Titre de la page.
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(
        array $sources,
        moodle_url $baseurl,
        ?stdClass $scope,
        ?stdClass $establishment,
        $showpromotion,
        $heading,
        $navigation = ''
    ) {
        global $USER;

        $period = self::period();
        $defenses = planning::get_defenses($sources, $period->from, $period->to, $USER->id);

        $format = optional_param('format', '', PARAM_ALPHA);
        if ($format === 'pdf') {
            self::send_pdf($defenses, $period, $scope, $establishment, $showpromotion);
        }
        $dataformat = optional_param('dataformat', '', PARAM_ALPHA);
        if ($dataformat !== '') {
            self::send_spreadsheet($defenses, $period, $dataformat);
        }

        // Page d'origine des dossiers ouverts d'ici, à la période affichée (voir backlinks).
        backlinks::remember(
            new moodle_url($baseurl, ['view' => $period->view, 'date' => $period->from]),
            get_string('planning', 'mod_these'),
            array_map(function ($source) {
                return (int) $source->these->id;
            }, $sources)
        );
        self::render($sources, $baseurl, $period, $defenses, $showpromotion, $heading, $navigation);
    }

    /**
     * Période demandée (semaine ou mois, autour d'une date) : bornes, périodes voisine et courante,
     * libellé et nom de fichier.
     *
     * @return stdClass {view, date, from, to, previous, next, current, label, filename}
     */
    protected static function period() {
        $view = optional_param('view', 'week', PARAM_ALPHA) === 'month' ? 'month' : 'week';
        $date = optional_param('date', 0, PARAM_INT) ?: time();
        $goto = optional_param('goto', '', PARAM_RAW_TRIMMED);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $goto, $match) && checkdate($match[2], $match[3], $match[1])) {
            $date = make_timestamp((int) $match[1], (int) $match[2], (int) $match[3], 12);
        }
        $period = (object) ['view' => $view, 'date' => $date];
        if ($view === 'week') {
            $period->from = planning::planning_week_start($date);
            $period->to = planning::planning_shift_week($period->from, 1);
            $period->previous = planning::planning_shift_week($period->from, -1);
            $period->current = planning::planning_week_start(time());
            $period->label = get_string('planningweekof', 'mod_these', (object) [
                'start' => userdate($period->from, get_string('strftimedate', 'langconfig')),
                'end' => userdate($period->to - DAYSECS, get_string('strftimedate', 'langconfig')),
            ]);
            $period->filename = 'planning-soutenances-semaine-' . userdate($period->from, '%Y-%m-%d');
        } else {
            $period->from = planning::planning_month_start($date);
            $period->to = planning::planning_shift_month($period->from, 1);
            $period->previous = planning::planning_shift_month($period->from, -1);
            $period->current = planning::planning_month_start(time());
            $period->label = userdate($period->from, get_string('strftimemonthyear', 'mod_these'));
            $period->filename = 'planning-soutenances-' . userdate($period->from, '%Y-%m');
        }
        $period->next = $period->to;
        return $period;
    }

    /**
     * Envoie le planning imprimable (PDF) de la période, et termine la requête.
     *
     * @param array $defenses Voir planning::get_defenses().
     * @param stdClass $period Voir period().
     * @param stdClass|null $scope
     * @param stdClass|null $establishment
     * @param bool $showpromotion
     * @return void
     */
    protected static function send_pdf(
        array $defenses,
        stdClass $period,
        ?stdClass $scope,
        ?stdClass $establishment,
        $showpromotion
    ) {
        $data = planning::planning_document_context($defenses, $period->label, $establishment, $scope, $showpromotion);
        send_file(
            documentrenderer::render_scope_doctemplate($scope, 'planning', $data),
            $period->filename . '.pdf',
            0,
            0,
            true,
            true,
            'application/pdf'
        );
    }

    /**
     * Envoie le planning de la période en tableur, et termine la requête.
     *
     * @param array $defenses Voir planning::get_defenses().
     * @param stdClass $period Voir period().
     * @param string $dataformat Format de téléchargement (voir core\dataformat).
     * @return void
     */
    protected static function send_spreadsheet(array $defenses, stdClass $period, $dataformat) {
        $columns = [
            'date' => get_string('defensedate', 'mod_these'),
            'time' => get_string('planningtime', 'mod_these'),
            'status' => get_string('status', 'mod_these'),
            'student' => get_string('student', 'mod_these'),
            'promotion' => get_string('planningpromotion', 'mod_these'),
            'title' => get_string('subjecttitle', 'mod_these'),
            'director' => get_string('director', 'mod_these'),
            'president' => get_string('juryrolepresident', 'mod_these'),
            'jury' => get_string('jury', 'mod_these'),
            'location' => get_string('defenselocation', 'mod_these'),
            'devenumber' => get_string('devenumber', 'mod_these'),
        ];
        \core\dataformat::download_data($period->filename, $dataformat, $columns, $defenses, function ($defense) use ($columns) {
            return rendering::spreadsheet_safe(array_intersect_key(planning::defense_row($defense), $columns));
        });
        die();
    }

    /**
     * Affiche la page : navigation, soutenances de la période, impression et téléchargement,
     * semaines à venir.
     *
     * @param stdClass[] $sources
     * @param moodle_url $baseurl
     * @param stdClass $period Voir period().
     * @param array $defenses
     * @param bool $showpromotion
     * @param string $heading
     * @param string $navigation
     * @return void
     */
    protected static function render(
        array $sources,
        moodle_url $baseurl,
        stdClass $period,
        array $defenses,
        $showpromotion,
        $heading,
        $navigation
    ) {
        global $OUTPUT, $PAGE, $USER;

        $renderer = $PAGE->get_renderer('mod_these');
        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);
        echo $navigation;
        echo $renderer->render(new output\planning_nav(
            $baseurl,
            $period->view,
            $period->from,
            $period->previous,
            $period->current,
            $period->next,
            $period->date
        ));

        echo $OUTPUT->heading($period->label . ' — ' . get_string('planningcount', 'mod_these', count($defenses)), 3);
        echo planning::render_planning($defenses, $showpromotion);

        // Impression et téléchargement de la période affichée.
        echo $renderer->render(new output\planning_print($baseurl, $period->view, $period->from));
        echo $OUTPUT->download_dataformat_selector(
            get_string('planningdownload', 'mod_these'),
            $baseurl->out_omit_querystring(),
            'dataformat',
            array_merge($baseurl->params(), ['view' => $period->view, 'date' => $period->from])
        );

        // Semaines à venir qui ont des soutenances.
        if ($period->view === 'week') {
            $start = max($period->current, $period->next);
            $counts = planning::count_defenses_by_week($sources, $start, self::UPCOMING_WEEKS, $USER->id);
            if ($counts) {
                echo $OUTPUT->heading(get_string('planningupcoming', 'mod_these'), 4, 'mt-4');
                echo $renderer->render(new output\planning_upcoming($baseurl, $counts));
            }
        }

        echo $OUTPUT->footer();
    }
}
