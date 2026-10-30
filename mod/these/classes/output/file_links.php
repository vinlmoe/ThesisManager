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
use mod_these\local\files;
use moodle_url;

/**
 * Liens vers les fichiers d'une zone d'un dossier, séparés par un saut de ligne.
 *
 * Le droit de voir la zone (these_can_view_filearea()) est vérifié par l'appelant.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_links implements \renderable, \templatable {
    /** @var context */
    protected $context;
    /** @var string */
    protected $filearea;
    /** @var int */
    protected $dossierid;
    /** @var string */
    protected $divclass;
    /** @var array|null Liens, calculés une fois. */
    protected $files = null;

    /**
     * Constructeur.
     *
     * @param context $context Contexte de l'activité.
     * @param string $filearea
     * @param int $dossierid
     * @param string $divclass Classes CSS d'un bloc autour des liens (vide : pas de bloc).
     */
    public function __construct(context $context, $filearea, $dossierid, $divclass = '') {
        $this->context = $context;
        $this->filearea = (string) $filearea;
        $this->dossierid = (int) $dossierid;
        $this->divclass = (string) $divclass;
    }

    /**
     * Liens vers les fichiers de la zone.
     *
     * @return array[] url, filename, separator (saut de ligne avant le lien)
     */
    protected function get_files(): array {
        if ($this->files === null) {
            $this->files = [];
            foreach (files::get_files($this->context, $this->filearea, $this->dossierid) as $file) {
                $url = moodle_url::make_pluginfile_url(
                    $this->context->id,
                    'mod_these',
                    $this->filearea,
                    $this->dossierid,
                    $file->get_filepath(),
                    $file->get_filename(),
                    true
                );
                $this->files[] = [
                    'url' => $url->out(false),
                    'filename' => $file->get_filename(),
                    'separator' => !empty($this->files),
                ];
            }
        }
        return $this->files;
    }

    /**
     * Indique si la zone ne contient aucun fichier.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->get_files());
    }

    /**
     * Données du gabarit mod_these/file_links.
     *
     * @param \renderer_base $output
     * @return array files (url, filename, separator), divclass
     */
    public function export_for_template(\renderer_base $output): array {
        return ['files' => $this->get_files(), 'divclass' => $this->divclass];
    }
}
