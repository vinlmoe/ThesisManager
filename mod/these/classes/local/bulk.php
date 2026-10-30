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
use stdClass;

/**
 * Actions groupées de la DEVE sur les dossiers cochés au tableau de bord : relance des signataires,
 * génération des documents de soutenance, accès du jury, validation de l'A6. Chaque action ne s'applique qu'aux dossiers
 * dont l'étape s'y prête ; les autres sont comptés comme ignorés.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk {
    /**
     * Actions proposées, dans l'ordre de la liste.
     *
     * @return array clé => libellé
     */
    public static function actions() {
        return [
            'remind' => get_string('bulkremind', 'mod_these'),
            'generate' => get_string('bulkgenerate', 'mod_these'),
            'notifyjury' => get_string('bulknotifyjury', 'mod_these'),
        ];
    }

    /**
     * Applique une action à un dossier.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param string $action Voir actions().
     * @param int $userid Compte de la DEVE.
     * @return bool L'action a porté (relance envoyée, documents générés, membre prévenu).
     */
    public static function apply(
        stdClass $these,
        stdClass $cm,
        context $context,
        stdClass $dossier,
        $action,
        $userid
    ) {
        $status = (int) $dossier->status;
        switch ($action) {
            case 'remind':
                // Signatures attendues des agents DEVE et des signataires sans compte.
                $available = array_values(array_filter(
                    array_keys(signatures::document_definitions()),
                    function ($doctype) use ($dossier) {
                        return signatures::document_available($dossier, $doctype);
                    }
                ));
                if (!$available || $status === THESE_STATUS_CANCELLED) {
                    return false;
                }
                $sent = signatureagent::request_agent_signatures($these, $cm, $dossier, $available) > 0;
                foreach (signatures::external_slots() as $slot) {
                    // Seules les demandes déjà émises par le circuit sont relancées : un nouveau lien n'est pas
                    // envoyé au directeur extérieur avant la validation du sujet par le directeur.
                    $requested = signaturetokens::requested_documents_for_slot($dossier, $slot, $available);
                    if ($requested) {
                        $sent = signaturetokens::request_signature($these, $cm, $dossier, $slot, $requested) !== null || $sent;
                    }
                }
                return $sent;
            case 'generate':
                if (!in_array($status, [THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED], true)) {
                    return false;
                }
                documents::generate_documents($these, $context, $dossier, $userid);
                return true;
            case 'notifyjury':
                if ($status < THESE_STATUS_DEFENSE_VALIDATED || $status > THESE_STATUS_SCHEDULED) {
                    return false;
                }
                return juryaccess::notify_jury($these, $cm, $dossier)->sent > 0;
        }
        return false;
    }
}
