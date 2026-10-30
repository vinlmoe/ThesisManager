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

namespace mod_thesesynthesis\local;

use core_text;
use mod_these\local\rights;
use mod_these\local\workflow;
use stdClass;

/**
 * Dossiers et jurys suivis par l'utilisateur dans les activités liées : dossiers des activités où
 * il voit tout ou qu'il dirige, jurys où il siège, et lecture par lot des étudiants et directeurs.
 *
 * Les fonctions thesesynthesis_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossiers {
    /**
     * Étudiants et directeurs de dossiers, lus en une requête : les champs du nom, pour fullname().
     *
     * @param stdClass[] $dossiers
     * @return stdClass[] userid => utilisateur (absent s'il n'existe plus).
     */
    public static function load_users(array $dossiers) {
        global $DB;

        $userids = [];
        foreach ($dossiers as $dossier) {
            $userids[(int) $dossier->userid] = true;
            if ($dossier->directorid) {
                $userids[(int) $dossier->directorid] = true;
            }
        }
        unset($userids[0]);
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($userids), SQL_PARAMS_NAMED);
        $fields = 'id, email, ' . implode(', ', \core_user\fields::for_name()->get_required_fields());
        return $DB->get_records_select('user', "id $insql", $params, '', $fields);
    }

    /**
     * Année de soutenance d'un dossier (retenue, à défaut proposée).
     *
     * @param stdClass $dossier
     * @return int 0 si aucune date.
     */
    public static function defense_year(stdClass $dossier) {
        $date = (int) ($dossier->defensedate ?: $dossier->proposeddate);
        return $date ? (int) userdate($date, '%Y') : 0;
    }

    /**
     * Dossiers suivis par l'utilisateur dans les activités liées : tous ceux des activités où il voit
     * tous les dossiers (DEVE, bibliothèque), et ceux qu'il dirige ailleurs.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters {status (int|null), search (string), cmid (int)}
     * @return array Liste d'objets {dossier, link, student, director, actor, todo, knowssubject}
     */
    public static function get_dossiers(array $links, $userid, array $filters = []) {
        global $DB;

        $rows = [];
        foreach ($links as $link) {
            if (!empty($filters['cmid']) && (int) $filters['cmid'] !== (int) $link->cm->id) {
                continue;
            }
            if ($link->rights->viewall) {
                $records = $DB->get_records('these_dossier', ['theseid' => $link->these->id], 'timemodified DESC');
            } else if ($link->direct) {
                $records = $DB->get_records(
                    'these_dossier',
                    ['theseid' => $link->these->id, 'directorid' => $userid],
                    'timemodified DESC'
                );
            } else {
                continue;
            }
            if (isset($filters['status']) && $filters['status'] !== null) {
                $records = array_filter($records, function ($dossier) use ($filters) {
                    return (int) $dossier->status === (int) $filters['status'];
                });
            }
            $users = self::load_users($records);
            // Thèse confidentielle : titre masqué, et absent de la recherche, pour qui ne doit pas le
            // connaître.
            $knowssubjects = rights::user_knows_subjects($records, $link->context, $userid);
            foreach ($records as $dossier) {
                $student = $users[(int) $dossier->userid] ?? null;
                $knows = $knowssubjects[(int) $dossier->id];
                $search = core_text::strtolower(trim((string) ($filters['search'] ?? '')));
                if (
                    $search !== '' && strpos(core_text::strtolower(($student ? fullname($student) : '') . ' '
                        . ($knows ? $dossier->title : '') . ' ' . $dossier->devenumber), $search) === false
                ) {
                    continue;
                }
                $actor = workflow::step_actor($dossier->status);
                $todo = ($actor === 'director' && (int) $dossier->directorid === (int) $userid && $link->direct)
                    || ($actor === 'deve' && $link->rights->deve)
                    || ($actor === 'library' && $link->rights->library);
                $rows[] = (object) [
                    'dossier' => $dossier,
                    'link' => $link,
                    'student' => $student,
                    'director' => $dossier->directorid ? ($users[(int) $dossier->directorid] ?? null) : null,
                    'actor' => $actor,
                    'todo' => $todo,
                    'knowssubject' => $knows,
                ];
            }
        }
        return $rows;
    }

    /**
     * Jurys où siège l'utilisateur, dans les activités liées : membre retenu avec son adresse, ou
     * enseignant de l'annuaire portant son adresse.
     *
     * @param array $links Voir links::get_active_links().
     * @param stdClass $user
     * @return array Liste d'objets {member, dossier, link, student}
     */
    public static function get_juries(array $links, stdClass $user) {
        global $DB;

        $email = trim((string) $user->email);
        if ($email === '' || !$links) {
            return [];
        }
        $byinstance = [];
        foreach ($links as $link) {
            $byinstance[(int) $link->these->id] = $link;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($byinstance), SQL_PARAMS_NAMED);
        $params['email'] = core_text::strtolower($email);
        $params['email2'] = core_text::strtolower($email);
        $sql = "SELECT j.*, d.theseid
                  FROM {these_jury} j
                  JOIN {these_dossier} d ON d.id = j.dossierid
             LEFT JOIN {these_juror} r ON r.id = j.jurorid
                 WHERE d.theseid $insql
                   AND d.status <> :cancelled
                   AND (LOWER(j.email) = :email OR LOWER(r.email) = :email2)
              ORDER BY d.defensedate DESC, j.id";
        $params['cancelled'] = THESE_STATUS_CANCELLED;
        $members = $DB->get_records_sql($sql, $params);
        $dossiers = $members ? $DB->get_records_list('these_dossier', 'id', array_column($members, 'dossierid')) : [];
        $users = self::load_users($dossiers);
        $rows = [];
        foreach ($members as $member) {
            $dossier = $dossiers[(int) $member->dossierid];
            $rows[] = (object) [
                'member' => $member,
                'dossier' => $dossier,
                'link' => $byinstance[(int) $member->theseid],
                'student' => $users[(int) $dossier->userid] ?? null,
            ];
        }
        return $rows;
    }
}
