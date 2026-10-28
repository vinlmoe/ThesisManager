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
     * | activity | user     | status    | title        | defensedate              | defenselocation |
     * | these1   | student1 | scheduled | Leptospirose | ##monday next week 14:00## | Amphithéâtre A  |
     *
     * Le statut est le nom d'une constante THESE_STATUS_* en minuscules (draft par défaut) ; titre,
     * date et lieu de soutenance, confidentialité (confidential : 0 ou 1), directeur (director :
     * identifiant de connexion), agrément (agreement : 1 pour un agrément administratif rempli) et
     * conclusions sont facultatifs.
     *
     * Les signatures se créent ensuite : demandées aux agents DEVE (« agent signature requests »,
     * avec le compte qui a validé) ou apposées (« signatures », avec la manière de signer).
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
            'library agents' => [
                'singular' => 'library agent',
                'datagenerator' => 'library_agent',
                'required' => ['activity', 'user'],
                'switchids' => ['activity' => 'cmid', 'user' => 'userid'],
            ],
            'deve agents' => [
                'singular' => 'deve agent',
                'datagenerator' => 'deve_agent',
                'required' => ['activity', 'user'],
                'switchids' => ['activity' => 'cmid', 'user' => 'userid'],
            ],
            'agent signature requests' => [
                'singular' => 'agent signature request',
                'datagenerator' => 'agent_signature_request',
                'required' => ['activity', 'user', 'documents', 'validator'],
                'switchids' => ['activity' => 'cmid', 'user' => 'userid'],
            ],
            'signatures' => [
                'singular' => 'signature',
                'datagenerator' => 'signature',
                'required' => ['activity', 'user', 'document', 'slot'],
                'switchids' => ['activity' => 'cmid', 'user' => 'userid'],
            ],
            'jurors' => [
                'singular' => 'juror',
                'datagenerator' => 'juror',
                'required' => ['title', 'firstname', 'lastname'],
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
        foreach (['title', 'defenselocation'] as $field) {
            if (isset($data[$field])) {
                $record[$field] = $data[$field];
            }
        }
        if (isset($data['confidential'])) {
            $record['confidential'] = (int) $data['confidential'];
        }
        if (isset($data['defensedate'])) {
            $record['defensedate'] = $record['proposeddate'] = (int) $data['defensedate'];
        }
        if (!empty($data['director'])) {
            $record['directorid'] = $DB->get_field('user', 'id', ['username' => $data['director']], MUST_EXIST);
        }
        if (!empty($data['agreement'])) {
            $record['agreement'] = json_encode(['defvdate' => time(), 'mobility' => 1]);
        }
        if (isset($data['conclusions'])) {
            $record['conclusions'] = $data['conclusions'];
        }
        $this->componentdatagenerator->create_dossier($theseid, $data['userid'], $record);
    }

    /**
     * Dossier d'un étudiant dans une activité, et contexte du module.
     *
     * @param array $data cmid, userid
     * @return array [instance, cm, contexte, dossier]
     */
    protected function dossier_of(array $data): array {
        global $DB;

        $cm = get_coursemodule_from_id('these', $data['cmid'], 0, false, MUST_EXIST);
        $these = $DB->get_record('these', ['id' => $cm->instance], '*', MUST_EXIST);
        $dossier = $DB->get_record('these_dossier', ['theseid' => $these->id, 'userid' => $data['userid']], '*', MUST_EXIST);
        return [$these, $cm, \context_module::instance($cm->id), $dossier];
    }

    /**
     * Désigne un agent DEVE signataire dans les paramètres du circuit de l'activité.
     *
     * @param array $data
     * @return void
     */
    protected function process_deve_agent(array $data): void {
        global $DB;

        $theseid = $DB->get_field('course_modules', 'instance', ['id' => $data['cmid']], MUST_EXIST);
        $scope = these_settings_scope($DB->get_record('these', ['id' => $theseid], '*', MUST_EXIST));
        $agents = trim((string) \mod_these\local\scopesettings::get_all($scope)['deveagent']);
        $username = $DB->get_field('user', 'username', ['id' => $data['userid']], MUST_EXIST);
        \mod_these\local\scopesettings::set($scope, ['deveagent' => ltrim($agents . "\n" . $username)]);
    }

    /**
     * Demande aux agents DEVE de signer des documents du dossier d'un étudiant, comme à la validation
     * de l'équipe DEVE (documents : liste séparée par des virgules ; validator : identifiant de
     * connexion du compte qui a validé).
     *
     * @param array $data
     * @return void
     */
    protected function process_agent_signature_request(array $data): void {
        global $DB;

        [$these, $cm, , $dossier] = $this->dossier_of($data);
        $validatorid = $DB->get_field('user', 'id', ['username' => $data['validator']], MUST_EXIST);
        $doctypes = array_map('trim', explode(',', $data['documents']));
        these_request_agent_signatures($these, $cm, $dossier, $doctypes, $validatorid);
    }

    /**
     * Appose une signature sur un document du dossier d'un étudiant (method : online par défaut, ou
     * scan).
     *
     * @param array $data
     * @return void
     */
    protected function process_signature(array $data): void {
        [, , $context, $dossier] = $this->dossier_of($data);
        $method = $data['method'] ?? 'online';
        these_sign_document(
            $context,
            $dossier,
            $data['document'],
            $data['slot'],
            0,
            'Signataire ' . $data['slot'],
            $method === 'scan' ? '' : these_sample_signature_image(),
            '',
            ['method' => $method]
        );
    }

    /**
     * Ajoute un enseignant à l'annuaire (title : Dr ou Pr ; status : mcf par défaut ; email et
     * institution facultatifs).
     *
     * @param array $data
     * @return void
     */
    protected function process_juror(array $data): void {
        \mod_these\local\jurors::save((object) [
            'title' => $data['title'],
            'lastname' => $data['lastname'],
            'firstname' => $data['firstname'],
            'email' => $data['email'] ?? '',
            'status' => $data['status'] ?? 'mcf',
            'institution' => $data['institution'] ?? '',
            'active' => 1,
        ]);
    }

    /**
     * Désigne un agent de la bibliothèque dans les paramètres du circuit de l'activité.
     *
     * @param array $data
     * @return void
     */
    protected function process_library_agent(array $data): void {
        global $DB;

        $theseid = $DB->get_field('course_modules', 'instance', ['id' => $data['cmid']], MUST_EXIST);
        $these = $DB->get_record('these', ['id' => $theseid], '*', MUST_EXIST);
        $scope = these_settings_scope($these);
        $agents = trim((string) \mod_these\local\scopesettings::get_all($scope)['libraryagent']);
        $username = $DB->get_field('user', 'username', ['id' => $data['userid']], MUST_EXIST);
        \mod_these\local\scopesettings::set($scope, ['libraryagent' => ltrim($agents . "\n" . $username)]);
    }
}
