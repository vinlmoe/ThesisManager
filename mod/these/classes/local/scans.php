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
 * Scans des documents signés hors du module (signataires sans compte, signature sur papier).
 *
 * Le scan est joint au dossier pour conservation, et chaque dépôt est inscrit à l'historique avec
 * l'empreinte SHA-256 de chaque fichier. Les signataires que la DEVE coche comme couverts par le scan
 * sont inscrits comme ayant signé à la main à la date portée sur le document (méthode « scan ») : le
 * PDF du document et le suivi des signatures en tiennent compte.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scans {
    /** Documents qui reçoivent une signature hors module (voir these_document_definitions()). */
    const DOCTYPES = ['subject', 'agreement', 'signaletique', 'contract', 'conclusions'];

    /**
     * Un scan se dépose une fois le dossier validé par la DEVE, tant qu'il n'est pas abandonné.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function allowed(stdClass $dossier) {
        return (int) $dossier->status >= THESE_STATUS_DEFENSE_VALIDATED
            && (int) $dossier->status !== THESE_STATUS_CANCELLED;
    }

    /**
     * Types de document proposés au dépôt, libellés.
     *
     * @return array clé => libellé
     */
    public static function doctype_options() {
        $options = [];
        foreach (self::DOCTYPES as $doctype) {
            $options[$doctype] = get_string('scandoctype' . $doctype, 'mod_these');
        }
        return $options;
    }

    /**
     * Signatures encore attendues, par document proposé au dépôt d'un scan : emplacements non signés
     * des documents dont les données sont saisies.
     *
     * @param stdClass $dossier
     * @return array doctype => [emplacement => libellé]
     */
    public static function pending_slots(stdClass $dossier) {
        $pending = [];
        foreach (self::DOCTYPES as $doctype) {
            if (!these_document_available($dossier, $doctype)) {
                continue;
            }
            foreach (these_document_slots($dossier, $doctype) as $slot) {
                if (in_array($doctype, these_pending_documents_for_slot($dossier, $slot, [$doctype]), true)) {
                    $pending[$doctype][$slot] = these_slot_label($slot);
                }
            }
        }
        return $pending;
    }

    /**
     * Nom imprimé pour un signataire couvert par un scan. Le nom saisi au dépôt quand un seul
     * signataire est coché ou que la signature est donnée par délégation (c'est celui de la personne
     * qui a signé) ; sinon celui que le module connaît pour l'emplacement (étudiant, directeur,
     * signataires des paramètres, président du jury), à défaut le nom saisi.
     *
     * @param stdClass $dossier
     * @param string $slot
     * @param string $typed Nom saisi au dépôt.
     * @param bool $usetyped Seul signataire coché, ou signature par délégation.
     * @return string
     */
    public static function slot_signer_name(stdClass $dossier, $slot, $typed, $usetyped = false) {
        global $DB;

        $fallback = trim($typed);
        if ($usetyped && $fallback !== '') {
            return $fallback;
        }
        $userid = ['student' => $dossier->userid, 'director' => $dossier->directorid][$slot] ?? 0;
        if ($userid && ($user = $DB->get_record('user', ['id' => $userid]))) {
            return fullname($user);
        }
        $name = trim((string) (these_external_signers($dossier)[$slot]['name'] ?? ''));
        return $name !== '' ? $name : $fallback;
    }

    /**
     * Joint les fichiers du brouillon à la zone du dossier, nommés par date, document et signataire,
     * et inscrit le dépôt à l'historique avec l'empreinte de chaque fichier.
     *
     * @param context $context Contexte du module.
     * @param stdClass $dossier
     * @param int $userid Auteur du dépôt.
     * @param string $doctype Clé de self::DOCTYPES.
     * @param string $signer Nom du signataire.
     * @param int $signeddate Date de la signature.
     * @param int $draftitemid Zone de brouillon du gestionnaire de fichiers.
     * @param string $remark Commentaire facultatif.
     * @param array $slots Emplacements couverts par le scan : emplacement => signé par délégation.
     * @return int Nombre de fichiers joints (aucune signature inscrite si aucun fichier).
     */
    public static function add(
        context $context,
        stdClass $dossier,
        $userid,
        $doctype,
        $signer,
        $signeddate,
        $draftitemid,
        $remark = '',
        array $slots = []
    ) {
        $fs = get_file_storage();
        $usercontext = \context_user::instance($userid);
        $drafts = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);

        // Nom lisible par un système d'archivage : date, document, signataire en minuscules sans accents.
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', \core_text::strtolower(\core_text::specialtoascii($signer))), '-');
        $prefix = userdate($signeddate, '%Y-%m-%d') . '-' . $doctype . ($slug !== '' ? '-' . $slug : '');
        $hashes = [];
        foreach ($drafts as $draft) {
            $filename = clean_filename($prefix . '-' . $draft->get_filename());
            if ($fs->file_exists($context->id, 'mod_these', 'signedscan', $dossier->id, '/', $filename)) {
                $filename = clean_filename($prefix . '-' . uniqid() . '-' . $draft->get_filename());
            }
            $stored = $fs->create_file_from_storedfile([
                'contextid' => $context->id,
                'component' => 'mod_these',
                'filearea' => 'signedscan',
                'itemid' => $dossier->id,
                'filepath' => '/',
                'filename' => $filename,
            ], $draft);
            $hashes[] = $filename . ' : ' . hash('sha256', $stored->get_content());
        }
        if (!$hashes) {
            return 0;
        }

        $logremark = get_string('scanlogremark', 'mod_these', (object) [
            'doctype' => get_string('scandoctype' . $doctype, 'mod_these'),
            'signer' => $signer,
            'files' => implode(' ; ', $hashes),
        ]);
        $remark = trim((string) $remark);
        if ($remark !== '') {
            $logremark .= ' ' . $remark;
        }
        dossiers::log($dossier->id, $userid, 'signedscan', $dossier->status, $dossier->status, $logremark);

        // Signataires couverts : signés à la main, à la date du document, enregistrés par la DEVE.
        $pending = self::pending_slots($dossier)[$doctype] ?? [];
        foreach ($slots as $slot => $delegated) {
            if (!isset($pending[$slot])) {
                continue;
            }
            $delegated = $slot === 'dg' && $delegated;
            $name = self::slot_signer_name($dossier, $slot, $signer, $delegated || count($slots) === 1);
            these_sign_document($context, $dossier, $doctype, $slot, 0, $name, '', '', [
                'method' => 'scan',
                'delegated' => $delegated,
                'verifiedby' => $userid,
                'time' => $signeddate,
            ]);
        }
        return count($hashes);
    }
}
