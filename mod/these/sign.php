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

/**
 * Signature des documents qui attendent un signataire sans compte Moodle (président du jury,
 * direction générale, CCEM, présidence de l'université, directeur de thèse extérieur).
 *
 * Deux accès :
 * - par le lien à jeton reçu par courriel (voir these_request_signature()), sans authentification
 *   Moodle : le jeton, tiré au hasard et effacé dès la signature, fait foi ;
 * - sur place, par la DEVE connectée (id, dossierid, slot), pour un signataire sans adresse ou qui
 *   signe en sa présence.
 *
 * Dans les deux cas, le signataire consulte chaque document en PDF avant de signer, et sa
 * signature est apposée sur tous les documents qui l'attendent dans le dossier.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Pas de require_login() pour l'accès par jeton, et c'est volontaire, comme pour l'évaluation du
// maître de stage dans mod_stage : le signataire n'a pas de compte Moodle. Le contrôle d'accès est
// assuré par le jeton (48 caractères aléatoires, effacé à la signature), vérifié juste en dessous.
// L'accès sur place exige en revanche la connexion et les droits de la DEVE.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\output;
use mod_these\output\parts;

$token = optional_param('token', '', PARAM_ALPHANUM);
$preview = optional_param('preview', '', PARAM_ALPHA);

if ($token !== '') {
    $pending = these_get_signatures_by_token($token);
    if (!$pending) {
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/mod/these/sign.php', ['token' => $token]));
        $PAGE->set_pagelayout('embedded');
        $PAGE->set_title(get_string('signpagetitle', 'mod_these'));
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('signinvalidtoken', 'mod_these'), 'info');
        echo $OUTPUT->footer();
        exit;
    }
    $dossier = $DB->get_record('these_dossier', ['id' => $pending[0]->dossierid], '*', MUST_EXIST);
    $slot = $pending[0]->slot;
    $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('these', $these->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $pageparams = ['token' => $token];
    $inperson = false;
} else {
    $id = required_param('id', PARAM_INT);
    $dossierid = required_param('dossierid', PARAM_INT);
    $slot = required_param('slot', PARAM_ALPHA);
    $cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    these_require_service($context, 'deve');
    $dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
    if (!in_array($slot, these_external_slots(), true)) {
        throw new moodle_exception('invalidparameter', 'debug');
    }
    $pageparams = ['id' => $cm->id, 'dossierid' => $dossier->id, 'slot' => $slot];
    $inperson = true;
}

// Signatures devenues caduques (contenu ou mise en forme modifiés depuis) : invalidées avant de
// présenter les documents.
these_refresh_signatures($context, $dossier);

// Documents à signer : ceux qui attendent ce signataire et dont les données sont saisies.
$doctypes = array_values(array_filter(
    these_pending_documents_for_slot($dossier, $slot, $token !== '' ? array_column($pending, 'doctype') : null),
    function ($doctype) use ($dossier) {
        return these_document_available($dossier, $doctype);
    }
));

if ($preview !== '' && in_array($preview, $doctypes, true)) {
    send_file(
        these_signed_document_pdf($these, $context, $dossier, $preview),
        these_document_filename($preview),
        0,
        0,
        true,
        false,
        'application/pdf'
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/these/sign.php', $pageparams));
$PAGE->set_pagelayout($inperson ? 'incourse' : 'embedded');
$PAGE->set_title(get_string('signpagetitle', 'mod_these'));
$PAGE->set_heading(format_string($these->name));

$signer = these_external_signers($dossier)[$slot];
$dossierurl = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$renderer = $PAGE->get_renderer('mod_these');
$links = array_map(function ($doctype) use ($pageparams) {
    return [parts::link(
        new moodle_url('/mod/these/sign.php', $pageparams + ['preview' => $doctype]),
        these_document_label($doctype),
        '',
        '_blank'
    )];
}, $doctypes);

// Signataire connecté avec le compte rattaché à l'adresse invitée lors de la demande (membre du jury
// ayant un compte Moodle, par exemple) : il peut reprendre ou enregistrer sa signature. Jamais pour
// une signature sur place.
$signeruserid = $token !== '' ? (int) $pending[0]->userid : 0;
$allowsaved = !$inperson && $signeruserid > 0 && isloggedin() && !isguestuser() && (int) $USER->id === $signeruserid;
$mform = new \mod_these\form\sign_form($PAGE->url, [
    'documents' => $links ? $renderer->render(new output\bullet_list($links)) : '-',
    'signername' => $signer['name'] !== '' ? $signer['name'] : ($allowsaved ? fullname($USER) : ''),
    'allowsaved' => $allowsaved,
]);
$mform->set_data($pageparams);

if ($doctypes && ($data = $mform->get_data())) {
    if (!$allowsaved) {
        unset($data->usesavedsignature, $data->savesignature);
    }
    $signature = these_signature_from_form($data);
    foreach ($doctypes as $doctype) {
        these_sign_document(
            $context,
            $dossier,
            $doctype,
            $slot,
            $allowsaved ? $USER->id : 0,
            $signature->name,
            $signature->png,
            $signer['email'] !== '' ? $signer['email'] : $pending[0]->signeremail ?? ''
        );
    }
    if ($inperson) {
        these_log(
            $dossier->id,
            $USER->id,
            'signedinperson',
            $dossier->status,
            $dossier->status,
            these_slot_label($slot) . ' — ' . $data->signername
        );
        redirect($dossierurl, get_string('signaturesrecorded', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($PAGE->url, get_string('signaturesrecorded', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('signpagetitle', 'mod_these'));
echo $OUTPUT->box(
    $renderer->render(new output\message(get_string('signpageintro', 'mod_these', (object) [
        'slot' => these_slot_label($slot),
        'student' => $student ? fullname($student) : '',
        'title' => format_string($dossier->title, true, ['escape' => false]),
    ]), '', 'p')),
    'generalbox mb-3'
);
if (!$doctypes) {
    echo $OUTPUT->notification(get_string('signnothingpending', 'mod_these'), 'success');
    if ($inperson) {
        echo $renderer->render(new output\link($dossierurl, get_string('back')));
    }
} else {
    $mform->display();
}
echo $OUTPUT->footer();
