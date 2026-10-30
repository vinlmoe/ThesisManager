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

use coding_exception;
use context;
use core_text;
use stdClass;

/**
 * Production des documents : PDF d'un modèle, PDF d'un document signé (scellé ou produit à la
 * demande), assemblage du contrat de diffusion avec le contrat type (FPDI), contrôle d'un modèle
 * et des conclusions.
 *
 * Les fonctions these_*() correspondantes de locallib/doctemplates.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documentrenderer {
    /** Longueur maximale des conclusions, espaces compris : elles doivent tenir sur une page avec les signatures. */
    public const CONCLUSIONS_MAX = 2200;

    /**
     * Vérifie que des conclusions tiennent sur une page, signatures comprises : longueur maximale,
     * puis mise en page réelle avec le modèle de l'activité.
     *
     * @param stdClass $these
     * @param context|null $context
     * @param string $text
     * @return string|null Message d'erreur, ou null si elles tiennent.
     */
    public static function conclusions_error(stdClass $these, ?context $context, $text) {
        $length = core_text::strlen(trim((string) $text));
        if ($length > self::CONCLUSIONS_MAX) {
            return get_string('conclusionstoolong', 'mod_these', (object) ['max' => self::CONCLUSIONS_MAX, 'length' => $length]);
        }
        $data = documentsamples::sample_document_context(doctemplates::settings_scope($these), $these);
        $data['conclusions'] = documentcontext::conclusions_context((string) $text);
        try {
            $pages = \mod_these\pdf\html_document::page_count(self::render_doctemplate($these, 'conclusions', $data));
        } catch (\Throwable $e) {
            // Mise en page impossible (modèle de l'activité défaillant) : seule la longueur est vérifiée.
            debugging('Mise en page des conclusions impossible : ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
        return $pages > 1 ? get_string('conclusionsonepage', 'mod_these') : null;
    }

    /**
     * Produit le PDF d'un modèle de document, avec le modèle en vigueur pour l'activité (voir
     * these_settings_scope()).
     *
     * @param stdClass $these Activité.
     * @param string $type Voir these_doctemplate_types().
     * @param array $data Voir these_build_document_context().
     * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
     * @return string Contenu PDF.
     */
    public static function render_doctemplate(stdClass $these, $type, array $data, $template = null) {
        return self::render_scope_doctemplate(doctemplates::settings_scope($these), $type, $data, $template);
    }

    /**
     * Produit le PDF d'un modèle de document d'une portée.
     *
     * @param stdClass|null $scope Voir these_settings_scope() ; null : modèle d'origine.
     * @param string $type
     * @param array $data
     * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
     * @return string Contenu PDF.
     */
    public static function render_scope_doctemplate(?stdClass $scope, $type, array $data, $template = null) {
        return \mod_these\pdf\html_document::render(
            $template ?? ($scope ? doctemplates::get_doctemplate($scope, $type) : doctemplates::doctemplate_default($type)),
            $data,
            doctemplates::doctemplate_label($type)
        );
    }

    /**
     * Produit le PDF d'un document signé.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @return string Contenu PDF.
     */
    public static function signed_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
        if (!array_key_exists($doctype, signatures::document_definitions())) {
            throw new coding_exception('Document inconnu : ' . $doctype);
        }
        // Document entièrement signé : le PDF scellé fait foi (scellé au besoin pour un document signé
        // avant l'apparition des scellés).
        $sealed = seals::seal_document($context, $dossier, $doctype);
        if ($sealed) {
            return $sealed->get_content();
        }
        return self::render_document_pdf($these, $context, $dossier, $doctype);
    }

    /**
     * Produit le PDF d'un document signé à partir de son modèle en vigueur, sans tenir compte du
     * scellé.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @return string Contenu PDF.
     */
    public static function render_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
        $data = documentcontext::document_context($these, $context, $dossier, $doctype);
        if ($doctype !== 'contract') {
            return self::render_doctemplate($these, $doctype, $data);
        }

        // Contrat de diffusion, comme les conventions de mod_stage : page de données et de choix,
        // contrat type officiel déposé dans les paramètres, page de signatures.
        $template = doctemplates::get_contract_template($these);
        if ($template !== null) {
            try {
                return self::merge_pdfs([
                    self::render_doctemplate($these, 'contractdata', $data),
                    $template,
                    self::render_doctemplate($these, 'contractsign', $data),
                ]);
            } catch (\Throwable $e) {
                // Contrat type illisible par l'analyseur libre de FPDI (certaines compressions récentes) :
                // les pages du module restent produites, avec un avertissement à la place du contrat, et
                // le document n'est pas scellé (voir these_contract_template_problem()).
                debugging('Contrat type de diffusion illisible : ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $data['contract']['templatemissing'] = true;
        return self::merge_pdfs([
            self::render_doctemplate($these, 'contractdata', $data),
            self::render_doctemplate($these, 'contractsign', $data),
        ]);
    }

    /**
     * Problème du contrat type de diffusion déposé dans les paramètres de l'activité, qui empêche de
     * produire le contrat complet : sans lui, le contrat n'est pas scellé et la DEVE en est avertie.
     *
     * @param stdClass $these
     * @return string|null 'contracttemplatemissing', 'contracttemplateunreadable' ou null.
     */
    public static function contract_template_problem(stdClass $these) {
        $template = doctemplates::get_contract_template($these);
        if ($template === null) {
            return 'contracttemplatemissing';
        }
        try {
            self::merge_pdfs([$template]);
        } catch (\Throwable $e) {
            // Le détail de l'erreur est tracé quand le contrat est produit (voir render_document_pdf()) ;
            // cette vérification, faite à chaque affichage des documents signés et avant chaque scellé, n'en dit
            // pas plus.
            return 'contracttemplateunreadable';
        }
        return null;
    }

    /**
     * Vérifie qu'un modèle produit un document avec les données d'exemple.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param string $type
     * @param string $content
     * @param stdClass|null $activity Activité dont reprendre l'établissement dans l'exemple.
     * @return string|null Message d'erreur, ou null si le modèle est utilisable.
     */
    public static function doctemplate_error(stdClass $scope, $type, $content, ?stdClass $activity = null) {
        try {
            $data = documentsamples::doctemplate_sample_context($scope, $type, $activity);
            self::render_scope_doctemplate($scope, $type, $data, $content);
        } catch (\Throwable $e) {
            return get_string('doctplerror', 'mod_these', $e->getMessage());
        }
        return null;
    }

    /**
     * Assemble plusieurs PDF en un seul, page à page et au format de chaque page (FPDI, fournie dans
     * thirdparty/, comme dans mod_stage).
     *
     * @param string[] $sources Contenus PDF, dans l'ordre.
     * @return string Contenu PDF assemblé.
     */
    public static function merge_pdfs(array $sources) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->dirroot . '/mod/these/thirdparty/vendor/autoload.php');

        $merger = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $merger->setPrintHeader(false);
        $merger->setPrintFooter(false);
        foreach ($sources as $source) {
            $pagecount = $merger->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($source));
            for ($pageno = 1; $pageno <= $pagecount; $pageno++) {
                $tplidx = $merger->importPage($pageno);
                $size = $merger->getTemplateSize($tplidx);
                $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merger->useTemplate($tplidx);
            }
        }
        return $merger->Output('', 'S');
    }
}
