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
    /**
     * Colonnes reconnues dans un fichier d'import de dossiers. « student » et « title » sont
     * obligatoires ; les autres facultatives.
     *
     * @return string[]
     */
    public static function import_columns() {
        return ['student', 'title', 'titleen', 'summary', 'keywords', 'director', 'codirector', 'devenumber', 'libraryref'];
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
     * Importe des dossiers existants depuis un fichier CSV : chaque ligne crée un dossier au statut
     * « actif » (sujet déjà validé et enregistré hors du module), avec une entrée d'historique
     * « importé ». Les lignes en erreur sont ignorées et signalées ; aucune ligne n'écrase un dossier
     * existant.
     *
     * @param stdClass $these
     * @param string $content Contenu du fichier.
     * @param string $delimiter Nom du séparateur (voir csv_import_reader::get_delimiter_list()).
     * @param string $encoding
     * @param bool $dryrun Vérifie sans rien créer.
     * @param int $userid Auteur de l'import.
     * @return array Liste de ['line' => int, 'student' => string, 'ok' => bool, 'message' => string].
     */
    public static function import_dossiers(stdClass $these, $content, $delimiter, $encoding, $dryrun, $userid) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/csvlib.class.php');

        $iid = csv_import_reader::get_new_iid('mod_these');
        $reader = new csv_import_reader($iid, 'mod_these');
        $count = $reader->load_csv_content($content, $encoding, $delimiter);
        if ($count === false) {
            throw new moodle_exception('importinvalidfile', 'mod_these', '', $reader->get_error());
        }
        $columns = array_map(function ($column) {
            return core_text::strtolower(trim($column));
        }, $reader->get_columns());
        foreach (['student', 'title'] as $required) {
            if (!in_array($required, $columns, true)) {
                $reader->cleanup();
                throw new moodle_exception('importmissingcolumn', 'mod_these', '', $required);
            }
        }

        $report = [];
        $seen = [];
        $numbers = [];
        $line = 1;
        $reader->init();
        while ($row = $reader->next()) {
            $line++;
            $values = [];
            foreach ($columns as $index => $column) {
                if (in_array($column, these_import_columns(), true)) {
                    $values[$column] = trim((string) ($row[$index] ?? ''));
                }
            }
            $values += array_fill_keys(these_import_columns(), '');
            $result = ['line' => $line, 'student' => $values['student'], 'ok' => false, 'message' => ''];

            $student = these_find_user($values['student']);
            $director = $values['director'] !== '' ? these_find_user($values['director']) : null;
            if (!$student) {
                $result['message'] = get_string('importusernotfound', 'mod_these', s($values['student']));
            } else if (
                isset($seen[$student->id])
                    || $DB->record_exists('these_dossier', ['theseid' => $these->id, 'userid' => $student->id])
            ) {
                $result['message'] = get_string('importdossierexists', 'mod_these');
            } else if ($values['title'] === '') {
                $result['message'] = get_string('importtitlemissing', 'mod_these');
            } else if ($values['director'] !== '' && !$director) {
                $result['message'] = get_string('importusernotfound', 'mod_these', s($values['director']));
            } else if (
                $values['devenumber'] !== ''
                    && (isset($numbers[$values['devenumber']]) || these_devenumber_taken($these->id, $values['devenumber']))
            ) {
                $result['message'] = get_string('devenumbertaken', 'mod_these');
            } else {
                $result['ok'] = true;
                $result['student'] = fullname($student);
                $seen[$student->id] = true;
                if ($values['devenumber'] !== '') {
                    $numbers[$values['devenumber']] = true;
                }
                if (!$dryrun) {
                    $now = time();
                    $dossierid = $DB->insert_record('these_dossier', (object) [
                        'theseid' => $these->id,
                        'userid' => $student->id,
                        'status' => THESE_STATUS_ACTIVE,
                        'statustime' => $now,
                        'title' => $values['title'],
                        'titleen' => $values['titleen'],
                        'summary' => $values['summary'],
                        'keywords' => $values['keywords'],
                        'directorid' => $director ? $director->id : 0,
                        'codirectorname' => $values['codirector'],
                        'devenumber' => $values['devenumber'],
                        'libraryref' => $values['libraryref'],
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                    these_log($dossierid, $userid, 'imported', THESE_STATUS_ACTIVE, THESE_STATUS_ACTIVE);
                }
                $result['message'] = get_string($dryrun ? 'importwouldcreate' : 'importcreated', 'mod_these');
            }
            $report[] = $result;
        }
        $reader->close();
        $reader->cleanup();
        return $report;
    }
}
