<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Étapes de mise à jour de mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Mise à jour du module.
 *
 * @param int $oldversion Version installée.
 * @return bool
 */
function xmldb_these_upgrade($oldversion) {
    global $CFG, $DB;

    if ($oldversion < 2026102504) {
        // Les documents réservés au personnel (rapport du directeur, grille de conformité) ne sont
        // plus rangés dans la zone « generated », consultable par l'étudiant : retrait de ceux que
        // les générations précédentes y ont déposés. L'archive transmise à l'université les rend
        // désormais à la demande.
        require_once($CFG->dirroot . '/mod/these/locallib.php');
        $filenames = [];
        foreach (these_document_definitions() as $doctype => $definition) {
            if ($definition['staffonly']) {
                $filenames[] = these_document_filename($doctype);
            }
        }
        [$insql, $params] = $DB->get_in_or_equal($filenames, SQL_PARAMS_NAMED);
        $params['component'] = 'mod_these';
        $params['filearea'] = 'generated';
        $records = $DB->get_recordset_select(
            'files',
            "component = :component AND filearea = :filearea AND filename $insql",
            $params
        );
        $fs = get_file_storage();
        foreach ($records as $record) {
            $fs->get_file_instance($record)->delete();
        }
        $records->close();

        upgrade_mod_savepoint(true, 2026102504, 'these');
    }

    return true;
}
