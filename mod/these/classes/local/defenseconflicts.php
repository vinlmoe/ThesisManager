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

use core_text;
use stdClass;

/**
 * Chevauchements de soutenances, toutes promotions confondues : même salle sur un créneau commun,
 * membre du jury engagé dans deux jurys à la fois, et leurs avertissements.
 *
 * Les fonctions these_*() correspondantes de locallib/jury.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defenseconflicts {
    /**
     * Durée d'une soutenance d'un dossier, en secondes : paramètre du circuit de son activité.
     *
     * @param stdClass $dossier
     * @return int
     */
    public static function defense_duration(stdClass $dossier) {
        return max(1, (int) scopesettings::for_dossier($dossier)['defenseduration']) * MINSECS;
    }

    /**
     * Autres soutenances qui chevauchent celle d'un dossier (date arrêtée, ou à défaut date proposée),
     * toutes promotions confondues, chacune durant la durée fixée dans les paramètres du circuit de son
     * activité. Les dossiers abandonnés sont ignorés.
     *
     * @param stdClass $dossier
     * @return stdClass[] Dossiers, par date.
     */
    public static function overlapping_dossiers(stdClass $dossier) {
        global $DB;

        $time = (int) ($dossier->defensedate ?: $dossier->proposeddate);
        if (!$time) {
            return [];
        }
        // Candidats : soutenances commençant au plus une journée avant et avant la fin de celle-ci ; le
        // chevauchement est ensuite vérifié avec la durée propre à chacune.
        $end = $time + self::defense_duration($dossier);
        $params = ['id' => (int) $dossier->id, 'cancelled' => THESE_STATUS_CANCELLED, 'from1' => $time - DAYSECS,
            'to1' => $end - 1, 'from2' => $time - DAYSECS, 'to2' => $end - 1];
        $others = $DB->get_records_select(
            'these_dossier',
            'id <> :id AND status <> :cancelled
             AND ((defensedate > 0 AND defensedate BETWEEN :from1 AND :to1)
                  OR (defensedate = 0 AND proposeddate BETWEEN :from2 AND :to2))',
            $params,
            'defensedate ASC, proposeddate ASC, id ASC'
        );
        return array_values(array_filter($others, function ($other) use ($time) {
            return (int) ($other->defensedate ?: $other->proposeddate) + self::defense_duration($other) > $time;
        }));
    }

    /**
     * Lieu ramené à une forme comparable : casse, espaces.
     *
     * @param string|null $location
     * @return string
     */
    public static function normalise_location($location) {
        return core_text::strtolower(trim(preg_replace('/\s+/u', ' ', (string) $location)));
    }

    /**
     * Autres soutenances prévues dans la même salle sur un créneau qui chevauche celle d'un dossier
     * (voir overlapping_dossiers()). Les soutenances sans lieu sont ignorées.
     *
     * @param stdClass $dossier
     * @param string|null $location Lieu à vérifier (par défaut, celui du dossier).
     * @return stdClass[] {location, time, student (nom), activity (nom de l'activité)}
     */
    public static function room_conflicts(stdClass $dossier, $location = null) {
        global $DB;

        $room = self::normalise_location($location ?? $dossier->defenselocation);
        if ($room === '') {
            return [];
        }
        $conflicts = [];
        foreach (self::overlapping_dossiers($dossier) as $other) {
            if (self::normalise_location($other->defenselocation) !== $room) {
                continue;
            }
            $student = $DB->get_record('user', ['id' => $other->userid]);
            $conflicts[] = (object) [
                'location' => trim((string) $other->defenselocation),
                'time' => (int) ($other->defensedate ?: $other->proposeddate),
                'student' => $student ? fullname($student) : '-',
                'activity' => (string) $DB->get_field('these', 'name', ['id' => $other->theseid]),
            ];
        }
        return $conflicts;
    }

    /**
     * Avertissement des conflits de salle d'un dossier (voir room_conflicts()), pour le personnel.
     *
     * @param stdClass $dossier
     * @return string HTML, vide sans conflit.
     */
    public static function render_room_conflicts(stdClass $dossier) {
        global $OUTPUT, $PAGE;

        $lines = array_map(function ($conflict) {
            return get_string('roomconflict', 'mod_these', (object) [
                'location' => $conflict->location,
                'date' => userdate($conflict->time, get_string('strftimedaydatetime', 'langconfig')),
                'student' => $conflict->student,
                'activity' => format_string($conflict->activity),
            ]);
        }, self::room_conflicts($dossier));
        if (!$lines) {
            return '';
        }
        $list = $PAGE->get_renderer('mod_these')->render(new \mod_these\output\bullet_list($lines));
        return $OUTPUT->notification(get_string('roomconflicts', 'mod_these') . $list, 'warning');
    }

    /**
     * Membres du jury d'un dossier déjà engagés dans un autre jury sur le même créneau, toutes
     * promotions confondues : les deux soutenances (date arrêtée, ou à défaut date proposée) se
     * chevauchent, chacune durant la durée fixée dans les paramètres du circuit de son activité. Les
     * dossiers abandonnés sont ignorés.
     *
     * @param stdClass $dossier
     * @param array|null $members Membres à vérifier (par défaut, le jury enregistré du dossier).
     * @return stdClass[] {member (nom), time, student (nom), activity (nom de l'activité)}
     */
    public static function jury_conflicts(stdClass $dossier, ?array $members = null) {
        global $DB;

        $members = $members ?? jury::get_jury($dossier->id);
        if (!$members) {
            return [];
        }
        $conflicts = [];
        foreach (self::overlapping_dossiers($dossier) as $other) {
            $otherjury = jury::get_jury($other->id);
            foreach ($members as $member) {
                foreach ($otherjury as $othermember) {
                    if (!jury::same_member($member, $othermember)) {
                        continue;
                    }
                    $student = $DB->get_record('user', ['id' => $other->userid]);
                    $conflicts[] = (object) [
                        'member' => trim(($member->jurytitle ?? '') . ' ' . $member->fullname),
                        'time' => (int) ($other->defensedate ?: $other->proposeddate),
                        'student' => $student ? fullname($student) : '-',
                        'activity' => (string) $DB->get_field('these', 'name', ['id' => $other->theseid]),
                    ];
                }
            }
        }
        return $conflicts;
    }

    /**
     * Avertissement des conflits de jury d'un dossier (voir jury_conflicts()), pour la DEVE.
     *
     * @param stdClass $dossier
     * @return string HTML, vide sans conflit.
     */
    public static function render_jury_conflicts(stdClass $dossier) {
        global $OUTPUT, $PAGE;

        $lines = array_map(function ($conflict) {
            return get_string('juryconflict', 'mod_these', (object) [
                'member' => $conflict->member,
                'date' => userdate($conflict->time, get_string('strftimedaydatetime', 'langconfig')),
                'student' => $conflict->student,
                'activity' => format_string($conflict->activity),
            ]);
        }, self::jury_conflicts($dossier));
        if (!$lines) {
            return '';
        }
        $list = $PAGE->get_renderer('mod_these')->render(new \mod_these\output\bullet_list($lines));
        return $OUTPUT->notification(get_string('juryconflicts', 'mod_these') . $list, 'warning');
    }
}
