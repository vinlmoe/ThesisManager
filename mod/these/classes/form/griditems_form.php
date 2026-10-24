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

namespace mod_these\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Éléments de la grille de conformité de l'activité (DEVE), dans l'ordre : rubrique, libellé, élément
 * facultatif. Un libellé vidé supprime l'élément.
 *
 * customdata : count (nombre d'éléments existants).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class griditems_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $sections = [];
        foreach (these_grid_sections() as $section) {
            $sections[$section] = get_string('gridsection' . $section, 'mod_these');
        }
        $repeat = [
            $mform->createElement('static', 'griditemseparator', '', \html_writer::tag('hr', '')),
            $mform->createElement('hidden', 'itemkey'),
            $mform->createElement('select', 'section', get_string('griditemsection', 'mod_these'), $sections),
            $mform->createElement('textarea', 'label', get_string('griditemlabel', 'mod_these'), ['rows' => 2, 'cols' => 90]),
            $mform->createElement('advcheckbox', 'conditional', '', get_string('griditemconditional', 'mod_these')),
        ];
        $this->repeat_elements(
            $repeat,
            (int) $this->_customdata['count'] + 1,
            ['itemkey' => ['type' => PARAM_ALPHANUMEXT], 'label' => ['type' => PARAM_TEXT]],
            'itemrepeats',
            'itemaddfields',
            2,
            get_string('griditemadd', 'mod_these'),
            true
        );

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'resetbutton', get_string('griditemsreset', 'mod_these')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }
}
