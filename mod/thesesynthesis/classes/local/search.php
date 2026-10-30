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
use mod_these\local\jury;
use mod_these\local\questionnaire;
use mod_these\local\rights;
use mod_these\local\workflow;
use stdClass;

/**
 * Recherche de thèses dans les activités liées : texte, promotion, statut, année et réponses au
 * questionnaire, puis tableau des résultats.
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
     * Recherche de thèses dans les activités liées.
     *
     * Dans une activité où l'utilisateur voit tous les dossiers (DEVE, bibliothèque, gestionnaire),
     * tous les dossiers sont cherchés et peuvent être ouverts. Ailleurs, un enseignant ne trouve que
     * les thèses enregistrées par la DEVE et non annulées, et n'ouvre que celles qu'il dirige.
     *
     * Une thèse confidentielle se trouve comme les autres, mais, pour qui ne doit pas connaître son
     * sujet (voir \mod_these\local\rights::user_knows_subject()), ni son sujet ni ses réponses au
     * questionnaire n'entrent dans la recherche ou ne sont renvoyés.
     *
     * @param array $links Voir links::get_active_links().
     * @param int $userid
     * @param array $filters {text, cmid, status (int|null), year (int), answers (questionid => valeur)}
     * @param array $questions Questions filtrables (voir \mod_these\local\questionnaire::get_questions()).
     * @return array Liste d'objets {dossier, link, student, director, canopen, answers, year,
     *                     knowssubject}
     */
    public static function find(array $links, $userid, array $filters, array $questions = []) {
        $words = array_filter(preg_split('/\s+/', self::normalise($filters['text'] ?? '')), 'strlen');
        $answerfilters = array_filter((array) ($filters['answers'] ?? []), function ($value) {
            return trim((string) $value) !== '';
        });
        $rows = [];
        foreach ($links as $link) {
            if (!empty($filters['cmid']) && (int) $filters['cmid'] !== (int) $link->cm->id) {
                continue;
            }
            // Premier tri sans requête : visibilité, statut, année. Les utilisateurs et la connaissance
            // du sujet sont ensuite chargés en une fois pour les dossiers restants.
            $candidates = self::candidates($link, $userid, $filters);
            $users = dossiers::load_users(array_column($candidates, 0));
            $knowssubjects = rights::user_knows_subjects(array_column($candidates, 0), $link->context, $userid);
            // Réponses au questionnaire et jurys lus en une requête chacun, seulement si la recherche
            // les utilise.
            $dossierids = array_keys($candidates);
            $allanswers = questionnaire::get_answers_for($dossierids);
            $juries = $words ? jury::get_juries($dossierids) : [];
            foreach ($candidates as [$dossier, $year, $directs]) {
                // Thèse confidentielle dont l'utilisateur ne connaît pas le sujet : ni le sujet ni les
                // réponses au questionnaire n'entrent dans la recherche.
                $knows = $knowssubjects[(int) $dossier->id];
                if (!$knows && $answerfilters) {
                    continue;
                }
                $answers = $allanswers[(int) $dossier->id];
                if (!self::matches_answers($answers, $answerfilters, $questions)) {
                    continue;
                }
                $student = $users[(int) $dossier->userid] ?? null;
                $director = $dossier->directorid ? ($users[(int) $dossier->directorid] ?? null) : null;
                $jury = $juries[(int) $dossier->id] ?? [];
                if ($words && !self::matches_words($words, $dossier, $jury, $link, $student, $director, $knows ? $answers : null)) {
                    continue;
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
     * Dossiers d'une activité liée que l'utilisateur peut trouver, filtrés par statut et année.
     *
     * @param stdClass $link Voir links::get_active_links().
     * @param int $userid
     * @param array $filters Voir find().
     * @return array dossierid => [dossier, année de soutenance, dirigé par l'utilisateur (bool)]
     */
    protected static function candidates(stdClass $link, $userid, array $filters) {
        global $DB;

        $registered = workflow::status_rank(THESE_STATUS_SUBJECT_REGISTERED);
        $candidates = [];
        foreach ($DB->get_records('these_dossier', ['theseid' => $link->these->id], 'timemodified DESC') as $dossier) {
            $status = (int) $dossier->status;
            $directs = $link->direct && (int) $dossier->directorid === (int) $userid;
            // Une thèse confidentielle se trouve comme les autres (promotion, année, étudiant,
            // directeur, jury), son sujet restant masqué à qui ne doit pas le connaître.
            $public = $status !== THESE_STATUS_CANCELLED && workflow::status_rank($status) >= $registered;
            if (!$link->rights->viewall && !$directs && !$public) {
                continue;
            }
            if (isset($filters['status']) && $filters['status'] !== null && $status !== (int) $filters['status']) {
                continue;
            }
            $year = dossiers::defense_year($dossier);
            if (!empty($filters['year']) && $year !== (int) $filters['year']) {
                continue;
            }
            $candidates[(int) $dossier->id] = [$dossier, $year, $directs];
        }
        return $candidates;
    }

    /**
     * Les réponses d'un dossier satisfont-elles les filtres sur le questionnaire ? Une question à
     * choix exige la valeur choisie ; une question libre, le texte cherché dans la réponse.
     *
     * @param array $answers Voir \mod_these\local\questionnaire::get_answers().
     * @param array $answerfilters questionid => valeur (non vide).
     * @param array $questions Questions filtrables.
     * @return bool
     */
    protected static function matches_answers(array $answers, array $answerfilters, array $questions) {
        $matches = true;
        foreach ($answerfilters as $questionid => $value) {
            $answer = $answers[(int) $questionid] ?? '';
            $question = $questions[(int) $questionid] ?? null;
            if ($question && in_array($question->qtype, ['list', 'multi'], true)) {
                $matches = $matches && in_array((string) $value, (array) $answer, true);
            } else {
                $matches = $matches && strpos(
                    self::normalise(questionnaire::answer_text($answer)),
                    self::normalise(trim($value))
                ) !== false;
            }
        }
        return $matches;
    }

    /**
     * Tous les mots cherchés figurent-ils dans le dossier ? Le texte cherché couvre l'étudiant, les
     * directeurs et le jury, le sujet et les réponses au questionnaire si l'utilisateur doit les
     * connaître, et les références administratives s'il voit tous les dossiers.
     *
     * @param string[] $words Mots normalisés.
     * @param stdClass $dossier
     * @param stdClass[] $jury Membres du jury du dossier.
     * @param stdClass $link
     * @param stdClass|null $student
     * @param stdClass|null $director
     * @param array|null $answers Réponses au questionnaire, null si le sujet doit rester masqué.
     * @return bool
     */
    protected static function matches_words(
        array $words,
        stdClass $dossier,
        array $jury,
        stdClass $link,
        ?stdClass $student,
        ?stdClass $director,
        ?array $answers
    ) {
        $haystack = [$dossier->extdirectorname, $student ? fullname($student) : '',
            $director ? fullname($director) : ''];
        if ($answers !== null) {
            array_push($haystack, $dossier->title, $dossier->titleen, $dossier->summary, $dossier->keywords);
        }
        if ($link->rights->viewall) {
            $haystack[] = $dossier->devenumber;
            $haystack[] = $dossier->libraryref;
        }
        foreach ($jury as $member) {
            $haystack[] = $member->fullname;
        }
        foreach ($answers ?? [] as $answer) {
            $haystack[] = questionnaire::answer_text($answer);
        }
        $haystack = self::normalise(implode(' ', $haystack));
        foreach ($words as $word) {
            if (strpos($haystack, $word) === false) {
                return false;
            }
        }
        return true;
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
