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

/**
 * Tableau du jury d'un dossier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jury implements \renderable, \templatable {
    /** @var array */
    protected $members;
    /** @var bool */
    protected $showemail;
    /** @var bool */
    protected $staff;

    /**
     * Constructeur.
     *
     * @param array $members Membres (voir these_get_jury()).
     * @param bool $showemail Affiche les adresses (droit évalué par l'appelant).
     * @param bool $staff Vue de la DEVE : éligibilité et notification des membres.
     */
    public function __construct(array $members, $showemail = true, $staff = false) {
        $this->members = $members;
        $this->showemail = (bool) $showemail;
        $this->staff = (bool) $staff;
    }

    /**
     * Cellule d'éligibilité d'un membre (vue de la DEVE).
     *
     * @param \stdClass $member
     * @return array Fragments.
     */
    protected function eligibility(\stdClass $member): array {
        if (!\mod_these\local\jury::is_voting($member)) {
            return [parts::text('-')];
        }
        if (!empty($member->eligible)) {
            $cell = [parts::badge(get_string('juryeligibilityvalidated', 'mod_these'), 'badge badge-success bg-success')];
        } else {
            $state = these_jury_eligibility($member);
            $cell = [parts::badge(
                get_string('juryeligibility' . $state, 'mod_these'),
                'badge ' . ($state === 'ko' ? 'badge-danger bg-danger' : 'badge-warning bg-warning text-dark')
            )];
        }
        if (!empty($member->notifiedtime)) {
            $cell[] = parts::muted(get_string('jurynotifiedon', 'mod_these', userdate(
                $member->notifiedtime,
                get_string('strftimedatetimeshort', 'langconfig')
            )));
        }
        return $cell;
    }

    /**
     * Données du gabarit mod_these/jury.
     *
     * @param \renderer_base $output
     * @return array hasmembers, heading, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        if (empty($this->members)) {
            return ['hasmembers' => false, 'heading' => '', 'table' => false];
        }
        $roles = these_jury_role_options();
        $head = [get_string('juryrole', 'mod_these'), get_string('juryname', 'mod_these'),
            get_string('jurytitle', 'mod_these'), get_string('jurystatus', 'mod_these'),
            get_string('juryinstitution', 'mod_these')];
        if ($this->showemail) {
            $head[] = get_string('juryemail', 'mod_these');
        }
        if ($this->staff) {
            $head[] = get_string('juryeligiblecheck', 'mod_these');
        }
        $rows = [];
        foreach ($this->members as $member) {
            $row = [
                [parts::text($roles[$member->juryrole] ?? $member->juryrole)],
                [parts::text($member->fullname)],
                [parts::text($member->jurytitle)],
                [parts::text(\mod_these\local\jurors::status_label($member->jurystatus ?? ''))],
                [parts::text($member->institution)],
            ];
            if ($this->showemail) {
                $row[] = [parts::text($member->email)];
            }
            if ($this->staff) {
                $row[] = $this->eligibility($member);
            }
            $rows[] = $row;
        }
        return [
            'hasmembers' => true,
            'heading' => get_string('jury', 'mod_these'),
            'table' => table_context::export('generaltable', $head, $rows),
        ];
    }
}
