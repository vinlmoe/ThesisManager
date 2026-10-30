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
 * Fournisseurs de messages de mod_these : une famille par type de courriel (voir
 * \mod_these\local\messaging::providers()). Les destinataires qui ont un compte Moodle reçoivent
 * les notifications par l'API de messagerie, selon leurs préférences ; par défaut, courriel et
 * notification à l'écran.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$defaults = [
    'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
    'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
];

$messageproviders = [
    // Dossier à traiter à l'étape courante, et relance.
    'dossierawaiting' => ['defaults' => $defaults],
    // Changement d'état du dossier : renvoi, sujet enregistré, clôture, ajournement, changement de
    // sujet, demande ou proposition de date.
    'statuschanged' => ['defaults' => $defaults],
    // Signature demandée ou refusée (agents DEVE signataires).
    'signaturerequest' => ['defaults' => $defaults],
    // Convocation, désignation du jury, envoi à l'université.
    'juryconvocation' => ['defaults' => $defaults],
    // Récapitulatif hebdomadaire de la DEVE.
    'digest' => ['defaults' => $defaults],
];
