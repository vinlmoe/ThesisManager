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

namespace mod_these\local;

use html_writer;
use moodle_url;

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
     * @param \stdClass[] $sources Activités du planning, voir these_planning_source().
     * @param moodle_url $baseurl Adresse de la page (avec ses paramètres d'accès).
     * @param \stdClass|null $scope Portée dont reprendre le modèle du planning et ses images.
     * @param \stdClass|null $establishment Activité dont reprendre l'établissement.
     * @param bool $showpromotion Afficher la promotion de chaque soutenance.
     * @param string $heading Titre de la page.
     * @param string $navigation HTML affiché sous le titre.
     * @return void
     */
    public static function output(
        array $sources,
        moodle_url $baseurl,
        ?\stdClass $scope,
        ?\stdClass $establishment,
        $showpromotion,
        $heading,
        $navigation = ''
    ) {
        global $OUTPUT, $USER;

        $view = optional_param('view', 'week', PARAM_ALPHA) === 'month' ? 'month' : 'week';
        $date = optional_param('date', 0, PARAM_INT) ?: time();
        $goto = optional_param('goto', '', PARAM_RAW_TRIMMED);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $goto, $match) && checkdate($match[2], $match[3], $match[1])) {
            $date = make_timestamp((int) $match[1], (int) $match[2], (int) $match[3], 12);
        }
        $format = optional_param('format', '', PARAM_ALPHA);
        $dataformat = optional_param('dataformat', '', PARAM_ALPHA);

        if ($view === 'week') {
            $from = these_planning_week_start($date);
            $to = these_planning_shift_week($from, 1);
            $previous = these_planning_shift_week($from, -1);
            $next = $to;
            $current = these_planning_week_start(time());
            $label = get_string('planningweekof', 'mod_these', (object) [
                'start' => userdate($from, get_string('strftimedate', 'langconfig')),
                'end' => userdate($to - DAYSECS, get_string('strftimedate', 'langconfig')),
            ]);
            $filename = 'planning-soutenances-semaine-' . userdate($from, '%Y-%m-%d');
        } else {
            $from = these_planning_month_start($date);
            $to = these_planning_shift_month($from, 1);
            $previous = these_planning_shift_month($from, -1);
            $next = $to;
            $current = these_planning_month_start(time());
            $label = userdate($from, '%B %Y');
            $filename = 'planning-soutenances-' . userdate($from, '%Y-%m');
        }
        $defenses = these_get_defenses($sources, $from, $to, $USER->id);

        // Planning imprimable.
        if ($format === 'pdf') {
            $data = these_planning_document_context($defenses, $label, $establishment, $scope, $showpromotion);
            send_file(
                these_render_scope_doctemplate($scope, 'planning', $data),
                $filename . '.pdf',
                0,
                0,
                true,
                true,
                'application/pdf'
            );
        }

        // Planning en tableur.
        if ($dataformat !== '') {
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
            \core\dataformat::download_data($filename, $dataformat, $columns, $defenses, function ($defense) use ($columns) {
                return array_intersect_key(these_defense_row($defense), $columns);
            });
            die();
        }

        $url = function (array $params) use ($baseurl, $view) {
            return new moodle_url($baseurl, array_merge(['view' => $view], $params));
        };

        echo $OUTPUT->header();
        echo $OUTPUT->heading($heading);
        echo $navigation;

        // Vue par semaine ou par mois.
        $views = [];
        foreach (['week' => 'planningviewweek', 'month' => 'planningviewmonth'] as $name => $string) {
            $views[] = html_writer::link(
                new moodle_url($baseurl, ['view' => $name, 'date' => $from]),
                get_string($string, 'mod_these'),
                ['class' => 'btn btn-sm ' . ($name === $view ? 'btn-primary' : 'btn-outline-primary') . ' mr-1']
            );
        }
        echo html_writer::div(implode('', $views), 'mb-3');

        // Période précédente, courante, suivante, et accès à une date.
        $suffix = $view === 'week' ? 'week' : 'month';
        $button = function ($date, $text, $class) use ($url) {
            return html_writer::link($url(['date' => $date]), $text, ['class' => 'btn btn-secondary mb-1 ' . $class]);
        };
        $navlinks = [
            $button($previous, '« ' . get_string('planningprev' . $suffix, 'mod_these'), 'mr-1'),
            $button($current, get_string('planningthis' . $suffix, 'mod_these'), 'mr-1' . ($current === $from ? ' active' : '')),
            $button($next, get_string('planningnext' . $suffix, 'mod_these') . ' »', 'mr-3'),
        ];
        $form = html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out_omit_querystring(),
            'class' => 'form-inline d-inline-flex mb-1']);
        foreach ($baseurl->params() as $name => $value) {
            $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'view', 'value' => $view]);
        $form .= html_writer::label(get_string('planninggoto', 'mod_these'), 'these-planning-goto', true, ['class' => 'mr-1']);
        $form .= html_writer::empty_tag('input', ['type' => 'date', 'name' => 'goto', 'id' => 'these-planning-goto',
            'value' => userdate($date, '%Y-%m-%d'), 'class' => 'form-control mr-1']);
        $form .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('go'),
            'class' => 'btn btn-secondary']);
        $form .= html_writer::end_tag('form');
        echo html_writer::div(implode('', $navlinks) . $form, 'these-planning-nav mb-3');

        echo $OUTPUT->heading($label . ' — ' . get_string('planningcount', 'mod_these', count($defenses)), 3);
        echo these_render_planning($defenses, $showpromotion);

        // Impression et téléchargement de la période affichée.
        echo html_writer::div(
            html_writer::link(
                $url(['date' => $from, 'format' => 'pdf']),
                get_string($view === 'month' ? 'planningpdfmonth' : 'planningpdfweek', 'mod_these'),
                ['class' => 'btn btn-primary']
            ),
            'mt-3 mb-2'
        );
        echo $OUTPUT->download_dataformat_selector(
            get_string('planningdownload', 'mod_these'),
            $baseurl->out_omit_querystring(),
            'dataformat',
            array_merge($baseurl->params(), ['view' => $view, 'date' => $from])
        );

        // Semaines à venir qui ont des soutenances.
        if ($view === 'week') {
            $start = max($current, $next);
            $counts = these_count_defenses_by_week($sources, $start, self::UPCOMING_WEEKS, $USER->id);
            if ($counts) {
                $items = [];
                foreach ($counts as $week => $count) {
                    $items[] = html_writer::link($url(['date' => $week]), get_string('planningweekof', 'mod_these', (object) [
                        'start' => userdate($week, get_string('strftimedate', 'langconfig')),
                        'end' => userdate(these_planning_shift_week($week, 1) - DAYSECS, get_string('strftimedate', 'langconfig')),
                    ])) . ' — ' . get_string('planningcount', 'mod_these', $count);
                }
                echo $OUTPUT->heading(get_string('planningupcoming', 'mod_these'), 4, 'mt-4');
                echo html_writer::alist($items);
            }
        }

        echo $OUTPUT->footer();
    }
}
