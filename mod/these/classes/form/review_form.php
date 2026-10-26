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
 * - librarysubject : référence de la notice bibliographique ;
 * - directordefense : avis du rapport du directeur, grille de conformité, agrément scientifique ;
 * - devedefense : numéro d'enregistrement DEVE (attribué après le dépôt de la thèse), date et lieu
 *   retenus, jury (modifiables par la DEVE), agrément administratif.
 *
 * Aux étapes qui signent des documents (voir these_review_steps()), la validation exige la
 * signature de l'acteur, apposée sur tous les documents de l'étape.
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
            case 'librarysubject':
                $mform->addElement('text', 'libraryref', get_string('libraryref', 'mod_these'), ['size' => 40]);
                $mform->setType('libraryref', PARAM_TEXT);
                break;
            case 'directordefense':
                // Rapport du directeur, rédigé sur sa propre page (report.php) et signé ici.
                $report = $this->_customdata['report'] ?? null;
                $status = !$report || trim((string) $report->text) === ''
                    ? get_string('reportnotwritten', 'mod_these')
                    : get_string($report->favorable ? 'reportwrittenfavorable' : 'reportwrittennotfavorable', 'mod_these');
                $mform->addElement('static', 'reportstatus', get_string('reporttext', 'mod_these'), $status . ' '
                    . \html_writer::link($report->url ?? '#', get_string('writereport', 'mod_these')));
                // Grille de conformité du manuscrit.
                $mform->addElement('header', 'gridheader', get_string('docgrid', 'mod_these'));
                $mform->setExpanded('gridheader');
                if (!empty($this->_customdata['gridprefilled'])) {
                    $mform->addElement('static', 'gridprefilled', '', \html_writer::span(
                        get_string('gridprefilledbystudent', 'mod_these'),
                        'alert alert-info d-block'
                    ));
                }
                $mform->addElement('text', 'ethicsnumber', get_string('ethicsnumber', 'mod_these'), ['size' => 20]);
                $mform->setType('ethicsnumber', PARAM_TEXT);
                // Éléments de la grille de l'activité (modifiables par la DEVE) : la grille signée
                // n'est produite qu'à partir de ce formulaire rempli.
                foreach (these_get_grid_items($this->_customdata['scope']) as $section => $items) {
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
                // Agrément scientifique.
                $mform->addElement('header', 'scientificheader', get_string('agreementscientific', 'mod_these'));
                $mform->setExpanded('scientificheader');
                $mform->addElement('static', 'scientifichelp', '', get_string(
                    !empty($this->_customdata['externaldirector']) ? 'agreementmembertext' : 'agreementdirectortext',
                    'mod_these'
                ));
                if (empty($this->_customdata['externaldirector'])) {
                    $mform->addElement('text', 'directorfunction', get_string('directorfunction', 'mod_these'), ['size' => 48]);
                    $mform->setType('directorfunction', PARAM_TEXT);
                    $mform->addHelpButton('directorfunction', 'directorfunction', 'mod_these');
                }
                break;
            case 'devedefense':
                $mform->addElement('text', 'devenumber', get_string('devenumber', 'mod_these'), ['size' => 20]);
                $mform->setType('devenumber', PARAM_TEXT);
                $mform->addHelpButton('devenumber', 'devenumber', 'mod_these');
                $mform->addElement('date_time_selector', 'defensedate', get_string('defensedate', 'mod_these'));
                \mod_these\local\scopesettings::add_location_field(
                    $mform,
                    $this->_customdata['rooms'] ?? [],
                    $this->_customdata['roomconflicts'] ?? ''
                );
                $jurycount = $this->_customdata['jurycount'] ?? 0;
                these_add_jury_fields(
                    $this,
                    $mform,
                    $jurycount,
                    true,
                    $this->_customdata['jurorids'] ?? [],
                    $this->_customdata['juryconflicts'] ?? ''
                );
                $this->jury_readonly(array_values($this->_customdata['jury'] ?? []));
                $mform->addElement('static', 'juryeligiblehelp', '', get_string('juryeligiblehelp', 'mod_these'));
                if ($mform->elementExists('juryedit')) {
                    $mform->hideIf('juryeligiblehelp', 'juryedit', 'notchecked');
                }
                // Agrément administratif.
                $mform->addElement('header', 'adminheader', get_string('agreementadmin', 'mod_these'));
                $mform->setExpanded('adminheader');
                $mform->addElement('date_selector', 'agdefvdate', get_string('agdefvdate', 'mod_these'), ['optional' => true]);
                $mform->addElement('advcheckbox', 'agmobility', '', get_string('agmobility', 'mod_these'));
                $mform->addElement('advcheckbox', 'aga6notyet', '', get_string('aga6notyet', 'mod_these'));
                $mform->addElement('date_selector', 'aga6date', get_string('aga6date', 'mod_these'), ['optional' => true]);
                $mform->hideIf('aga6date', 'aga6notyet', 'checked');
                $mform->addElement('static', 'agauthorized', '', get_string('agauthorized', 'mod_these'));
                break;
            case 'libraryconformity':
                $mform->addElement('advcheckbox', 'conformitychecked', get_string('conformitychecked', 'mod_these'));
                break;
        }

        if (!empty($this->_customdata['signs'])) {
            these_add_signature_fields(
                $mform,
                $this->_customdata['signername'],
                get_string('signreviewintro', 'mod_these', $this->_customdata['signs'])
            );
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
        // Renvoi : bouton secondaire, d'alerte, pour ne pas le confondre avec la validation ; un
        // commentaire est obligatoire (voir validation()).
        $buttons[] = $mform->createElement(
            'submit',
            'returndossier',
            get_string('returndossier', 'mod_these'),
            null,
            false,
            ['customclassoverride' => 'btn-outline-danger']
        );
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
            case 'librarysubject':
                if (trim((string) $data['libraryref']) === '') {
                    $errors['libraryref'] = get_string('required');
                }
                break;
            case 'directordefense':
                $report = $this->_customdata['report'] ?? null;
                if (!$report || trim((string) $report->text) === '') {
                    $errors['reportstatus'] = get_string('directorreportrequired', 'mod_these');
                } else if (empty($report->favorable)) {
                    $errors['reportstatus'] = get_string('reportfavorablerequired', 'mod_these');
                }
                if (empty($this->_customdata['externaldirector']) && trim((string) ($data['directorfunction'] ?? '')) === '') {
                    $errors['directorfunction'] = get_string('required');
                }
                foreach (these_get_grid_items($this->_customdata['scope']) as $items) {
                    foreach ($items as $key => $item) {
                        if (!$item->conditional && empty($data['grid_' . $key])) {
                            $errors['grid_' . $key] = get_string('gridrequired', 'mod_these');
                        }
                    }
                }
                break;
            case 'devedefense':
                $number = trim((string) $data['devenumber']);
                if ($number === '') {
                    $errors['devenumber'] = get_string('required');
                } else if (these_devenumber_taken($this->_customdata['theseid'], $number, $data['dossierid'])) {
                    $errors['devenumber'] = get_string('devenumbertaken', 'mod_these');
                }
                if (empty($data['agdefvdate'])) {
                    $errors['agdefvdate'] = get_string('required');
                }
                if (empty($data['aga6notyet']) && empty($data['aga6date'])) {
                    $errors['aga6date'] = get_string('a6required', 'mod_these');
                }
                if (trim((string) $data['defenselocation']) === '') {
                    $errors['defenselocation'] = get_string('required');
                }
                if (empty($data['returndossier'])) {
                    $members = these_extract_submitted_jury($data, true);
                    $juryerror = these_validate_jury($members, $this->_customdata['minjury'])
                        ?? these_validate_jury_eligibility($members);
                    if ($juryerror !== null) {
                        $errors['juryhelp'] = get_string('juryproblem', 'mod_these', $juryerror);
                    }
                }
                break;
            case 'libraryconformity':
                if (empty($data['conformitychecked'])) {
                    $errors['conformitychecked'] = get_string('conformitycheckrequired', 'mod_these');
                }
                break;
        }
        if (!empty($this->_customdata['signs'])) {
            $errors = array_merge($errors, these_signature_errors($data));
        }
        return $errors;
    }

    /**
     * Jury proposé présenté en lecture à la DEVE : tableau des membres, et seulement les cases
     * d'éligibilité à vérifier (membres extérieurs). « Modifier la composition du jury » rouvre la saisie
     * complète. Sans jury enregistré, la saisie reste ouverte.
     *
     * @param array $members Jury enregistré (voir these_get_jury()), dans l'ordre des lignes du formulaire.
     * @return void
     */
    protected function jury_readonly(array $members) {
        $mform = $this->_form;
        if (!$members) {
            return;
        }
        $first = 'jurymemberlabel[0]';
        $summary = $mform->createElement('static', 'jurysummary', '', these_render_jury($members, true, false));
        $mform->insertElementBefore($summary, $first);
        $toggle = $mform->createElement('advcheckbox', 'juryedit', '', get_string('juryedit', 'mod_these'));
        $mform->insertElementBefore($toggle, $first);
        $mform->hideIf('jurysummary', 'juryedit', 'checked');

        $fields = ['jurymemberlabel', 'juryrole', 'jurorid', 'juryname', 'jurytitle', 'jurystatus', 'juryinstitution',
            'juryemail'];
        for ($i = 0; $mform->elementExists("juryrole[$i]"); $i++) {
            foreach ($fields as $field) {
                $mform->hideIf("{$field}[$i]", 'juryedit', 'notchecked');
            }
            $member = $members[$i] ?? null;
            if ($member) {
                // Case d'éligibilité nommée : elle reste seule visible en lecture.
                $mform->getElement("juryeligible[$i]")->setText(
                    get_string('juryeligiblecheckof', 'mod_these', s($member->fullname))
                );
            }
            if (!$member || these_jury_eligibility($member) === 'ok') {
                $mform->hideIf("juryeligible[$i]", 'juryedit', 'notchecked');
            }
        }
        if ($mform->elementExists('juryaddfields')) {
            $mform->hideIf('juryaddfields', 'juryedit', 'notchecked');
        }
    }
}
