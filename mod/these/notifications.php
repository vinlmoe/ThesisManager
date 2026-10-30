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
 * Personnalisation des courriels de l'activité par la DEVE.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\page;

$id = required_param('id', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
these_require_service($context, 'deve');

$PAGE->set_url('/mod/these/notifications.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('notifications', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$dashboardurl = new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]);
$mform = new \mod_these\form\notifications_form($PAGE->url);
$formdata = ['id' => $cm->id];
foreach (array_keys(these_get_email_definitions()) as $key) {
    $custom = these_get_custom_email_template($these->id, $key);
    $formdata['subject_' . $key] = $custom ? $custom->subject : '';
    $formdata['body_' . $key] = $custom ? $custom->body : '';
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($dashboardurl);
} else if ($data = $mform->get_data()) {
    foreach (array_keys(these_get_email_definitions()) as $key) {
        these_save_email_template($these->id, $key, (string) $data->{'subject_' . $key}, (string) $data->{'body_' . $key});
    }
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notifications', 'mod_these'));
echo these_render_navlinks($cm, $context);
$mform->display();
echo $OUTPUT->footer();
