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

namespace mod_thesesynthesis\local;

use context;
use mod_these\local\backlinks;
use moodle_url;
use stdClass;

/**
 * Navigation de l'activité de suivi : accueil, cible des retours, liens vers ses pages.
 *
 * Les fonctions thesesynthesis_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Accueil de l'activité de suivi, cible du retour de ses autres pages (voir \mod_these\local\backlinks).
     *
     * @param stdClass $cm
     * @return stdClass {url, label}
     */
    public static function back_home(stdClass $cm) {
        return backlinks::target(
            new moodle_url('/mod/thesesynthesis/view.php', ['id' => $cm->id]),
            get_string('modulename', 'mod_thesesynthesis')
        );
    }

    /**
     * Liens vers les pages de l'activité, selon les droits de l'utilisateur, précédés du lien de retour.
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass|null $back Cible du retour (voir \mod_these\local\backlinks::target()) ; null : l'accueil de l'activité.
     * @return string HTML
     */
    public static function render_navlinks(stdClass $cm, context $context, ?stdClass $back = null) {
        global $PAGE;

        return backlinks::render($back ?? self::back_home($cm))
            . $PAGE->get_renderer('mod_thesesynthesis')->render(new \mod_thesesynthesis\output\navlinks($cm, $context));
    }
}
