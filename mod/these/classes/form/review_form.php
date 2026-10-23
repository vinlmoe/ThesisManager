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
 * Formulaire commun aux six étapes de validation du circuit (voir these_review_steps()) : un
 * commentaire, un bouton de validation et un bouton de renvoi, plus les champs propres à
 * l'étape :
 *
 * - devesubject : numéro d'enregistrement DEVE ;
 * - librarysubject : référence de la notice bibliographique ;
 * - directordefense : rapport du directeur, obligatoire ;
 * - devedefense : date et lieu retenus, jury (modifiables par la DEVE).
 *
 * Les champs propres à l'étape ne sont exigés qu'à la validation, le commentaire qu'au renvoi :
 * formslib ne permettant pas de conditionner une règle au bouton cliqué, le contrôle est fait dans
 * validation(), comme dans les formulaires de revue de mod_stage.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $stepkey = $this->_customdata['stepkey'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('header', 'stepheader', get_string('review' . $stepkey, 'mod_these'));
        $mform->setExpanded('stepheader');
        $mform->addElement('static', 'stephelp', '', get_string('review' . $stepkey . '_help', 'mod_these'));

        switch ($stepkey) {
            case 'devesubject':
                $mform->addElement('text', 'devenumber', get_string('devenumber', 'mod_these'), ['size' => 20]);
                $mform->setType('devenumber', PARAM_TEXT);
                $mform->addHelpButton('devenumber', 'devenumber', 'mod_these');
                break;
            case 'librarysubject':
                $mform->addElement('text', 'libraryref', get_string('libraryref', 'mod_these'), ['size' => 40]);
                $mform->setType('libraryref', PARAM_TEXT);
                break;
            case 'directordefense':
                $mform->addElement(
                    'filemanager',
                    'directorreport',
                    get_string('directorreport', 'mod_these'),
                    null,
                    these_filemanager_options('directorreport')
                );
                $mform->addHelpButton('directorreport', 'directorreport', 'mod_these');
                break;
            case 'devedefense':
                $mform->addElement('date_time_selector', 'defensedate', get_string('defensedate', 'mod_these'));
                $mform->addElement('text', 'defenselocation', get_string('defenselocation', 'mod_these'), ['size' => 64]);
                $mform->setType('defenselocation', PARAM_TEXT);
                these_add_jury_fields($this, $mform, $this->_customdata['jurycount'] ?? 0);
                break;
            case 'libraryconformity':
                $mform->addElement('advcheckbox', 'conformitychecked', get_string('conformitychecked', 'mod_these'));
                break;
        }

        $mform->addElement('header', 'remarkheader', get_string('reviewremark', 'mod_these'));
        $mform->setExpanded('remarkheader');
        $mform->addElement('textarea', 'remark', get_string('reviewremark', 'mod_these'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('remark', PARAM_TEXT);
        $mform->addHelpButton('remark', 'reviewremark', 'mod_these');

        $returnto = $this->_customdata['returnto'];
        if (count($returnto) > 1) {
            $options = [];
            foreach ($returnto as $target) {
                $options[$target] = these_actor_label($target);
            }
            $mform->addElement('select', 'returntarget', get_string('returntarget', 'mod_these'), $options);
        } else {
            $mform->addElement('hidden', 'returntarget', reset($returnto));
            $mform->setType('returntarget', PARAM_ALPHA);
        }

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'validatestep', get_string('validate' . $stepkey, 'mod_these'));
        $buttons[] = $mform->createElement('submit', 'returndossier', get_string('returndossier', 'mod_these'));
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Contrôles propres à l'étape à la validation ; motif obligatoire au renvoi.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['returndossier'])) {
            if (trim((string) $data['remark']) === '') {
                $errors['remark'] = get_string('returncommentrequired', 'mod_these');
            }
            if (!in_array($data['returntarget'] ?? '', $this->_customdata['returnto'], true)) {
                $errors['returntarget'] = get_string('required');
            }
            return $errors;
        }
        if (empty($data['validatestep'])) {
            return $errors;
        }

        switch ($this->_customdata['stepkey']) {
            case 'devesubject':
                $number = trim((string) $data['devenumber']);
                if ($number === '') {
                    $errors['devenumber'] = get_string('required');
                } else if (these_devenumber_taken($this->_customdata['theseid'], $number, $data['dossierid'])) {
                    $errors['devenumber'] = get_string('devenumbertaken', 'mod_these');
                }
                break;
            case 'librarysubject':
                if (trim((string) $data['libraryref']) === '') {
                    $errors['libraryref'] = get_string('required');
                }
                break;
            case 'directordefense':
                if (these_count_draft_files($data['directorreport']) < 1) {
                    $errors['directorreport'] = get_string('directorreportrequired', 'mod_these');
                }
                break;
            case 'devedefense':
                if (trim((string) $data['defenselocation']) === '') {
                    $errors['defenselocation'] = get_string('required');
                }
                $juryerror = these_validate_jury(these_extract_submitted_jury($data), $this->_customdata['minjury']);
                if ($juryerror !== null) {
                    $errors['juryname[0]'] = $juryerror;
                }
                break;
            case 'libraryconformity':
                if (empty($data['conformitychecked'])) {
                    $errors['conformitychecked'] = get_string('conformitycheckrequired', 'mod_these');
                }
                break;
        }
        return $errors;
    }
}
