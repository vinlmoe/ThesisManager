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

/**
 * Fonctions internes de mod_thesesynthesis : liens vers les activités « Gestion des thèses » de
 * plusieurs promotions, et lecture, pour l'utilisateur connecté, des dossiers qu'il suit dans
 * chacune (directeur, DEVE, bibliothèque) et des jurys où il siège.
 *
 * L'activité ne stocke aucun droit ni aucune donnée de thèse : tout est relu à l'affichage dans les
 * activités liées, et seulement là où l'utilisateur a toujours ses droits à la source.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Liens d'une instance.
 *
 * @param int $synthesisid
 * @return array thesecmid => enregistrement
 */
function thesesynthesis_get_links($synthesisid) {
    global $DB;

    $links = [];
    foreach ($DB->get_records('thesesynthesis_link', ['synthesisid' => $synthesisid], 'id') as $link) {
        $links[(int) $link->thesecmid] = $link;
    }
    return $links;
}

/**
 * Remplace les liens d'une instance.
 *
 * @param int $synthesisid
 * @param int[] $thesecmids
 * @return void
 */
function thesesynthesis_set_links($synthesisid, array $thesecmids) {
    global $DB;

    $thesecmids = array_unique(array_map('intval', $thesecmids));
    $existing = thesesynthesis_get_links($synthesisid);
    foreach ($existing as $cmid => $link) {
        if (!in_array($cmid, $thesecmids, true)) {
            $DB->delete_records('thesesynthesis_link', ['id' => $link->id]);
        }
    }
    foreach ($thesecmids as $cmid) {
        if (!isset($existing[$cmid])) {
            $DB->insert_record('thesesynthesis_link', (object) [
                'synthesisid' => $synthesisid,
                'thesecmid' => $cmid,
                'timecreated' => time(),
            ]);
        }
    }
}

/**
 * Activités « Gestion des thèses » qu'un utilisateur peut lier : celles où il voit tous les
 * dossiers (DEVE, bibliothèque, gestionnaire), pour ne jamais exposer le nom d'un cours auquel il
 * n'a pas accès.
 *
 * @param int $userid
 * @return array thesecmid => {thesecmid, thesename, coursename, visible, coursevisible}
 */
function thesesynthesis_get_available_activities($userid) {
    global $DB;

    $sql = "SELECT cm.id AS thesecmid, t.name AS thesename, c.fullname AS coursename, cm.visible,
                   c.visible AS coursevisible
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'these'
              JOIN {these} t ON t.id = cm.instance
              JOIN {course} c ON c.id = cm.course
             WHERE cm.deletioninprogress = 0
          ORDER BY c.fullname, t.name";
    $available = [];
    foreach ($DB->get_records_sql($sql) as $record) {
        $rights = these_get_rights(context_module::instance($record->thesecmid), null, $userid);
        if ($rights->viewall) {
            $available[(int) $record->thesecmid] = $record;
        }
    }
    return $available;
}

/**
 * Activités liées encore présentes, avec les droits de l'utilisateur dans chacune.
 *
 * @param int $synthesisid
 * @param int $userid
 * @return array Liste d'objets {cm, these, course, context, rights, direct}
 */
function thesesynthesis_get_active_links($synthesisid, $userid) {
    global $DB;

    $active = [];
    foreach (array_keys(thesesynthesis_get_links($synthesisid)) as $cmid) {
        $cm = get_coursemodule_from_id('these', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || !empty($cm->deletioninprogress)) {
            continue;
        }
        $context = context_module::instance($cm->id);
        $active[] = (object) [
            'cm' => $cm,
            'these' => $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST),
            'course' => get_course($cm->course),
            'context' => $context,
            'rights' => these_get_rights($context, null, $userid),
            'direct' => has_capability('mod/these:direct', $context, $userid),
        ];
    }
    return $active;
}

/**
 * Dossiers suivis par l'utilisateur dans les activités liées : tous ceux des activités où il voit
 * tous les dossiers (DEVE, bibliothèque), et ceux qu'il dirige ailleurs.
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param int $userid
 * @param array $filters {status (int|null), search (string), cmid (int)}
 * @return array Liste d'objets {dossier, link, student, director, actor, todo}
 */
function thesesynthesis_get_dossiers(array $links, $userid, array $filters = []) {
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
        foreach ($records as $dossier) {
            if (isset($filters['status']) && $filters['status'] !== null && (int) $dossier->status !== (int) $filters['status']) {
                continue;
            }
            $student = $DB->get_record('user', ['id' => $dossier->userid]);
            $search = core_text::strtolower(trim((string) ($filters['search'] ?? '')));
            if (
                $search !== '' && strpos(core_text::strtolower(($student ? fullname($student) : '') . ' ' . $dossier->title
                    . ' ' . $dossier->devenumber), $search) === false
            ) {
                continue;
            }
            $actor = these_step_actor($dossier->status);
            $todo = ($actor === 'director' && (int) $dossier->directorid === (int) $userid && $link->direct)
                || ($actor === 'deve' && $link->rights->deve)
                || ($actor === 'library' && $link->rights->library);
            $rows[] = (object) [
                'dossier' => $dossier,
                'link' => $link,
                'student' => $student,
                'director' => $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null,
                'actor' => $actor,
                'todo' => $todo,
            ];
        }
    }
    return $rows;
}

/**
 * Jurys où siège l'utilisateur, dans les activités liées : membre retenu avec son adresse, ou
 * enseignant de l'annuaire portant son adresse.
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param stdClass $user
 * @return array Liste d'objets {member, dossier, link, student}
 */
function thesesynthesis_get_juries(array $links, stdClass $user) {
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
    $rows = [];
    foreach ($DB->get_records_sql($sql, $params) as $member) {
        $dossier = $DB->get_record('these_dossier', ['id' => $member->dossierid]);
        $rows[] = (object) [
            'member' => $member,
            'dossier' => $dossier,
            'link' => $byinstance[(int) $member->theseid],
            'student' => $DB->get_record('user', ['id' => $dossier->userid]),
        ];
    }
    return $rows;
}
