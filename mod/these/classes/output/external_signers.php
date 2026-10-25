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

use moodle_url;
use stdClass;

/**
 * Signataires sans compte d'un dossier (président du jury, direction...) et agent responsable de la
 * DEVE : documents qu'ils doivent encore signer, relance par courriel, signature sur place.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_signers implements \renderable, \templatable {
    /** @var stdClass */
    protected $cm;
    /** @var stdClass */
    protected $dossier;
    /** @var moodle_url */
    protected $pageurl;
    /** @var array[]|null Lignes, calculées une fois. */
    protected $rows = null;

    /**
     * Constructeur.
     *
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param moodle_url $pageurl Adresse de la page de suivi des signatures (relances).
     */
    public function __construct(stdClass $cm, stdClass $dossier, moodle_url $pageurl) {
        $this->cm = $cm;
        $this->dossier = $dossier;
        $this->pageurl = $pageurl;
    }

    /**
     * Lignes du tableau : un signataire sans compte par emplacement en attente, puis l'agent.
     *
     * @return array[] Cellules (listes de fragments).
     */
    protected function get_rows(): array {
        if ($this->rows !== null) {
            return $this->rows;
        }
        $dossier = $this->dossier;
        $signers = these_external_signers($dossier);
        $this->rows = [];
        foreach (these_external_slots() as $slot) {
            $pending = array_filter(these_pending_documents_for_slot($dossier, $slot), function ($doctype) use ($dossier) {
                return these_document_available($dossier, $doctype);
            });
            if (!$pending) {
                continue;
            }
            $actions = [];
            if ($signers[$slot]['email'] !== '') {
                $actions[] = parts::link(
                    new moodle_url($this->pageurl, ['resend' => $slot, 'sesskey' => sesskey()]),
                    get_string('resendsignature', 'mod_these'),
                    'btn btn-sm btn-secondary mr-1'
                );
            }
            $actions[] = parts::link(
                new moodle_url('/mod/these/sign.php', ['id' => $this->cm->id, 'dossierid' => $dossier->id, 'slot' => $slot]),
                get_string('signinperson', 'mod_these'),
                'btn btn-sm btn-primary'
            );
            $this->rows[] = [
                [parts::text(these_slot_label($slot) . ($signers[$slot]['name'] !== '' ? ' — ' . $signers[$slot]['name'] : ''))],
                [$signers[$slot]['email'] !== '' ? parts::text($signers[$slot]['email'])
                    : parts::span(get_string('noemail', 'mod_these'), 'text-muted')],
                [parts::text(implode(', ', array_map('these_document_label', $pending)))],
                $actions,
            ];
        }
        $agents = these_deve_agents();
        if ($agents) {
            $pending = [];
            foreach (these_agent_slots() as $slot) {
                foreach (these_pending_documents_for_slot($dossier, $slot) as $doctype) {
                    if (these_agent_document_due($dossier, $doctype)) {
                        $pending[] = these_document_label($doctype) . ' (' . these_slot_label($slot) . ')';
                    }
                }
            }
            if ($pending) {
                $this->rows[] = [
                    [parts::text(get_string('agentdeve', 'mod_these') . ' — '
                        . implode(', ', array_map('fullname', $agents)))],
                    [parts::text(implode(', ', array_column($agents, 'email')))],
                    [parts::text(implode(', ', $pending))],
                    [parts::link(
                        new moodle_url($this->pageurl, ['resend' => 'agent', 'sesskey' => sesskey()]),
                        get_string('resendsignature', 'mod_these'),
                        'btn btn-sm btn-secondary'
                    )],
                ];
            }
        }
        return $this->rows;
    }

    /**
     * Indique si aucun signataire n'a de document à signer.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->get_rows());
    }

    /**
     * Données du gabarit mod_these/external_signers.
     *
     * @param \renderer_base $output
     * @return array heading, hasrows, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = $this->get_rows();
        $head = [get_string('signer', 'mod_these'), get_string('email'), get_string('documentstosign', 'mod_these'), ''];
        return [
            'heading' => get_string('externalsigners', 'mod_these'),
            'hasrows' => !empty($rows),
            'table' => table_context::export('generaltable', $head, $rows),
        ];
    }
}
