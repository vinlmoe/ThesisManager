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

/**
 * Signature enregistrée de l'utilisateur (image), avec le bouton pour la supprimer, ou mention de
 * son absence.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class saved_signature implements \renderable, \templatable {
    /** @var string|null */
    protected $png;
    /** @var moodle_url */
    protected $deleteurl;

    /**
     * Constructeur.
     *
     * @param string|null $png Image PNG, null si l'utilisateur n'a pas de signature enregistrée.
     * @param moodle_url $deleteurl Adresse de suppression (avec le sesskey).
     */
    public function __construct(?string $png, moodle_url $deleteurl) {
        $this->png = $png;
        $this->deleteurl = $deleteurl;
    }

    /**
     * Données du gabarit mod_these/saved_signature.
     *
     * @param \renderer_base $output
     * @return array hassignature, src, alt, deleteurl, deletelabel, nonelabel
     */
    public function export_for_template(\renderer_base $output): array {
        return [
            'hassignature' => $this->png !== null,
            'src' => $this->png !== null ? 'data:image/png;base64,' . base64_encode($this->png) : '',
            'alt' => get_string('signaturesaved', 'mod_these'),
            'deleteurl' => $this->deleteurl->out(false),
            'deletelabel' => get_string('signaturesaveddelete', 'mod_these'),
            'nonelabel' => get_string('signaturesavednone', 'mod_these'),
        ];
    }
}
