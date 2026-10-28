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
 * Fonctions métier internes pour mod_these.
 *
 * Le circuit suit le document de travail « Circuit de dépôt du sujet et de la soutenance » :
 *
 * Phase 1 — sujet : l'étudiant dépose sa fiche de sujet, le directeur la valide (ou la renvoie),
 * la DEVE l'enregistre (numéro), la bibliothèque l'inscrit au référentiel documentaire. Le dossier
 * reste ensuite actif et « dormant » jusqu'au dépôt des documents de soutenance.
 *
 * Phase 2 — soutenance : l'étudiant dépose manuscrit et proposition de date et de jury, le
 * directeur valide ET joint son rapport en une seule étape, la DEVE valide le dossier complet, la
 * bibliothèque atteste la conformité du manuscrit, la DEVE génère et transmet les documents de
 * soutenance, puis dépose le procès-verbal, ce qui clôt le dossier.
 *
 * Chaque étape de validation peut renvoyer le dossier : à l'étudiant (qui le corrige et le soumet
 * de nouveau, le dossier revenant alors directement à l'étape qui l'avait renvoyé) ou, pour la
 * DEVE, au directeur de thèse.
 *
 * Le code métier est dans des classes autochargées, une par domaine (mod_these\local\workflow,
 * rights, dossiers, jury, signatures, doctemplates, planning...). Les fonctions these_*() de
 * locallib/, chargées par ce fichier, en sont des façades d'une ligne gardées pour les appelants
 * existants ; ce fichier reste le seul à inclure (il définit aussi les constantes).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// send_file() et les fonctions de fichiers : chargées par require_login() avec un cours, mais pas
// sur les pages hors cours (« Mes signatures ») ni sur la page de signature par lien, sans connexion.
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/mod/these/lib.php');

require_once($CFG->dirroot . '/mod/these/locallib/workflow.php');
require_once($CFG->dirroot . '/mod/these/locallib/rights.php');
require_once($CFG->dirroot . '/mod/these/locallib/dossiers.php');
require_once($CFG->dirroot . '/mod/these/locallib/jury.php');
require_once($CFG->dirroot . '/mod/these/locallib/files.php');
require_once($CFG->dirroot . '/mod/these/locallib/notifications.php');
require_once($CFG->dirroot . '/mod/these/locallib/documents.php');
require_once($CFG->dirroot . '/mod/these/locallib/rendering.php');
require_once($CFG->dirroot . '/mod/these/locallib/import.php');
require_once($CFG->dirroot . '/mod/these/locallib/signatures.php');
require_once($CFG->dirroot . '/mod/these/locallib/doctemplates.php');
require_once($CFG->dirroot . '/mod/these/locallib/settingstransfer.php');
require_once($CFG->dirroot . '/mod/these/locallib/questionnaire.php');
require_once($CFG->dirroot . '/mod/these/locallib/signatureagent.php');
require_once($CFG->dirroot . '/mod/these/locallib/subjectchange.php');
require_once($CFG->dirroot . '/mod/these/locallib/planning.php');
