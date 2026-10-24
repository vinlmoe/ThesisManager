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
 * @return array Liste d'objets {dossier, link, student, director, actor, todo, knowssubject}
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
            // Thèse confidentielle : titre masqué, et absent de la recherche, pour qui ne doit pas le
            // connaître.
            $knows = these_user_knows_subject($dossier, $link->context, $userid);
            $search = core_text::strtolower(trim((string) ($filters['search'] ?? '')));
            if (
                $search !== '' && strpos(core_text::strtolower(($student ? fullname($student) : '') . ' '
                    . ($knows ? $dossier->title : '') . ' ' . $dossier->devenumber), $search) === false
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

/**
 * Liens vers les pages de l'activité, selon les droits de l'utilisateur.
 *
 * @param stdClass $cm
 * @param context $context
 * @return string HTML
 */
function thesesynthesis_render_navlinks(stdClass $cm, context $context) {
    $links = [
        get_string('dashboard', 'mod_these') => new moodle_url('/mod/thesesynthesis/view.php', ['id' => $cm->id]),
        get_string('planning', 'mod_these') => new moodle_url('/mod/thesesynthesis/planning.php', ['id' => $cm->id]),
    ];
    if (has_capability('mod/thesesynthesis:search', $context)) {
        $links[get_string('searchtheses', 'mod_thesesynthesis')] =
            new moodle_url('/mod/thesesynthesis/search.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/thesesynthesis:managelinks', $context)) {
        $links[get_string('managelinks', 'mod_thesesynthesis')] =
            new moodle_url('/mod/thesesynthesis/administration.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/thesesynthesis:managesettings', $context)) {
        $links[get_string('doctemplates', 'mod_these')] = new moodle_url('/mod/thesesynthesis/doctemplates.php', ['id' => $cm->id]);
        $links[get_string('griditems', 'mod_these')] = new moodle_url('/mod/thesesynthesis/griditems.php', ['id' => $cm->id]);
        $links[get_string('questions', 'mod_these')] = new moodle_url('/mod/thesesynthesis/questions.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/thesesynthesis:managejurors', $context)) {
        $links[get_string('jurors', 'mod_these')] = new moodle_url('/mod/thesesynthesis/jurors.php', ['id' => $cm->id]);
    }
    return html_writer::div(these_render_actions($links, 'btn btn-secondary mr-1 mb-1'), 'these-navlinks mb-3');
}

/**
 * Texte normalisé pour la recherche : minuscules, sans accents.
 *
 * @param string $text
 * @return string
 */
function thesesynthesis_normalise($text) {
    return core_text::strtolower(core_text::specialtoascii((string) $text));
}

/**
 * Année de soutenance d'un dossier (retenue, à défaut proposée).
 *
 * @param stdClass $dossier
 * @return int 0 si aucune date.
 */
function thesesynthesis_defense_year(stdClass $dossier) {
    $date = (int) ($dossier->defensedate ?: $dossier->proposeddate);
    return $date ? (int) userdate($date, '%Y') : 0;
}

/**
 * Recherche de thèses dans les activités liées.
 *
 * Dans une activité où l'utilisateur voit tous les dossiers (DEVE, bibliothèque, gestionnaire),
 * tous les dossiers sont cherchés et peuvent être ouverts. Ailleurs, un enseignant ne trouve que
 * les thèses enregistrées par la DEVE, non confidentielles et non annulées, et n'ouvre que celles
 * qu'il dirige.
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param int $userid
 * @param array $filters {text, cmid, status (int|null), year (int), answers (questionid => valeur)}
 * @param array $questions Questions filtrables (voir these_get_questions()).
 * @return array Liste d'objets {dossier, link, student, director, canopen, answers, year,
 *                     knowssubject}
 */
function thesesynthesis_search(array $links, $userid, array $filters, array $questions = []) {
    global $DB;

    $words = array_filter(preg_split('/\s+/', thesesynthesis_normalise($filters['text'] ?? '')), 'strlen');
    $answerfilters = array_filter((array) ($filters['answers'] ?? []), function ($value) {
        return trim((string) $value) !== '';
    });
    $registered = these_status_rank(THESE_STATUS_SUBJECT_REGISTERED);
    $rows = [];
    foreach ($links as $link) {
        if (!empty($filters['cmid']) && (int) $filters['cmid'] !== (int) $link->cm->id) {
            continue;
        }
        foreach ($DB->get_records('these_dossier', ['theseid' => $link->these->id], 'timemodified DESC') as $dossier) {
            $status = (int) $dossier->status;
            $directs = $link->direct && (int) $dossier->directorid === (int) $userid;
            $public = !$dossier->confidential && $status !== THESE_STATUS_CANCELLED
                && these_status_rank($status) >= $registered;
            if (!$link->rights->viewall && !$directs && !$public) {
                continue;
            }
            // Thèse confidentielle dont l'utilisateur ne connaît pas le sujet : ni le sujet ni les
            // réponses au questionnaire n'entrent dans la recherche.
            $knows = these_user_knows_subject($dossier, $link->context, $userid);
            if (!$knows && $answerfilters) {
                continue;
            }
            if (isset($filters['status']) && $filters['status'] !== null && $status !== (int) $filters['status']) {
                continue;
            }
            $year = thesesynthesis_defense_year($dossier);
            if (!empty($filters['year']) && $year !== (int) $filters['year']) {
                continue;
            }
            $answers = these_get_answers($dossier->id);
            $matches = true;
            foreach ($answerfilters as $questionid => $value) {
                $answer = $answers[(int) $questionid] ?? '';
                $question = $questions[(int) $questionid] ?? null;
                if ($question && in_array($question->qtype, ['list', 'multi'], true)) {
                    $matches = $matches && in_array((string) $value, (array) $answer, true);
                } else {
                    $matches = $matches && strpos(
                        thesesynthesis_normalise(these_answer_text($answer)),
                        thesesynthesis_normalise(trim($value))
                    ) !== false;
                }
            }
            if (!$matches) {
                continue;
            }
            $student = $DB->get_record('user', ['id' => $dossier->userid]);
            $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;
            if ($words) {
                $haystack = [$dossier->codirectorname, $dossier->extdirectorname, $student ? fullname($student) : '',
                    $director ? fullname($director) : ''];
                if ($knows) {
                    array_push($haystack, $dossier->title, $dossier->titleen, $dossier->summary, $dossier->keywords);
                }
                if ($link->rights->viewall) {
                    $haystack[] = $dossier->devenumber;
                    $haystack[] = $dossier->libraryref;
                }
                foreach (these_get_jury($dossier->id) as $member) {
                    $haystack[] = $member->fullname;
                }
                foreach ($knows ? $answers : [] as $answer) {
                    $haystack[] = these_answer_text($answer);
                }
                $haystack = thesesynthesis_normalise(implode(' ', $haystack));
                foreach ($words as $word) {
                    if (strpos($haystack, $word) === false) {
                        continue 2;
                    }
                }
            }
            $rows[] = (object) [
                'dossier' => $dossier,
                'link' => $link,
                'student' => $student,
                'director' => $director,
                'canopen' => $link->rights->viewall || $directs,
                'answers' => $knows ? $answers : [],
                'knowssubject' => $knows,
                'year' => $year,
            ];
        }
    }
    usort($rows, function ($a, $b) {
        return [$b->year, $a->dossier->title] <=> [$a->year, $b->dossier->title];
    });
    return $rows;
}
