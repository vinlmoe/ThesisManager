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
 * Validation de l'A6 (année d'approfondissement) d'un étudiant, saisie par la DEVE. Elle est liée à
 * l'étudiant et non à son dossier de thèse (table these_a6, commune à toutes les activités) : elle
 * peut être enregistrée avant la création du dossier, et vaut pour chacun de ses dossiers. La DEVE la
 * saisit à la validation du dossier de soutenance, dans la modification d'un dossier, en lot depuis le
 * tableau de bord ou par import d'un fichier (Excel, OpenDocument ou CSV). L'agrément administratif et
 * la fiche signalétique la reprennent. Faute de saisie, la valeur d'avant cette table : celle de
 * l'agrément du dossier, sinon celle que l'étudiant avait déclarée dans sa fiche.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class a6 {
    /**
     * Validation de l'A6 d'un étudiant.
     *
     * @param int $userid
     * @param stdClass|null $dossier Dossier dont reprendre l'ancienne valeur, à défaut de saisie.
     * @return array {date (int, 0 si inconnue ou non validée), notyet (bool), school (string),
     *               recorded (saisie par la DEVE)}
     */
    public static function get_for_user($userid, ?stdClass $dossier = null) {
        global $DB;

        $record = $userid ? $DB->get_record('these_a6', ['userid' => (int) $userid]) : null;
        if ($record) {
            return ['date' => (int) $record->a6date, 'notyet' => empty($record->a6date),
                'school' => (string) $record->a6school, 'recorded' => true];
        }
        $legacy = $dossier ? self::legacy(these_get_agreement($dossier), these_get_bordereau($dossier)) : null;
        return $legacy ?? ['date' => 0, 'notyet' => true, 'school' => '', 'recorded' => false];
    }

    /**
     * Valeur d'avant la table these_a6 : saisie de la DEVE dans l'agrément, sinon déclaration de
     * l'étudiant dans sa fiche.
     *
     * @param array $agreement
     * @param array $bordereau
     * @return array|null Voir get_for_user(), null sans aucune valeur.
     */
    protected static function legacy(array $agreement, array $bordereau) {
        $inagreement = array_key_exists('a6date', $agreement) || array_key_exists('a6notyet', $agreement);
        $source = $inagreement ? $agreement : $bordereau;
        if (!array_key_exists('a6date', $source) && !array_key_exists('a6notyet', $source)) {
            return null;
        }
        $notyet = !empty($source['a6notyet']) || empty($source['a6date']);
        return ['date' => $notyet ? 0 : (int) $source['a6date'], 'notyet' => $notyet,
            'school' => (string) ($agreement['a6school'] ?? ($bordereau['a6school'] ?? '')), 'recorded' => false];
    }

    /**
     * Validation de l'A6 de l'étudiant d'un dossier.
     *
     * @param stdClass $dossier
     * @return array Voir get_for_user().
     */
    public static function get(stdClass $dossier) {
        return self::get_for_user((int) ($dossier->userid ?? 0), $dossier);
    }

    /**
     * Validation de l'A6 pour les documents d'un dossier : celle de l'étudiant, sinon celle des données
     * du document (agrément, fiche), qui servent aussi aux exemples des modèles.
     *
     * @param int $userid
     * @param array $agreement
     * @param array $bordereau
     * @return array Voir get_for_user().
     */
    public static function for_documents($userid, array $agreement, array $bordereau) {
        $a6 = self::get_for_user($userid);
        if ($a6['recorded']) {
            return $a6;
        }
        return self::legacy($agreement, $bordereau) ?? $a6;
    }

    /**
     * Enregistre la validation de l'A6 d'un étudiant et l'inscrit à l'historique de ses dossiers.
     *
     * @param int $userid Étudiant.
     * @param int $date Date de validation (0 : pas encore validée).
     * @param string $school École (voir these_school_options()), vide pour garder celle connue.
     * @param int $actorid Compte de la DEVE.
     * @return void
     */
    public static function set_for_user($userid, $date, $school, $actorid) {
        global $DB;

        $userid = (int) $userid;
        $existing = $DB->get_record('these_a6', ['userid' => $userid]);
        $record = (object) [
            'userid' => $userid,
            'a6date' => (int) $date,
            'a6school' => (string) $school !== '' ? (string) $school : (string) ($existing->a6school ?? ''),
            'usermodified' => (int) $actorid,
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('these_a6', $record);
        } else {
            $DB->insert_record('these_a6', $record);
        }
        $label = self::label_for_user($userid);
        foreach ($DB->get_records('these_dossier', ['userid' => $userid], '', 'id, status') as $dossier) {
            these_log($dossier->id, $actorid, 'a6recorded', $dossier->status, $dossier->status, $label);
        }
    }

    /**
     * Enregistre la validation de l'A6 de l'étudiant d'un dossier.
     *
     * @param stdClass $dossier
     * @param int $date
     * @param string $school Vide pour garder celle connue (y compris celle du dossier, à défaut de saisie).
     * @param int $actorid
     * @return stdClass Le dossier.
     */
    public static function set(stdClass $dossier, $date, $school, $actorid) {
        if ((string) $school === '') {
            $school = self::get($dossier)['school'];
        }
        self::set_for_user($dossier->userid, $date, $school, $actorid);
        return $dossier;
    }

    /**
     * Libellé d'une validation de l'A6 (voir get_for_user()).
     *
     * @param array $a6
     * @return string
     */
    protected static function format(array $a6) {
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
     * Libellé de la validation de l'A6 d'un étudiant.
     *
     * @param int $userid
     * @return string
     */
    public static function label_for_user($userid) {
        return self::format(self::get_for_user($userid));
    }

    /**
     * Libellé de la validation de l'A6 de l'étudiant d'un dossier, pour le dossier et le dépôt.
     *
     * @param stdClass $dossier
     * @return string
     */
    public static function label(stdClass $dossier) {
        return self::format(self::get($dossier));
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
     * datea6, a6), ecole (ou school). Tout étudiant inscrit au cours de l'activité peut figurer, qu'il ait
     * ou non un dossier de thèse.
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

        $coursecontext = \context_course::instance($these->course);
        $report = [];
        $line = 1;
        foreach ($rows as $row) {
            $line++;
            $identifier = trim((string) ($row[$columns['student']] ?? ''));
            $result = ['line' => $line, 'student' => $identifier, 'ok' => false, 'message' => ''];
            $student = these_find_user($identifier);
            $date = self::parse_date($row[$columns['date']] ?? '');
            $school = isset($columns['school']) ? self::parse_school($row[$columns['school']] ?? '') : '';
            if (!$student) {
                $result['message'] = get_string('importusernotfound', 'mod_these', s($identifier));
            } else if (!is_enrolled($coursecontext, $student)) {
                $result['message'] = get_string('a6notenrolled', 'mod_these');
            } else if ($date === null) {
                $result['message'] = get_string('a6baddate', 'mod_these', s((string) ($row[$columns['date']] ?? '')));
            } else if ($school === null) {
                $result['message'] = get_string('a6badschool', 'mod_these', s((string) ($row[$columns['school']] ?? '')));
            } else {
                $result['ok'] = true;
                $result['student'] = fullname($student);
                if (!$dryrun) {
                    self::set_for_user($student->id, $date, $school, $userid);
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
