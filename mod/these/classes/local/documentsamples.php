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

use core_text;
use stdClass;

/**
 * Données d'exemple des modèles de documents : dossier fictif entièrement signé, tracé de
 * signature fictif, données du planning ou de la liste du corps enseignant, et liste des variables
 * d'un contexte pour l'aide de la page de modification d'un modèle.
 *
 * Les fonctions these_*() correspondantes de locallib/doctemplates.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documentsamples {
    /**
     * Données d'exemple d'un modèle : celles d'un dossier, celles d'un mois de soutenances pour le
     * planning mensuel, ou une liste d'enseignants pour la liste du corps enseignant.
     *
     * @param stdClass|null $scope
     * @param string $type Voir these_doctemplate_types().
     * @param stdClass|null $activity Activité dont reprendre l'établissement.
     * @return array
     */
    public static function doctemplate_sample_context(?stdClass $scope, $type, ?stdClass $activity = null) {
        if ($type === 'planning') {
            return planning::sample_planning_context($scope, $activity);
        }
        if ($type === 'teachinglist') {
            return jurors::sample_teaching_list_context($scope, $activity);
        }
        return self::sample_document_context($scope, $activity);
    }

    /**
     * Données fictives pour l'aperçu d'un modèle : un dossier complet, signé à tous les emplacements.
     *
     * @param stdClass|null $scope Portée des paramètres (images, grille) ; null : ceux d'origine.
     * @param stdClass|null $activity Activité dont reprendre l'établissement (sinon des valeurs fictives).
     * @return array Voir these_build_document_context().
     */
    public static function sample_document_context(?stdClass $scope = null, ?stdClass $activity = null) {
        $now = time();
        $these = $activity ?? (object) [
            'establishmentname' => 'École vétérinaire', 'establishmentaddress' => "1 rue de l'École\n00000 Ville",
            'establishmentcity' => 'Ville', 'signatoryname' => 'Prénom NOM',
            'signatorytitle' => 'Directrice des Études et de la Vie Étudiante', 'deveemail' => 'deve@example.org',
        ];
        $bordereau = [
            'birthname' => 'Martin', 'firstnames' => 'Camille', 'usagename' => '', 'publicationname' => '',
            'birthdate' => make_timestamp(1999, 4, 12), 'birthplace' => 'Lyon', 'birthdept' => '69', 'nationality' => 'Française',
            'street' => '12 rue des Écoles', 'postcode' => '69007', 'city' => 'Lyon', 'country' => 'France',
            'phone' => '06 12 34 56 78', 'personalemail' => 'camille.martin@example.org', 'emailconsent' => 1,
            'defvdate' => make_timestamp(2023, 7, 3), 'defvschool' => 'lyon', 'a6date' => make_timestamp(2025, 7, 1),
            'a6school' => 'lyon', 'a6notyet' => 0, 'nbvolumes' => 1, 'nbpages' => 112, 'nbrefs' => 86,
            'summaryfr' => str_repeat("Ce travail étudie la prise en charge de la douleur chronique chez le chien âgé. ", 12),
            'summaryen' => str_repeat("This work studies the management of chronic pain in elderly dogs. ", 12),
            'keywords' => ['Chien', 'Douleur chronique', 'Gériatrie', 'Analgésie', 'Arthrose'],
            'confidentialuntil' => 0, 'embargo' => 0, 'embargountil' => 0, 'diffusion' => 'internet', 'visio' => 0,
        ];
        $dossier = (object) [
            'id' => 0, 'userid' => 0, 'directorid' => 0,
            'title' => 'Prise en charge de la douleur chronique chez le chien âgé : enquête auprès des vétérinaires praticiens',
            'titleen' => 'Management of chronic pain in elderly dogs: a survey of veterinary practitioners',
            'summary' => 'Résumé du sujet.', 'keywords' => 'Chien ; Douleur chronique ; Gériatrie ; Analgésie',
            'studyyear' => 'A5', 'confidential' => 0, 'directortitle' => 'Pr',
            'externaldirector' => 0, 'extdirectorname' => '', 'extdirectorgrade' => '', 'extdirectorinstitution' => '',
            'extdirectoremail' => '', 'commitments' => signatures::COMMIT_GUIDE | signatures::COMMIT_DUPLICATE,
            'ethicsnumber' => '2526',
            'bordereau' => json_encode($bordereau),
            'conclusions' => "Premier paragraphe des conclusions.\n\n"
                . "Dernier paragraphe des conclusions, qui reste avec les signatures.",
            'reportfavorable' => 1,
            'conformitygrid' => json_encode(array_fill_keys(
                array_merge(...array_map('array_keys', array_values(grid::get_grid_items($scope)))),
                1
            )),
            'agreement' => json_encode(['defvdate' => make_timestamp(2023, 7, 3), 'mobility' => 1,
                'a6date' => make_timestamp(2025, 7, 1), 'a6notyet' => 0, 'directorfunction' => 'Professeur']),
            'devenumber' => '2026-042', 'libraryref' => 'THV-2026-042', 'proposeddate' => $now + 30 * DAYSECS,
            'defensedate' => $now + 30 * DAYSECS, 'defenselocation' => 'Amphithéâtre A', 'defenseresult' => 0,
            'mention' => '',
        ];
        $jury = [];
        foreach (
            [
            ['president', 'Pr', 'Jean DUPONT', 'Université'],
            ['member', 'Dr', 'Claire BERNARD', 'École vétérinaire'],
            ['member', 'Dr', 'Paul LEROY', 'École vétérinaire'],
            ['director', 'Pr', 'Anne DURAND', 'École vétérinaire'],
            ['guest', 'Dr', 'Luc PETIT', 'Clinique vétérinaire des Monts'],
            ] as $i => [$role, $title, $name, $institution]
        ) {
            $jury[] = (object) ['juryrole' => $role, 'jurytitle' => $title, 'fullname' => $name, 'institution' => $institution,
                'email' => 'jury' . $i . '@example.org'];
        }
        $image = self::sample_signature_image();
        $signatures = [];
        foreach (['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl', 'president'] as $slot) {
            $signatures[$slot] = ['name' => signatures::slot_label($slot), 'time' => $now, 'hash' => '3f2a9c51d0e4',
                'image' => $image];
        }
        return documentcontext::build_document_context([
            'these' => $these,
            'dossier' => $dossier,
            'student' => (object) ['firstname' => 'Camille', 'lastname' => 'Martin', 'email' => 'camille.martin@example.org'],
            'director' => (object) ['firstname' => 'Anne', 'lastname' => 'Durand', 'email' => 'anne.durand@example.org'],
            'jury' => $jury,
            'signatures' => $signatures,
            'recipient' => null,
            'scope' => $scope,
        ]);
    }

    /**
     * Tracé de signature fictif (PNG) pour les aperçus.
     *
     * @return string
     */
    public static function sample_signature_image() {
        $image = imagecreatetruecolor(300, 100);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        $ink = imagecolorallocate($image, 20, 40, 120);
        imagesetthickness($image, 3);
        $previous = null;
        for ($x = 20; $x <= 280; $x += 4) {
            $point = [$x, (int) (50 + 28 * sin($x / 18) * cos($x / 47))];
            if ($previous) {
                imageline($image, $previous[0], $previous[1], $point[0], $point[1], $ink);
            }
            $previous = $point;
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /**
     * Variables d'un contexte de modèle, à plat, avec un exemple de valeur : aide de la page de
     * modification d'un modèle.
     *
     * @param array $data
     * @param string $prefix
     * @return array nom => exemple (texte)
     */
    public static function doctemplate_variables(array $data, $prefix = '') {
        $variables = [];
        foreach ($data as $key => $value) {
            $name = $prefix . $key;
            if ($key === 'img') {
                foreach (array_keys($value) as $image) {
                    $variables[$name . '.' . $image] = get_string('doctplvarimage', 'mod_these');
                }
            } else if (is_array($value) && isset($value[0]) && is_array($value[0])) {
                $variables[$name] = get_string('doctplvarlist', 'mod_these', implode(', ', array_keys($value[0])));
            } else if (is_array($value) && !isset($value[0])) {
                $variables += self::doctemplate_variables($value, $name . '.');
            } else if (is_bool($value)) {
                $variables[$name] = get_string('doctplvarbool', 'mod_these', $value ? 'true' : 'false');
            } else if (is_scalar($value)) {
                $value = (string) $value;
                $variables[$name] = strpos($value, '<') !== false ? get_string('doctplvarhtml', 'mod_these')
                    : core_text::substr($value, 0, 60) . (core_text::strlen($value) > 60 ? '…' : '');
            }
        }
        return $variables;
    }
}
