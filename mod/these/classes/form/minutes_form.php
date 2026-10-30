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

use mod_these\local\files;
use mod_these\local\workflow;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Dépôt du procès-verbal de soutenance par la DEVE (phase 2, étape 6) : le PV signé par le jury,
 * scanné, et les décisions cochées par le jury, reportées ici : résultat, mention, proposition
 * pour un prix de thèse, corrections demandées. Sans corrections, le dépôt clôt le dossier.
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

        $mform->addElement('filemanager', 'pv', get_string('minutesscan', 'mod_these'), null, files::filemanager_options('pv'));
        $mform->addHelpButton('pv', 'minutesscan', 'mod_these');
        $mform->addRule('pv', null, 'required', null, 'client');

        $mform->addElement('header', 'decisionsheader', get_string('jurydecisions', 'mod_these'));
        $mform->setExpanded('decisionsheader');
        $mform->addElement('static', 'decisionshelp', '', get_string('jurydecisions_help', 'mod_these'));
        $mform->addElement('select', 'defenseresult', get_string('defenseresult', 'mod_these'), workflow::result_options());
        $mform->addElement(
            'select',
            'mention',
            get_string('mention', 'mod_these'),
            ['' => get_string('choosedots')] + workflow::mention_options()
        );
        $mform->hideIf('mention', 'defenseresult', 'neq', THESE_RESULT_ADMITTED);
        $mform->addElement('advcheckbox', 'prizeproposal', '', get_string('prizeproposal', 'mod_these'));
        $mform->hideIf('prizeproposal', 'defenseresult', 'neq', THESE_RESULT_ADMITTED);
        $mform->addElement('advcheckbox', 'correctionsrequested', '', get_string('correctionsrequested', 'mod_these'));
        $mform->hideIf('correctionsrequested', 'defenseresult', 'neq', THESE_RESULT_ADMITTED);
        $mform->addElement('textarea', 'correctionsnote', get_string('correctionsnote', 'mod_these'), ['rows' => 5, 'cols' => 70]);
        $mform->setType('correctionsnote', PARAM_TEXT);
        $mform->addHelpButton('correctionsnote', 'correctionsnote', 'mod_these');
        $mform->hideIf('correctionsnote', 'correctionsrequested', 'notchecked');
        $mform->hideIf('correctionsnote', 'defenseresult', 'neq', THESE_RESULT_ADMITTED);

        $mform->addElement('textarea', 'remark', get_string('reviewremark', 'mod_these'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('remark', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('recordminutes', 'mod_these'));
    }

    /**
     * Le procès-verbal scanné est obligatoire ; une thèse admise reçoit une mention ; les
     * corrections demandées sont précisées.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (files::count_draft_files($data['pv']) < 1) {
            $errors['pv'] = get_string('minutesrequired', 'mod_these');
        }
        if ((int) $data['defenseresult'] === THESE_RESULT_ADMITTED) {
            if (!array_key_exists((string) $data['mention'], workflow::mention_options())) {
                $errors['mention'] = get_string('required');
            }
            if (!empty($data['correctionsrequested']) && trim((string) $data['correctionsnote']) === '') {
                $errors['correctionsnote'] = get_string('required');
            }
        }
        return $errors;
    }
}
