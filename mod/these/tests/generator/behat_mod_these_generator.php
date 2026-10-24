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
 * Générateur de données Behat pour mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_these_generator extends behat_generator_base {
    /**
     * Entités créables : des dossiers directement à une étape du circuit.
     *
     * | activity | user     | status | title        |
     * | these1   | student1 | active | Leptospirose |
     *
     * Le statut est le nom d'une constante THESE_STATUS_* en minuscules (draft par défaut).
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'dossiers' => [
                'singular' => 'dossier',
                'datagenerator' => 'dossier',
                'required' => ['activity', 'user'],
                'switchids' => ['activity' => 'cmid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Crée un dossier.
     *
     * @param array $data
     * @return void
     */
    protected function process_dossier(array $data): void {
        global $DB;

        $theseid = $DB->get_field('course_modules', 'instance', ['id' => $data['cmid']], MUST_EXIST);
        $constant = 'THESE_STATUS_' . strtoupper($data['status'] ?? 'draft');
        if (!defined($constant)) {
            throw new Exception('Statut de dossier inconnu : ' . $data['status']);
        }
        $record = ['status' => constant($constant)];
        if (isset($data['title'])) {
            $record['title'] = $data['title'];
        }
        $this->componentdatagenerator->create_dossier($theseid, $data['userid'], $record);
    }
}
