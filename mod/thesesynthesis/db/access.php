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
 * Capability definitions for mod_thesesynthesis.
 *
 * @package   mod_thesesynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ajouter une instance de l'activité dans un cours.
    'mod/thesesynthesis:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    // Consulter le suivi : ses thèses dirigées, ses jurys, et pour la DEVE et la bibliothèque tous
    // les dossiers des activités liées où elles ont leurs droits.
    'mod/thesesynthesis:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Choisir les activités « Gestion des thèses » (promotions) suivies ici. Réservé à qui administre
    // l'activité, comme pour le suivi des stages : l'écran énumère des cours extérieurs à celui-ci.
    'mod/thesesynthesis:managelinks' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Tenir l'annuaire du jury (commun à toutes les activités « Gestion des thèses »).
    'mod/thesesynthesis:managejurors' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Définir les paramètres partagés avec les activités suivies : modèles des documents et leurs
    // images, grille de conformité, questionnaire préalable au jury (rôle de la DEVE).
    'mod/thesesynthesis:managesettings' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Rechercher des thèses dans les activités suivies (enseignants, DEVE, bibliothèque). Les
    // enseignants ne voient que les thèses enregistrées et non confidentielles ; la DEVE et la
    // bibliothèque, toutes celles des activités où elles ont leurs droits.
    'mod/thesesynthesis:search' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
