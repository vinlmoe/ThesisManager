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
 * Éléments de la grille de conformité de l'activité (DEVE). Le directeur remplit la grille par le
 * formulaire de validation de la soutenance ; la grille signée en PDF est produite à partir de ses
 * réponses et des éléments en vigueur.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\form\griditems_form;

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('these', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
these_require_service($context, 'deve');

$PAGE->set_url('/mod/these/griditems.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($these->name) . ' - ' . get_string('griditems', 'mod_these'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$items = [];
foreach (these_get_grid_items($these->id) as $sectionitems) {
    foreach ($sectionitems as $item) {
        $items[] = $item;
    }
}
$mform = new griditems_form($PAGE->url, ['count' => count($items)]);
$formdata = ['id' => $cm->id, 'itemkey' => [], 'section' => [], 'label' => [], 'conditional' => []];
foreach ($items as $i => $item) {
    $formdata['itemkey'][$i] = $item->itemkey;
    $formdata['section'][$i] = $item->section;
    $formdata['label'][$i] = $item->label;
    $formdata['conditional'][$i] = $item->conditional;
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]));
} else if ($data = $mform->get_data()) {
    if (!empty($data->resetbutton)) {
        $DB->delete_records('these_griditem', ['theseid' => $these->id]);
    } else {
        $submitted = [];
        foreach ((array) $data->label as $i => $label) {
            $submitted[] = (object) [
                'itemkey' => $data->itemkey[$i] ?? '',
                'section' => $data->section[$i] ?? 'forme',
                'label' => $label,
                'conditional' => $data->conditional[$i] ?? 0,
            ];
        }
        these_save_grid_items($these->id, $submitted);
    }
    redirect($PAGE->url, get_string('griditemssaved', 'mod_these'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('griditems', 'mod_these'));
echo these_render_navlinks($cm, $context);
echo html_writer::div(get_string('griditems_desc', 'mod_these'), 'mb-3');
echo html_writer::div(html_writer::link(
    new moodle_url('/mod/these/doctemplates.php', ['id' => $cm->id, 'type' => 'grid', 'action' => 'preview']),
    get_string('doctplpreview', 'mod_these'),
    ['class' => 'btn btn-secondary', 'target' => '_blank']
), 'mb-3');
$mform->display();
echo $OUTPUT->footer();
