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
 * Capability definitions for mod_these.
 *
 * Quatre acteurs interviennent dans le circuit : l'étudiant, le directeur de thèse, la DEVE et la
 * bibliothèque. Les trois premiers correspondent aux archétypes standard (comme dans mod_stage, la
 * DEVE est le rôle « Enseignant ») ; la bibliothèque n'a pas d'équivalent : ses agents sont désignés
 * dans les paramètres du circuit (voir INSTALL.md), sans rôle dédié.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ajouter une instance de l'activité dans un cours.
    'mod/these:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    // Voir l'activité (tous les rôles inscrits).
    'mod/these:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Étudiant : proposer un sujet et déposer ses documents de soutenance.
    'mod/these:submit' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],

    // Pouvoir être choisi comme directeur de thèse, et valider les dossiers dont on est le
    // directeur. Le droit ne porte que sur ces dossiers-là : il ne donne accès à aucun autre.
    'mod/these:direct' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    // DEVE : enregistrer les sujets, valider les dossiers de soutenance, générer les documents,
    // déposer les procès-verbaux, modifier ou abandonner un dossier, paramétrer les courriels et
    // les modèles HTML des documents (RISK_XSS).
    'mod/these:managedeve' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Bibliothèque : enregistrer les sujets dans le référentiel documentaire et attester la
    // conformité des manuscrits. Aucun archétype d'enseignant : voir l'en-tête de ce fichier.
    'mod/these:managelibrary' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Voir tous les dossiers, quel que soit leur directeur (DEVE, bibliothèque, gestion).
    'mod/these:viewall' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
