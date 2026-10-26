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
 * Dépôt des documents de soutenance (phase 2, étape 1) : manuscrit, autres documents, date et
 * lieu proposés, composition du jury, puis les données des documents de l'établissement qui
 * reviennent à l'étudiant : identité et cursus (fiche signalétique), bordereau de thèse (ABES),
 * conclusions et contrat de diffusion électronique. La soumission signe le bordereau et le
 * contrat. Le dépôt peut être préparé en plusieurs fois ; tout n'est exigé qu'à la soumission.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defense_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'dossierid');
        $mform->setType('dossierid', PARAM_INT);

        $mform->addElement('header', 'documentsheader', get_string('defensedocuments', 'mod_these'));
        $mform->setExpanded('documentsheader');
        $mform->addElement(
            'filemanager',
            'manuscript',
            get_string('manuscript', 'mod_these'),
            null,
            these_filemanager_options('manuscript')
        );
        $mform->addHelpButton('manuscript', 'manuscript', 'mod_these');
        if (!empty($this->_customdata['teachinglisturl'])) {
            // Page à inclure dans le manuscrit (« Présence de la liste à jour du corps enseignant »).
            $mform->addElement('static', 'teachinglist', '', get_string('teachinglistdefense', 'mod_these') . ' '
                . \html_writer::link($this->_customdata['teachinglisturl'], get_string('teachinglist', 'mod_these')));
            $mform->addHelpButton('teachinglist', 'teachinglist', 'mod_these');
        }
        $mform->addElement(
            'filemanager',
            'defensedocs',
            get_string('defensedocs', 'mod_these'),
            null,
            these_filemanager_options('defensedocs')
        );
        $mform->addHelpButton('defensedocs', 'defensedocs', 'mod_these');

        $mform->addElement('header', 'dateheader', get_string('defenseproposal', 'mod_these'));
        $mform->setExpanded('dateheader');
        // Pas de date préremplie (l'heure courante serait refusée) : l'étudiant l'active et la choisit.
        $mform->addElement('date_time_selector', 'proposeddate', get_string('proposeddate', 'mod_these'), ['optional' => true]);
        \mod_these\local\scopesettings::add_location_field($mform, $this->_customdata['rooms'] ?? []);
        $mform->addElement('advcheckbox', 'bvisio', '', get_string('visio', 'mod_these'));

        these_add_jury_fields($this, $mform, $this->_customdata['jurycount'] ?? 0, false, $this->_customdata['jurorids'] ?? []);

        // Identité et coordonnées : communes au bordereau ABES, à la fiche signalétique et au
        // contrat de diffusion.
        $mform->addElement('header', 'identityheader', get_string('identity', 'mod_these'));
        $mform->setExpanded('identityheader');
        $mform->addElement('static', 'identityhelp', '', get_string('identityhelp', 'mod_these'));
        foreach (['birthname', 'firstnames', 'usagename', 'publicationname'] as $field) {
            $mform->addElement('text', 'b' . $field, get_string('b' . $field, 'mod_these'), ['size' => 48]);
            $mform->setType('b' . $field, PARAM_TEXT);
        }
        $mform->addElement('date_selector', 'bbirthdate', get_string('bbirthdate', 'mod_these'), [
            'startyear' => 1950, 'stopyear' => (int) date('Y'),
        ]);
        $fields = ['birthplace', 'birthdept', 'nationality', 'street', 'postcode', 'city', 'country', 'phone', 'personalemail'];
        foreach ($fields as $field) {
            $mform->addElement('text', 'b' . $field, get_string('b' . $field, 'mod_these'), ['size' => 48]);
            $mform->setType('b' . $field, $field === 'personalemail' ? PARAM_RAW_TRIMMED : PARAM_TEXT);
        }
        $mform->addHelpButton('bpersonalemail', 'bpersonalemail', 'mod_these');
        $mform->addElement('advcheckbox', 'bemailconsent', '', get_string('bemailconsent', 'mod_these'));

        // Cursus (fiche signalétique).
        $mform->addElement('header', 'cursusheader', get_string('cursus', 'mod_these'));
        $mform->addElement('date_selector', 'bdefvdate', get_string('bdefvdate', 'mod_these'), ['optional' => true]);
        $mform->addElement('select', 'bdefvschool', get_string('bschool', 'mod_these'), these_school_options());
        $mform->addElement('advcheckbox', 'ba6notyet', '', get_string('ba6notyet', 'mod_these'));
        $mform->addElement('date_selector', 'ba6date', get_string('ba6date', 'mod_these'), ['optional' => true]);
        $mform->hideIf('ba6date', 'ba6notyet', 'checked');
        $mform->addElement('select', 'ba6school', get_string('bschool', 'mod_these'), these_school_options());
        $mform->hideIf('ba6school', 'ba6notyet', 'checked');

        // Bordereau de thèse (ABES).
        $mform->addElement('header', 'bordereauheader', get_string('docbordereau', 'mod_these'));
        $mform->addElement('textarea', 'titleen', get_string('subjecttitleen', 'mod_these'), ['rows' => 2, 'cols' => 80]);
        $mform->setType('titleen', PARAM_TEXT);
        foreach (['nbvolumes', 'nbpages', 'nbrefs'] as $field) {
            $mform->addElement('text', 'b' . $field, get_string('b' . $field, 'mod_these'), ['size' => 6]);
            $mform->setType('b' . $field, PARAM_INT);
        }
        $mform->setDefault('bnbvolumes', 1);
        foreach (['summaryfr', 'summaryen'] as $field) {
            $mform->addElement('textarea', 'b' . $field, get_string('b' . $field, 'mod_these'), ['rows' => 8, 'cols' => 80]);
            $mform->setType('b' . $field, PARAM_TEXT);
            $mform->addHelpButton('b' . $field, 'bsummary', 'mod_these');
        }
        $keywords = [];
        for ($i = 0; $i < THESE_BORDEREAU_KEYWORDS; $i++) {
            $keywords[] = $mform->createElement('text', "bkeyword[$i]", '', ['size' => 22]);
        }
        $mform->addGroup($keywords, 'bkeywordgroup', get_string('bkeywords', 'mod_these'), ' ', false);
        for ($i = 0; $i < THESE_BORDEREAU_KEYWORDS; $i++) {
            $mform->setType("bkeyword[$i]", PARAM_TEXT);
        }
        $mform->addHelpButton('bkeywordgroup', 'bkeywords', 'mod_these');

        // Grille de conformité : remplie et attestée par l'étudiant, puis vérifiée et signée par le
        // directeur à la validation (formulaire prérempli).
        $mform->addElement('header', 'gridheader', get_string('docgrid', 'mod_these'));
        $mform->addElement('static', 'gridhelp', '', get_string('gridstudenthelp', 'mod_these'));
        $mform->addElement('text', 'ethicsnumber', get_string('ethicsnumber', 'mod_these'), ['size' => 20]);
        $mform->setType('ethicsnumber', PARAM_TEXT);
        foreach (these_get_grid_items(these_settings_scope($this->_customdata['these'])) as $section => $items) {
            $mform->addElement(
                'static',
                'gridsection' . $section,
                '',
                \html_writer::tag('strong', get_string('gridsection' . $section, 'mod_these'))
            );
            foreach ($items as $key => $item) {
                $label = format_string($item->label) . ($item->conditional
                    ? ' ' . \html_writer::span(get_string('gridconditional', 'mod_these'), 'text-muted') : '');
                $mform->addElement('advcheckbox', 'grid_' . $key, '', $label);
            }
        }
        $mform->addElement('advcheckbox', 'gridconfirm', '', get_string('gridstudentconfirm', 'mod_these'));

        // Conclusions, signées ensuite par le directeur, le président du jury et les autorités.
        $mform->addElement('header', 'conclusionsheader', get_string('docconclusions', 'mod_these'));
        $mform->addElement('textarea', 'conclusions', get_string('conclusionstext', 'mod_these'), [
            'rows' => 14,
            'cols' => 80,
            'maxlength' => THESE_CONCLUSIONS_MAX,
        ]);
        $mform->setType('conclusions', PARAM_TEXT);
        $mform->addHelpButton('conclusions', 'conclusionstext', 'mod_these');

        // Contrat de diffusion électronique.
        $mform->addElement('header', 'contractheader', get_string('doccontract', 'mod_these'));
        $mform->addElement('static', 'contracthelp', '', get_string('contracthelp', 'mod_these'));
        $mform->addElement('date_selector', 'bconfidentialuntil', get_string('bconfidentialuntil', 'mod_these'), [
            'optional' => true, 'startyear' => (int) date('Y'), 'stopyear' => (int) date('Y') + 30,
        ]);
        $mform->addHelpButton('bconfidentialuntil', 'bconfidentialuntil', 'mod_these');
        // Embargo : diffusion différée de THESE_EMBARGO_YEARS ans après la soutenance, sur demande motivée.
        $mform->addElement('advcheckbox', 'bembargo', '', get_string('bembargo', 'mod_these', THESE_EMBARGO_YEARS));
        $mform->addHelpButton('bembargo', 'embargo', 'mod_these');
        $mform->addElement('textarea', 'bembargoreason', get_string('bembargoreason', 'mod_these'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('bembargoreason', PARAM_TEXT);
        $mform->hideIf('bembargoreason', 'bembargo', 'notchecked');
        $mform->addElement('select', 'bdiffusion', get_string('bdiffusion', 'mod_these'), [
            '' => get_string('choosedots'),
            'internet' => get_string('diffusioninternet', 'mod_these'),
            'restricted' => get_string('diffusionrestricted', 'mod_these'),
        ]);
        $mform->addHelpButton('bdiffusion', 'bdiffusion', 'mod_these');

        // Questionnaire préalable au jury : ses questions obligatoires conditionnent le dépôt.
        if (!empty($this->_customdata['questionnaireurl'])) {
            $mform->addElement('header', 'questionnaireheader', get_string('questionnaire', 'mod_these'));
            $mform->setExpanded('questionnaireheader');
            $missing = $this->_customdata['questionnairemissing'] ?? [];
            $status = $missing
                ? get_string('questionnairemissing', 'mod_these', implode(' ; ', array_map('format_string', $missing)))
                : get_string('questionnairecomplete', 'mod_these');
            $mform->addElement('static', 'questionnairestatus', '', $status . ' ' . \html_writer::link(
                $this->_customdata['questionnaireurl'],
                get_string('questionnairefill', 'mod_these')
            ));
        }

        these_add_signature_fields($mform, $this->_customdata['signername'], get_string('signdefenseintro', 'mod_these'));

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'submitdefense', get_string($this->_customdata['submitlabel'], 'mod_these'));
        $buttons[] = $mform->createElement('submit', 'savedraft', get_string('savedraft', 'mod_these'));
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * À la soumission : manuscrit déposé, date à venir, lieu renseigné, jury valide et questionnaire
     * préalable au jury rempli.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['submitdefense'])) {
            return $errors;
        }
        if (these_count_draft_files($data['manuscript']) < 1) {
            $errors['manuscript'] = get_string('manuscriptrequired', 'mod_these');
        }
        if (empty($data['proposeddate'])) {
            $errors['proposeddate'] = get_string('proposeddaterequired', 'mod_these');
        } else if ((int) $data['proposeddate'] <= time()) {
            $errors['proposeddate'] = get_string('datemustbefuture', 'mod_these');
        }
        if (trim((string) $data['defenselocation']) === '') {
            $errors['defenselocation'] = get_string('required');
        }
        // Grille de conformité : éléments obligatoires cochés et attestation de l'étudiant.
        foreach (these_get_grid_items(these_settings_scope($this->_customdata['these'])) as $items) {
            foreach ($items as $key => $item) {
                if (!$item->conditional && empty($data['grid_' . $key])) {
                    $errors['grid_' . $key] = get_string('gridrequired', 'mod_these');
                }
            }
        }
        if (empty($data['gridconfirm'])) {
            $errors['gridconfirm'] = get_string('gridstudentconfirmrequired', 'mod_these');
        }
        $juryerror = these_validate_jury(these_extract_submitted_jury($data), $this->_customdata['minjury']);
        if ($juryerror !== null) {
            $errors['juryhelp'] = get_string('juryproblem', 'mod_these', $juryerror);
        }
        $required = ['bbirthname', 'bfirstnames', 'bnationality', 'bstreet', 'bpostcode', 'bcity', 'bcountry', 'bpersonalemail',
            'bnbpages', 'bnbrefs', 'bsummaryfr', 'titleen', 'conclusions', 'bdiffusion'];
        foreach ($required as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $isnumber = in_array($field, ['bnbpages', 'bnbrefs'], true);
            if ($value === '' || ($isnumber && (int) $value <= 0)) {
                $errors[$field] = get_string('required');
            }
        }
        if (!isset($errors['conclusions'])) {
            $conclusionserror = these_conclusions_error(
                $this->_customdata['these'],
                $this->_customdata['context'] ?? null,
                (string) $data['conclusions']
            );
            if ($conclusionserror !== null) {
                $errors['conclusions'] = $conclusionserror;
            }
        }
        if (!empty($data['bembargo']) && trim((string) ($data['bembargoreason'] ?? '')) === '') {
            $errors['bembargoreason'] = get_string('bembargoreasonrequired', 'mod_these');
        }
        if (!isset($errors['bpersonalemail']) && !validate_email($data['bpersonalemail'])) {
            $errors['bpersonalemail'] = get_string('invalidemail');
        }
        foreach (['bsummaryfr', 'bsummaryen'] as $field) {
            if (\core_text::strlen((string) ($data[$field] ?? '')) > THESE_BORDEREAU_SUMMARY_MAX) {
                $errors[$field] = get_string('summarytoolong', 'mod_these', THESE_BORDEREAU_SUMMARY_MAX);
            }
        }
        if (!array_filter(array_map('trim', (array) ($data['bkeyword'] ?? [])), 'strlen')) {
            $errors['bkeywordgroup'] = get_string('required');
        }
        if (empty($data['bdefvdate'])) {
            $errors['bdefvdate'] = get_string('required');
        }
        if (empty($data['ba6notyet']) && empty($data['ba6date'])) {
            $errors['ba6date'] = get_string('a6required', 'mod_these');
        }
        if (!empty($this->_customdata['questionnairemissing'])) {
            $errors['questionnairestatus'] = get_string('questionnairerequired', 'mod_these');
        }
        return array_merge($errors, these_signature_errors($data));
    }

    /**
     * Avancement du dépôt, rubrique par rubrique : les contrôles de la soumission (voir validation())
     * appliqués aux données enregistrées. La signature et l'attestation de la grille, données au
     * moment de soumettre, ne comptent pas.
     *
     * @param array $data Données du formulaire, telles que passées à set_data().
     * @return array[] Rubriques dans l'ordre du formulaire : name (en-tête), label, missing (champs à compléter).
     */
    public function section_progress(array $data): array {
        $errors = $this->validation(['submitdefense' => 1] + $data, []);
        unset($errors['gridconfirm']);
        $sections = [];
        $header = null;
        foreach ($this->_form->_elements as $element) {
            $name = $element->getName();
            if ($element->getType() === 'header') {
                $header = $name === 'signatureheader' ? null : $name;
                if ($header !== null) {
                    $sections[$header] = ['name' => $header, 'label' => strip_tags($element->_text), 'missing' => 0];
                }
            } else if ($header !== null && isset($errors[$name])) {
                $sections[$header]['missing']++;
            }
        }
        return array_values($sections);
    }

    /**
     * Déplie les rubriques à compléter et replie les rubriques complètes (voir section_progress()).
     *
     * @param array[] $sections Voir section_progress().
     * @return void
     */
    public function expand_incomplete(array $sections) {
        foreach ($sections as $section) {
            $this->_form->setExpanded($section['name'], $section['missing'] > 0);
        }
    }
}
