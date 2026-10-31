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
 * Index unique du numéro DEVE : à lancer une fois corrigés les doublons signalés par la mise à jour
 * 2026103100 (numéros attribués à plusieurs dossiers du site). Liste les doublons restants, ou rend
 * l'index unique s'il n'y en a plus.
 *
 * Usage : php mod/these/cli/devenumber_index.php
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/these/db/upgradelib.php');

[$options, $unrecognised] = cli_get_params(['help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help']) {
    echo "Index unique du numéro d'enregistrement DEVE : liste les numéros attribués à plusieurs dossiers\n"
        . "du site ou, s'il n'y en a plus, rend l'index unique. Code de sortie 1 s'il reste des doublons.\n\n"
        . "Options :\n"
        . "  -h, --help  Cette aide.\n";
    exit(0);
}

$duplicates = these_upgrade_devenumber_index();
if ($duplicates) {
    echo these_upgrade_devenumber_report($duplicates) . "\n";
    exit(1);
}
echo "Aucun numéro DEVE en double : l'index du numéro est unique.\n";
exit(0);
