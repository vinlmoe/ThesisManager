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
    // Les paramètres du circuit des activités suivies changent de portée.
    \mod_these\local\scopesettings::reset_cache();
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
 * Signatures en attente dans les activités liées, groupées par dossier, chacune avec la page où agir :
 * - mine : documents que l'utilisateur signe lui-même (agent DEVE, bibliothèque, directeur, juré
 *   ayant un compte...), à signer depuis « Mes signatures » ;
 * - awaited : pour la DEVE, signatures demandées à d'autres et pas encore apposées, à relancer ou à
 *   recueillir sur place depuis le suivi des signatures du dossier.
 *
 * @param array $links Voir thesesynthesis_get_active_links().
 * @param stdClass $user
 * @return array mine, awaited : listes d'objets {dossier, link, student, knowssubject, documents
 *               (libellés), since (première demande), url, label}
 */
function thesesynthesis_get_signatures(array $links, stdClass $user) {
    global $DB;

    $bycmid = [];
    foreach ($links as $link) {
        $bycmid[(int) $link->cm->id] = $link;
    }
    $mine = [];
    $mineids = [];
    $mysignatures = new moodle_url('/mod/these/mysignatures.php');
    foreach (these_get_user_pending_signatures($user) as $signature) {
        $link = $bycmid[(int) $signature->cm->id] ?? null;
        if (!$link) {
            continue;
        }
        $mineids[(int) $signature->id] = true;
        // Signature d'agent DEVE (sans jeton) : « par délégation » à l'emplacement de la direction générale.
        $label = $signature->token === null
            ? \mod_these\local\signatureagent::document_label($signature->doctype, $signature->slot)
            : these_document_label($signature->doctype) . ' (' . these_slot_label($signature->slot) . ')';
        $row = thesesynthesis_signature_row($mine, $signature->dossier, $link, $user->id, $signature->requesttime);
        $row->documents[] = $label;
        $row->url = $mysignatures;
        $row->label = get_string('sign', 'mod_thesesynthesis');
    }

    $awaited = [];
    foreach ($links as $link) {
        if (empty($link->rights->deve)) {
            continue;
        }
        $signatures = $DB->get_records_sql(
            "SELECT s.*
               FROM {these_signature} s
               JOIN {these_dossier} d ON d.id = s.dossierid
              WHERE d.theseid = :theseid AND s.timesigned = 0
                AND d.status <> :cancelled AND d.status <> :closed
           ORDER BY d.id, s.id",
            ['theseid' => $link->these->id, 'cancelled' => THESE_STATUS_CANCELLED, 'closed' => THESE_STATUS_CLOSED]
        );
        $dossiers = [];
        foreach ($signatures as $signature) {
            if (isset($mineids[(int) $signature->id])) {
                continue;
            }
            if (!isset($dossiers[$signature->dossierid])) {
                $dossiers[$signature->dossierid] = $DB->get_record('these_dossier', ['id' => $signature->dossierid]);
            }
            $dossier = $dossiers[$signature->dossierid];
            if (!these_document_available($dossier, $signature->doctype)) {
                continue;
            }
            $row = thesesynthesis_signature_row($awaited, $dossier, $link, $user->id, $signature->requesttime);
            $row->documents[] = these_document_label($signature->doctype) . ' (' . these_slot_label($signature->slot) . ')';
            $row->url = new moodle_url('/mod/these/signatures.php', ['id' => $link->cm->id, 'dossierid' => $dossier->id]);
            $row->label = get_string('managesignatures', 'mod_these');
        }
    }
    return ['mine' => array_values($mine), 'awaited' => array_values($awaited)];
}

/**
 * Ligne d'un dossier dans une liste de signatures (voir thesesynthesis_get_signatures()), créée au
 * premier document ; la date retenue est celle de la plus ancienne demande.
 *
 * @param array $groups Lignes, indexées par dossier.
 * @param stdClass $dossier
 * @param stdClass $link
 * @param int $userid
 * @param int $requesttime
 * @return stdClass La ligne du dossier.
 */
function thesesynthesis_signature_row(array &$groups, stdClass $dossier, stdClass $link, $userid, $requesttime) {
    global $DB;

    if (!isset($groups[$dossier->id])) {
        $groups[$dossier->id] = (object) [
            'dossier' => $dossier,
            'link' => $link,
            'student' => $DB->get_record('user', ['id' => $dossier->userid]),
            'knowssubject' => these_user_knows_subject($dossier, $link->context, $userid),
            'documents' => [],
            'since' => 0,
            'url' => null,
            'label' => '',
        ];
    }
    $row = $groups[$dossier->id];
    $requesttime = (int) $requesttime;
    if ($requesttime && (!$row->since || $requesttime < $row->since)) {
        $row->since = $requesttime;
    }
    return $row;
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
    global $PAGE;

    return $PAGE->get_renderer('mod_thesesynthesis')->render(new \mod_thesesynthesis\output\navlinks($cm, $context));
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
 * les thèses enregistrées par la DEVE et non annulées, et n'ouvre que celles qu'il dirige.
 *
 * Une thèse confidentielle se trouve comme les autres, mais, pour qui ne doit pas connaître son
 * sujet (voir these_user_knows_subject()), ni son sujet ni ses réponses au questionnaire n'entrent
 * dans la recherche ou ne sont renvoyés.
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
            // Une thèse confidentielle se trouve comme les autres (promotion, année, étudiant,
            // directeur, jury), son sujet restant masqué à qui ne doit pas le connaître.
            $public = $status !== THESE_STATUS_CANCELLED && these_status_rank($status) >= $registered;
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

/**
 * Tableau des résultats d'une recherche de thèses. Pour une thèse confidentielle dont l'utilisateur
 * ne connaît pas le sujet, titre, mots-clés et résumé sont remplacés par « Thèse confidentielle ».
 *
 * @param array $rows Voir thesesynthesis_search().
 * @param array $questions Questions du questionnaire (voir these_get_questions()).
 * @return string HTML
 */
function thesesynthesis_render_search_results(array $rows, array $questions) {
    global $PAGE;

    return $PAGE->get_renderer('mod_thesesynthesis')->render(new \mod_thesesynthesis\output\search_results($rows, $questions));
}
