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

use mod_these\local\settingstransfer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Transfert des paramètres de l'activité (DEVE) : export dans un fichier, ou reprise depuis une
 * autre activité ou depuis un fichier exporté.
 *
 * customdata : mode ('export' ou 'import'), sources (voir these_settings_transfer_sources()).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_transfer_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $import = $this->_customdata['mode'] === 'import';

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'mode', $this->_customdata['mode']);
        $mform->setType('mode', PARAM_ALPHA);

        if ($import) {
            $sources = $this->_customdata['sources'];
            $options = [];
            if ($sources) {
                $options['activity'] = get_string('transfersourceactivity', 'mod_these');
            }
            $options['file'] = get_string('transfersourcefile', 'mod_these');
            $mform->addElement('select', 'source', get_string('transfersource', 'mod_these'), $options);
            if ($sources) {
                $mform->addElement('select', 'sourceid', get_string('transferactivity', 'mod_these'), $sources);
                $mform->hideIf('sourceid', 'source', 'neq', 'activity');
            }
            $mform->addElement('filepicker', 'settingsfile', get_string('transferfile', 'mod_these'), null, [
                'accepted_types' => ['.json'],
                'maxfiles' => 1,
            ]);
            $mform->hideIf('settingsfile', 'source', 'neq', 'file');
        }

        // Les deux formulaires partagent la page : les cases de l'export sont préfixées pour que leurs
        // identifiants restent uniques.
        $prefix = $import ? '' : 'export';
        $groups = [];
        foreach (settingstransfer::settings_groups() as $group) {
            $label = get_string('transfergroup' . $group, 'mod_these');
            $groups[] = $mform->createElement('advcheckbox', $prefix . $group, '', $label);
            $mform->setDefault($prefix . $group, 1);
        }
        $mform->addGroup($groups, $prefix . 'groups', get_string('transfergroups', 'mod_these'), '<br>', false);
        $mform->addHelpButton($prefix . 'groups', 'transfergroups', 'mod_these');

        if ($import) {
            $mform->addElement('advcheckbox', 'confirmreplace', '', get_string('transferconfirmreplace', 'mod_these'));
            $this->add_action_buttons(true, get_string('transferimport', 'mod_these'));
        } else {
            $mform->addElement('submit', 'exportbutton', get_string('transferexport', 'mod_these'));
        }
    }

    /**
     * Au moins un groupe ; à l'import, une source lisible et la confirmation du remplacement.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $prefix = $this->_customdata['mode'] === 'import' ? '' : 'export';
        $chosen = array_filter(settingstransfer::settings_groups(), function ($group) use ($data, $prefix) {
            return !empty($data[$prefix . $group]);
        });
        if (!$chosen) {
            $errors[$prefix . 'groups'] = get_string('transfernogroup', 'mod_these');
        }
        if ($this->_customdata['mode'] !== 'import') {
            return $errors;
        }
        if (empty($data['confirmreplace'])) {
            $errors['confirmreplace'] = get_string('transferconfirmrequired', 'mod_these');
        }
        if (($data['source'] ?? '') === 'file') {
            $content = self::draft_content((int) ($data['settingsfile'] ?? 0));
            if ($content === null) {
                $errors['settingsfile'] = get_string('required');
            } else if (settingstransfer::parse_settings_file($content) === null) {
                $errors['settingsfile'] = get_string('transferbadfile', 'mod_these');
            }
        }
        $sourceid = (int) ($data['sourceid'] ?? 0);
        if (($data['source'] ?? '') === 'activity' && !array_key_exists($sourceid, $this->_customdata['sources'])) {
            $errors['sourceid'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Contenu du fichier déposé dans une zone brouillon. get_file_content() ne peut pas servir
     * pendant la validation : elle exige un formulaire déjà validé, et relancerait la validation.
     *
     * @param int $draftitemid
     * @return string|null
     */
    public static function draft_content($draftitemid) {
        global $USER;

        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_user::instance($USER->id)->id, 'user', 'draft', $draftitemid, 'id', false);
        $file = reset($files);
        return $file ? $file->get_content() : null;
    }
}
