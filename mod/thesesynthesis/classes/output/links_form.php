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
 * Formulaire des promotions suivies : une case par activité « Gestion des thèses » proposée à
 * l'utilisateur (celles où il voit tous les dossiers), cochée si elle est déjà suivie.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class links_form implements \renderable, \templatable {
    /** @var moodle_url */
    protected $action;
    /** @var array */
    protected $available;
    /** @var array */
    protected $linked;

    /**
     * Constructeur.
     *
     * @param moodle_url $action Adresse du formulaire.
     * @param array $available Activités proposées (voir thesesynthesis_get_available_activities()).
     * @param array $linked Activités suivies, par cmid (voir thesesynthesis_get_links()).
     */
    public function __construct(moodle_url $action, array $available, array $linked) {
        $this->action = $action;
        $this->available = $available;
        $this->linked = $linked;
    }

    /**
     * Nom formaté suivi, au besoin, d'un badge « caché ».
     *
     * @param string $name
     * @param bool $visible
     * @param string $hiddenstring Chaîne du badge.
     * @return array Fragments.
     */
    protected static function name_cell(string $name, bool $visible, string $hiddenstring): array {
        $cell = [parts::html(format_string($name))];
        if (!$visible) {
            $cell[] = parts::text(' ');
            $cell[] = parts::span(get_string($hiddenstring, 'mod_thesesynthesis'), 'badge badge-secondary');
        }
        return $cell;
    }

    /**
     * Données du gabarit mod_thesesynthesis/links_form.
     *
     * @param \renderer_base $output
     * @return array action, sesskey, table (voir \mod_these\output\table_context), savelabel
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        foreach ($this->available as $activity) {
            $cmid = (string) $activity->thesecmid;
            $rows[] = [
                [parts::checkbox('thesecmid[]', $cmid, isset($this->linked[(int) $cmid]), 'thesecmid_' . $cmid)],
                self::name_cell((string) $activity->coursename, (bool) $activity->coursevisible, 'hiddencourse'),
                self::name_cell((string) $activity->thesename, (bool) $activity->visible, 'hiddenactivity'),
            ];
        }
        $head = [get_string('linked', 'mod_thesesynthesis'), get_string('course'), get_string('activity')];
        return [
            'action' => $this->action->out(false),
            'sesskey' => sesskey(),
            'table' => table_context::export('generaltable', $head, $rows),
            'savelabel' => get_string('savechanges'),
        ];
    }
}
