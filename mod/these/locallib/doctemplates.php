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
 * Modèles des documents et production des PDF.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Modèles des documents.

/**
 * Voir \mod_these\local\doctemplates::doctemplate_types().
 *
 * @return array type de modèle => document signé dont il reprend les signatures (null : aucun).
 */
function these_doctemplate_types() {
    return \mod_these\local\doctemplates::doctemplate_types();
}

/**
 * Voir \mod_these\local\doctemplates::doctemplate_label().
 *
 * @param string $type
 * @return string
 */
function these_doctemplate_label($type) {
    return \mod_these\local\doctemplates::doctemplate_label($type);
}

/**
 * Voir \mod_these\local\doctemplates::doctemplate_default().
 *
 * @param string $type
 * @return string
 */
function these_doctemplate_default($type) {
    return \mod_these\local\doctemplates::doctemplate_default($type);
}

/**
 * Voir \mod_these\local\doctemplates::local_scope().
 *
 * @param stdClass $these
 * @return stdClass {theseid, synthesisid, shared, component, contextid, cmid, name}
 */
function these_local_scope(stdClass $these) {
    return \mod_these\local\doctemplates::local_scope($these);
}

/**
 * Voir \mod_these\local\doctemplates::synthesis_scope().
 *
 * @param int $synthesisid
 * @return stdClass|null Voir these_local_scope().
 */
function these_synthesis_scope($synthesisid) {
    return \mod_these\local\doctemplates::synthesis_scope($synthesisid);
}

/**
 * Voir \mod_these\local\doctemplates::settings_scope().
 *
 * @param stdClass $these
 * @return stdClass Voir these_local_scope().
 */
function these_settings_scope(stdClass $these) {
    return \mod_these\local\doctemplates::settings_scope($these);
}

/**
 * Voir \mod_these\local\doctemplates::scope_conditions().
 *
 * @param stdClass $scope
 * @return array
 */
function these_scope_conditions(stdClass $scope) {
    return \mod_these\local\doctemplates::scope_conditions($scope);
}

/**
 * Voir \mod_these\local\doctemplates::get_doctemplate().
 *
 * @param stdClass $scope Voir these_settings_scope().
 * @param string $type
 * @return string
 */
function these_get_doctemplate(stdClass $scope, $type) {
    return \mod_these\local\doctemplates::get_doctemplate($scope, $type);
}

/**
 * Voir \mod_these\local\doctemplates::doctemplate_customised().
 *
 * @param stdClass $scope
 * @param string $type
 * @return bool
 */
function these_doctemplate_customised(stdClass $scope, $type) {
    return \mod_these\local\doctemplates::doctemplate_customised($scope, $type);
}

/**
 * Voir \mod_these\local\doctemplates::set_doctemplate().
 *
 * @param stdClass $scope
 * @param string $type
 * @param string|null $content
 * @return void
 */
function these_set_doctemplate(stdClass $scope, $type, $content) {
    return \mod_these\local\doctemplates::set_doctemplate($scope, $type, $content);
}

/**
 * Voir \mod_these\local\doctemplates::doctemplate_image_name().
 *
 * @param string $filename
 * @return string
 */
function these_doctemplate_image_name($filename) {
    return \mod_these\local\doctemplates::doctemplate_image_name($filename);
}

/**
 * Voir \mod_these\local\doctemplates::doctemplate_images().
 *
 * @param stdClass|null $scope null : images du module seules.
 * @return array nom => source pour TCPDF ('@' suivi du contenu en base64)
 */
function these_doctemplate_images(?stdClass $scope) {
    return \mod_these\local\doctemplates::doctemplate_images($scope);
}

/**
 * Voir \mod_these\local\documentcontext::document_sources().
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string|null $doctype Document signé dont reprendre les signatures (null : aucun).
 * @return array Voir these_build_document_context().
 */
function these_document_sources(stdClass $these, context $context, stdClass $dossier, $doctype) {
    return \mod_these\local\documentcontext::document_sources($these, $context, $dossier, $doctype);
}

/**
 * Voir \mod_these\local\documentcontext::document_context().
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string|null $doctype Document signé dont reprendre les signatures (null : aucun).
 * @param stdClass|null $recipient Destinataire d'une convocation : membre du jury, ou null pour
 *                                 l'étudiant.
 * @return array
 */
function these_document_context(stdClass $these, context $context, stdClass $dossier, $doctype, ?stdClass $recipient = null) {
    return \mod_these\local\documentcontext::document_context($these, $context, $dossier, $doctype, $recipient);
}

/**
 * Voir \mod_these\local\documentcontext::doctemplate_box().
 *
 * @param bool $checked
 * @return string HTML
 */
function these_doctemplate_box($checked) {
    return \mod_these\local\documentcontext::doctemplate_box($checked);
}

/**
 * Voir \mod_these\local\documentcontext::doctemplate_text().
 *
 * @param string|null $text
 * @return string HTML
 */
function these_doctemplate_text($text) {
    return \mod_these\local\documentcontext::doctemplate_text($text);
}

/**
 * Voir \mod_these\local\documentcontext::build_document_context().
 *
 * @param array $src {these, dossier, student, director, jury, signatures, recipient, scope}, voir
 *                   these_document_sources().
 * @return array
 */
function these_build_document_context(array $src) {
    return \mod_these\local\documentcontext::build_document_context($src);
}

/** Alias de \mod_these\local\documentrenderer::CONCLUSIONS_MAX. */
define('THESE_CONCLUSIONS_MAX', \mod_these\local\documentrenderer::CONCLUSIONS_MAX);

