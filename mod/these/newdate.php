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
 * Nouvelle date de soutenance proposée par l'étudiant, quand la DEVE en a demandé une autre (voir
 * \mod_these\local\daterequest) : le reste du dossier n'est pas modifié.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\backlinks;
use mod_these\local\daterequest;
use mod_these\local\page;
use mod_these\output;
use mod_these\output\parts;

$id = required_param('id', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
require_capability('mod/these:submit', $context);

$viewurl = new moodle_url('/mod/these/view.php', ['id' => $cm->id]);
$dossier = these_get_user_dossier($these->id, $USER->id);
if (!$dossier || !daterequest::pending($dossier)) {
    redirect($viewurl, get_string('newdatenotexpected', 'mod_these'), null, \core\output\notification::NOTIFY_INFO);
}

$PAGE->set_url(daterequest::url($cm));
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('newdate', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$refused = (int) ($dossier->defensedate ?: $dossier->proposeddate);
$mform = new \mod_these\form\newdate_form($PAGE->url, [
    'refuseddate' => $refused,
    'mindelayweeks' => (int) \mod_these\local\scopesettings::for_context($context)['mindelayweeks'],
    'earliestdate' => \mod_these\local\scopesettings::earliest_defense($context),
]);
$mform->set_data(['id' => $cm->id, 'proposeddate' => $refused ?: time()]);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    daterequest::propose($these, $cm, $dossier, $USER->id, (int) $data->proposeddate);
    redirect($viewurl, get_string('newdatesaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$renderer = $PAGE->get_renderer('mod_these');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('newdate', 'mod_these'));
echo backlinks::render(backlinks::student_dossier($cm));
$notice = [parts::text(get_string('newdaterequested', 'mod_these', $refused
    ? userdate($refused, get_string('strftimedaydatetime', 'langconfig')) : '-'))];
if (trim((string) $dossier->daterequestcomment) !== '') {
    $notice[] = parts::br();
    $notice[] = parts::html(format_text($dossier->daterequestcomment, FORMAT_PLAIN));
}
echo $renderer->render(new output\message($notice, 'alert alert-warning'));
$mform->display();
echo $OUTPUT->footer();
