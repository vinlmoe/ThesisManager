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
use moodle_url;

/**
 * Jurys où siège l'utilisateur dans les promotions suivies, avec l'accès aux documents du jury.
 *
 * Un membre du jury connaît le sujet de la thèse (these_user_knows_subject()) : le titre n'est
 * masqué que si ce n'est pas le cas, ce qui n'arrive pas pour un jury trouvé par
 * thesesynthesis_get_juries().
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jury_table implements \renderable, \templatable {
    /** @var array */
    protected $juries;

    /**
     * Constructeur.
     *
     * @param array $juries Voir thesesynthesis_get_juries().
     */
    public function __construct(array $juries) {
        $this->juries = $juries;
    }

    /**
     * Cellule d'accès aux documents du jury : en attente, close, ou bouton.
     *
     * @param \stdClass $jury
     * @return array Fragment.
     */
    protected function access(\stdClass $jury): array {
        if (empty($jury->member->notifiedtime)) {
            return parts::span(get_string('juryaccesspending', 'mod_thesesynthesis'), 'text-muted');
        }
        if (!these_jury_access_open($jury->dossier, $jury->member)) {
            return parts::span(get_string('juryaccessclosed', 'mod_thesesynthesis'), 'text-muted');
        }
        return parts::link(
            new moodle_url('/mod/these/jury.php', ['member' => $jury->member->id]),
            get_string('juryaccess', 'mod_these'),
            'btn btn-sm btn-primary'
        );
    }

    /**
     * Données du gabarit mod_thesesynthesis/jury_table.
     *
     * @param \renderer_base $output
     * @return array isempty, emptymessage, table (voir \mod_these\output\table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        global $USER;

        if (!$this->juries) {
            return ['isempty' => true, 'emptymessage' => get_string('nojuries', 'mod_thesesynthesis'), 'table' => false];
        }
        $roles = these_jury_role_options();
        $head = [
            get_string('promotion', 'mod_thesesynthesis'),
            get_string('student', 'mod_these'),
            get_string('subjecttitle', 'mod_these'),
            get_string('juryrole', 'mod_these'),
            get_string('defensedate', 'mod_these'),
            get_string('defenselocation', 'mod_these'),
            get_string('juryaccessdocuments', 'mod_these'),
        ];
        $rows = [];
        foreach ($this->juries as $jury) {
            $date = (int) ($jury->dossier->defensedate ?: $jury->dossier->proposeddate);
            $knows = these_user_knows_subject($jury->dossier, $jury->link->context, $USER->id);
            $rows[] = [
                [parts::html(format_string($jury->link->course->shortname ?: $jury->link->course->fullname))],
                [parts::text($jury->student ? fullname($jury->student) : '-')],
                [$knows ? parts::html(format_string($jury->dossier->title))
                    : parts::em(get_string('confidentialtitle', 'mod_these'))],
                [parts::text($roles[$jury->member->juryrole] ?? $jury->member->juryrole)],
                [parts::text($date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-')],
                [parts::text($jury->dossier->defenselocation)],
                [$this->access($jury)],
            ];
        }
        return [
            'isempty' => false,
            'emptymessage' => '',
            'table' => table_context::export('generaltable', $head, $rows),
        ];
    }
}
