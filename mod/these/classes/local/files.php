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

namespace mod_these\local;

use context;
use stdClass;

/**
 * Zones de fichiers des dossiers.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class files {
    /**
     * Options des gestionnaires de fichiers selon la zone.
     *
     * @param string $filearea
     * @return array
     */
    public static function filemanager_options($filearea) {
        $options = ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 1, 'accepted_types' => ['.pdf']];
        switch ($filearea) {
            case 'subjectdocs':
                $options['maxfiles'] = 10;
                $options['accepted_types'] = '*';
                break;
            case 'defensedocs':
                // Documents transmis au jury : PDF seulement (pas de .html ou .svg).
                $options['maxfiles'] = 10;
                break;
            case 'directorreport':
                $options['accepted_types'] = ['.pdf', '.doc', '.docx', '.odt'];
                break;
            case 'pv':
                // Procès-verbal signé par le jury, scanné en un PDF ou en images.
                $options['maxfiles'] = 5;
                $options['accepted_types'] = ['.pdf', '.jpg', '.jpeg', '.png'];
                break;
            case 'signedscan':
                // Documents signés hors du module (signataires sans compte), scannés en PDF ou en images.
                $options['maxfiles'] = 10;
                $options['accepted_types'] = ['.pdf', '.jpg', '.jpeg', '.png'];
                break;
        }
        return $options;
    }

    /**
     * Enregistre les brouillons des gestionnaires de fichiers d'un formulaire dans les zones du dossier.
     * À appeler dans l'opération qui enregistre le formulaire (dossiers::dossier_operation()), sous le
     * verrou du dossier : deux envois simultanés du même formulaire ne se disputent plus la zone ; le
     * second trouve le dossier changé (refus attendu) ou réenregistre le même brouillon, sans effet.
     *
     * @param stdClass $dossier
     * @param array $drafts Zone => identifiant du brouillon (draftitemid).
     * @return void
     */
    public static function save_drafts(stdClass $dossier, array $drafts) {
        global $CFG;

        if (!$drafts) {
            return;
        }
        require_once($CFG->libdir . '/filelib.php');
        $cm = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        foreach ($drafts as $filearea => $draftitemid) {
            file_save_draft_area_files(
                (int) $draftitemid,
                $context->id,
                'mod_these',
                $filearea,
                $dossier->id,
                self::filemanager_options($filearea)
            );
        }
    }

    /**
     * Fichiers d'une zone d'un dossier, triés par nom.
     *
     * @param context $context
     * @param string $filearea
     * @param int $dossierid
     * @return stored_file[]
     */
    public static function get_files(context $context, $filearea, $dossierid) {
        $fs = get_file_storage();
        return array_values($fs->get_area_files($context->id, 'mod_these', $filearea, $dossierid, 'filename', false));
    }

    /**
     * Indique si une zone de fichiers d'un dossier contient au moins un fichier.
     *
     * @param context $context
     * @param string $filearea
     * @param int $dossierid
     * @return bool
     */
    public static function has_files(context $context, $filearea, $dossierid) {
        $fs = get_file_storage();
        return !$fs->is_area_empty($context->id, 'mod_these', $filearea, $dossierid);
    }

    /**
     * Nombre de fichiers dans une zone de brouillon (contrôle d'un dépôt obligatoire).
     *
     * @param int $draftitemid
     * @return int
     */
    public static function count_draft_files($draftitemid) {
        $info = file_get_draft_area_info($draftitemid);
        return (int) $info['filecount'];
    }
}
