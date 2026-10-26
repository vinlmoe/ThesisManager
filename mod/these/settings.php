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
 * Réglages du site pour mod_these (Administration du site > Plugins > Modules d'activité >
 * Gestion des thèses) : rôles des services, et signataires et en-tête des documents générés.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Rôles des services : comme la DEVE sur le rôle « Enseignant », la bibliothèque est reconnue à
    // son rôle, le rôle interne « Bibliothèque (thèses) » créé à l'installation par défaut.
    $roles = [0 => get_string('none')];
    foreach (role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT) as $role) {
        $roles[$role->id] = $role->localname;
    }
    $settings->add(new admin_setting_heading(
        'mod_these/rolesheading',
        get_string('settingsroles', 'mod_these'),
        get_string('settingsroles_desc', 'mod_these')
    ));
    $editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
    $library = (int) $DB->get_field('role', 'id', ['shortname' => 'theselibrary']);
    $settings->add(new admin_setting_configselect(
        'mod_these/deverole',
        get_string('deverole', 'mod_these'),
        get_string('deverole_desc', 'mod_these'),
        $editingteacher,
        $roles
    ));
    $settings->add(new admin_setting_configselect(
        'mod_these/libraryrole',
        get_string('libraryrole', 'mod_these'),
        get_string('libraryrole_desc', 'mod_these'),
        $library,
        $roles
    ));

    // En-tête et signataires des documents générés.
    $settings->add(new admin_setting_heading(
        'mod_these/documentsheading',
        get_string('settingsdocuments', 'mod_these'),
        get_string('settingsdocuments_desc', 'mod_these')
    ));
    // Contrat de diffusion électronique : le PDF officiel est déposé tel quel ; le module le fait
    // précéder d'une page de données et de choix de l'auteur et suivre d'une page de signatures.
    $settings->add(new admin_setting_configstoredfile(
        'mod_these/contracttemplate',
        get_string('settingscontracttemplate', 'mod_these'),
        get_string('settingscontracttemplate_desc', 'mod_these'),
        'contracttemplate',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.pdf']]
    ));
    $texts = [
        'institution' => '',
        'university' => '',
        'hetopurl' => 'https://www.hetop.eu/hetop/',
        'devename' => '',
        'devetitle' => 'Directrice des Etudes et de la Vie Etudiante',
        'dgtitle' => '',
        'dgname' => '',
        'dgemail' => '',
        'visaplace' => '',
        'ccemname' => '',
        'ccememail' => '',
        'ucbltitle' => '',
        'ucblname' => '',
        'ucblemail' => '',
    ];
    // Agents DEVE signataires : l'un d'eux signe le visa du sujet et l'agrément administratif une fois
    // que l'équipe de la DEVE a tout rempli et vérifié.
    $settings->add(new admin_setting_configtextarea(
        'mod_these/deveagent',
        get_string('settingsdeveagent', 'mod_these'),
        get_string('settingsdeveagent_desc', 'mod_these'),
        '',
        PARAM_TEXT,
        '60',
        '3'
    ));
    foreach ($texts as $name => $default) {
        $type = in_array($name, ['dgemail', 'ccememail', 'ucblemail'], true) ? PARAM_EMAIL : PARAM_TEXT;
        $settings->add(new admin_setting_configtext(
            'mod_these/' . $name,
            get_string('settings' . $name, 'mod_these'),
            get_string('settings' . $name . '_desc', 'mod_these'),
            $default,
            $type
        ));
    }
}
