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

/**
 * Abandon (ou réouverture) d'un dossier par la DEVE, motif à l'appui.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancel_form extends base_form {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $reopen = !empty($this->_customdata['reopen']);

        $this->add_hidden_ids(true);

        $mform->addElement('static', 'cancelhelp', '', get_string($reopen ? 'reopenhelp' : 'cancelhelp', 'mod_these'));
        $mform->addElement('textarea', 'remark', get_string('reviewremark', 'mod_these'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('remark', PARAM_TEXT);
        if (!$reopen) {
            $mform->addRule('remark', null, 'required', null, 'client');
        }

        $this->add_action_buttons(true, get_string($reopen ? 'reopendossier' : 'canceldossier', 'mod_these'));
    }
}
