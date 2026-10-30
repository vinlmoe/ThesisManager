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
use moodle_url;
use stdClass;

/**
 * Signatures préalables à chaque étape : un dossier ne passe pas à l'étape suivante tant que les
 * documents des étapes précédentes ne sont pas signés.
 *
 * Pour chaque statut, la liste des signatures qui doivent être apposées avant de le quitter (validation
 * de l'étape, transmission des documents, procès-verbal). Les signataires de l'université (présidence
 * de l'université, CCEM) signent sur papier les pièces scellées qui leur sont envoyées, en ligne ou
 * par délégation (voir university) : leurs signatures ne sont exigées qu'au procès-verbal (sur
 * papier, une fois les scans revenus et déposés). Seuls comptent les documents dont les données sont saisies.
 *
 * Le blocage s'applique aux pages (review.php, documents.php, minutes.php), pas aux fonctions du
 * circuit elles-mêmes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prerequisites {
    /** Emplacements des signataires de l'université, qui signent sur papier les pièces scellées. */
    const UNIVERSITY_SLOTS = ['ucbl', 'ccem'];

    /** Tous les emplacements du document. */
    const ALL = '*';

    /** Tous les emplacements du document, sauf ceux de l'université. */
    const BUT_UNIVERSITY = '-university';

    /**
     * Signatures à apposer avant de quitter un statut : document => emplacements (liste, ou ALL,
     * ou BUT_UNIVERSITY).
     *
     * @param int $status
     * @return array
     */
    public static function required($status) {
        $defense = [
            'bordereau' => self::ALL,
            'grid' => self::ALL,
            'agreement' => self::BUT_UNIVERSITY,
            'signaletique' => self::BUT_UNIVERSITY,
            'contract' => self::BUT_UNIVERSITY,
            'conclusions' => self::BUT_UNIVERSITY,
        ];
        switch ((int) $status) {
            case THESE_STATUS_SUBJECT_SUBMITTED:
                return ['subject' => ['student']];
            case THESE_STATUS_SUBJECT_APPROVED:
                return ['subject' => ['student', 'director', 'extdirector']];
            case THESE_STATUS_SUBJECT_REGISTERED:
                return ['subject' => self::ALL];
            case THESE_STATUS_DEFENSE_SUBMITTED:
                return ['bordereau' => self::ALL, 'contract' => ['student']];
            case THESE_STATUS_DEFENSE_APPROVED:
                return ['bordereau' => self::ALL, 'grid' => self::ALL,
                    'agreement' => ['director'], 'contract' => ['student', 'director'], 'conclusions' => ['director']];
            case THESE_STATUS_DEFENSE_VALIDATED:
                return $defense;
            case THESE_STATUS_CONFORM:
                return $defense + ['conformity' => self::ALL];
            case THESE_STATUS_SCHEDULED:
                // Procès-verbal : tout est signé, université comprise. Les corrections qui suivent
                // n'attendent plus aucun document.
                return array_fill_keys(array_keys($defense), self::ALL) + ['conformity' => self::ALL];
        }
        return [];
    }

    /**
     * Signatures encore manquantes pour quitter le statut actuel du dossier.
     *
     * @param stdClass $dossier
     * @return array Liste de ['doctype' => string, 'slot' => string, 'requesttime' => int].
     */
    public static function missing(stdClass $dossier) {
        $signatures = signatures::get_signatures($dossier->id);
        $missing = [];
        foreach (self::required($dossier->status) as $doctype => $required) {
            if (!signatures::document_available($dossier, $doctype)) {
                continue;
            }
            foreach (signatures::document_slots($dossier, $doctype) as $slot) {
                $wanted = $required === self::ALL
                    || ($required === self::BUT_UNIVERSITY && !in_array($slot, self::UNIVERSITY_SLOTS, true))
                    || (is_array($required) && in_array($slot, $required, true));
                $signature = $signatures[$doctype . ':' . $slot] ?? null;
                if ($wanted && empty($signature->timesigned)) {
                    $missing[] = ['doctype' => $doctype, 'slot' => $slot, 'requesttime' => (int) ($signature->requesttime ?? 0)];
                }
            }
        }
        return $missing;
    }

    /**
     * Dossiers bloqués par des signatures manquantes, toutes activités confondues (ou une seule) :
     * pour préparer la mise en service du blocage, ou pour le suivi de la DEVE.
     *
     * @param int $theseid Activité, 0 pour toutes.
     * @return array Liste de {dossier, these, student, missing (voir missing())}, par activité puis dossier.
     */
    public static function blocked_dossiers($theseid = 0) {
        global $DB;

        $conditions = $theseid ? ['theseid' => (int) $theseid] : [];
        $blocked = [];
        $activities = [];
        foreach ($DB->get_records('these_dossier', $conditions, 'theseid ASC, id ASC') as $dossier) {
            $missing = self::missing($dossier);
            if (!$missing) {
                continue;
            }
            if (!isset($activities[$dossier->theseid])) {
                $activities[$dossier->theseid] = $DB->get_record('these', ['id' => $dossier->theseid]);
            }
            $blocked[] = (object) [
                'dossier' => $dossier,
                'these' => $activities[$dossier->theseid],
                'student' => $DB->get_record('user', ['id' => $dossier->userid]) ?: null,
                'missing' => $missing,
            ];
        }
        return $blocked;
    }

    /**
     * Indique s'il ne manque, pour quitter le statut actuel, que des signatures de l'université.
     *
     * @param array $missing Voir missing().
     * @param string[]|null $slots Emplacements comptés (par défaut, tous ceux de l'université ; voir
     *                             university::paper_slots() pour ceux signés sur papier).
     * @return bool
     */
    public static function only_university(array $missing, ?array $slots = null) {
        $slots = $slots ?? self::UNIVERSITY_SLOTS;
        foreach ($missing as $item) {
            if (!in_array($item['slot'], $slots, true)) {
                return false;
            }
        }
        return (bool) $missing;
    }

    /**
     * Avertissement d'une étape bloquée : signatures manquantes et, pour la DEVE, liens vers le suivi
     * des signatures et le dépôt des scans.
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param array $missing Voir missing().
     * @return string HTML
     */
    public static function render_missing(stdClass $cm, context $context, stdClass $dossier, array $missing) {
        global $DB, $PAGE, $USER;

        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $items = [];
        foreach ($missing as $item) {
            $label = signatures::document_label($item['doctype']) . ' — ' . signatures::slot_label($item['slot']);
            if ($item['requesttime']) {
                $label .= ' (' . get_string('signaturerequestedon', 'mod_these', userdate($item['requesttime'], $dateformat)) . ')';
            }
            $items[] = $label;
        }
        $paper = self::only_university($missing, university::paper_slots($these));
        $parts = [
            \mod_these\output\parts::strong(get_string($paper ? 'prerequisitesuniversity' : 'prerequisitesmissing', 'mod_these')),
            \mod_these\output\parts::html($PAGE->get_renderer('mod_these')->render(new \mod_these\output\bullet_list($items))),
        ];
        if (rights::get_rights($context, $dossier, $USER->id)->deve) {
            $params = ['id' => $cm->id, 'dossierid' => $dossier->id];
            $parts[] = \mod_these\output\parts::link(
                new moodle_url('/mod/these/signatures.php', $params),
                get_string('managesignatures', 'mod_these'),
                'btn btn-sm btn-secondary mr-1'
            );
            if (scans::allowed($dossier)) {
                $parts[] = \mod_these\output\parts::link(
                    new moodle_url('/mod/these/scan.php', $params),
                    get_string('addsignedscan', 'mod_these'),
                    'btn btn-sm btn-secondary'
                );
            }
        }
        return $PAGE->get_renderer('mod_these')->render(new \mod_these\output\message($parts, 'alert alert-warning'));
    }
}
