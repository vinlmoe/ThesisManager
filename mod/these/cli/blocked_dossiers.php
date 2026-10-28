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
 * Dossiers bloqués par des signatures manquantes (voir \mod_these\local\prerequisites) : à lancer
 * après la mise à jour qui introduit le blocage, pour que la DEVE fasse signer ou dépose les scans
 * des dossiers en cours.
 *
 * Usage : php mod/these/cli/blocked_dossiers.php [--these=ID] [--csv]
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

[$options, $unrecognised] = cli_get_params(
    ['these' => 0, 'csv' => false, 'help' => false],
    ['h' => 'help']
);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help']) {
    echo "Dossiers bloqués par des signatures manquantes.\n\n"
        . "Options :\n"
        . "  --these=ID  Seulement l'activité « Gestion des thèses » d'identifiant ID (table these).\n"
        . "  --csv       Sortie CSV (point-virgule), une ligne par signature manquante.\n"
        . "  -h, --help  Cette aide.\n";
    exit(0);
}

$blocked = \mod_these\local\prerequisites::blocked_dossiers((int) $options['these']);
$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$requested = function (array $item) use ($dateformat) {
    return $item['requesttime'] ? userdate($item['requesttime'], $dateformat) : '';
};

if ($options['csv']) {
    $out = fopen('php://output', 'w');
    fputcsv($out, ['activite', 'dossier', 'etudiant', 'statut', 'document', 'signataire', 'demandee'], ';');
    foreach ($blocked as $row) {
        foreach ($row->missing as $item) {
            fputcsv($out, [
                format_string($row->these->name),
                $row->dossier->id,
                $row->student ? fullname($row->student) : '',
                these_status_label($row->dossier->status),
                these_document_label($item['doctype']),
                these_slot_label($item['slot']),
                $requested($item),
            ], ';');
        }
    }
    fclose($out);
    exit(0);
}

if (!$blocked) {
    cli_writeln('Aucun dossier bloqué.');
    exit(0);
}
$count = 0;
foreach ($blocked as $row) {
    cli_writeln(sprintf(
        '%s — dossier %d — %s — %s',
        format_string($row->these->name),
        $row->dossier->id,
        $row->student ? fullname($row->student) : '-',
        these_status_label($row->dossier->status)
    ));
    foreach ($row->missing as $item) {
        $when = $requested($item);
        cli_writeln('    ' . these_document_label($item['doctype']) . ' — ' . these_slot_label($item['slot'])
            . ($when !== '' ? ' (demandée le ' . $when . ')' : ' (jamais demandée)'));
        $count++;
    }
}
cli_writeln('');
cli_writeln(count($blocked) . ' dossier(s) bloqué(s), ' . $count . ' signature(s) manquante(s).');
