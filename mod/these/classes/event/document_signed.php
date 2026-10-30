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

namespace mod_these\event;

/**
 * Événement : un document du dossier a été signé (voir signatures::sign_document()).
 *
 * @property-read array $other {
 *      - string doctype: document signé.
 *      - string slot: emplacement de signature.
 *      - string method: online, inperson ou scan.
 *      - int signatureid: enregistrement these_signature.
 * }
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_signed extends dossier_event {
    /**
     * Initialisation.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Nom de l'événement.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdocumentsigned', 'mod_these');
    }

    /**
     * Description de l'événement.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' signed the document '" . $this->other['doctype'] . "' (slot '"
            . $this->other['slot'] . "', " . $this->other['method'] . ") of the thesis file with id '$this->objectid' "
            . "in the thesis management activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Vérifications propres à l'événement.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        foreach (['doctype', 'slot', 'method'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The 'other' property must contain '$key'.");
            }
        }
    }
}
