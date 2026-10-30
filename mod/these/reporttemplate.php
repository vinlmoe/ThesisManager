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
 * Téléchargement du modèle de rapport du directeur (document Word déposé dans les paramètres du
 * circuit, voir \mod_these\local\scopesettings::report_template()), depuis la page de dépôt du
 * rapport : pour le directeur du dossier et le personnel qui suit les dossiers.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

use mod_these\local\page;

$id = required_param('id', PARAM_INT);
$dossierid = required_param('dossierid', PARAM_INT);

$activity = page::load_activity($id);
['cm' => $cm, 'course' => $course, 'these' => $these, 'context' => $context] = (array) $activity;
require_login($course, true, $cm);
['dossier' => $dossier, 'rights' => $rights] = (array) page::load_dossier($activity, $dossierid, $USER->id);
if (!$rights->director && !$rights->viewall) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('reporttemplate', 'mod_these'));
}

$file = \mod_these\local\scopesettings::report_template(these_settings_scope($these));
if (!$file) {
    throw new moodle_exception('reporttemplatemissing', 'mod_these');
}
send_stored_file($file, 0, 0, true);
