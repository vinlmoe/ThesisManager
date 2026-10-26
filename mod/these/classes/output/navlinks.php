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
use moodle_url;
use stdClass;

/**
 * Barre de navigation commune aux pages du personnel. Les liens dépendent des droits de l'utilisateur
 * (these_get_rights()), évalués ici.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navlinks implements \renderable, \templatable {
    /** @var stdClass */
    protected $cm;
    /** @var context */
    protected $context;

    /**
     * Constructeur.
     *
     * @param stdClass $cm
     * @param context $context
     */
    public function __construct(stdClass $cm, context $context) {
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Liens de la barre pour l'utilisateur courant.
     *
     * @return array libellé => moodle_url
     */
    public function get_links(): array {
        global $USER;

        $rights = these_get_rights($this->context, null, $USER->id);
        $id = ['id' => $this->cm->id];
        $links = [];
        if ($rights->viewall || has_capability('mod/these:direct', $this->context)) {
            $links[get_string('dashboard', 'mod_these')] = new moodle_url('/mod/these/dashboard.php', $id);
            $links[get_string('planning', 'mod_these')] = new moodle_url('/mod/these/planning.php', $id);
        }
        if ($rights->deve) {
            $links[get_string('createdossier', 'mod_these')] = new moodle_url('/mod/these/create.php', $id);
            $links[get_string('importdossiers', 'mod_these')] = new moodle_url('/mod/these/import.php', $id);
            $links[get_string('notifications', 'mod_these')] = new moodle_url('/mod/these/notifications.php', $id);
            $links[get_string('doctemplates', 'mod_these')] = new moodle_url('/mod/these/doctemplates.php', $id);
            $links[get_string('griditems', 'mod_these')] = new moodle_url('/mod/these/griditems.php', $id);
            $links[get_string('questions', 'mod_these')] = new moodle_url('/mod/these/questions.php', $id);
            $links[get_string('jurors', 'mod_these')] = new moodle_url('/mod/these/jurors.php', $id);
            $links[get_string('scopesettings', 'mod_these')] = new moodle_url('/mod/these/parameters.php', $id);
            $links[get_string('transfersettings', 'mod_these')] = new moodle_url('/mod/these/settingstransfer.php', $id);
        }
        if ($rights->viewall) {
            $links[get_string('export', 'mod_these')] = new moodle_url('/mod/these/export.php', $id);
        }
        $tosign = count(these_get_user_pending_signatures($USER));
        if ($tosign) {
            $links[get_string('mysignaturescount', 'mod_these', $tosign)] = new moodle_url('/mod/these/mysignatures.php');
        }
        return $links;
    }

    /**
     * Données du gabarit mod_these/navlinks.
     *
     * @param \renderer_base $output
     * @return array haslinks, buttons (contexte de mod_these/actions)
     */
    public function export_for_template(\renderer_base $output): array {
        $links = $this->get_links();
        return [
            'haslinks' => !empty($links),
            'buttons' => (new actions($links, 'btn btn-secondary mr-1 mb-1'))->export_for_template($output),
        ];
    }
}
