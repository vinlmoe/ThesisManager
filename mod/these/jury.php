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
$PAGE->set_url(new moodle_url('/mod/these/jury.php', $pageparams));
$PAGE->set_pagelayout('embedded');
if (!$member) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('juryaccess', 'mod_these'));
    echo $OUTPUT->header();
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
    send_file(
        these_signed_document_pdf($these, $context, $dossier, $doc),
        these_document_filename($doc),
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
echo html_writer::div(get_string('juryaccessintro', 'mod_these', (object) [
    'name' => s(trim($member->jurytitle . ' ' . $member->fullname)),
    'role' => s($roles[$member->juryrole] ?? $member->juryrole),
]), 'mb-3');

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('student', 'mod_these'), s($student ? fullname($student) : '-')],
    [get_string('subjecttitle', 'mod_these'), format_string($dossier->title)],
    [get_string('defensedate', 'mod_these'), $date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-'],
    [get_string('defenselocation', 'mod_these'), s($dossier->defenselocation)],
];
echo html_writer::table($table);

$jury = new html_table();
$jury->head = [
    get_string('juryrole', 'mod_these'),
    get_string('juryname', 'mod_these'),
    get_string('juryinstitution', 'mod_these'),
];
$jury->attributes['class'] = 'generaltable';
foreach (these_get_jury($dossier->id) as $juror) {
    $jury->data[] = [s($roles[$juror->juryrole] ?? $juror->juryrole), s(trim($juror->jurytitle . ' ' . $juror->fullname)),
        s($juror->institution)];
}
echo $OUTPUT->heading(get_string('jury', 'mod_these'), 3);
echo html_writer::table($jury);
echo these_render_answers($these, $dossier);

$links = [];
foreach ($allowed['areas'] as $filearea) {
    foreach (these_get_files($context, $filearea, $dossier->id) as $file) {
        $links[] = html_writer::link(
            new moodle_url('/mod/these/jury.php', $pageparams + ['area' => $filearea, 'file' => $file->get_filename()]),
            s($file->get_filename())
        ) . ' — ' . get_string($filearea, 'mod_these');
    }
}
foreach ($allowed['documents'] as $doctype) {
    if (these_document_available($dossier, $doctype)) {
        $links[] = html_writer::link(
            new moodle_url('/mod/these/jury.php', $pageparams + ['doc' => $doctype]),
            these_document_label($doctype)
        );
    }
}
echo $OUTPUT->heading(get_string('juryaccessdocuments', 'mod_these'), 3);
echo $links ? html_writer::alist($links) : $OUTPUT->notification(get_string('juryaccessnodocuments', 'mod_these'), 'info');
echo $OUTPUT->footer();
