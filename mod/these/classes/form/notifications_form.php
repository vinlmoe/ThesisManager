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
 * Personnalisation des courriels de l'activité par la DEVE : pour chacun, un sujet et un corps
 * facultatifs qui remplacent le texte par défaut, avec la liste des variables disponibles.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('static', 'notificationshelp', '', get_string('notificationshelp', 'mod_these'));

        foreach (these_get_email_definitions() as $key => $definition) {
            $mform->addElement('header', 'header_' . $key, get_string('emailkey' . $key, 'mod_these'));
            $vars = implode(' ', array_map(function ($var) {
                return '{{' . $var . '}}';
            }, $definition['vars']));
            $mform->addElement('static', 'vars_' . $key, get_string('emailvars', 'mod_these'), \html_writer::tag('code', $vars));
            $default = (object) array_combine($definition['vars'], array_map(function ($var) {
                return '{{' . $var . '}}';
            }, $definition['vars']));
            $mform->addElement(
                'static',
                'default_' . $key,
                get_string('emaildefault', 'mod_these'),
                \html_writer::tag('pre', s(get_string($definition['subjectstring'], 'mod_these', $default) . "\n\n"
                    . get_string($definition['bodystring'], 'mod_these', $default)), ['class' => 'these-email-default'])
            );
            $mform->addElement('text', 'subject_' . $key, get_string('emailsubject', 'mod_these'), ['size' => 80]);
            $mform->setType('subject_' . $key, PARAM_TEXT);
            $mform->addElement('textarea', 'body_' . $key, get_string('emailbody', 'mod_these'), ['rows' => 8, 'cols' => 80]);
            $mform->setType('body_' . $key, PARAM_RAW);
        }

        $this->add_action_buttons();
    }
}
