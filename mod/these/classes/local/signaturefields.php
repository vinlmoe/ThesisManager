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

namespace mod_these\local;

use html_writer;
use MoodleQuickForm;

/**
 * Bloc de signature des formulaires : zone de tracé, nom du signataire, certification, et son contrôle.
 *
 * Les fonctions these_*() correspondantes de locallib/signatures.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signaturefields {
    /**
     * Ajoute à un formulaire le bloc de signature : zone de tracé, nom du signataire et
     * certification. La zone de tracé alimente un champ caché « signaturedata » (module AMD
     * mod_these/signaturepad).
     *
     * Le nom du signataire n'est saisi que par la DEVE (signature sur place, par délégation) : pour
     * les autres, c'est le nom de leur compte Moodle (ou celui de l'invitation), affiché sans pouvoir
     * être modifié.
     *
     * @param MoodleQuickForm $mform
     * @param string $defaultname Nom du signataire (proposé s'il est modifiable).
     * @param string $intro Texte d'introduction (ce qui est signé).
     * @param bool $allowsaved Proposer de reprendre ou d'enregistrer la signature de l'utilisateur.
     * @param bool $editablename Nom saisi par le signataire (DEVE, ou nom inconnu).
     * @return void
     */
    public static function add_signature_fields(
        MoodleQuickForm $mform,
        $defaultname,
        $intro = '',
        $allowsaved = true,
        $editablename = false
    ) {
        global $PAGE, $USER;

        $mform->addElement('header', 'signatureheader', get_string('signature', 'mod_these'));
        $mform->setExpanded('signatureheader');
        if ($intro !== '') {
            $mform->addElement('static', 'signatureintro', '', $intro);
        }
        // Signature enregistrée de l'utilisateur connecté : reprise d'office, sans nouveau tracé.
        $cansave = $allowsaved && isloggedin() && !isguestuser();
        $saved = $cansave ? signatureagent::get_saved_signature($USER->id) : null;
        if ($saved !== null) {
            $mform->addElement('advcheckbox', 'usesavedsignature', '', get_string('signatureusesaved', 'mod_these')
                . html_writer::empty_tag('br') . html_writer::empty_tag('img', [
                    'src' => 'data:image/png;base64,' . base64_encode($saved), 'alt' => get_string('signaturesaved', 'mod_these'),
                    'class' => 'these-saved-signature border mt-1', 'style' => 'max-height:80px;background:#fff;',
                ]));
            $mform->setDefault('usesavedsignature', 1);
        }
        $pad = html_writer::div(
            html_writer::tag('canvas', '', ['width' => 520, 'height' => 170, 'class' => 'these-signature-canvas',
                'aria-label' => get_string('signaturedraw', 'mod_these')])
                . html_writer::div(
                    html_writer::tag('button', get_string('signatureclear', 'mod_these'), [
                        'type' => 'button', 'class' => 'btn btn-sm btn-secondary', 'data-action' => 'these-signature-clear',
                    ]),
                    'mt-1'
                ),
            'these-signature-pad'
        );
        $mform->addElement('static', 'signaturepad', get_string('signaturedraw', 'mod_these'), $pad);
        $mform->addElement('hidden', 'signaturedata', '');
        $mform->setType('signaturedata', PARAM_RAW);
        if ($cansave) {
            $mform->addElement('advcheckbox', 'savesignature', '', get_string('signaturesave', 'mod_these'));
            $mform->setDefault('savesignature', 1);
        }
        if ($saved !== null) {
            $mform->hideIf('signaturepad', 'usesavedsignature', 'checked');
            $mform->hideIf('savesignature', 'usesavedsignature', 'checked');
        }
        $mform->addElement('text', 'signername', get_string('signername', 'mod_these'), ['size' => 48]);
        $mform->setType('signername', PARAM_TEXT);
        $mform->setDefault('signername', $defaultname);
        if (!$editablename && trim((string) $defaultname) !== '') {
            // Nom imposé : affiché seulement, la valeur soumise est ignorée au profit de celle-ci.
            $mform->hardFreeze('signername');
        }
        $mform->addElement('advcheckbox', 'signatureconsent', '', get_string('signatureconsent', 'mod_these'));
        $PAGE->requires->js_call_amd('mod_these/signaturepad', 'init');
    }

    /**
     * Contrôle du bloc de signature (voir these_add_signature_fields()).
     *
     * @param array $data
     * @return array Erreurs, indexées par champ.
     */
    public static function signature_errors(array $data) {
        global $USER;

        $errors = [];
        $saved = !empty($data['usesavedsignature']) && signatureagent::get_saved_signature($USER->id) !== null;
        if (!$saved && signatures::decode_signature_image($data['signaturedata'] ?? '') === null) {
            $errors['signername'] = get_string('signaturemissing', 'mod_these');
        } else if (trim((string) ($data['signername'] ?? '')) === '') {
            $errors['signername'] = get_string('required');
        }
        if (empty($data['signatureconsent'])) {
            $errors['signatureconsent'] = get_string('signatureconsentrequired', 'mod_these');
        }
        return $errors;
    }
}
