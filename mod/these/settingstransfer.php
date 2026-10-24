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
 * Transfert des paramètres de l'activité (DEVE) : paramètres de l'activité (établissement,
 * signataire, jury, adresses de service, relances), courriels, modèles et images des documents.
 * Ils s'exportent dans un fichier, ou se reprennent d'une autre activité (nouveau cours, nouvelle
 * année) ou d'un fichier exporté (autre plateforme).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\settings_transfer_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');

$PAGE->set_url('/mod/these/settingstransfer.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('transfersettings', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$chosengroups = function (stdClass $data, $prefix = '') {
    return array_values(array_filter(these_settings_groups(), function ($group) use ($data, $prefix) {
        return !empty($data->{$prefix . $group});
    }));
};

$sources = these_settings_transfer_sources($these, $USER->id);
$exportform = new settings_transfer_form($PAGE->url, ['mode' => 'export', 'sources' => $sources]);
$importform = new settings_transfer_form($PAGE->url, ['mode' => 'import', 'sources' => $sources]);
$exportform->set_data(['id' => $cm->id]);
$importform->set_data(['id' => $cm->id, 'source' => $sources ? 'activity' : 'file']);

$mode = optional_param('mode', '', PARAM_ALPHA);
if ($mode === 'export' && ($data = $exportform->get_data())) {
    $settings = these_export_settings($these, $context, $chosengroups($data, 'export'));
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = clean_filename('parametres-' . core_text::strtolower(core_text::specialtoascii(format_string($these->name)))
        . '-' . userdate(time(), '%Y%m%d') . '.json');
    send_file($json, $filename, 0, 0, true, true, 'application/json');
}

$result = null;
if ($mode === 'import') {
    if ($importform->is_cancelled()) {
        redirect(new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]));
    }
    if ($data = $importform->get_data()) {
        if ($data->source === 'activity') {
            $source = $DB->get_record('these', ['id' => $data->sourceid], '*', MUST_EXIST);
            $sourcecm = get_coursemodule_from_instance('these', $source->id, 0, false, MUST_EXIST);
            $settings = these_export_settings($source, context_module::instance($sourcecm->id), these_settings_groups());
            $origin = $sources[$source->id];
        } else {
            $settings = these_parse_settings_file(settings_transfer_form::draft_content((int) $data->settingsfile));
            $origin = $importform->get_new_filename('settingsfile');
        }
        $result = these_import_settings($these, $context, $settings, $chosengroups($data));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('transfersettings', 'mod_these'));
echo these_render_navlinks($cm, $context);
echo html_writer::div(get_string('transfersettings_desc', 'mod_these'), 'mb-3');

if ($result) {
    $lines = [];
    if ($result->activity) {
        $lines[] = get_string('transferdoneactivity', 'mod_these');
    }
    foreach (['emails', 'doctemplates', 'docimages'] as $group) {
        if (in_array($group, $chosengroups($data), true)) {
            $lines[] = get_string('transferdone' . $group, 'mod_these', $result->$group);
        }
    }
    echo $OUTPUT->notification(
        get_string('transferdone', 'mod_these', s($origin)) . html_writer::alist($lines),
        \core\output\notification::NOTIFY_SUCCESS
    );
    if ($result->skipped) {
        echo $OUTPUT->notification(
            get_string('transferskipped', 'mod_these') . html_writer::alist(array_map('s', $result->skipped)),
            \core\output\notification::NOTIFY_WARNING
        );
    }
}

echo $OUTPUT->heading(get_string('transferimportheading', 'mod_these'), 3, 'mt-4');
$importform->display();
echo $OUTPUT->heading(get_string('transferexportheading', 'mod_these'), 3, 'mt-4');
echo html_writer::div(get_string('transferexport_desc', 'mod_these'), 'mb-2');
$exportform->display();
echo $OUTPUT->footer();
