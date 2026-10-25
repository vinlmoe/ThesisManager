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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../these_testcase.php');

/**
 * Socle des tests des composants de rendu : un dossier confidentiel dont chaque donnée qui révèle le
 * sujet porte un marqueur unique, le contexte d'un composant sérialisé en JSON et son rendu.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class output_testcase extends \mod_these\these_testcase {
    /** @var string[] Marqueurs de chaque donnée qui révèle le sujet. */
    const SECRETS = [
        'title' => 'SECRETTITRE',
        'titleen' => 'SECRETENGLISH',
        'summary' => 'SECRETRESUME',
        'keywords' => 'SECRETMOTCLE',
        'oldtitle' => 'SECRETANCIEN',
        'answer' => 'SECRETREPONSE',
        'embargoreason' => 'SECRETMOTIF',
    ];

    /**
     * Renderer de mod_these.
     *
     * @return renderer
     */
    protected function renderer(): renderer {
        global $PAGE;
        $PAGE->set_url(new \moodle_url('/mod/these/dossier.php'));
        return $PAGE->get_renderer('mod_these');
    }

    /**
     * Contexte d'un composant, sérialisé en JSON (caractères non échappés, pour la recherche de texte).
     *
     * @param \templatable $component
     * @return string
     */
    protected function context_json(\templatable $component): string {
        return json_encode($component->export_for_template($this->renderer()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Vérifie qu'aucun marqueur du sujet n'apparaît dans un texte.
     *
     * @param string $text
     * @param string $message
     * @return void
     */
    protected function assert_no_secret(string $text, string $message = ''): void {
        foreach (self::SECRETS as $field => $secret) {
            $this->assertStringNotContainsString($secret, $text, trim("$message ($field)"));
        }
    }

    /**
     * Dossier confidentiel programmé, avec jury, ancien sujet, réponse au questionnaire, demande
     * d'embargo motivée et fichiers.
     *
     * @return \stdClass
     */
    protected function confidential_dossier(): \stdClass {
        global $DB;

        $dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED,
            'directorid' => $this->director->id,
            'confidential' => 1,
            'title' => self::SECRETS['title'] . ' sur la maladie de Carré',
            'titleen' => self::SECRETS['titleen'],
            'summary' => 'Résumé ' . self::SECRETS['summary'],
            'keywords' => 'chien ; ' . self::SECRETS['keywords'],
            'devenumber' => '2026-099',
            'defensedate' => make_timestamp(2026, 12, 3, 14),
            'proposeddate' => make_timestamp(2026, 12, 3, 14),
            'defenselocation' => 'Amphithéâtre A',
            'bordereau' => json_encode(['embargo' => 1, 'embargoreason' => self::SECRETS['embargoreason'],
                'diffusion' => 'restricted']),
        ]);
        these_save_jury($dossier->id, $this->generator->default_jury());
        $DB->insert_record('these_subjecthistory', (object) ['dossierid' => $dossier->id,
            'title' => self::SECRETS['oldtitle'], 'directorname' => 'Dr Ancien', 'confidential' => 1,
            'reason' => 'Réorientation', 'changedby' => 0, 'timecreated' => make_timestamp(2026, 3, 1)]);
        $scope = these_settings_scope($this->these);
        $question = these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        these_save_answers($dossier, these_get_questions($scope), ['q' . $question => self::SECRETS['answer']]);
        $fs = get_file_storage();
        foreach (['subjectdocs', 'manuscript'] as $area) {
            $fs->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_these',
                'filearea' => $area, 'itemid' => $dossier->id, 'filepath' => '/', 'filename' => $area . '.pdf'], 'x');
        }
        return $this->reload($dossier->id);
    }
}
