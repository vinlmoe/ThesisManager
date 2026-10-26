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

use context;
use moodle_url;
use stdClass;

/**
 * Liens vers les pages de l'activité, selon les capacités de l'utilisateur (évaluées ici).
 *
 * @package   mod_thesesynthesis
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
     * Liens de la barre, selon les capacités.
     *
     * @return array libellé => moodle_url
     */
    public function get_links(): array {
        $id = ['id' => $this->cm->id];
        $links = [
            get_string('dashboard', 'mod_these') => new moodle_url('/mod/thesesynthesis/view.php', $id),
            get_string('planning', 'mod_these') => new moodle_url('/mod/thesesynthesis/planning.php', $id),
        ];
        if (has_capability('mod/thesesynthesis:search', $this->context)) {
            $links[get_string('searchtheses', 'mod_thesesynthesis')] = new moodle_url('/mod/thesesynthesis/search.php', $id);
        }
        if (has_capability('mod/thesesynthesis:managelinks', $this->context)) {
            $links[get_string('managelinks', 'mod_thesesynthesis')] = new moodle_url('/mod/thesesynthesis/administration.php', $id);
        }
        if (has_capability('mod/thesesynthesis:managesettings', $this->context)) {
            $links[get_string('doctemplates', 'mod_these')] = new moodle_url('/mod/thesesynthesis/doctemplates.php', $id);
            $links[get_string('griditems', 'mod_these')] = new moodle_url('/mod/thesesynthesis/griditems.php', $id);
            $links[get_string('questions', 'mod_these')] = new moodle_url('/mod/thesesynthesis/questions.php', $id);
            $links[get_string('scopesettings', 'mod_these')] = new moodle_url('/mod/thesesynthesis/parameters.php', $id);
        }
        if (has_capability('mod/thesesynthesis:managejurors', $this->context)) {
            $links[get_string('jurors', 'mod_these')] = new moodle_url('/mod/thesesynthesis/jurors.php', $id);
        }
        return $links;
    }

    /**
     * Données du gabarit mod_thesesynthesis/navlinks.
     *
     * @param \renderer_base $output
     * @return array buttons (contexte de mod_these/actions)
     */
    public function export_for_template(\renderer_base $output): array {
        $buttons = new \mod_these\output\actions($this->get_links(), 'btn btn-secondary mr-1 mb-1');
        return ['buttons' => $buttons->export_for_template($output)];
    }
}
