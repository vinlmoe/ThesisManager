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
use stdClass;

/**
 * Conditions SQL des listes paginées du suivi des thèses (dossiers suivis, recherche) : paramètres
 * nommés uniques, texte cherché sans tenir compte de la casse ni des accents, et connaissance du sujet
 * d'une thèse confidentielle évaluée dans la requête.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sqlfilter {
    /** @var array Paramètres nommés de la requête. */
    protected $params = [];

    /** @var int Compteur des noms de paramètres. */
    protected $count = 0;

    /** @var array|null Lettres latines accentuées et leur équivalent ASCII, voir fold_map(). */
    protected static $foldmap = null;

    /**
     * Paramètres nommés accumulés.
     *
     * @return array
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Ajoute un paramètre nommé.
     *
     * @param mixed $value
     * @return string Marque du paramètre (:nom).
     */
    public function param($value): string {
        $name = 'tsf' . (++$this->count);
        $this->params[$name] = $value;
        return ':' . $name;
    }

    /**
     * Condition « champ dans la liste » ; toujours fausse pour une liste vide.
     *
     * @param string $field
     * @param array $values
     * @return string
     */
    public function in(string $field, array $values): string {
        global $DB;

        if (!$values) {
            return '1 = 0';
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($values), SQL_PARAMS_NAMED, 'tsf' . (++$this->count) . 'i');
        $this->params += $params;
        return "$field $insql";
    }

    /**
     * Mots cherchés : texte replié (voir fold_text()), coupé aux espaces.
     *
     * @param string $text
     * @return string[]
     */
    public static function words($text): array {
        return array_values(array_filter(preg_split('/\s+/', self::fold_text($text)), 'strlen'));
    }

    /**
     * Lettres latines accentuées (U+00C0 à U+017F) et leur équivalent ASCII en minuscules, tel que le
     * donne search::normalise().
     *
     * @return array [lettres remplacées une à une, leurs équivalents, lettres remplacées par plusieurs
     *               (lettre => équivalent)]
     */
    protected static function fold_map(): array {
        if (self::$foldmap === null) {
            $from = $to = '';
            $multiple = [];
            for ($code = 0xC0; $code <= 0x17F; $code++) {
                $char = core_text::code2utf8($code);
                $ascii = search::normalise($char);
                if (!preg_match('/^[a-z]+$/', $ascii)) {
                    continue;
                }
                if (strlen($ascii) === 1) {
                    $from .= $char;
                    $to .= $ascii;
                } else {
                    $multiple[$char] = $ascii;
                }
            }
            self::$foldmap = [$from, $to, $multiple];
        }
        return self::$foldmap;
    }

    /**
     * Texte replié : minuscules, lettres latines accentuées remplacées par leur équivalent ASCII. Les
     * autres écritures (grec, cyrillique...) restent telles quelles, pour se comparer aux données
     * enregistrées.
     *
     * @param string $text
     * @return string
     */
    public static function fold_text($text): string {
        [$from, $to, $multiple] = self::fold_map();
        $pairs = $multiple;
        foreach (mb_str_split($from) as $i => $char) {
            $pairs[$char] = $to[$i];
        }
        return strtr(core_text::strtolower((string) $text), $pairs);
    }

    /**
     * Expression régulière PostgreSQL qui reconnaît un texte replié (voir fold_text()) dans un texte
     * accentué : chaque lettre devient la classe de ses variantes accentuées (« e » : [eéèêë...]), et
     * chaque suite de lettres équivalente à une seule lettre (« oe », « ae », « ss »...) admet aussi
     * cette lettre (œ, æ, ß...). Les autres caractères sont pris littéralement.
     *
     * @param string $text Texte replié.
     * @return string
     */
    public static function accent_pattern(string $text): string {
        static $classes = null;
        [$from, $to, $multiple] = self::fold_map();
        if ($classes === null) {
            $classes = [];
            foreach (mb_str_split($from) as $i => $char) {
                $classes[$to[$i]] = ($classes[$to[$i]] ?? $to[$i]) . $char;
            }
        }
        $sequences = [];
        foreach ($multiple as $char => $ascii) {
            $sequences[$ascii] = ($sequences[$ascii] ?? '') . $char;
        }
        $class = function ($char) use ($classes) {
            if (isset($classes[$char])) {
                return '[' . $classes[$char] . ']';
            }
            // Caractère ASCII autre que lettre ou chiffre : échappé (littéral pour PostgreSQL).
            return preg_match('/^[\x00-\x7F]$/', $char) && !ctype_alnum($char) ? '\\' . $char : $char;
        };
        $chars = mb_str_split($text);
        $pattern = '';
        for ($i = 0; $i < count($chars); $i++) {
            $pair = $chars[$i] . ($chars[$i + 1] ?? '');
            if (isset($sequences[$pair])) {
                $pattern .= '(?:' . $class($chars[$i]) . $class($chars[$i + 1]) . '|[' . $sequences[$pair] . '])';
                $i++;
            } else {
                $pattern .= $class($chars[$i]);
            }
        }
        return $pattern;
    }

    /**
     * Condition « l'expression contient le texte », sans tenir compte de la casse ni des accents
     * latins. PostgreSQL ne sait pas comparer sans tenir compte des accents (voir sql_like()) : le
     * texte y est cherché par une expression régulière (voir accent_pattern()). Les autres bases le
     * font par leur collation.
     *
     * @param string $expr Expression SQL (champ ou concaténation).
     * @param string $text Texte replié (voir words(), fold_text()).
     * @return string
     */
    public function contains(string $expr, string $text): string {
        global $DB;

        if ($DB->get_dbfamily() === 'postgres') {
            return $expr . ' ' . $DB->sql_regex(true, false) . ' ' . $this->param(self::accent_pattern($text));
        }
        return $DB->sql_like($expr, $this->param('%' . self::escape($text) . '%'), false, false);
    }

    /**
     * Condition « l'expression contient exactement ce texte » (casse et accents compris), ou, sans
     * jokers, « l'expression vaut ce texte ».
     *
     * @param string $expr
     * @param string $text
     * @param bool $anywhere Texte contenu (true) ou valeur exacte (false).
     * @return string
     */
    public function exact(string $expr, string $text, bool $anywhere): string {
        global $DB;

        $pattern = self::escape($text);
        return $DB->sql_like($expr, $this->param($anywhere ? '%' . $pattern . '%' : $pattern), true, true);
    }

    /**
     * Texte échappé pour LIKE (caractère d'échappement \ compris).
     *
     * @param string $text
     * @return string
     */
    protected static function escape(string $text): string {
        global $DB;

        return $DB->sql_like_escape(str_replace('\\', '\\\\', $text));
    }

    /**
     * Expressions concaténées, séparées par une espace, les valeurs nulles comptant pour du vide.
     *
     * @param string[] $exprs
     * @return string
     */
    public static function concat(array $exprs): string {
        global $DB;

        return $DB->sql_concat_join("' '", array_map(function ($expr) {
            return "COALESCE($expr, '')";
        }, $exprs));
    }

    /**
     * Condition « l'utilisateur connaît le sujet du dossier » : même règle que
     * \mod_these\local\rights::user_knows_subject() (sujet non confidentiel, étudiant, directeur, DEVE
     * ou bibliothèque de l'activité, membre du jury par son compte rattaché, actif).
     *
     * @param stdClass[] $links Activités du dossier (voir links::get_active_links()).
     * @param int $userid
     * @param string $alias Alias de la table these_dossier.
     * @return string
     */
    public function knows_subject(array $links, $userid, string $alias = 'd'): string {
        // DEVE et bibliothèque (capacité ou liste des agents) connaissent tous les sujets de l'activité.
        $all = [];
        foreach ($links as $link) {
            if (!empty($link->rights->deve) || !empty($link->rights->library)) {
                $all[] = (int) $link->these->id;
            }
        }
        $conditions = ["$alias.confidential = 0"];
        if ($all) {
            $conditions[] = $this->in("$alias.theseid", $all);
        }
        if ($userid) {
            $conditions[] = "$alias.userid = " . $this->param((int) $userid);
            $conditions[] = "$alias.directorid = " . $this->param((int) $userid);
            $conditions[] = "EXISTS (SELECT 1
                                       FROM {these_jury} kj
                                       JOIN {user} ku ON ku.id = kj.userid AND ku.deleted = 0 AND ku.suspended = 0
                                      WHERE kj.dossierid = $alias.id AND kj.userid = " . $this->param((int) $userid) . ')';
        }
        return '(' . implode(' OR ', $conditions) . ')';
    }
}
