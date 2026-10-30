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
 * - sur place, par la DEVE connectée (id, dossierid, slot), pour un signataire présent : signataire
 *   sans compte, étudiant ou directeur. La DEVE certifie d'abord avoir vérifié l'identité du
 *   signataire (étape inscrite à l'historique, à son nom), puis la page de signature s'ouvre.
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

use mod_these\local\backlinks;
use mod_these\local\page;
use mod_these\output;
use mod_these\output\parts;

$token = optional_param('token', '', PARAM_ALPHANUM);
$preview = optional_param('preview', '', PARAM_ALPHA);

if ($token !== '') {
    $pending = these_get_signatures_by_token($token);
    if (!$pending) {
        $PAGE->set_context(context_system::instance());
        $PAGE->set_url(new moodle_url('/mod/these/sign.php'));
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
    $activity = page::load_activity($id);
    ['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
    require_login($course, true, $cm);
    these_require_service($context, 'deve');
    $dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $these->id], '*', MUST_EXIST);
    if (!in_array($slot, these_inperson_slots(), true)) {
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
// Le jeton reste hors de l'adresse de la page (journaux, navigation) : les formulaires et les liens le
// reprennent par $formurl.
$PAGE->set_url(new moodle_url('/mod/these/sign.php', $inperson ? $pageparams : []));
$formurl = new moodle_url('/mod/these/sign.php', $pageparams);
$PAGE->set_pagelayout($inperson ? 'incourse' : 'embedded');
$PAGE->set_title(get_string('signpagetitle', 'mod_these'));
$PAGE->set_heading(format_string($these->name));

$signer = these_inperson_signer($dossier, $slot);
$signaturesurl = new moodle_url('/mod/these/signatures.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
$signaturesback = backlinks::target($signaturesurl, get_string('managesignatures', 'mod_these'));
$renderer = $PAGE->get_renderer('mod_these');
$links = array_map(function ($doctype) use ($pageparams) {
    return [parts::link(
        new moodle_url('/mod/these/sign.php', $pageparams + ['preview' => $doctype]),
        these_document_label($doctype),
        '',
        '_blank'
    )];
}, $doctypes);
$student = $DB->get_record('user', ['id' => $dossier->userid]);

// Informations du document à signer, affichées au-dessus de la signature (et de la vérification d'identité).
$info = new output\detail_section(get_string('documentstosign', 'mod_these'), [
    get_string('signinfoslot', 'mod_these') => [parts::text(these_slot_label($slot)
        . ($signer['name'] !== '' ? ' — ' . $signer['name'] : ''))],
    get_string('signinfostudent', 'mod_these') => [parts::text($student ? fullname($student) : '-')],
    get_string('signinfotitle', 'mod_these') => [parts::text(format_string($dossier->title, true, ['escape' => false]))],
    get_string('signinfonumber', 'mod_these') => trim((string) $dossier->devenumber) !== ''
        ? [parts::text($dossier->devenumber)] : null,
    get_string('signinfodefense', 'mod_these') => !empty($dossier->defensedate)
        ? [parts::text(userdate($dossier->defensedate, get_string('strftimedaydatetime', 'langconfig')))] : null,
    get_string('signinfodocuments', 'mod_these') => $links
        ? [parts::html($renderer->render(new output\bullet_list($links)))] : null,
]);

// Sur place : la DEVE certifie d'abord avoir vérifié l'identité du signataire ; la vérification,
// inscrite à l'historique à son nom, vaut pour cette page de signature (session).
$verifiedkey = 'these_identity_' . $dossier->id . '_' . $slot;
if ($inperson && $doctypes && empty($SESSION->$verifiedkey)) {
    $identityform = new \mod_these\form\identity_form($formurl);
    $identityform->set_data($pageparams);
    if ($identityform->is_cancelled()) {
        redirect($signaturesurl);
    }
    if ($identityform->get_data()) {
        $SESSION->$verifiedkey = $USER->id;
        these_log(
            $dossier->id,
            $USER->id,
            'identityverified',
            $dossier->status,
            $dossier->status,
            these_slot_label($slot) . ($signer['name'] !== '' ? ' — ' . $signer['name'] : '') . ' — '
                . get_string('signidentityverifiedby', 'mod_these', fullname($USER))
        );
        redirect($formurl);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('signpagetitle', 'mod_these'));
    echo backlinks::render($signaturesback);
    echo $renderer->render($info);
    $identityform->display();
    echo $OUTPUT->footer();
    exit;
}

// Signataire connecté avec le compte de l'adresse invitée (membre du jury ayant un compte Moodle,
// par exemple) : il peut reprendre ou enregistrer sa signature. Jamais pour une signature sur place.
$signeremail = core_text::strtolower(trim((string) ($token !== '' ? $pending[0]->signeremail : '')));
$allowsaved = !$inperson && $signeremail !== '' && isloggedin() && !isguestuser()
    && core_text::strtolower(trim((string) $USER->email)) === $signeremail;
$mform = new \mod_these\form\sign_form($formurl, [
    // Direction générale : signée par délégation, la personne qui signe saisit son propre nom.
    'signername' => $slot !== 'dg' && $signer['name'] !== '' ? $signer['name'] : ($allowsaved ? fullname($USER) : ''),
    'allowsaved' => $allowsaved,
    // Nom saisi seulement par la DEVE : sur place, ou par délégation pour la direction générale.
    'editablename' => $inperson || $slot === 'dg',
]);
$mform->set_data($pageparams);

if ($doctypes && ($data = $mform->get_data())) {
    if (!$allowsaved) {
        unset($data->usesavedsignature, $data->savesignature);
    }
    $signature = these_signature_from_form($data);
    // Sur place : identité vérifiée par la DEVE, dont le compte est conservé avec la signature ; un
    // étudiant ou un directeur signe en son nom de compte.
    $options = [
        'method' => $inperson ? 'inperson' : 'online',
        'delegated' => $slot === 'dg',
        'verifiedby' => $inperson ? (int) $SESSION->$verifiedkey : 0,
    ];
    foreach ($doctypes as $doctype) {
        these_sign_document(
            $context,
            $dossier,
            $doctype,
            $slot,
            $allowsaved ? $USER->id : ($inperson ? $signer['userid'] : 0),
            $signature->name,
            $signature->png,
            $signer['email'] !== '' ? $signer['email'] : $pending[0]->signeremail ?? '',
            $options
        );
    }
    if ($inperson) {
        unset($SESSION->$verifiedkey);
        these_log(
            $dossier->id,
            $USER->id,
            'signedinperson',
            $dossier->status,
            $dossier->status,
            these_slot_label($slot) . ' — ' . $data->signername . ' — '
                . get_string('signidentityverifiedby', 'mod_these', fullname($USER))
        );
        redirect($signaturesurl, get_string('signaturesrecorded', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($formurl, get_string('signaturesrecorded', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('signpagetitle', 'mod_these'));
// Signature sur place : retour au suivi des signatures. Le signataire par lien n'a pas de page où revenir.
if ($inperson) {
    echo backlinks::render($signaturesback);
}
echo $renderer->render($info);
if (!$doctypes) {
    echo $OUTPUT->notification(get_string('signnothingpending', 'mod_these'), 'success');
} else {
    $mform->display();
}
echo $OUTPUT->footer();
