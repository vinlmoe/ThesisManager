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
 * Bordereau de thèse : données saisies par l'étudiant au dépôt de sa soutenance (état civil,
 * cursus, résumés, contrat de diffusion) et embargo de diffusion.
 *
 * Les fonctions these_*() correspondantes de locallib/signatures.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bordereau {
    /** Durée maximale de l'embargo de diffusion qu'un étudiant peut demander, en mois après la soutenance. */
    public const EMBARGO_MAX_MONTHS = 12;

    /** Longueur maximale d'un résumé du bordereau, espaces compris. */
    public const BORDEREAU_SUMMARY_MAX = 1700;

    /** Nombre de mots-clés du bordereau. */
    public const BORDEREAU_KEYWORDS = 8;

    /**
     * Écoles nationales vétérinaires, pour le cursus déclaré dans la fiche signalétique.
     *
     * @return array
     */
    public static function school_options() {
        return [
            '' => get_string('choosedots'),
            'alfort' => 'EnvA (Maisons-Alfort)',
            'lyon' => 'VetAgro Sup (Lyon)',
            'nantes' => 'Oniris VetAgroBio (Nantes)',
            'toulouse' => 'ENVT (Toulouse)',
            'rouen' => 'UniLaSalle (Rouen)',
            'other' => get_string('schoolother', 'mod_these'),
        ];
    }

    /**
     * Bordereau de thèse d'un dossier.
     *
     * @param stdClass $dossier
     * @return array Voir these_bordereau_fields() ; tableau vide s'il n'a pas été renseigné.
     */
    public static function get_bordereau(stdClass $dossier) {
        $data = json_decode((string) ($dossier->bordereau ?? ''), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Données saisies par l'étudiant au dépôt de sa soutenance, communes au bordereau ABES, à la
     * fiche signalétique et au contrat de diffusion (stockées ensemble dans these_dossier.bordereau).
     *
     * @return string[]
     */
    public static function bordereau_fields() {
        return [
            // État civil et coordonnées.
            'birthname', 'firstnames', 'usagename', 'publicationname', 'birthdate', 'birthplace', 'birthdept',
            'nationality', 'street', 'postcode', 'city', 'country', 'phone', 'personalemail', 'emailconsent',
            // Cursus.
            'defvdate', 'defvschool', 'a6date', 'a6school', 'a6notyet',
            // Bordereau ABES.
            'nbvolumes', 'nbpages', 'nbrefs', 'summaryfr', 'summaryen', 'keywords',
            // Contrat de diffusion.
            'confidentialuntil', 'embargo', 'embargomonths', 'embargountil', 'embargoreason', 'diffusion',
            // Soutenance.
            'visio',
        ];
    }

    /**
     * Fin de l'embargo de diffusion d'une thèse : la durée demandée, en mois (self::EMBARGO_MAX_MONTHS
     * au plus, et par défaut), après la soutenance (date arrêtée, à défaut date proposée). Un embargo
     * enregistré avec une date garde sa date, sans dépasser un an après la soutenance.
     *
     * @param stdClass $dossier
     * @return int 0 sans embargo, ou tant que la soutenance n'a pas de date.
     */
    public static function embargo_until(stdClass $dossier) {
        $bordereau = self::get_bordereau($dossier);
        if (empty($bordereau['embargo'])) {
            return 0;
        }
        $defense = (int) ($dossier->defensedate ?: $dossier->proposeddate);
        $max = $defense ? self::add_months($defense, self::EMBARGO_MAX_MONTHS) : 0;
        if (!empty($bordereau['embargountil'])) {
            $until = (int) $bordereau['embargountil'];
            return $max ? min($until, $max) : $until;
        }
        if (!$defense) {
            return 0;
        }
        return self::add_months($defense, self::embargo_months($bordereau));
    }

    /**
     * Durée de l'embargo demandée, en mois : de 1 à self::EMBARGO_MAX_MONTHS, cette durée maximale à
     * défaut (demandes antérieures au choix de la durée).
     *
     * @param array $bordereau Voir these_get_bordereau().
     * @return int
     */
    public static function embargo_months(array $bordereau) {
        $months = (int) ($bordereau['embargomonths'] ?? 0);
        return $months >= 1 && $months <= self::EMBARGO_MAX_MONTHS ? $months : self::EMBARGO_MAX_MONTHS;
    }

    /**
     * Jour (à minuit) tombant un nombre de mois après une date, ramené au dernier jour du mois s'il
     * n'existe pas (31 janvier plus un mois : 28 ou 29 février).
     *
     * @param int $time
     * @param int $months
     * @return int
     */
    protected static function add_months($time, $months) {
        $date = usergetdate($time);
        $month = $date['mon'] - 1 + (int) $months;
        $year = $date['year'] + intdiv($month, 12);
        $month = $month % 12 + 1;
        $day = min($date['mday'], (int) date('t', make_timestamp($year, $month, 1, 12)));
        return make_timestamp($year, $month, $day);
    }
}
