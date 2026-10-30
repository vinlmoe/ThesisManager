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

namespace mod_these\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Classe de base des formulaires du module : champs cachés d'identification communs.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_form extends \moodleform {
    /**
     * Ajoute les champs cachés qui identifient la page : id (module de cours), dossierid si demandé,
     * puis d'autres champs cachés éventuels, dans cet ordre.
     *
     * @param bool $withdossier Ajoute le champ dossierid.
     * @param array $extra Autres champs cachés, nom => PARAM_*.
     * @return void
     */
    protected function add_hidden_ids(bool $withdossier = false, array $extra = []): void {
        $mform = $this->_form;
        $fields = ['id' => PARAM_INT];
        if ($withdossier) {
            $fields['dossierid'] = PARAM_INT;
        }
        foreach ($fields + $extra as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }
    }
}
