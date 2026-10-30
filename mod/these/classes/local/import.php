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

use context_course;
use core_text;
use csv_import_reader;
use moodle_exception;
use stdClass;

/**
 * Import des dossiers par fichier CSV.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import {
    /** Statuts dans lesquels un dossier peut être importé (clé => constante). */
    const STATUSES = [
        'active' => THESE_STATUS_ACTIVE,
        'corrections' => THESE_STATUS_CORRECTIONS,
        'closed' => THESE_STATUS_CLOSED,
    ];

    /**
     * Colonnes reconnues dans un fichier d'import de dossiers. « student » et « title » sont
     * obligatoires ; les autres facultatives.
     *
     * @return string[]
     */
    public static function import_columns() {
        return ['student', 'title', 'titleen', 'summary', 'keywords', 'director', 'devenumber', 'libraryref',
            'status', 'defensedate', 'defenselocation', 'mention', 'corrections'];
    }

    /**
     * Retrouve un utilisateur par identifiant de connexion, adresse de courriel ou numéro
     * d'identification, dans cet ordre.
     *
     * @param string $value
     * @return stdClass|null
     */
    public static function find_user($value) {
        global $CFG, $DB;

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $user = $DB->get_record('user', ['username' => core_text::strtolower($value), 'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0]);
        if (!$user) {
            $users = $DB->get_records_select('user', 'LOWER(email) = LOWER(?) AND deleted = 0', [$value], 'id', '*', 0, 2);
            $user = count($users) === 1 ? reset($users) : null;
        }
        if (!$user) {
            $users = $DB->get_records('user', ['idnumber' => $value, 'deleted' => 0], 'id', '*', 0, 2);
            $user = count($users) === 1 ? reset($users) : null;
        }
        return $user ?: null;
    }

    /**
     * Texte comparable : minuscules, sans accents ni ponctuation, espaces simples.
     *
     * @param string $value
     * @return string
     */
    protected static function normalise($value) {
        $value = core_text::strtolower(core_text::specialtoascii(trim((string) $value)));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /**
     * Statut d'import à partir d'une clé (active, corrections, closed) ou du libellé d'un de ces
     * statuts, en français ou en anglais ; vide : dossier actif.
     *
     * @param string $value
     * @return int|null Statut, null s'il n'est pas reconnu ou pas importable.
     */
    public static function parse_status($value) {
        $value = self::normalise($value);
        if ($value === '') {
            return THESE_STATUS_ACTIVE;
        }
        $strings = get_string_manager();
        $keys = [THESE_STATUS_ACTIVE => 'statusactive', THESE_STATUS_CORRECTIONS => 'statuscorrections',
            THESE_STATUS_CLOSED => 'statusclosed'];
        foreach (self::STATUSES as $key => $status) {
            $labels = [$key];
            foreach (['fr', 'en'] as $lang) {
                $labels[] = self::normalise($strings->get_string($keys[$status], 'mod_these', null, $lang));
            }
            if (in_array($value, $labels, true)) {
                return $status;
            }
        }
        return null;
    }

    /**
     * Date et heure lues d'un fichier : nombre de jours d'un tableur (partie décimale : heure),
     * « jj/mm/aaaa [hh:mm] » ou « aaaa-mm-jj [hh:mm] ». Sans heure, 14 h.
     *
     * @param mixed $value
     * @return int|null Horodatage, null si illisible.
     */
    public static function parse_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            $days = (int) floor((float) $value);
            $minutes = (int) round(((float) $value - $days) * 1440);
            $date = (new \DateTime('1899-12-30', new \DateTimeZone('UTC')))->modify("+$days days");
            return make_timestamp(
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
                $minutes ? intdiv($minutes, 60) : 14,
                $minutes % 60
            );
        }
        $time = '(?:[ T](\d{1,2})[:h](\d{2}))?';
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})' . $time . '#', $value, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else if (preg_match('#^(\d{4})-(\d{2})-(\d{2})' . $time . '#', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        $hour = isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 14;
        $minute = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0;
        return make_timestamp($year, $month, $day, $hour, $minute);
    }

    /**
     * Mention à partir d'une clé ou d'un libellé (français ou anglais) ; vide : aucune.
     *
     * @param string $value
     * @return string|null Clé de these_mention_options(), '' sans mention, null si inconnue.
     */
    public static function parse_mention($value) {
        $value = self::normalise($value);
        if ($value === '') {
            return '';
        }
        // « très honorable » vaut la clé « treshonorable ».
        $compact = str_replace(' ', '', $value);
        foreach (array_keys(these_mention_options()) as $key) {
            if ($compact === $key) {
                return $key;
            }
        }
        $strings = get_string_manager();
        foreach (array_keys(these_mention_options()) as $key) {
            $labels = [$key];
            foreach (['fr', 'en'] as $lang) {
                $labels[] = self::normalise($strings->get_string('mention' . $key, 'mod_these', null, $lang));
            }
            if (in_array($value, $labels, true)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Importe des dossiers existants depuis un fichier CSV (voir import_rows()).
     *
     * @param stdClass $these
     * @param string $content Contenu du fichier.
     * @param string $delimiter Nom du séparateur (voir csv_import_reader::get_delimiter_list()).
     * @param string $encoding
     * @param bool $dryrun Vérifie sans rien créer.
     * @param int $userid Auteur de l'import.
     * @return array Voir import_rows().
     */
    public static function import_dossiers(stdClass $these, $content, $delimiter, $encoding, $dryrun, $userid) {
        global $CFG;

        require_once($CFG->libdir . '/csvlib.class.php');

        $iid = csv_import_reader::get_new_iid('mod_these');
        $reader = new csv_import_reader($iid, 'mod_these');
        $count = $reader->load_csv_content($content, $encoding, $delimiter);
        if ($count === false) {
            throw new moodle_exception('importinvalidfile', 'mod_these', '', $reader->get_error());
        }
        $rows = [$reader->get_columns()];
        $reader->init();
        while ($row = $reader->next()) {
            $rows[] = $row;
        }
        $reader->close();
        $reader->cleanup();
        return self::import_rows($these, $rows, $dryrun, $userid);
    }

    /**
     * Importe des dossiers existants : chaque ligne crée un dossier, avec une entrée d'historique
     * « importé ». Par défaut le dossier est actif (sujet validé et enregistré hors du module) ; la
     * colonne « status » reprend aussi une thèse soutenue dont les corrections sont en cours
     * (corrections) ou une thèse close (closed), avec sa date et son lieu de soutenance, sa mention
     * et les corrections demandées. Un co-directeur qui est un enseignant interne de l'annuaire y
     * est rattaché. L'étudiant et le directeur doivent être inscrits au cours : l'import ne rattache
     * pas un dossier à un compte quelconque du site. Les lignes en erreur sont ignorées et signalées ;
     * aucune ligne n'écrase un dossier existant.
     *
     * Les documents du circuit ne sont pas repris (agréments, fiche signalétique...) : un dossier
     * importé n'attend donc aucune signature pour avancer.
     *
     * @param stdClass $these
     * @param array[] $rows Lignes du fichier, la première étant l'en-tête (voir a6::read_rows()).
     * @param bool $dryrun Vérifie sans rien créer.
     * @param int $userid Auteur de l'import.
     * @return array Liste de ['line' => int, 'student' => string, 'ok' => bool, 'message' => string].
     */
    public static function import_rows(stdClass $these, array $rows, $dryrun, $userid) {
        global $DB;

        $columns = array_map(function ($column) {
            return core_text::strtolower(trim((string) $column));
        }, (array) array_shift($rows));
        foreach (['student', 'title'] as $required) {
            if (!in_array($required, $columns, true)) {
                throw new moodle_exception('importmissingcolumn', 'mod_these', '', $required);
            }
        }

        $report = [];
        $seen = [];
        $numbers = [];
        $line = 1;
        $coursecontext = context_course::instance($these->course);
        foreach ($rows as $row) {
            $line++;
            $values = [];
            foreach ($columns as $index => $column) {
                if (in_array($column, self::import_columns(), true)) {
                    $values[$column] = trim((string) ($row[$index] ?? ''));
                }
            }
            $values += array_fill_keys(self::import_columns(), '');
            $result = ['line' => $line, 'student' => $values['student'], 'ok' => false, 'message' => ''];

            $student = self::find_user($values['student']);
            $director = $values['director'] !== '' ? self::find_user($values['director']) : null;
            $status = self::parse_status($values['status']);
            $defended = $status !== null && $status !== THESE_STATUS_ACTIVE;
            $defensedate = $values['defensedate'] !== '' ? self::parse_datetime($values['defensedate']) : 0;
            $mention = self::parse_mention($values['mention']);
            if (!$student) {
                $result['message'] = get_string('importusernotfound', 'mod_these', s($values['student']));
            } else if (!is_enrolled($coursecontext, $student)) {
                $result['message'] = get_string('importusernotenrolled', 'mod_these', s($values['student']));
            } else if (
                isset($seen[$student->id])
                    || $DB->record_exists('these_dossier', ['theseid' => $these->id, 'userid' => $student->id])
            ) {
                $result['message'] = get_string('importdossierexists', 'mod_these');
            } else if ($values['title'] === '') {
                $result['message'] = get_string('importtitlemissing', 'mod_these');
            } else if ($values['director'] !== '' && !$director) {
                $result['message'] = get_string('importusernotfound', 'mod_these', s($values['director']));
            } else if ($director && !is_enrolled($coursecontext, $director)) {
                $result['message'] = get_string('importusernotenrolled', 'mod_these', s($values['director']));
            } else if (
                $values['devenumber'] !== ''
                    && (isset($numbers[$values['devenumber']]) || these_devenumber_taken($these->id, $values['devenumber']))
            ) {
                $result['message'] = get_string('devenumbertaken', 'mod_these');
            } else if ($status === null) {
                $result['message'] = get_string('importbadstatus', 'mod_these', s($values['status']));
            } else if ($defensedate === null || ($defended && !$defensedate)) {
                $result['message'] = get_string('importbaddefensedate', 'mod_these', s($values['defensedate']));
            } else if ($mention === null) {
                $result['message'] = get_string('importbadmention', 'mod_these', s($values['mention']));
            } else {
                $result['ok'] = true;
                $result['student'] = fullname($student);
                $seen[$student->id] = true;
                if ($values['devenumber'] !== '') {
                    $numbers[$values['devenumber']] = true;
                }
                if (!$dryrun) {
                    $now = time();
                    $record = [
                        'theseid' => $these->id,
                        'userid' => $student->id,
                        'status' => $status,
                        'statustime' => $now,
                        'title' => $values['title'],
                        'titleen' => $values['titleen'],
                        'summary' => $values['summary'],
                        'keywords' => $values['keywords'],
                        'directorid' => $director ? $director->id : 0,
                        'devenumber' => $values['devenumber'],
                        'libraryref' => $values['libraryref'],
                        'defensedate' => (int) $defensedate,
                        'proposeddate' => (int) $defensedate,
                        'defenselocation' => $values['defenselocation'],
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ];
                    if ($defended) {
                        $record['defenseresult'] = THESE_RESULT_ADMITTED;
                        $record['mention'] = $mention;
                        $record['correctionsrequested'] = (int) ($status === THESE_STATUS_CORRECTIONS
                            || $values['corrections'] !== '');
                        $record['correctionsnote'] = $values['corrections'];
                    }
                    $dossierid = $DB->insert_record('these_dossier', (object) $record);
                    these_log($dossierid, $userid, 'imported', $status, $status);
                }
                $result['message'] = get_string($dryrun ? 'importwouldcreate' : 'importcreated', 'mod_these')
                    . ($status !== THESE_STATUS_ACTIVE ? ' — ' . these_status_label($status) : '');
            }
            $report[] = $result;
        }
        return $report;
    }
}
