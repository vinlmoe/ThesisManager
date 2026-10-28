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

namespace mod_thesesynthesis\output;

use mod_these\output\parts;
use mod_these\output\table_context;

/**
 * Signatures en attente, toutes promotions confondues : une ligne par dossier, avec les documents
 * attendus et le lien vers la page où agir (« Mes signatures », suivi des signatures du dossier).
 *
 * Le titre d'une thèse confidentielle n'entre dans le contexte du gabarit que si l'utilisateur
 * connaît son sujet (knowssubject, évalué par thesesynthesis_get_signatures()).
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signature_table implements \renderable, \templatable {
    /** @var array */
    protected $rows;
    /** @var string */
    protected $emptymessage;

    /**
     * Constructeur.
     *
     * @param array $rows Voir thesesynthesis_get_signatures() (mine ou awaited).
     * @param string $emptymessage Message affiché sans signature.
     */
    public function __construct(array $rows, $emptymessage) {
        $this->rows = $rows;
        $this->emptymessage = (string) $emptymessage;
    }

    /**
     * Données du gabarit mod_thesesynthesis/signature_table.
     *
     * @param \renderer_base $output
     * @return array isempty, emptymessage, table (voir \mod_these\output\table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        if (!$this->rows) {
            return ['isempty' => true, 'emptymessage' => $this->emptymessage, 'table' => false];
        }
        $head = [
            get_string('promotion', 'mod_thesesynthesis'),
            get_string('student', 'mod_these'),
            get_string('subjecttitle', 'mod_these'),
            get_string('signaturedocuments', 'mod_thesesynthesis'),
            get_string('signaturesince', 'mod_thesesynthesis'),
            '',
        ];
        $rows = [];
        foreach ($this->rows as $row) {
            $documents = [];
            foreach ($row->documents as $i => $document) {
                if ($i) {
                    $documents[] = parts::br();
                }
                $documents[] = parts::text($document);
            }
            $rows[] = [
                [parts::html(format_string($row->link->course->shortname ?: $row->link->course->fullname))],
                [parts::text($row->student ? fullname($row->student) : '-')],
                [$row->knowssubject ? parts::html(format_string($row->dossier->title))
                    : parts::em(get_string('confidentialtitle', 'mod_these'))],
                $documents,
                [parts::text($row->since ? userdate($row->since, get_string('strftimedatefullshort', 'langconfig')) : '-')],
                [parts::link($row->url, $row->label, 'btn btn-sm btn-primary')],
            ];
        }
        return [
            'isempty' => false,
            'emptymessage' => '',
            'table' => table_context::export('generaltable these-signatures', $head, $rows),
        ];
    }
}
