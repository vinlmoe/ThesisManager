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
use mod_these\local\questionnaire;
use mod_these\local\workflow;

/**
 * Recherche de thèses dans les activités liées : texte, promotion, statut, année et réponses au
 * questionnaire, filtrés et paginés en base, puis tableau des résultats.
 *
 * Les fonctions thesesynthesis_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search {
    /**
     * Texte normalisé pour la recherche : minuscules, sans accents.
     *
     * @param string $text
     * @return string
     */
    public static function normalise($text) {
        return core_text::strtolower(core_text::specialtoascii((string) $text));
    }

    /**
     * Recherche de thèses dans les activités liées, filtrée et paginée en base.
     *
     * Dans une activité où l'utilisateur voit tous les dossiers (DEVE, bibliothèque, gestionnaire),
     * tous les dossiers sont cherchés et peuvent être ouverts. Ailleurs, un enseignant ne trouve que
     * les thèses enregistrées par la DEVE et non annulées, et n'ouvre que celles qu'il dirige.
     *
     * Une thèse confidentielle se trouve comme les autres, mais, pour qui ne doit pas connaître son
     * sujet (voir \mod_these\local\rights::user_knows_subject(), règle reprise dans la requête par
     * sqlfilter::knows_subject()), ni son sujet ni ses réponses au questionnaire n'entrent dans la
     * recherche ou ne sont renvoyés.
     *
     * Résultats triés par année de soutenance décroissante, puis par titre.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters {text, cmid, status (int|null), year (int), answers (questionid => valeur)}
     * @param array $questions Questions filtrables (voir \mod_these\local\questionnaire::get_questions()).
     * @param int $limitfrom Rang du premier résultat de la page.
     * @param int $limitnum Résultats par page (0 : tous).
     * @return array Liste d'objets {dossier, link, student, director, canopen, answers, year,
     *                     knowssubject}
     */
    public static function find(array $links, $userid, array $filters, array $questions = [], $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $query = self::query($links, $userid, $filters, $questions);
        if (!$query) {
            return [];
        }
        [$from, $params, $bytheseid] = $query;
        $records = $DB->get_records_sql(
            'SELECT d.* ' . $from . ' ORDER BY ' . self::year_order($from, $params) . $DB->sql_order_by_text('d.title', 255)
                . ', d.id',
            $params,
            $limitfrom,
            $limitnum
        );
        $users = dossiers::load_users($records);
        $knowssubjects = dossiers::knows_subjects($records, $bytheseid, $userid);
        $allanswers = questionnaire::get_answers_for(array_keys($records));
        $rows = [];
        foreach ($records as $dossier) {
            $link = $bytheseid[(int) $dossier->theseid];
            $knows = $knowssubjects[(int) $dossier->id];
            $rows[] = (object) [
                'dossier' => $dossier,
                'link' => $link,
                'student' => $users[(int) $dossier->userid] ?? null,
                'director' => $dossier->directorid ? ($users[(int) $dossier->directorid] ?? null) : null,
                'canopen' => $link->rights->viewall || ($link->direct && (int) $dossier->directorid === (int) $userid),
                'answers' => $knows ? $allanswers[(int) $dossier->id] : [],
                'knowssubject' => $knows,
                'year' => dossiers::defense_year($dossier),
            ];
        }
        return $rows;
    }

    /**
     * Nombre de thèses trouvées (voir find()), compté en base.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters Voir find().
     * @param array $questions Voir find().
     * @return int
     */
    public static function count(array $links, $userid, array $filters, array $questions = []) {
        global $DB;

        $query = self::query($links, $userid, $filters, $questions);
        return $query ? (int) $DB->count_records_sql('SELECT COUNT(1) ' . $query[0], $query[1]) : 0;
    }

    /**
     * Date retenue pour l'année de soutenance d'un dossier (voir dossiers::defense_year()) : date de
     * soutenance, à défaut date proposée.
     *
     * @return string Expression SQL.
     */
    protected static function date_expression() {
        return 'CASE WHEN d.defensedate <> 0 THEN d.defensedate ELSE d.proposeddate END';
    }

    /**
     * Tri par année de soutenance décroissante, dans le fuseau de l'utilisateur comme
     * dossiers::defense_year() : comparaison de la date aux débuts d'année des dates trouvées (lues en
     * une requête).
     *
     * @param string $from Clause FROM ... WHERE de la recherche.
     * @param array $params
     * @return string Début de la clause ORDER BY (vide si aucun dossier trouvé n'a de date).
     */
    protected static function year_order($from, array $params) {
        global $DB;

        $date = self::date_expression();
        $range = $DB->get_record_sql("SELECT MIN($date) AS first, MAX($date) AS last $from AND $date > 0", $params);
        if (empty($range->first)) {
            return '';
        }
        $cases = [];
        for ($year = (int) userdate($range->last, '%Y'); $year >= (int) userdate($range->first, '%Y'); $year--) {
            $cases[] = "WHEN $date >= " . (int) make_timestamp($year, 1, 1) . " THEN $year";
        }
        return "CASE WHEN $date <= 0 THEN 0 " . implode(' ', $cases) . ' ELSE 0 END DESC, ';
    }

    /**
     * Requête de la recherche : dossiers que l'utilisateur peut trouver, puis filtres (statut, année,
     * réponses au questionnaire, mots du texte), tous appliqués en base.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters Voir find().
     * @param array $questions Voir find().
     * @return array|null [clause FROM ... WHERE, paramètres, activités par instance (theseid => lien)] ;
     *                    null sans activité à chercher.
     */
    protected static function query(array $links, $userid, array $filters, array $questions) {
        $sql = new sqlfilter();
        $bytheseid = [];
        $viewall = [];
        $others = [];
        $directs = [];
        foreach ($links as $link) {
            if (!empty($filters['cmid']) && (int) $filters['cmid'] !== (int) $link->cm->id) {
                continue;
            }
            $theseid = (int) $link->these->id;
            $bytheseid[$theseid] = $link;
            if ($link->rights->viewall) {
                $viewall[] = $theseid;
            } else {
                $others[] = $theseid;
                if ($link->direct) {
                    $directs[] = $theseid;
                }
            }
        }
        if (!$bytheseid) {
            return null;
        }
        $links = array_values($bytheseid);

        // Visibilité : tous les dossiers là où l'utilisateur voit tout ; ailleurs, les thèses enregistrées
        // par la DEVE et non annulées, et celles qu'il dirige.
        $registered = workflow::status_rank(THESE_STATUS_SUBJECT_REGISTERED);
        $public = array_values(array_filter(workflow::all_statuses(), function ($status) use ($registered) {
            return $status !== THESE_STATUS_CANCELLED && workflow::status_rank($status) >= $registered;
        }));
        $visible = [];
        if ($viewall) {
            $visible[] = $sql->in('d.theseid', $viewall);
        }
        if ($others) {
            $visible[] = '(' . $sql->in('d.theseid', $others) . ' AND ' . $sql->in('d.status', $public) . ')';
        }
        if ($directs && $userid) {
            $visible[] = '(' . $sql->in('d.theseid', $directs) . ' AND d.directorid = ' . $sql->param((int) $userid) . ')';
        }
        $where = ['(' . implode(' OR ', $visible) . ')'];

        if (isset($filters['status']) && $filters['status'] !== null) {
            $where[] = 'd.status = ' . $sql->param((int) $filters['status']);
        }
        if (!empty($filters['year'])) {
            $year = (int) $filters['year'];
            $date = self::date_expression();
            $where[] = "$date > 0 AND $date >= " . $sql->param((int) make_timestamp($year, 1, 1))
                . " AND $date < " . $sql->param((int) make_timestamp($year + 1, 1, 1));
        }

        // Réponses au questionnaire : seulement pour qui connaît le sujet. Une question à choix exige la
        // valeur choisie (QCM : élément du tableau JSON enregistré) ; une question libre, le texte cherché.
        $answerfilters = array_filter((array) ($filters['answers'] ?? []), function ($value) {
            return trim((string) $value) !== '';
        });
        if ($answerfilters) {
            $where[] = $sql->knows_subject($links, $userid);
        }
        foreach ($answerfilters as $questionid => $value) {
            $qtype = $questions[(int) $questionid]->qtype ?? '';
            if ($qtype === 'list') {
                $match = $sql->exact('a.value', (string) $value, false);
            } else if ($qtype === 'multi') {
                $match = $sql->exact('a.value', json_encode((string) $value, JSON_UNESCAPED_UNICODE), true);
            } else {
                $match = $sql->contains('a.value', sqlfilter::fold_text(trim($value)));
            }
            $where[] = 'EXISTS (SELECT 1 FROM {these_answer} a WHERE a.dossierid = d.id AND a.questionid = '
                . $sql->param((int) $questionid) . " AND $match)";
        }

        // Texte : chaque mot dans l'étudiant, les directeurs ou le jury ; dans le sujet et les réponses
        // pour qui doit le connaître ; dans les références administratives là où l'utilisateur voit tout.
        foreach (sqlfilter::words($filters['text'] ?? '') as $word) {
            $people = sqlfilter::concat(['d.extdirectorname', 'su.firstname', 'su.lastname', 'du.firstname', 'du.lastname']);
            $any = [
                $sql->contains($people, $word),
                'EXISTS (SELECT 1 FROM {these_jury} wj WHERE wj.dossierid = d.id AND '
                    . $sql->contains('wj.fullname', $word) . ')',
                '(' . $sql->knows_subject($links, $userid) . ' AND ('
                    . $sql->contains(sqlfilter::concat(['d.title', 'd.titleen', 'd.summary', 'd.keywords']), $word)
                    . ' OR EXISTS (SELECT 1 FROM {these_answer} wa WHERE wa.dossierid = d.id AND '
                    . $sql->contains('wa.value', $word) . ')))',
            ];
            if ($viewall) {
                $any[] = '(' . $sql->in('d.theseid', $viewall) . ' AND '
                    . $sql->contains(sqlfilter::concat(['d.devenumber', 'd.libraryref']), $word) . ')';
            }
            $where[] = '(' . implode(' OR ', $any) . ')';
        }
        $from = 'FROM {these_dossier} d
            LEFT JOIN {user} su ON su.id = d.userid
            LEFT JOIN {user} du ON du.id = d.directorid AND d.directorid <> 0
                WHERE ' . implode(' AND ', $where);
        return [$from, $sql->get_params(), $bytheseid];
    }

    /**
     * Tableau des résultats d'une recherche de thèses. Pour une thèse confidentielle dont l'utilisateur
     * ne connaît pas le sujet, titre, mots-clés et résumé sont remplacés par « Thèse confidentielle ».
     *
     * @param array $rows Voir find().
     * @param array $questions Questions du questionnaire (voir \mod_these\local\questionnaire::get_questions()).
     * @return string HTML
     */
    public static function render_search_results(array $rows, array $questions) {
        global $PAGE;

        return $PAGE->get_renderer('mod_thesesynthesis')->render(new \mod_thesesynthesis\output\search_results($rows, $questions));
    }
}
