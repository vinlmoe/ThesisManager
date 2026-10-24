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
 * Data generator for mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Générateur de données de test pour mod_these : au-delà de l'instance elle-même, les tests ont
 * besoin de dossiers à une étape donnée du circuit et de jurys valides, sans rejouer tout le
 * circuit à chaque fois.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_these_generator extends testing_module_generator {
    /**
     * Crée un dossier directement dans le statut voulu.
     *
     * @param stdClass|int $these Instance (ou son id).
     * @param int $userid Étudiant.
     * @param array $record Champs à surcharger (status, directorid, title...).
     * @return stdClass Le dossier créé.
     */
    public function create_dossier($these, $userid, array $record = []) {
        global $DB;

        $theseid = is_object($these) ? $these->id : $these;
        $now = time();
        $record = array_merge([
            'theseid' => $theseid,
            'userid' => $userid,
            'status' => THESE_STATUS_DRAFT,
            'statustime' => $now,
            'title' => 'Sujet de test',
            'titleen' => '',
            'summary' => 'Résumé de test',
            'keywords' => 'test',
            'directorid' => 0,
            'codirectorname' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ], $record);

        $id = $DB->insert_record('these_dossier', (object) $record);
        return $DB->get_record('these_dossier', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Jury valide : un président, le directeur et un assesseur (trois membres votants), plus un
     * invité sans adresse de courriel.
     *
     * @return array Membres au format de these_save_jury().
     */
    public function default_jury() {
        return [
            (object) ['juryrole' => 'president', 'fullname' => 'Paul Président', 'jurytitle' => 'Professeur',
                'institution' => 'Faculté de médecine', 'email' => 'president@example.com', 'jurystatus' => 'pr'],
            (object) ['juryrole' => 'director', 'fullname' => 'Dora Directrice', 'jurytitle' => 'Maître de conférences',
                'institution' => 'École vétérinaire', 'email' => 'directrice@example.com', 'jurystatus' => 'mcf'],
            (object) ['juryrole' => 'member', 'fullname' => 'Albert Assesseur', 'jurytitle' => 'Professeur',
                'institution' => 'École vétérinaire', 'email' => 'assesseur@example.com', 'jurystatus' => 'pr'],
            (object) ['juryrole' => 'guest', 'fullname' => 'Iris Invitée', 'jurytitle' => 'Docteur vétérinaire',
                'institution' => 'Clinique', 'email' => '', 'jurystatus' => 'other'],
        ];
    }
}
