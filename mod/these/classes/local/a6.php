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

use core_text;
use moodle_exception;
use stdClass;

/**
 * Validation de l'A6 (année d'approfondissement) d'un étudiant, saisie par la DEVE : dans la
 * validation du dossier de soutenance, la modification d'un dossier, en lot depuis le tableau de bord
 * ou par import d'un fichier (Excel, OpenDocument ou CSV). Elle est conservée dans l'agrément du
 * dossier (a6date, a6notyet, a6school) et reprise par l'agrément administratif et la fiche
 * signalétique. Les dossiers où l'étudiant l'avait déclarée dans sa fiche gardent cette déclaration
 * tant que la DEVE ne l'a pas saisie.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class a6 {
    /**
     * Validation de l'A6 d'un dossier.
     *
     * @param stdClass $dossier
     * @return array {date (int, 0 si inconnue), notyet (bool), school (string), recorded (saisie par la DEVE)}
     */
    public static function get(stdClass $dossier) {
        $agreement = these_get_agreement($dossier);
        if (array_key_exists('a6date', $agreement) || array_key_exists('a6notyet', $agreement)) {
            $declared = these_get_bordereau($dossier);
            return [
                'date' => (int) ($agreement['a6date'] ?? 0),
                'notyet' => !empty($agreement['a6notyet']),
                'school' => (string) ($agreement['a6school'] ?? ($declared['a6school'] ?? '')),
                'recorded' => true,
            ];
        }
        $declared = these_get_bordereau($dossier);
        return [
            'date' => (int) ($declared['a6date'] ?? 0),
            'notyet' => !empty($declared['a6notyet']),
            'school' => (string) ($declared['a6school'] ?? ''),
            'recorded' => false,
        ];
    }

    /**
     * Enregistre la validation de l'A6 d'un dossier (agrément) et l'inscrit à l'historique.
     *
     * @param stdClass $dossier
     * @param int $date Date de validation (0 : pas encore validée).
     * @param string $school École (voir these_school_options()), vide pour garder celle connue.
     * @param int $userid Compte de la DEVE.
     * @return stdClass Le dossier à jour.
     */
    public static function set(stdClass $dossier, $date, $school, $userid) {
        global $DB;

        $current = self::get($dossier);
        $school = (string) $school !== '' ? (string) $school : $current['school'];
        $agreement = array_merge(these_get_agreement($dossier), [
            'a6date' => (int) $date,
            'a6notyet' => (int) empty($date),
            'a6school' => $school,
        ]);
        $dossier->agreement = json_encode($agreement);
        $DB->update_record('these_dossier', (object) ['id' => $dossier->id, 'agreement' => $dossier->agreement,
            'timemodified' => time()]);
        these_log($dossier->id, $userid, 'a6recorded', $dossier->status, $dossier->status, self::label($dossier));
        return $dossier;
    }

    /**
     * Libellé de la validation de l'A6 d'un dossier, pour l'historique et le dossier.
     *
     * @param stdClass $dossier
     * @return string
     */
    public static function label(stdClass $dossier) {
        $a6 = self::get($dossier);
        if ($a6['notyet'] || !$a6['date']) {
            return get_string('a6notvalidated', 'mod_these');
        }
        $schools = these_school_options();
        $label = userdate($a6['date'], get_string('strftimedate', 'langconfig'));
        if ($a6['school'] !== '' && isset($schools[$a6['school']])) {
            $label .= ' (' . $schools[$a6['school']] . ')';
        }
        return $label;
    }

    /**
     * Lit une date d'un fichier importé : nombre de jours d'un tableur, « jj/mm/aaaa » ou
     * « aaaa-mm-jj ».
     *
     * @param mixed $value
     * @return int|null Horodatage (midi), 0 pour « non » (A6 pas encore validée), null si illisible.
     */
    public static function parse_date($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (in_array(core_text::strtolower($value), ['non', 'no', 'non validée', 'non validee'], true)) {
            return 0;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            // Date d'un tableur : jours depuis le 30 décembre 1899.
            $days = (int) floor((float) $value);
            $time = (new \DateTime('1899-12-30', new \DateTimeZone('UTC')))->modify("+$days days");
            return make_timestamp((int) $time->format('Y'), (int) $time->format('n'), (int) $time->format('j'), 12);
        }
        $french = preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $value, $m);
        if ($french && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return make_timestamp((int) $m[3], (int) $m[2], (int) $m[1], 12);
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 12);
        }
        return null;
    }

    /**
     * École d'un fichier importé : clé ou libellé de these_school_options(), casse et accents ignorés.
     *
     * @param string $value
     * @return string|null Clé, '' si vide, null si inconnue.
     */
    public static function parse_school($value) {
        $value = self::normalise($value);
        if ($value === '') {
            return '';
        }
        foreach (these_school_options() as $key => $label) {
            if ($key !== '' && ($value === self::normalise($key) || strpos(self::normalise($label), $value) !== false)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Texte ramené à une forme comparable : minuscules, sans accents ni espaces superflus.
     *
     * @param string $value
     * @return string
     */
    protected static function normalise($value) {
        return trim(preg_replace('/\s+/', ' ', core_text::strtolower(core_text::specialtoascii((string) $value))));
    }

    /**
     * Lignes d'un fichier tableur ou CSV : tableaux de cellules, la première étant l'en-tête.
     *
     * @param string $path Fichier temporaire.
     * @param string $filename Nom d'origine (son extension désigne le format).
     * @return array[]
     */
    public static function read_rows($path, $filename) {
        global $CFG;

        // Moodle 4.1 charge PhpSpreadsheet par son autoload ; les versions suivantes, par le sien.
        $autoload = $CFG->libdir . '/phpspreadsheet/vendor/autoload.php';
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory') && file_exists($autoload)) {
            require_once($autoload);
        }
        $extension = core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $types = ['xlsx' => 'Xlsx', 'xls' => 'Xls', 'ods' => 'Ods', 'csv' => 'Csv', 'txt' => 'Csv'];
        if (!isset($types[$extension])) {
            throw new moodle_exception('a6importformat', 'mod_these');
        }
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($types[$extension]);
        if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
            $content = (string) file_get_contents($path);
            $firstline = strtok($content, "\n");
            $reader->setDelimiter(substr_count($firstline, ';') > substr_count($firstline, ',') ? ';' : ',');
            if (!mb_check_encoding($content, 'UTF-8')) {
                $reader->setInputEncoding('Windows-1252');
            }
        }
        $reader->setReadDataOnly(true);
        $rows = [];
        foreach ($reader->load($path)->getActiveSheet()->toArray(null, false, false, false) as $row) {
            if (
                array_filter($row, function ($cell) {
                    return trim((string) $cell) !== '';
                })
            ) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Importe des validations de l'A6 : une ligne par étudiant (identifiant, adresse ou numéro
     * d'étudiant), avec la date de validation (« non » si elle n'est pas encore validée) et, au besoin,
     * l'école. Colonnes reconnues à leur en-tête : etudiant (ou student, identifiant, email), date (ou
     * datea6, a6), ecole (ou school). Seuls les dossiers de l'activité sont mis à jour.
     *
     * @param stdClass $these
     * @param array[] $rows Voir read_rows().
     * @param bool $dryrun Vérifie sans rien enregistrer.
     * @param int $userid Compte de la DEVE.
     * @return array Liste de ['line' => int, 'student' => string, 'ok' => bool, 'message' => string].
     */
    public static function import(stdClass $these, array $rows, $dryrun, $userid) {
        global $DB;

        $aliases = [
            'student' => ['etudiant', 'student', 'identifiant', 'email', 'courriel', 'numero', 'idnumber'],
            'date' => ['date', 'datea6', 'a6', 'date a6', 'validation a6', 'date de validation'],
            'school' => ['ecole', 'school', 'etablissement'],
        ];
        $header = array_map([self::class, 'normalise'], (array) array_shift($rows));
        $columns = [];
        foreach ($aliases as $key => $names) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true)) {
                    $columns[$key] = $index;
                    break;
                }
            }
        }
        foreach (['student', 'date'] as $required) {
            if (!isset($columns[$required])) {
                throw new moodle_exception('importmissingcolumn', 'mod_these', '', $aliases[$required][0]);
            }
        }

        $report = [];
        $line = 1;
        foreach ($rows as $row) {
            $line++;
            $identifier = trim((string) ($row[$columns['student']] ?? ''));
            $result = ['line' => $line, 'student' => $identifier, 'ok' => false, 'message' => ''];
            $student = these_find_user($identifier);
            $dossier = $student ? $DB->get_record('these_dossier', ['theseid' => $these->id, 'userid' => $student->id]) : null;
            $date = self::parse_date($row[$columns['date']] ?? '');
            $school = isset($columns['school']) ? self::parse_school($row[$columns['school']] ?? '') : '';
            if (!$student) {
                $result['message'] = get_string('importusernotfound', 'mod_these', s($identifier));
            } else if (!$dossier) {
                $result['message'] = get_string('a6nodossier', 'mod_these');
            } else if ($date === null) {
                $result['message'] = get_string('a6baddate', 'mod_these', s((string) ($row[$columns['date']] ?? '')));
            } else if ($school === null) {
                $result['message'] = get_string('a6badschool', 'mod_these', s((string) ($row[$columns['school']] ?? '')));
            } else {
                $result['ok'] = true;
                $result['student'] = fullname($student);
                if (!$dryrun) {
                    self::set($dossier, $date, $school, $userid);
                }
                $day = $date ? userdate($date, get_string('strftimedate', 'langconfig')) : '';
                $result['message'] = $date
                    ? get_string($dryrun ? 'a6wouldrecord' : 'a6recordedon', 'mod_these', $day)
                    : get_string('a6notvalidated', 'mod_these');
            }
            $report[] = $result;
        }
        return $report;
    }
}
