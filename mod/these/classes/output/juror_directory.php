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

use mod_these\local\jurors;
use moodle_url;

/**
 * Annuaire des enseignants en lecture seule, pour les étudiants : recherche, enseignants pouvant
 * présider, et pour chacun nom, statut, matière, établissement et présidence possible ; l'adresse de
 * ceux qui peuvent présider, pour que l'étudiant les contacte. Aucune action.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class juror_directory implements \renderable, \templatable {
    /** @var moodle_url */
    protected $url;
    /** @var \stdClass[] */
    protected $jurors;
    /** @var string */
    protected $search;
    /** @var bool */
    protected $presidentsonly;

    /**
     * Constructeur.
     *
     * @param moodle_url $url Adresse de la page (avec son paramètre id).
     * @param \stdClass[] $jurors Enseignants à afficher (voir jurors::directory()).
     * @param string $search Texte recherché.
     * @param bool $presidentsonly Seulement ceux qui peuvent présider.
     */
    public function __construct(moodle_url $url, array $jurors, $search = '', $presidentsonly = false) {
        $this->url = $url;
        $this->jurors = $jurors;
        $this->search = (string) $search;
        $this->presidentsonly = (bool) $presidentsonly;
    }

    /**
     * Données du gabarit mod_these/juror_directory.
     *
     * @param \renderer_base $output
     * @return array action, id, search, placeholder, presidents, presidentslabel, filterlabel, count, hasrows,
     *               none, table (voir table_context)
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        foreach ($this->jurors as $juror) {
            $canpreside = jurors::can_preside($juror->status);
            // Adresse des seuls enseignants pouvant présider : l'étudiant les sollicite pour son jury.
            $email = trim((string) ($juror->email ?? ''));
            $rows[] = [
                [parts::text(jurors::name($juror))],
                [parts::text(jurors::status_label($juror->status))],
                [parts::text((string) ($juror->discipline ?? ''))],
                [parts::text((string) ($juror->institution ?? ''))],
                [parts::text(get_string($canpreside ? 'yes' : 'no'))],
                [$canpreside && $email !== '' ? parts::html(\html_writer::link('mailto:' . $email, s($email))) : parts::text('')],
            ];
        }
        $head = [
            get_string('juryname', 'mod_these'),
            get_string('jurystatus', 'mod_these'),
            get_string('jurordiscipline', 'mod_these'),
            get_string('juryinstitution', 'mod_these'),
            get_string('jurorpresident', 'mod_these'),
            get_string('email'),
        ];
        return [
            'action' => $this->url->out_omit_querystring(),
            'id' => (int) $this->url->get_param('id'),
            'search' => $this->search,
            'placeholder' => get_string('jurordirectorysearch', 'mod_these'),
            'presidents' => $this->presidentsonly,
            'presidentslabel' => get_string('jurordirectorypresidents', 'mod_these'),
            'filterlabel' => get_string('filter'),
            'count' => get_string('jurordirectorycount', 'mod_these', count($rows)),
            'hasrows' => !empty($rows),
            'none' => get_string('jurordirectorynone', 'mod_these'),
            'table' => table_context::export('generaltable these-juror-directory', $head, $rows),
        ];
    }
}
