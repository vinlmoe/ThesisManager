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
use moodle_url;
use stdClass;

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
            return parts::badge(get_string('signedby', 'mod_these', (object) [
                'name' => $signature->signername,
                'date' => userdate($signature->timesigned, $dateformat),
            ]), 'badge badge-success');
        }
        if ($signature && $signature->requesttime) {
            return parts::badge(
                get_string('signaturerequestedon', 'mod_these', userdate($signature->requesttime, $dateformat)),
                'badge badge-warning'
            );
        }
        return parts::badge(get_string('signaturepending', 'mod_these'), 'badge badge-light');
    }

    /**
     * Données du gabarit mod_these/signed_documents.
     *
     * @param \renderer_base $output
     * @return array hasdocuments, heading, table (voir table_context), warning (alerte de la DEVE, ou false)
     */
    public function export_for_template(\renderer_base $output): array {
        $signatures = these_get_signatures($this->dossier->id);
        $rows = [];
        foreach (array_keys(these_document_definitions()) as $doctype) {
            if (!these_document_available($this->dossier, $doctype) || !these_can_view_document($doctype, $this->rights)) {
                continue;
            }
            $states = [];
            foreach (these_document_slots($this->dossier, $doctype) as $slot) {
                if ($states) {
                    $states[] = parts::br();
                }
                $states[] = parts::strong(these_slot_label($slot));
                $states[] = parts::text(' : ');
                $states[] = $this->state($signatures[$doctype . ':' . $slot] ?? null);
            }
            $url = new moodle_url('/mod/these/document.php', [
                'id' => $this->cm->id,
                'dossierid' => $this->dossier->id,
                'type' => $doctype,
            ]);
            $rows[] = [
                [parts::text(these_document_label($doctype))],
                $states,
                [parts::link($url, get_string('downloadpdf', 'mod_these'), 'btn btn-sm btn-secondary')],
            ];
        }
        if (empty($rows)) {
            return ['hasdocuments' => false, 'heading' => '', 'table' => false, 'warning' => false];
        }
        $warning = false;
        if (
            $this->rights->deve && these_document_available($this->dossier, 'contract')
                && ($problem = these_contract_template_problem()) !== null
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
