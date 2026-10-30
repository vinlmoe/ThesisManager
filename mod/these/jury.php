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
 * Accès d'un membre du jury aux documents du dossier : soutenance, composition du jury, manuscrit
 * et documents déposés, rapport du directeur, grille de conformité, conclusions et fiche
 * signalétique.
 *
 * Deux accès :
 * - par le lien personnel reçu à sa désignation (jeton), sans compte Moodle ;
 * - depuis son compte Moodle (activité « Suivi des thèses »), le membre étant reconnu à son
 *   adresse (paramètre member).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Pas de require_login() pour l'accès par jeton, et c'est volontaire, comme pour la signature par
// lien : un membre du jury extérieur n'a pas de compte Moodle. L'accès est contrôlé par le jeton
// personnel du membre (48 caractères aléatoires, dont seule l'empreinte est conservée), vérifié juste
// en dessous ; il expire après la soutenance et cesse à la clôture ou à l'abandon du dossier.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\output;
use mod_these\output\parts;

$token = optional_param('token', '', PARAM_ALPHANUM);
$memberid = optional_param('member', 0, PARAM_INT);
$area = optional_param('area', '', PARAM_ALPHA);
$filename = optional_param('file', '', PARAM_FILE);
$doc = optional_param('doc', '', PARAM_ALPHA);

if ($token !== '') {
    $member = these_get_jury_access($token);
    $pageparams = ['token' => $token];
} else {
    require_login();
    $member = these_get_jury_member_access($memberid, $USER);
    $pageparams = ['member' => $memberid];
}
// Le jeton reste hors de l'adresse de la page (journaux, navigation) : il n'est repris que dans les liens.
$PAGE->set_url(new moodle_url('/mod/these/jury.php', $token !== '' ? [] : $pageparams));
$PAGE->set_pagelayout('embedded');
if (!$member) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('juryaccess', 'mod_these'));
    echo $OUTPUT->header();
    if ($token === '') {
        echo \mod_these\local\backlinks::render(\mod_these\local\backlinks::from_global());
    }
    echo $OUTPUT->notification(get_string('juryaccessinvalid', 'mod_these'), 'info');
    echo $OUTPUT->footer();
    exit;
}
$dossier = $member->dossier;
$these = $member->these;
$context = context_module::instance($member->cm->id);
$PAGE->set_context($context);
$PAGE->set_title(get_string('juryaccess', 'mod_these'));
$PAGE->set_heading(format_string($these->name));
$allowed = these_jury_documents();

// Téléchargements.
if ($area !== '' && in_array($area, $allowed['areas'], true)) {
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_these', $area, $dossier->id, '/', $filename);
    if (!$file || $file->is_directory()) {
        throw new moodle_exception('filenotfound', 'error');
    }
    send_stored_file($file, 0, 0, true);
}
if ($doc !== '' && in_array($doc, $allowed['documents'], true) && these_document_available($dossier, $doc)) {
    $pdf = these_signed_document_pdf($these, $context, $dossier, $doc);
    send_file(
        $pdf,
        \mod_these\local\documents::signed_document_filename($context, $dossier, $doc),
        0,
        0,
        true,
        true,
        'application/pdf'
    );
}

$student = $DB->get_record('user', ['id' => $dossier->userid]);
$roles = these_jury_role_options();
$date = (int) ($dossier->defensedate ?: $dossier->proposeddate);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('juryaccess', 'mod_these'));
// Membre du jury connecté (ouvert depuis « Mes jurys ») : retour à la page d'origine. L'accès par
// lien, sans compte, n'a pas de page où revenir.
if ($token === '') {
    echo \mod_these\local\backlinks::render(\mod_these\local\backlinks::from_global());
}
$renderer = $PAGE->get_renderer('mod_these');
echo $renderer->render(new output\message(get_string('juryaccessintro', 'mod_these', (object) [
    'name' => trim($member->jurytitle . ' ' . $member->fullname),
    'role' => $roles[$member->juryrole] ?? $member->juryrole,
]), 'mb-3'));

// Le membre du jury connaît le sujet (voir these_user_knows_subject()) : le titre est affiché.
echo $renderer->render(new output\table([], [
    [[parts::text(get_string('student', 'mod_these'))], [parts::text($student ? fullname($student) : '-')]],
    [[parts::text(get_string('subjecttitle', 'mod_these'))], [parts::html(format_string($dossier->title))]],
    [[parts::text(get_string('defensedate', 'mod_these'))],
        [parts::text($date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-')]],
    [[parts::text(get_string('defenselocation', 'mod_these'))], [parts::text($dossier->defenselocation)]],
]));

$rows = [];
foreach (these_get_jury($dossier->id) as $juror) {
    $rows[] = [
        [parts::text($roles[$juror->juryrole] ?? $juror->juryrole)],
        [parts::text(trim($juror->jurytitle . ' ' . $juror->fullname))],
        [parts::text($juror->institution)],
    ];
}
echo $OUTPUT->heading(get_string('jury', 'mod_these'), 3);
echo $renderer->render(new output\table([
    get_string('juryrole', 'mod_these'),
    get_string('juryname', 'mod_these'),
    get_string('juryinstitution', 'mod_these'),
], $rows));
echo these_render_answers($these, $dossier);

$links = [];
foreach ($allowed['areas'] as $filearea) {
    foreach (these_get_files($context, $filearea, $dossier->id) as $file) {
        $links[] = [
            parts::link(
                new moodle_url('/mod/these/jury.php', $pageparams + ['area' => $filearea, 'file' => $file->get_filename()]),
                $file->get_filename(),
                ''
            ),
            parts::text(' — ' . get_string($filearea, 'mod_these')),
        ];
    }
}
foreach ($allowed['documents'] as $doctype) {
    if (these_document_available($dossier, $doctype)) {
        $links[] = [parts::link(
            new moodle_url('/mod/these/jury.php', $pageparams + ['doc' => $doctype]),
            these_document_label($doctype),
            ''
        )];
    }
}
echo $OUTPUT->heading(get_string('juryaccessdocuments', 'mod_these'), 3);
echo $links ? $renderer->render(new output\bullet_list($links))
    : $OUTPUT->notification(get_string('juryaccessnodocuments', 'mod_these'), 'info');
echo $OUTPUT->footer();
