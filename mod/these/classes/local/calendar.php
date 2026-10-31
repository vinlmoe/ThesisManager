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

use stdClass;

/**
 * Soutenances dans le calendrier Moodle : un événement de cours par soutenance dont la date est
 * arrêtée par la DEVE (dossier validé et au-delà), visible des participants du cours qui voient
 * l'activité. Il porte l'étudiant, la date, la durée, la salle et le jury ; le titre d'une thèse
 * confidentielle n'y figure pas. L'événement suit le dossier : déplacé avec la soutenance, supprimé
 * à l'abandon ou à l'ajournement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar {
    /** @var string Type des événements de soutenance. */
    const EVENTTYPE = 'defense';

    /**
     * Identifiant de l'événement d'un dossier.
     *
     * @param stdClass $dossier
     * @return string
     */
    protected static function uuid(stdClass $dossier) {
        return 'these-dossier-' . (int) $dossier->id;
    }

    /**
     * Le dossier a-t-il une soutenance à afficher au calendrier ?
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function has_event(stdClass $dossier) {
        return !empty($dossier->defensedate) && (int) $dossier->status >= THESE_STATUS_DEFENSE_VALIDATED;
    }

    /**
     * Crée, met à jour ou supprime l'événement de soutenance d'un dossier.
     *
     * @param stdClass $dossier
     * @return void
     */
    public static function sync(stdClass $dossier) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/calendar/lib.php');
        $these = $DB->get_record('these', ['id' => $dossier->theseid]);
        $cm = $these ? get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING) : null;
        $existing = $DB->get_records('event', [
            'modulename' => 'these',
            'instance' => (int) $dossier->theseid,
            'eventtype' => self::EVENTTYPE,
            'uuid' => self::uuid($dossier),
        ]);
        if (!$cm || !self::has_event($dossier)) {
            foreach ($existing as $record) {
                \calendar_event::load($record)->delete();
            }
            return;
        }
        $data = self::event_data($these, $cm, $dossier);
        $record = array_shift($existing);
        foreach ($existing as $duplicate) {
            \calendar_event::load($duplicate)->delete();
        }
        if ($record) {
            \calendar_event::load($record)->update($data, false);
        } else {
            \calendar_event::create($data, false);
        }
    }

    /**
     * Données de l'événement d'un dossier.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @return stdClass
     */
    protected static function event_data(stdClass $these, stdClass $cm, stdClass $dossier) {
        global $DB;

        $student = $DB->get_record('user', ['id' => $dossier->userid]);
        $name = $student ? fullname($student) : '-';
        $lines = [];
        if (empty($dossier->confidential) && trim((string) $dossier->title) !== '') {
            $lines[] = s($dossier->title);
        }
        if (trim((string) $dossier->defenselocation) !== '') {
            $lines[] = get_string('defenselocation', 'mod_these') . ' : ' . s($dossier->defenselocation);
        }
        $roles = workflow::jury_role_options();
        $jury = array_map(function ($member) use ($roles) {
            return s(trim($member->jurytitle . ' ' . $member->fullname)) . ' — ' . ($roles[$member->juryrole] ?? '');
        }, jury::get_jury($dossier->id));
        if ($jury) {
            $lines[] = get_string('jury', 'mod_these') . ' : ' . implode(' ; ', $jury);
        }
        return (object) [
            'name' => get_string('calendardefense', 'mod_these', $name),
            'description' => implode('<br />', $lines),
            'format' => FORMAT_HTML,
            'courseid' => (int) $cm->course,
            'groupid' => 0,
            'userid' => 0,
            'modulename' => 'these',
            'instance' => (int) $these->id,
            'eventtype' => self::EVENTTYPE,
            'uuid' => self::uuid($dossier),
            'timestart' => (int) $dossier->defensedate,
            'timeduration' => defenseconflicts::defense_duration($dossier),
            'visible' => (int) $cm->visible,
        ];
    }

    /**
     * Supprime les événements de soutenance de dossiers supprimés (effacement RGPD d'un étudiant, par
     * exemple), sans attendre la synchronisation quotidienne.
     *
     * @param int[] $dossierids
     * @return void
     */
    public static function delete_events(array $dossierids) {
        global $CFG, $DB;

        if (!$dossierids) {
            return;
        }
        require_once($CFG->dirroot . '/calendar/lib.php');
        $uuids = array_map(function ($id) {
            return 'these-dossier-' . (int) $id;
        }, $dossierids);
        [$insql, $params] = $DB->get_in_or_equal($uuids, SQL_PARAMS_NAMED);
        $params['modulename'] = 'these';
        $params['eventtype'] = self::EVENTTYPE;
        $events = $DB->get_records_select('event', "modulename = :modulename AND eventtype = :eventtype AND uuid $insql", $params);
        foreach ($events as $record) {
            \calendar_event::load($record)->delete();
        }
    }

    /**
     * Remet en accord le calendrier avec tous les dossiers (tâche quotidienne) : événements des
     * soutenances arrêtées, y compris celles fixées avant cette fonction, et suppression des autres.
     *
     * @return int Dossiers traités.
     */
    public static function sync_all() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/calendar/lib.php');
        $ids = $DB->get_fieldset_select(
            'these_dossier',
            'id',
            'defensedate > 0 AND status >= ?',
            [THESE_STATUS_DEFENSE_VALIDATED]
        );
        $events = $DB->get_records('event', ['modulename' => 'these', 'eventtype' => self::EVENTTYPE], '', 'id, uuid');
        foreach ($events as $event) {
            if (preg_match('/^these-dossier-(\d+)$/', (string) $event->uuid, $match)) {
                $ids[] = $match[1];
            }
        }
        $count = 0;
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $dossier = $DB->get_record('these_dossier', ['id' => $id]);
            if ($dossier) {
                self::sync($dossier);
                $count++;
            } else {
                // Dossier supprimé : son événement aussi.
                foreach ($DB->get_records('event', ['modulename' => 'these', 'uuid' => 'these-dossier-' . $id]) as $record) {
                    \calendar_event::load($record)->delete();
                }
            }
        }
        return $count;
    }
}