/**
 * Voir \mod_these\local\documentcontext::signature_caption().
 *
 * @param array $signature
 * @return string
 */
function these_signature_caption(array $signature) {
    return \mod_these\local\documentcontext::signature_caption($signature);
}

/**
 * Voir \mod_these\local\documentcontext::conclusions_context().
 *
 * @param string $text
 * @return array {html, bodyhtml, lasthtml}
 */
function these_conclusions_context($text) {
    return \mod_these\local\documentcontext::conclusions_context($text);
}

/**
 * Voir \mod_these\local\documentrenderer::conclusions_error().
 *
 * @param stdClass $these
 * @param context|null $context
 * @param string $text
 * @return string|null Message d'erreur, ou null si elles tiennent.
 */
function these_conclusions_error(stdClass $these, ?context $context, $text) {
    return \mod_these\local\documentrenderer::conclusions_error($these, $context, $text);
}

/**
 * Voir \mod_these\local\documentrenderer::render_doctemplate().
 *
 * @param stdClass $these Activité.
 * @param string $type Voir these_doctemplate_types().
 * @param array $data Voir these_build_document_context().
 * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
 * @return string Contenu PDF.
 */
function these_render_doctemplate(stdClass $these, $type, array $data, $template = null) {
    return \mod_these\local\documentrenderer::render_doctemplate($these, $type, $data, $template);
}

/**
 * Voir \mod_these\local\documentrenderer::render_scope_doctemplate().
 *
 * @param stdClass|null $scope Voir these_settings_scope() ; null : modèle d'origine.
 * @param string $type
 * @param array $data
 * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
 * @return string Contenu PDF.
 */
function these_render_scope_doctemplate(?stdClass $scope, $type, array $data, $template = null) {
    return \mod_these\local\documentrenderer::render_scope_doctemplate($scope, $type, $data, $template);
}

/**
 * Voir \mod_these\local\documentrenderer::signed_document_pdf().
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string $doctype
 * @return string Contenu PDF.
 */
function these_signed_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
    return \mod_these\local\documentrenderer::signed_document_pdf($these, $context, $dossier, $doctype);
}

/**
 * Voir \mod_these\local\documentrenderer::render_document_pdf().
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @param string $doctype
 * @return string Contenu PDF.
 */
function these_render_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
    return \mod_these\local\documentrenderer::render_document_pdf($these, $context, $dossier, $doctype);
}

/**
 * Voir \mod_these\local\documentrenderer::contract_template_problem().
 *
 * @param stdClass $these
 * @return string|null 'contracttemplatemissing', 'contracttemplateunreadable' ou null.
 */
function these_contract_template_problem(stdClass $these) {
    return \mod_these\local\documentrenderer::contract_template_problem($these);
}

/**
 * Voir \mod_these\local\documentsamples::doctemplate_sample_context().
 *
 * @param stdClass|null $scope
 * @param string $type Voir these_doctemplate_types().
 * @param stdClass|null $activity Activité dont reprendre l'établissement.
 * @return array
 */
function these_doctemplate_sample_context(?stdClass $scope, $type, ?stdClass $activity = null) {
    return \mod_these\local\documentsamples::doctemplate_sample_context($scope, $type, $activity);
}

/**
 * Voir \mod_these\local\documentrenderer::doctemplate_error().
 *
 * @param stdClass $scope Voir these_settings_scope().
 * @param string $type
 * @param string $content
 * @param stdClass|null $activity Activité dont reprendre l'établissement dans l'exemple.
 * @return string|null Message d'erreur, ou null si le modèle est utilisable.
 */
function these_doctemplate_error(stdClass $scope, $type, $content, ?stdClass $activity = null) {
    return \mod_these\local\documentrenderer::doctemplate_error($scope, $type, $content, $activity);
}

/**
 * Voir \mod_these\local\documentsamples::sample_document_context().
 *
 * @param stdClass|null $scope Portée des paramètres (images, grille) ; null : ceux d'origine.
 * @param stdClass|null $activity Activité dont reprendre l'établissement (sinon des valeurs fictives).
 * @return array Voir these_build_document_context().
 */
function these_sample_document_context(?stdClass $scope = null, ?stdClass $activity = null) {
    return \mod_these\local\documentsamples::sample_document_context($scope, $activity);
}

/**
 * Voir \mod_these\local\documentsamples::sample_signature_image().
 *
 * @return string
 */
function these_sample_signature_image() {
    return \mod_these\local\documentsamples::sample_signature_image();
}

/**
 * Voir \mod_these\local\documentsamples::doctemplate_variables().
 *
 * @param array $data
 * @param string $prefix
 * @return array nom => exemple (texte)
 */
function these_doctemplate_variables(array $data, $prefix = '') {
    return \mod_these\local\documentsamples::doctemplate_variables($data, $prefix);
}

/**
 * Voir \mod_these\local\doctemplates::get_contract_template().
 *
 * @param stdClass $these
 * @return string|null Contenu du PDF.
 */
function these_get_contract_template(stdClass $these) {
    return \mod_these\local\doctemplates::get_contract_template($these);
}

/**
 * Voir \mod_these\local\documentrenderer::merge_pdfs().
 *
 * @param string[] $sources Contenus PDF, dans l'ordre.
 * @return string Contenu PDF assemblé.
 */
function these_merge_pdfs(array $sources) {
    return \mod_these\local\documentrenderer::merge_pdfs($sources);
}
