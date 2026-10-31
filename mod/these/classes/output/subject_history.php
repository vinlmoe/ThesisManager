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

namespace mod_these\output;

use context;
use mod_these\local\files;
use mod_these\local\rights;
use mod_these\local\subjectchange;
use moodle_url;
use stdClass;

/**
 * Historique des sujets d'un dossier (changements de sujet), avec le PDF archivé de chaque ancien sujet.
 *
 * L'appelant n'affiche ce composant qu'à qui connaît le sujet en cours (voir
 * dossier::export_for_template()) ; un ancien sujet confidentiel reste en outre masqué (titre, motif et PDF
 * archivé) à qui ne connaîtrait pas un sujet confidentiel de ce dossier (voir
 * rights::user_knows_archived_subject()).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subject_history implements \renderable, \templatable {
    /** @var context */
    protected $context;
    /** @var stdClass */
    protected $dossier;
    /** @var int */
    protected $userid;

    /**
     * Constructeur.
     *
     * @param context $context Contexte de l'activité.
     * @param stdClass $dossier
     * @param int|null $userid Utilisateur qui consulte (l'utilisateur courant par défaut).
     */
    public function __construct(context $context, stdClass $dossier, $userid = null) {
        global $USER;

        $this->context = $context;
        $this->dossier = $dossier;
        $this->userid = (int) ($userid ?? $USER->id);
    }

    /**
     * Données du gabarit mod_these/subject_history.
     *
     * @param \renderer_base $output
     * @return array hasentries, heading, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        $history = subjectchange::get_subject_history($this->dossier->id);
        if (!$history) {
            return ['hasentries' => false, 'heading' => '', 'table' => false];
        }
        $files = [];
        foreach (files::get_files($this->context, 'subjectarchive', $this->dossier->id) as $file) {
            if (preg_match('/-(\d+)\.pdf$/', $file->get_filename(), $match)) {
                $files[(int) $match[1]] = $file;
            }
        }
        $head = [get_string('date'), get_string('subjecttitle', 'mod_these'), get_string('director', 'mod_these'),
            get_string('subjectchangereason', 'mod_these'), get_string('subjectarchive', 'mod_these')];
        $rows = [];
        foreach ($history as $entry) {
            if (!rights::user_knows_archived_subject($entry, $this->dossier, $this->context, $this->userid)) {
                $rows[] = [
                    [parts::text(userdate($entry->timecreated, get_string('strftimedate', 'langconfig')))],
                    [parts::em(get_string('confidentialtitle', 'mod_these'))],
                    [parts::text($entry->directorname)],
                    [parts::text('-')],
                    [parts::text('-')],
                ];
                continue;
            }
            $file = $files[(int) $entry->id] ?? null;
            $rows[] = [
                [parts::text(userdate($entry->timecreated, get_string('strftimedate', 'langconfig')))],
                [parts::html(format_string($entry->title))],
                [parts::text($entry->directorname)],
                [parts::html(format_text((string) $entry->reason, FORMAT_PLAIN))],
                [$file ? parts::link(moodle_url::make_pluginfile_url(
                    $this->context->id,
                    'mod_these',
                    'subjectarchive',
                    $this->dossier->id,
                    '/',
                    $file->get_filename(),
                    true
                ), $file->get_filename(), '') : parts::text('-')],
            ];
        }
        return [
            'hasentries' => true,
            'heading' => get_string('subjecthistory', 'mod_these'),
            'table' => table_context::export('generaltable these-subjecthistory', $head, $rows),
        ];
    }
}
