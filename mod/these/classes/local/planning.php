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

use mod_these\output;
use context;
use stdClass;

/**
 * Planning des soutenances.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planning {
    /**
     * Source du planning pour une activité « Gestion des thèses » : même forme que les liens d'une
     * activité « Suivi des thèses » (voir thesesynthesis_get_active_links()).
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param context $context
     * @param int $userid
     * @return stdClass {these, cm, course, context, rights, direct}
     */
    public static function planning_source(stdClass $these, stdClass $cm, context $context, $userid) {
        return (object) [
            'these' => $these,
            'cm' => $cm,
            'course' => get_course($cm->course),
            'context' => $context,
            'rights' => these_get_rights($context, null, $userid),
            'direct' => has_capability('mod/these:direct', $context, $userid),
        ];
    }

    /**
     * Début (lundi, 0 h) de la semaine qui contient un instant, dans le fuseau de l'utilisateur.
     *
     * @param int $time
     * @return int
     */
    public static function planning_week_start($time) {
        $midnight = usergetmidnight($time);
        $date = usergetdate($midnight);
        $offset = ((int) $date['wday'] + 6) % 7;
        // Midi plutôt que minuit avant de revenir à minuit : un changement d'heure dans l'intervalle ne
        // décale pas le jour.
        return usergetmidnight($midnight - $offset * DAYSECS + 12 * HOURSECS);
    }

    /**
     * Décale un début de semaine d'un nombre de semaines.
     *
     * @param int $weekstart
     * @param int $weeks
     * @return int
     */
    public static function planning_shift_week($weekstart, $weeks) {
        return usergetmidnight($weekstart + $weeks * WEEKSECS + 12 * HOURSECS);
    }

    /**
     * Début (1er, 0 h) du mois qui contient un instant, dans le fuseau de l'utilisateur.
     *
     * @param int $time
     * @return int
     */
    public static function planning_month_start($time) {
        $date = usergetdate($time);
        return make_timestamp($date['year'], $date['mon'], 1);
    }

    /**
     * Décale un début de mois d'un nombre de mois.
     *
     * @param int $monthstart
     * @param int $months
     * @return int
     */
    public static function planning_shift_month($monthstart, $months) {
        $date = usergetdate($monthstart);
        return make_timestamp($date['year'], $date['mon'] + $months, 1);
    }

    /**
     * Soutenances prévues sur une période, dans les activités données. La DEVE, la bibliothèque et
     * les détenteurs de mod/these:viewall voient toutes les soutenances d'une activité ; un directeur
     * de thèse, celles des dossiers qu'il dirige.
     *
     * Une soutenance figure au planning dès le dépôt du dossier de soutenance, à la date proposée par
     * l'étudiant ; la date est arrêtée quand la DEVE valide le dossier. Les dossiers abandonnés et les
     * thèses ajournées (revenues avant le dépôt) n'y figurent pas.
     *
     * @param stdClass[] $sources Voir these_planning_source().
     * @param int $from Début de la période (inclus).
     * @param int $to Fin de la période (exclue).
     * @param int $userid
     * @return stdClass[] {dossier, source, student, director, president, jury, confirmed}, dans l'ordre
     *                    chronologique.
     */
    public static function get_defenses(array $sources, $from, $to, $userid) {
        global $DB;

        $defenses = [];
        foreach ($sources as $source) {
            $params = [
                'theseid' => $source->these->id,
                'minstatus' => THESE_STATUS_DEFENSE_SUBMITTED,
                'from' => $from,
                'to' => $to,
            ];
            $where = 'theseid = :theseid AND status >= :minstatus AND defensedate >= :from AND defensedate < :to';
            if (!$source->rights->viewall) {
                if (!$source->direct) {
                    continue;
                }
                $where .= ' AND directorid = :directorid';
                $params['directorid'] = $userid;
            }
            foreach ($DB->get_records_select('these_dossier', $where, $params, 'defensedate ASC, id ASC') as $dossier) {
                $jury = array_values(these_get_jury($dossier->id));
                $president = null;
                foreach ($jury as $member) {
                    if ($member->juryrole === 'president') {
                        $president = $member;
                        break;
                    }
                }
                $defenses[] = (object) [
                    'dossier' => $dossier,
                    'source' => $source,
                    'student' => $DB->get_record('user', ['id' => $dossier->userid]),
                    'director' => $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null,
                    'president' => $president,
                    'jury' => $jury,
                    'confirmed' => these_rank_reached($dossier, THESE_STATUS_DEFENSE_VALIDATED),
                ];
            }
        }
        usort($defenses, function ($a, $b) {
            return [(int) $a->dossier->defensedate, $a->student ? $a->student->lastname : '']
                <=> [(int) $b->dossier->defensedate, $b->student ? $b->student->lastname : ''];
        });
        return $defenses;
    }

    /**
     * Nombre de soutenances par semaine sur plusieurs semaines, pour la navigation du planning.
     *
     * @param stdClass[] $sources
     * @param int $weekstart Première semaine.
     * @param int $weeks Nombre de semaines.
     * @param int $userid
     * @return int[] début de semaine => nombre de soutenances (semaines sans soutenance omises).
     */
    public static function count_defenses_by_week(array $sources, $weekstart, $weeks, $userid) {
        $end = these_planning_shift_week($weekstart, $weeks);
        $counts = [];
        foreach (these_get_defenses($sources, $weekstart, $end, $userid) as $defense) {
            $week = these_planning_week_start((int) $defense->dossier->defensedate);
            $counts[$week] = ($counts[$week] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * Valeurs affichées d'une soutenance, communes à la page, au tableur et au planning PDF.
     *
     * @param stdClass $defense Voir these_get_defenses().
     * @return array Textes bruts : day, date, time, student, title, director, president, jury,
     *               location, promotion, devenumber, confirmed, status.
     */
    public static function defense_row(stdClass $defense) {
        $dossier = $defense->dossier;
        $name = function ($member) {
            return trim($member->jurytitle . ' ' . $member->fullname);
        };
        $others = array_filter($defense->jury, function ($member) use ($defense) {
            return $member !== $defense->president;
        });
        $roles = these_jury_role_options();
        $director = $defense->director ? fullname($defense->director) : '';
        if (!empty($dossier->externaldirector) && trim((string) $dossier->extdirectorname) !== '') {
            $director = trim((string) $dossier->extdirectorname) . ($director !== '' ? ' (' . $director . ')' : '');
        }
        return [
            'day' => userdate($dossier->defensedate, get_string('strftimedaydate', 'langconfig')),
            'date' => userdate($dossier->defensedate, get_string('strftimedatefullshort', 'langconfig')),
            'time' => userdate($dossier->defensedate, get_string('strftimetime', 'langconfig')),
            'student' => $defense->student ? fullname($defense->student) : '',
            'title' => format_string(
                these_subject_field($dossier, 'title', $defense->source->context),
                true,
                ['context' => $defense->source->context, 'escape' => false]
            ),
            'director' => $director,
            'president' => $defense->president ? $name($defense->president) : '',
            'jury' => implode(', ', array_map(function ($member) use ($name, $roles) {
                // Le libellé d'un rôle peut lui-même contenir des parenthèses (« Directeur de thèse (membre
                // du jury) ») : il suit le nom après un tiret.
                return $name($member) . ($member->juryrole === 'member' ? '' : ' – ' . ($roles[$member->juryrole] ?? ''));
            }, $others)),
            'location' => trim((string) $dossier->defenselocation),
            'promotion' => format_string(
                $defense->source->course->shortname ?: $defense->source->course->fullname,
                true,
                ['context' => $defense->source->context, 'escape' => false]
            ),
            'devenumber' => (string) $dossier->devenumber,
            'confirmed' => (bool) $defense->confirmed,
            'status' => get_string($defense->confirmed ? 'planningconfirmed' : 'planningproposed', 'mod_these'),
        ];
    }

    /**
     * Rendu HTML des soutenances d'une période, regroupées par jour.
     *
     * @param stdClass[] $defenses Voir these_get_defenses().
     * @param bool $showpromotion Afficher la promotion (planning de plusieurs activités).
     * @return string
     */
    public static function render_planning(array $defenses, $showpromotion) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\planning($defenses, $showpromotion));
    }

    /**
     * Données du modèle « Planning mensuel des soutenances ».
     *
     * @param stdClass[] $defenses Voir these_get_defenses().
     * @param string $periodlabel Période (« novembre 2026 »).
     * @param stdClass|null $establishment Activité dont reprendre l'établissement.
     * @param stdClass|null $scope Portée dont reprendre les images (voir these_settings_scope()).
     * @param bool $showpromotion
     * @return array
     */
    public static function planning_document_context(
        array $defenses,
        $periodlabel,
        ?stdClass $establishment,
        ?stdClass $scope,
        $showpromotion
    ) {
        $days = [];
        foreach ($defenses as $defense) {
            $row = these_defense_row($defense);
            if (!$showpromotion) {
                $row['promotion'] = '';
            }
            $key = usergetmidnight($defense->dossier->defensedate);
            if (!isset($days[$key])) {
                $days[$key] = ['label' => $row['day'], 'defenses' => []];
            }
            $days[$key]['defenses'][] = $row;
        }
        return [
            'today' => userdate(time(), '%d/%m/%Y'),
            'establishment' => [
                'name' => (string) ($establishment->establishmentname ?? ''),
                'city' => (string) ($establishment->establishmentcity ?? ''),
            ],
            'period' => ['label' => $periodlabel],
            'count' => count($defenses),
            'hasdefenses' => !empty($defenses),
            'days' => array_values($days),
            'img' => these_doctemplate_images($scope),
        ];
    }

    /**
     * Données d'exemple du planning mensuel, pour l'aperçu et la vérification du modèle.
     *
     * @param stdClass|null $scope
     * @param stdClass|null $activity Activité dont reprendre l'établissement.
     * @return array
     */
    public static function sample_planning_context(?stdClass $scope = null, ?stdClass $activity = null) {
        $establishment = $activity ?? (object) ['establishmentname' => 'VetAgro Sup', 'establishmentcity' => "Marcy-l'Étoile"];
        $start = these_planning_month_start(time());
        $sample = function ($day, $hour, $student, $title, $director, $president, $jury, $location, $confirmed) use ($start) {
            $time = $start + ($day - 1) * DAYSECS + $hour * HOURSECS;
            return [
                'day' => userdate($time, get_string('strftimedaydate', 'langconfig')),
                'date' => userdate($time, get_string('strftimedatefullshort', 'langconfig')),
                'time' => userdate($time, get_string('strftimetime', 'langconfig')),
                'student' => $student, 'title' => $title, 'director' => $director, 'president' => $president,
                'jury' => $jury, 'location' => $location, 'promotion' => 'Promotion 2026', 'devenumber' => '2026-042',
                'confirmed' => $confirmed,
                'status' => get_string($confirmed ? 'planningconfirmed' : 'planningproposed', 'mod_these'),
            ];
        };
        $first = $sample(
            3,
            14,
            'Camille Martin',
            'Prise en charge de la douleur chronique chez le chien âgé',
            'Pr Anne Durand',
            'Pr Jean DUPONT',
            'Dr Claire BERNARD, Pr Anne DURAND (Directeur de thèse)',
            'Amphithéâtre Bourgelat',
            true
        );
        $second = $sample(
            3,
            16,
            'Lucas Bernard',
            'Leptospirose canine en zone périurbaine',
            'Dr Paul Leroy',
            'Pr Marie LEFÈVRE',
            'Dr Paul LEROY (Directeur de thèse), Dr Luc PETIT',
            'Salle du conseil',
            false
        );
        return [
            'today' => userdate(time(), '%d/%m/%Y'),
            'establishment' => [
                'name' => (string) ($establishment->establishmentname ?? ''),
                'city' => (string) ($establishment->establishmentcity ?? ''),
            ],
            'period' => ['label' => userdate($start, '%B %Y')],
            'count' => 2,
            'hasdefenses' => true,
            'days' => [['label' => $first['day'], 'defenses' => [$first, $second]]],
            'img' => these_doctemplate_images($scope),
        ];
    }
}
