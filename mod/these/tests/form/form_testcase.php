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

global $CFG;
require_once(__DIR__ . '/../these_testcase.php');

/**
 * Socle des tests de validation des formulaires : l'activité et ses acteurs, la page courante
 * (certains formulaires lisent les paramètres de son contexte), et les données qu'un navigateur
 * soumettrait : brouillon de fichiers, signature tracée, jury.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class form_testcase extends \mod_these\these_testcase {
    /**
     * Prépare l'activité, l'utilisateur courant et la page où le formulaire est construit.
     *
     * @param \stdClass|null $user Utilisateur courant (la DEVE par défaut).
     * @return void
     */
    protected function setup_form_page(?\stdClass $user = null): void {
        global $PAGE;

        $this->setup_activity();
        $this->setUser($user ?? $this->deve);
        $PAGE->set_url('/mod/these/view.php', ['id' => $this->cm->id]);
        $PAGE->set_context($this->context);
    }

    /**
     * Brouillon de l'utilisateur courant, avec ou sans fichier.
     *
     * @param string|null $filename Nom du fichier déposé, ou null pour un brouillon vide.
     * @param string $content
     * @return int Identifiant du brouillon.
     */
    protected function draft(?string $filename = 'document.pdf', string $content = '%PDF-1.4'): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        if ($filename !== null) {
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
            ], $content);
        }
        return $draftitemid;
    }

    /**
     * Champs d'une signature complète : tracé, nom du signataire, certification.
     *
     * @return array
     */
    protected function signature_data(): array {
        return [
            'signaturedata' => 'data:image/png;base64,' . base64_encode(these_sample_signature_image()),
            'signername' => 'Hélène Durand',
            'signatureconsent' => 1,
        ];
    }

    /**
     * Champs du jury tels que soumis par le formulaire.
     *
     * @param array|null $members Membres (voir \mod_these_generator::default_jury()), jury valide par défaut.
     * @return array
     */
    protected function jury_data(?array $members = null): array {
        return these_jury_form_data($members ?? $this->generator->default_jury());
    }

    /**
     * Jury par défaut dont un membre est modifié.
     *
     * @param int $index Rang du membre dans default_jury().
     * @param array $changes Champs à remplacer.
     * @return array Membres.
     */
    protected function jury_with(int $index, array $changes): array {
        $members = $this->generator->default_jury();
        foreach ($changes as $field => $value) {
            $members[$index]->$field = $value;
        }
        return $members;
    }

    /**
     * Vérifie les champs en erreur, sans tenir compte des messages.
     *
     * @param string[] $expected Champs attendus en erreur.
     * @param array $errors Résultat de validation().
     * @return void
     */
    protected function assert_error_fields(array $expected, array $errors): void {
        $actual = array_keys($errors);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
