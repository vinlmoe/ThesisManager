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

namespace mod_these\output;

use context;
use mod_these\local\doctemplates;
use mod_these\local\files;
use mod_these\local\rights;
use mod_these\local\signatures;
use moodle_url;
use stdClass;
use mod_these\local\documentrenderer;

/**
 * Documents signés d'un dossier, avec l'état de chaque signature.
 *
 * Seuls les documents disponibles et que l'utilisateur peut voir (these_can_view_document()) entrent
 * dans le contexte du gabarit.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signed_documents implements \renderable, \templatable {
    /** @var stdClass */
    protected $cm;
    /** @var context */
    protected $context;
    /** @var stdClass */
    protected $dossier;
    /** @var stdClass */
    protected $rights;

    /**
     * Constructeur.
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass $rights Voir these_get_rights().
     */
    public function __construct(stdClass $cm, context $context, stdClass $dossier, stdClass $rights) {
        $this->cm = $cm;
        $this->context = $context;
        $this->dossier = $dossier;
        $this->rights = $rights;
    }

    /**
     * État d'un emplacement de signature.
     *
     * @param stdClass|null $signature
     * @return array Fragment (badge).
     */
    protected function state(?stdClass $signature): array {
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        if ($signature && $signature->timesigned) {
            // Délégation et manière de signer (sur place, scan) précisées après le nom et la date.
            // Un scan porte la date écrite sur le document, sans heure.
            $format = ($signature->method ?? '') === 'scan' ? get_string('strftimedate', 'langconfig') : $dateformat;
            $label = get_string('signedby', 'mod_these', (object) [
                'name' => $signature->signername,
                'date' => userdate($signature->timesigned, $format),
            ]);
            if (!empty($signature->delegated)) {
                $label .= ' (' . get_string('signeddelegated', 'mod_these') . ')';
            }
            if (in_array($signature->method ?? '', ['inperson', 'scan'], true)) {
                $label .= ' — ' . get_string('signedmethod' . $signature->method, 'mod_these');
            }
            return parts::badge($label . $this->validator($signature), 'badge badge-success');
        }
        if ($signature && $signature->requesttime) {
            return parts::badge(
                get_string('signaturerequestedon', 'mod_these', userdate($signature->requesttime, $dateformat))
                    . $this->validator($signature),
                'badge badge-warning'
            );
        }
        return parts::badge(get_string('signaturepending', 'mod_these'), 'badge badge-light');
    }

    /**
     * Compte de l'équipe DEVE qui a validé les documents d'une signature d'agent, à la suite de son état.
     *
     * @param stdClass $signature
     * @return string « — validé par … », ou vide.
     */
    protected function validator(stdClass $signature): string {
        $label = \mod_these\local\signatureagent::validator_label($signature);
        return $label !== '' ? ' — ' . $label : '';
    }

    /**
     * Activité du dossier (contrat type de diffusion de ses paramètres).
     *
     * @return stdClass
     */
    protected function these(): stdClass {
        global $DB;
        return $DB->get_record('these', ['id' => $this->dossier->theseid], '*', MUST_EXIST);
    }

    /**
     * Données du gabarit mod_these/signed_documents.
     *
     * @param \renderer_base $output
     * @return array hasdocuments, heading, table (voir table_context), warning (alerte de la DEVE, ou false)
     */
    public function export_for_template(\renderer_base $output): array {
        $signatures = signatures::get_signatures($this->dossier->id);
        // Scans joints au dossier, par document : leur nom commence par la date puis le document
        // (voir \mod_these\local\scans::add()).
        $scans = [];
        if (rights::can_view_filearea('signedscan', $this->rights)) {
            foreach (files::get_files($this->context, 'signedscan', $this->dossier->id) as $file) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}-([a-z]+)(-|$)/', $file->get_filename(), $match)) {
                    $scans[$match[1]][] = $file;
                }
            }
        }
        $rows = [];
        foreach (array_keys(signatures::document_definitions()) as $doctype) {
            if (
                !signatures::document_available($this->dossier, $doctype)
                || !signatures::can_view_document($doctype, $this->rights)
            ) {
                continue;
            }
            $states = [];
            foreach (signatures::document_slots($this->dossier, $doctype) as $slot) {
                if ($states) {
                    $states[] = parts::br();
                }
                $states[] = parts::strong(signatures::slot_label($slot));
                $states[] = parts::text(' : ');
                $states[] = $this->state($signatures[$doctype . ':' . $slot] ?? null);
            }
            $url = new moodle_url('/mod/these/document.php', [
                'id' => $this->cm->id,
                'dossierid' => $this->dossier->id,
                'type' => $doctype,
            ]);
            // Le PDF est le document du module (scellé une fois signé) ; les scans des signatures à la
            // main sont proposés à côté.
            $actions = [parts::link($url, get_string('downloadpdf', 'mod_these'), 'btn btn-sm btn-secondary mr-1 mb-1')];
            foreach ($scans[$doctype] ?? [] as $file) {
                $actions[] = parts::link(
                    moodle_url::make_pluginfile_url(
                        $file->get_contextid(),
                        'mod_these',
                        'signedscan',
                        $file->get_itemid(),
                        $file->get_filepath(),
                        $file->get_filename(),
                        true
                    ),
                    get_string('downloadscan', 'mod_these'),
                    'btn btn-sm btn-outline-secondary mr-1 mb-1'
                );
            }
            $rows[] = [
                [parts::text(signatures::document_label($doctype))],
                $states,
                $actions,
            ];
        }
        if (empty($rows)) {
            return ['hasdocuments' => false, 'heading' => '', 'table' => false, 'warning' => false];
        }
        $warning = false;
        if (
            $this->rights->deve && signatures::document_available($this->dossier, 'contract')
                && ($problem = documentrenderer::contract_template_problem($this->these())) !== null
        ) {
            $warning = get_string($problem, 'mod_these');
        }
        return [
            'hasdocuments' => true,
            'heading' => get_string('signeddocuments', 'mod_these'),
            'table' => table_context::export(
                'generaltable these-signatures',
                [get_string('document', 'mod_these'), get_string('signatures', 'mod_these'), ''],
                $rows
            ),
            'warning' => $warning,
        ];
    }
}
