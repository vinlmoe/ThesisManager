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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Socle commun des tests de mod_these : un cours, une activité et les quatre acteurs du circuit,
 * chacun avec son rôle (la bibliothèque passant par un rôle dédié, comme sur un site réel).
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class these_testcase extends \advanced_testcase {
    /** @var \stdClass */
    protected $course;
    /** @var \stdClass Instance de l'activité. */
    protected $these;
    /** @var \stdClass */
    protected $cm;
    /** @var \context_module */
    protected $context;
    /** @var \stdClass */
    protected $student;
    /** @var \stdClass */
    protected $director;
    /** @var \stdClass */
    protected $deve;
    /** @var \stdClass */
    protected $librarian;
    /** @var \mod_these_generator */
    protected $generator;

    /**
     * Prépare le cours, l'activité et ses acteurs.
     *
     * @param array $settings Paramètres de l'activité à surcharger.
     * @return void
     */
    protected function setup_activity(array $settings = []): void {
        global $DB;

        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->these = $gen->create_module('these', array_merge([
            'course' => $this->course->id,
            'establishmentname' => 'École nationale vétérinaire',
            'establishmentcity' => 'Toulouse',
            'signatoryname' => 'La directrice',
            'signatorytitle' => "Directrice de l'école",
            'minjury' => 3,
            'reminderdays' => 14,
        ], $settings));
        $this->cm = get_coursemodule_from_instance('these', $this->these->id);
        $this->context = \context_module::instance($this->cm->id);
        $this->these = $DB->get_record('these', ['id' => $this->these->id]);
        $this->generator = $gen->get_plugin_generator('mod_these');

        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->director = $gen->create_and_enrol($this->course, 'teacher');
        $this->deve = $gen->create_and_enrol($this->course, 'editingteacher');

        // Rôle interne « Bibliothèque (thèses) », créé à l'installation du module et choisi par
        // défaut pour la bibliothèque dans les réglages du site.
        these_setup_default_roles();
        $this->librarian = $gen->create_and_enrol($this->course, 'theselibrary');
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * Dossier relu depuis la base.
     *
     * @param int $id
     * @return \stdClass
     */
    protected function reload(int $id): \stdClass {
        global $DB;
        return $DB->get_record('these_dossier', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Jeton du dernier lien envoyé à une adresse (seule son empreinte est conservée en base).
     *
     * @param array $messages Courriels interceptés.
     * @param string $email Destinataire.
     * @param string $script Page du lien : sign.php ou jury.php.
     * @return string
     */
    protected function token_sent_to(array $messages, string $email, string $script = 'sign.php'): string {
        $token = '';
        foreach ($messages as $message) {
            $body = quoted_printable_decode($message->body);
            if ($message->to === $email && preg_match('#' . preg_quote($script, '#') . '\?token=([A-Za-z0-9]+)#', $body, $match)) {
                $token = $match[1];
            }
        }
        $this->assertNotSame('', $token, 'Aucun lien envoyé à ' . $email);
        return $token;
    }

    /**
     * Données de soumission d'un dossier de soutenance (date dans un mois, jury par défaut).
     *
     * @return \stdClass
     */
    protected function defense_data(): \stdClass {
        $jury = these_jury_form_data($this->generator->default_jury());
        return (object) array_merge($jury, [
            'proposeddate' => time() + 30 * DAYSECS,
            'defenselocation' => 'Amphithéâtre A',
        ]);
    }
}
