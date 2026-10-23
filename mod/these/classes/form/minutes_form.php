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
 * Dépôt du procès-verbal de soutenance par la DEVE (phase 2, étape 6) : le PV signé, le résultat
 * et la mention éventuelle. Le dépôt clôt le dossier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class minutes_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('filemanager', 'pv', get_string('minutes', 'mod_these'), null, these_filemanager_options('pv'));
        $mform->addRule('pv', null, 'required', null, 'client');
        $mform->addElement('select', 'defenseresult', get_string('defenseresult', 'mod_these'), these_result_options());
        $mform->addElement('text', 'mention', get_string('mention', 'mod_these'), ['size' => 64]);
        $mform->setType('mention', PARAM_TEXT);
        $mform->addHelpButton('mention', 'mention', 'mod_these');
        $mform->addElement('textarea', 'remark', get_string('reviewremark', 'mod_these'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('remark', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('closedossier', 'mod_these'));
    }

    /**
     * Le procès-verbal signé est obligatoire.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (these_count_draft_files($data['pv']) < 1) {
            $errors['pv'] = get_string('minutesrequired', 'mod_these');
        }
        return $errors;
    }
}
