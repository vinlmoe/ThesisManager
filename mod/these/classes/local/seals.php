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
 * Scellés des documents signés : empreinte de la mise en forme (modèles, images, contrat type),
 * PDF conservé une fois toutes les signatures apposées, retrait du scellé.
 *
 * Les fonctions these_*() correspondantes de locallib/signatures.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seals {
    /**
     * Empreinte SHA-256 de la mise en forme d'un document : modèles en vigueur, images qu'ils
     * appellent et, pour le contrat de diffusion, contrat type déposé dans les paramètres.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return string
     */
    public static function document_template_hash(stdClass $dossier, $doctype) {
        global $DB;

        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $scope = doctemplates::settings_scope($these);
        $types = array_keys(array_filter(doctemplates::doctemplate_types(), function ($signed) use ($doctype) {
            return $signed === $doctype;
        }));
        $parts = [];
        $images = doctemplates::doctemplate_images($scope);
        foreach ($types as $type) {
            $template = doctemplates::get_doctemplate($scope, $type);
            $parts[] = $template;
            foreach ($images as $name => $source) {
                if (strpos($template, 'img.' . $name) !== false) {
                    $parts[] = $name . ':' . sha1($source);
                }
            }
        }
        if ($doctype === 'contract') {
            $parts[] = sha1((string) doctemplates::get_contract_template($these));
        }
        return hash('sha256', implode("\n", $parts));
    }

    /**
     * Indique si la mise en forme d'un document a changé depuis une signature. Un document scellé
     * ne dépend plus de son modèle ; une signature antérieure à l'enregistrement de cette empreinte
     * n'est pas remise en cause.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass $signature
     * @return bool
     */
    public static function signature_template_changed(context $context, stdClass $dossier, stdClass $signature) {
        if (empty($signature->templatehash) || self::get_sealed_document($context, $dossier->id, $signature->doctype)) {
            return false;
        }
        return $signature->templatehash !== self::document_template_hash($dossier, $signature->doctype);
    }

    /**
     * Indique si tous les emplacements de signature d'un document sont signés.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return bool
     */
    public static function document_fully_signed(stdClass $dossier, $doctype) {
        $signatures = signatures::get_signatures($dossier->id);
        foreach (signatures::document_slots($dossier, $doctype) as $slot) {
            if (empty($signatures[$doctype . ':' . $slot]->timesigned)) {
                return false;
            }
        }
        return true;
    }

    /**
     * PDF scellé d'un document.
     *
     * @param context $context
     * @param int $dossierid
     * @param string $doctype
     * @return stored_file|null
     */
    public static function get_sealed_document(context $context, $dossierid, $doctype) {
        $file = get_file_storage()->get_file($context->id, 'mod_these', 'sealed', $dossierid, '/', $doctype . '.pdf');
        return $file ?: null;
    }

    /**
     * Scelle un document dont toutes les signatures sont apposées : le PDF est produit une dernière
     * fois et conservé tel quel. Il ne dépend plus ensuite ni des modèles ni du contrat type, et son
     * empreinte SHA-256, calculée sur ses octets, est inscrite à l'historique du dossier.
     *
     * Une signature dont la mise en forme a changé depuis qu'elle a été apposée est d'abord invalidée
     * (voir these_refresh_signatures()) : le document n'est alors pas scellé.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @return stored_file|null Document scellé, null s'il attend encore une signature.
     */
    public static function seal_document(context $context, stdClass $dossier, $doctype) {
        global $DB;

        if ($sealed = self::get_sealed_document($context, $dossier->id, $doctype)) {
            return $sealed;
        }
        if (
            !self::document_fully_signed($dossier, $doctype)
                || ($doctype === 'contract'
                    && documentrenderer::contract_template_problem($DB->get_record('these', ['id' => $dossier->theseid])) !== null)
        ) {
            return null;
        }
        signatures::refresh_signatures($context, $dossier);
        if (!self::document_fully_signed($dossier, $doctype)) {
            return null;
        }
        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $pdf = documentrenderer::render_document_pdf($these, $context, $dossier, $doctype);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => 'sealed',
            'itemid' => $dossier->id,
            'filepath' => '/',
            'filename' => $doctype . '.pdf',
        ], $pdf);
        dossiers::log(
            $dossier->id,
            0,
            'documentsealed',
            $dossier->status,
            $dossier->status,
            signatures::document_label($doctype) . ' — SHA-256 ' . hash('sha256', $pdf)
        );
        return $file;
    }

    /**
     * Retire le scellé d'un document dont une signature disparaît : il sera scellé de nouveau une
     * fois toutes ses signatures apposées.
     *
     * @param context $context
     * @param int $dossierid
     * @param string $doctype
     * @return void
     */
    public static function unseal_document(context $context, $dossierid, $doctype) {
        if ($file = self::get_sealed_document($context, $dossierid, $doctype)) {
            $file->delete();
        }
        // La pièce scellée pour l'université ne vaut plus non plus.
        university::unseal($context, $dossierid, $doctype);
    }
}
