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
    /** Dossiers par page dans les listes paginées du suivi (à traiter, dossiers suivis, recherche). */
    public const PERPAGE = 50;

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
     * tous les dossiers (DEVE, bibliothèque, scolarité), et ceux qu'il dirige ailleurs.
     *
     * Droits et filtres sont appliqués dans la requête, pour qu'une page ne compte que des dossiers
     * affichables ; tri du plus récemment modifié au plus ancien.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters {status (int|null), search (string : chaque mot dans le nom de l'étudiant, le
     *                       numéro DEVE ou le titre), cmid (int), todo (bool : seulement les dossiers
     *                       qui attendent l'utilisateur)}
     * @param int $limitfrom Rang du premier dossier de la page.
     * @param int $limitnum Dossiers par page (0 : tous).
     * @return array Liste d'objets {dossier, link, student, director, actor, todo, knowssubject}
     */
    public static function get_dossiers(array $links, $userid, array $filters = [], $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $query = self::dossiers_query($links, $userid, $filters);
        if (!$query) {
            return [];
        }
        [$from, $params, $bytheseid] = $query;
        $records = $DB->get_records_sql("SELECT d.* $from ORDER BY d.timemodified DESC, d.id DESC", $params, $limitfrom, $limitnum);
        $users = self::load_users($records);
        $knowssubjects = self::knows_subjects($records, $bytheseid, $userid);
        $rows = [];
        foreach ($records as $dossier) {
            $link = $bytheseid[(int) $dossier->theseid];
            $rows[] = (object) [
                'dossier' => $dossier,
                'link' => $link,
                'student' => $users[(int) $dossier->userid] ?? null,
                'director' => $dossier->directorid ? ($users[(int) $dossier->directorid] ?? null) : null,
                'actor' => workflow::step_actor($dossier->status),
                'todo' => self::is_todo($dossier, $link, $userid),
                'knowssubject' => $knowssubjects[(int) $dossier->id],
            ];
        }
        return $rows;
    }

    /**
     * Nombre de dossiers suivis (voir get_dossiers()), compté en base.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters Voir get_dossiers().
     * @return int
     */
    public static function count_dossiers(array $links, $userid, array $filters = []) {
        global $DB;

        $query = self::dossiers_query($links, $userid, $filters);
        return $query ? (int) $DB->count_records_sql('SELECT COUNT(1) ' . $query[0], $query[1]) : 0;
    }

    /**
     * Connaissance du sujet des dossiers d'une page (voir \mod_these\local\rights::user_knows_subjects()),
     * activité par activité.
     *
     * @param stdClass[] $records Dossiers.
     * @param array $bytheseid Activités liées par instance (theseid => lien).
     * @param int $userid
     * @return bool[] dossierid => l'utilisateur connaît le sujet.
     */
    public static function knows_subjects(array $records, array $bytheseid, $userid) {
        $bythese = [];
        foreach ($records as $dossier) {
            $bythese[(int) $dossier->theseid][(int) $dossier->id] = $dossier;
        }
        $known = [];
        foreach ($bythese as $theseid => $dossiers) {
            $known += rights::user_knows_subjects($dossiers, $bytheseid[$theseid]->context, $userid);
        }
        return $known;
    }

    /**
     * Le dossier attend-il l'utilisateur ? Directeur à l'étape du directeur d'une thèse qu'il dirige,
     * DEVE ou bibliothèque à leur étape.
     *
     * @param stdClass $dossier
     * @param stdClass $link
     * @param int $userid
     * @return bool
     */
    protected static function is_todo(stdClass $dossier, stdClass $link, $userid) {
        $actor = workflow::step_actor($dossier->status);
        return ($actor === 'director' && (int) $dossier->directorid === (int) $userid && $link->direct)
            || ($actor === 'deve' && $link->rights->deve)
            || ($actor === 'library' && $link->rights->library);
    }

    /**
     * Statuts dont l'étape attend un acteur.
     *
     * @param string $actor Voir \mod_these\local\workflow::step_actor().
     * @return int[]
     */
    protected static function actor_statuses($actor) {
        return array_values(array_filter(workflow::all_statuses(), function ($status) use ($actor) {
            return workflow::step_actor($status) === $actor;
        }));
    }

    /**
     * Requête des dossiers suivis : droits de l'utilisateur dans chaque activité, puis filtres. La
     * condition « à traiter » reprend is_todo(), la recherche dans le titre la règle de
     * \mod_these\local\rights::user_knows_subject() (voir sqlfilter::knows_subject()).
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters Voir get_dossiers().
     * @return array|null [clause FROM ... WHERE, paramètres, activités par instance (theseid => lien)] ;
     *                    null si aucune activité n'a de dossier à montrer.
     */
    protected static function dossiers_query(array $links, $userid, array $filters) {
        $sql = new sqlfilter();
        $bytheseid = [];
        $visible = [];
        foreach ($links as $link) {
            if (!empty($filters['cmid']) && (int) $filters['cmid'] !== (int) $link->cm->id) {
                continue;
            }
            $theseid = (int) $link->these->id;
            if ($link->rights->viewall) {
                $condition = 'd.theseid = ' . $sql->param($theseid);
            } else if ($link->direct) {
                $condition = 'd.theseid = ' . $sql->param($theseid) . ' AND d.directorid = ' . $sql->param((int) $userid);
            } else {
                continue;
            }
            if (!empty($filters['todo'])) {
                $todo = [];
                if ($link->rights->deve) {
                    $todo[] = $sql->in('d.status', self::actor_statuses('deve'));
                }
                if ($link->rights->library) {
                    $todo[] = $sql->in('d.status', self::actor_statuses('library'));
                }
                if ($link->direct) {
                    $todo[] = '(d.directorid = ' . $sql->param((int) $userid) . ' AND '
                        . $sql->in('d.status', self::actor_statuses('director')) . ')';
                }
                if (!$todo) {
                    continue;
                }
                $condition .= ' AND (' . implode(' OR ', $todo) . ')';
            }
            $bytheseid[$theseid] = $link;
            $visible[] = "($condition)";
        }
        if (!$visible) {
            return null;
        }
        $where = ['(' . implode(' OR ', $visible) . ')'];
        if (isset($filters['status']) && $filters['status'] !== null) {
            $where[] = 'd.status = ' . $sql->param((int) $filters['status']);
        }
        // Texte : chaque mot dans le nom de l'étudiant, le numéro DEVE ou, pour qui doit le connaître, le
        // titre de la thèse.
        foreach (sqlfilter::words($filters['search'] ?? '') as $word) {
            $where[] = '(' . $sql->contains(sqlfilter::concat(['su.firstname', 'su.lastname', 'd.devenumber']), $word)
                . ' OR (' . $sql->knows_subject(array_values($bytheseid), $userid) . ' AND '
                . $sql->contains('d.title', $word) . '))';
        }
        $from = 'FROM {these_dossier} d
            LEFT JOIN {user} su ON su.id = d.userid
                WHERE ' . implode(' AND ', $where);
        return [$from, $sql->get_params(), $bytheseid];
    }

    /**
     * Jurys où siège l'utilisateur, dans les activités liées : membre auquel son compte est rattaché
     * (voir \mod_these\local\rights::user_in_jury()), compte ni suspendu ni supprimé.
     *
     * @param array $links Voir links::get_active_links().
     * @param stdClass $user
     * @return array Liste d'objets {member, dossier, link, student}
     */
    public static function get_juries(array $links, stdClass $user) {
        global $DB;

        if (empty($user->id) || !$links) {
            return [];
        }
        $byinstance = [];
        foreach ($links as $link) {
            $byinstance[(int) $link->these->id] = $link;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($byinstance), SQL_PARAMS_NAMED);
        $params['userid'] = (int) $user->id;
        $sql = "SELECT j.*, d.theseid
                  FROM {these_jury} j
                  JOIN {these_dossier} d ON d.id = j.dossierid
                  JOIN {user} u ON u.id = j.userid AND u.deleted = 0 AND u.suspended = 0
                 WHERE d.theseid $insql
                   AND d.status <> :cancelled
                   AND j.userid = :userid
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
