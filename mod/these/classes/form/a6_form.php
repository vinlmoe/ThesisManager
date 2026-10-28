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
 * Validation de l'A6 d'un étudiant, saisie par la DEVE sur la page de l'A6 (voir
 * \mod_these\local\a6) : date de validation, ou pas encore validée, et école.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class a6_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        foreach (['id' => PARAM_INT, 'userid' => PARAM_INT, 'returnurl' => PARAM_LOCALURL] as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }
        $mform->addElement('static', 'studentname', get_string('student', 'mod_these'), s($this->_customdata['studentname']));
        $mform->addElement('advcheckbox', 'aga6notyet', '', get_string('aga6notyet', 'mod_these'));
        $mform->addElement('date_selector', 'aga6date', get_string('aga6date', 'mod_these'), ['optional' => true]);
        $mform->hideIf('aga6date', 'aga6notyet', 'checked');
        $mform->addElement('select', 'aga6school', get_string('bschool', 'mod_these'), these_school_options());
        $mform->hideIf('aga6school', 'aga6notyet', 'checked');

        $this->add_action_buttons();
    }

    /**
     * Date de validation, ou case « pas encore validée » cochée.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['aga6notyet']) && empty($data['aga6date'])) {
            $errors['aga6date'] = get_string('a6required', 'mod_these');
        }
        return $errors;
    }
}
