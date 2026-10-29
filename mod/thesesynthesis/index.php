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
 * Displays the list of thesesynthesis instances in a course.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);

require_login($course);
$context = context_course::instance($course->id);

$PAGE->set_url('/mod/thesesynthesis/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_thesesynthesis'));
echo \mod_these\local\backlinks::render(\mod_these\local\backlinks::course($course->id));

$instances = get_all_instances_in_course('thesesynthesis', $course);

if (empty($instances)) {
    notice(get_string('nonewmodules', 'moodle'), new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}

$rows = [];
foreach ($instances as $instance) {
    $rows[] = [[\mod_these\output\parts::link(
        new moodle_url('/mod/thesesynthesis/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name, true, ['escape' => false]),
        ''
    )]];
}
echo $PAGE->get_renderer('mod_these')->render(new \mod_these\output\table([get_string('name')], $rows));

echo $OUTPUT->footer();
