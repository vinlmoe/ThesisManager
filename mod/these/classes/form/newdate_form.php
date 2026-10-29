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

/**
 * Nouvelle date de soutenance proposée par l'étudiant, à la demande de la DEVE (voir
 * \mod_these\local\daterequest).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class newdate_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('date_time_selector', 'proposeddate', get_string('newproposeddate', 'mod_these'));

        $this->add_action_buttons(true, get_string('newdatesubmit', 'mod_these'));
    }

    /**
     * Date à venir, différente de celle refusée.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $date = (int) ($data['proposeddate'] ?? 0);
        if ($date <= time()) {
            $errors['proposeddate'] = get_string('datemustbefuture', 'mod_these');
        } else if ($date === (int) ($this->_customdata['refuseddate'] ?? 0)) {
            $errors['proposeddate'] = get_string('newdatesame', 'mod_these');
        }
        return $errors;
    }
}
