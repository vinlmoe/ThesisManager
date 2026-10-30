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
 * Modification d'un dossier par la DEVE, à tout moment et sans changer son statut : corriger le
 * sujet, changer de directeur, rectifier un numéro, reporter une soutenance ou modifier le jury.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dossier_edit_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('header', 'subjectheader', get_string('subject', 'mod_these'));
        $mform->addElement('textarea', 'title', get_string('subjecttitle', 'mod_these'), ['rows' => 2, 'cols' => 80]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addElement('textarea', 'titleen', get_string('subjecttitleen', 'mod_these'), ['rows' => 2, 'cols' => 80]);
        $mform->setType('titleen', PARAM_TEXT);
        $mform->addElement('textarea', 'summary', get_string('subjectsummary', 'mod_these'), ['rows' => 8, 'cols' => 80]);
        $mform->setType('summary', PARAM_TEXT);
        $mform->addElement('text', 'keywords', get_string('subjectkeywords', 'mod_these'), ['size' => 80]);
        $mform->setType('keywords', PARAM_TEXT);
        // Directeur extérieur : le directeur du dossier dans le module est l'examinateur interne.
        $directorlabel = !empty($this->_customdata['externaldirector']) ? 'internalexaminer' : 'director';
        $mform->addElement('select', 'directorid', get_string($directorlabel, 'mod_these'), $this->_customdata['directors']);

        $mform->addElement('header', 'registrationheader', get_string('registration', 'mod_these'));
        $mform->addElement('text', 'devenumber', get_string('devenumber', 'mod_these'), ['size' => 20]);
        $mform->setType('devenumber', PARAM_TEXT);
        $mform->addElement('text', 'libraryref', get_string('libraryref', 'mod_these'), ['size' => 40]);
        $mform->setType('libraryref', PARAM_TEXT);
        // Validation de l'A6 : saisie sur sa page à part (voir \mod_these\local\a6), rappelée ici.
        \mod_these\local\a6::add_status_field(
            $mform,
            $this->_customdata['a6label'] ?? '',
            $this->_customdata['a6url'] ?? null
        );

        $mform->addElement('header', 'defenseheader', get_string('defense', 'mod_these'));
        $mform->addElement(
            'date_time_selector',
            'defensedate',
            get_string('defensedate', 'mod_these'),
            ['optional' => true]
        );
        \mod_these\local\scopesettings::add_location_field(
            $mform,
            $this->_customdata['rooms'] ?? [],
            $this->_customdata['roomconflicts'] ?? ''
        );

        these_add_jury_fields(
            $this,
            $mform,
            $this->_customdata['jurycount'] ?? 0,
            true,
            $this->_customdata['jurorids'] ?? [],
            $this->_customdata['juryconflicts'] ?? ''
        );
        these_jury_readonly($mform, array_values($this->_customdata['jury'] ?? []));

        // Page courte : sujet et enregistrements repliés, la soutenance (le plus souvent modifiée) ouverte.
        $mform->setExpanded('subjectheader', false);
        $mform->setExpanded('registrationheader', false);
        $mform->setExpanded('defenseheader', true);

        $this->add_action_buttons();
    }

    /**
     * Numéro DEVE unique dans l'activité ; jury valide dès qu'il est renseigné.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $number = trim((string) $data['devenumber']);
        if ($number !== '' && these_devenumber_taken($this->_customdata['theseid'], $number, $data['dossierid'])) {
            $errors['devenumber'] = get_string('devenumbertaken', 'mod_these');
        }
        $members = these_extract_submitted_jury($data);
        if ($members) {
            $juryerror = these_validate_jury($members, $this->_customdata['minjury']);
            if ($juryerror !== null) {
                $errors['juryhelp'] = get_string('juryproblem', 'mod_these', $juryerror);
            }
        }
        return $errors;
    }
}
