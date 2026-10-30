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
require_once(__DIR__ . '/these_testcase.php');

/**
 * Thèse confidentielle : aucun accès non autorisé au sujet, par aucun chemin.
 *
 * Le dossier des tests porte des marqueurs uniques dans chaque donnée qui révèle le sujet (titre,
 * titre anglais, résumé, mots-clés, ancien titre, réponse au questionnaire, motif d'embargo) ; chaque
 * test vérifie qu'aucun marqueur n'apparaît dans ce que voit, reçoit ou télécharge un utilisateur
 * qui ne doit pas connaître le sujet, et que ceux qui le doivent le voient.
 *
 * Connaissent le sujet : l'étudiant, la DEVE (rôle de service ou mod/these:managedeve), la
 * bibliothèque (rôle de service ou mod/these:managelibrary), le directeur actuel, les membres du jury
 * (par leur adresse ou par l'annuaire). Ne le connaissent pas : un détenteur de mod/these:viewall
 * sans rôle de service, un autre directeur, un autre étudiant, l'ancien directeur, un ancien membre
 * du jury, un utilisateur sans rôle.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::these_user_knows_subject
 * @covers     ::these_get_rights
 * @covers     ::these_can_view_dossier
 * @covers     ::these_can_view_filearea
 * @covers     ::these_can_view_document
 * @covers     ::these_render_dossier
 * @covers     ::these_render_dossier_table
 * @covers     ::these_get_dossiers
 * @covers     ::these_register_row
 * @covers     ::these_defense_row
 * @covers     ::these_planning_document_context
 * @covers     ::these_send_email
 * @covers     ::these_recipient_knows_subject
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('these_user_knows_subject')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_get_rights')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_can_view_dossier')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_can_view_filearea')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_can_view_document')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_render_dossier')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_render_dossier_table')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_get_dossiers')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_register_row')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_defense_row')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_planning_document_context')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_send_email')]
#[\PHPUnit\Framework\Attributes\CoversFunction('these_recipient_knows_subject')]
final class confidentiality_access_test extends these_testcase {
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

    /** @var \stdClass Dossier confidentiel. */
    private $dossier;

    /** @var \stdClass[] Utilisateurs qui connaissent le sujet, par nom. */
    private $allowed = [];

    /** @var \stdClass[] Utilisateurs qui ne le connaissent pas, par nom. */
    private $denied = [];

    /**
     * Dossier confidentiel complet (soutenance prévue cette semaine, jury, questionnaire, embargo,
     * ancien sujet, fichiers) et tous les profils d'utilisateurs.
     *
     * @return void
     */
    private function setup_confidential(): void {
        global $DB;
        $this->setup_activity();
        $gen = $this->getDataGenerator();

        $formerdirector = $gen->create_and_enrol($this->course, 'teacher');
        $otherdirector = $gen->create_and_enrol($this->course, 'teacher');
        $otherstudent = $gen->create_and_enrol($this->course, 'student');
        $juror = $gen->create_user(['email' => 'president@example.com']);
        $directoryjuror = $gen->create_user(['email' => 'annuaire@example.org']);
        $formerjuror = $gen->create_user(['email' => 'ancien.jury@example.org']);
        $outsider = $gen->create_user();

        // Détenteur de mod/these:viewall sans être la DEVE ni la bibliothèque (gestion, scolarité...).
        $viewall = $this->viewall_user();

        $monday = these_planning_week_start(time());
        $this->dossier = $this->generator->create_dossier($this->these, $this->student->id, [
            'status' => THESE_STATUS_SCHEDULED,
            'directorid' => $this->director->id,
            'confidential' => 1,
            'title' => self::SECRETS['title'] . ' sur la maladie de Carré',
            'titleen' => self::SECRETS['titleen'],
            'summary' => 'Résumé ' . self::SECRETS['summary'],
            'keywords' => 'chien ; ' . self::SECRETS['keywords'],
            'devenumber' => '2026-099',
            'defensedate' => $monday + 2 * DAYSECS + 14 * HOURSECS,
            'proposeddate' => $monday + 2 * DAYSECS + 14 * HOURSECS,
            'defenselocation' => 'Amphithéâtre A',
            'bordereau' => json_encode(['embargo' => 1, 'embargoreason' => self::SECRETS['embargoreason'],
                'diffusion' => 'restricted', 'birthname' => 'MARTIN']),
        ]);

        // Jury validé par la DEVE : un membre extérieur rattaché à son compte à la validation, un par
        // l'annuaire (sans adresse dans le jury, rattaché au compte de sa fiche).
        $jurorid = \mod_these\local\jurors::save((object) ['title' => 'Pr', 'lastname' => 'Annuaire', 'firstname' => 'Anne',
            'email' => 'ANNUAIRE@example.org', 'status' => 'pr', 'institution' => '', 'active' => 1]);
        $jury = $this->generator->default_jury();
        $jury[] = (object) ['juryrole' => 'member', 'fullname' => 'Anne ANNUAIRE', 'jurytitle' => 'Pr', 'institution' => '',
            'email' => '', 'jurorid' => $jurorid, 'jurystatus' => 'pr'];
        foreach ($jury as $member) {
            $member->eligible = true;
        }
        these_save_jury($this->dossier->id, $jury, $this->deve->id);

        // Questionnaire.
        $scope = these_settings_scope($this->these);
        $question = these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '',
            'required' => 0]);
        these_save_answers($this->dossier, these_get_questions($scope), ['q' . $question => self::SECRETS['answer']]);

        // Ancien sujet : historique et journal du changement.
        $DB->insert_record('these_subjecthistory', (object) ['dossierid' => $this->dossier->id,
            'title' => self::SECRETS['oldtitle'], 'directorid' => $formerdirector->id, 'reason' => 'Changement',
            'changedby' => $this->student->id, 'timecreated' => time() - DAYSECS]);
        these_log(
            $this->dossier->id,
            $this->student->id,
            'subjectchanged',
            THESE_STATUS_ACTIVE,
            THESE_STATUS_SUBJECT_SUBMITTED,
            get_string('subjectchangelog', 'mod_these', (object) ['old' => self::SECRETS['oldtitle'], 'reason' => 'Changement'])
        );

        // Fichiers de chaque zone.
        $fs = get_file_storage();
        foreach (THESE_FILEAREAS as $filearea) {
            $fs->create_file_from_string(['contextid' => $this->context->id, 'component' => 'mod_these',
                'filearea' => $filearea, 'itemid' => $this->dossier->id, 'filepath' => '/',
                'filename' => $filearea . '.pdf'], '%PDF-1.4 ' . self::SECRETS['title']);
        }

        // Un ancien membre du jury, retiré.
        $this->assertTrue(these_user_in_jury($this->dossier->id, $juror->id));
        $this->assertFalse(these_user_in_jury($this->dossier->id, $formerjuror->id));

        $this->dossier = $this->reload($this->dossier->id);
        $this->allowed = [
            'student' => $this->student,
            'deve' => $this->deve,
            'library' => $this->librarian,
            'director' => $this->director,
            'juror' => $juror,
            'directoryjuror' => $directoryjuror,
        ];
        $this->denied = [
            'viewall' => $viewall,
            'otherdirector' => $otherdirector,
            'otherstudent' => $otherstudent,
            'formerdirector' => $formerdirector,
            'formerjuror' => $formerjuror,
            'outsider' => $outsider,
        ];
    }

    /**
     * Aucun marqueur du sujet dans un texte.
     *
     * @param string $text
     * @param string $who
     * @return void
     */
    private function assert_no_secret(string $text, string $who): void {
        foreach (self::SECRETS as $field => $secret) {
            $this->assertStringNotContainsString($secret, $text, "$who voit $field");
        }
    }

    /**
     * Qui connaît le sujet, et les droits qui en découlent.
     */
    public function test_who_knows_the_subject(): void {
        $this->setup_confidential();
        foreach ($this->allowed as $name => $user) {
            $this->assertTrue(these_user_knows_subject($this->dossier, $this->context, $user->id), $name);
            $this->assertTrue(these_get_rights($this->context, $this->dossier, $user->id)->knowssubject, $name);
        }
        foreach ($this->denied as $name => $user) {
            $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, $user->id), $name);
            $this->assertFalse(these_get_rights($this->context, $this->dossier, $user->id)->knowssubject, $name);
            foreach (['title', 'titleen', 'summary', 'keywords'] as $field) {
                $this->assert_no_secret(these_subject_field($this->dossier, $field, $this->context, $user->id), $name);
            }
        }
        $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, 0));

        // Seul le détenteur de viewall ouvre le dossier sans connaître le sujet.
        foreach ($this->denied as $name => $user) {
            $canview = these_can_view_dossier(these_get_rights($this->context, $this->dossier, $user->id));
            $this->assertSame($name === 'viewall', $canview, $name);
        }
    }

    /**
     * Un membre du jury est reconnu au compte qui lui est rattaché, pas à son adresse : un autre compte
     * portant la même adresse ne connaît pas le sujet ; un compte rattaché suspendu ou supprimé le perd.
     */
    public function test_jury_recognised_by_account_not_by_email(): void {
        global $DB;
        $this->setup_confidential();
        $juror = $this->allowed['juror'];
        $directoryjuror = $this->allowed['directoryjuror'];

        $homonym = $this->getDataGenerator()->create_user(['email' => 'president@example.com']);
        $directoryhomonym = $this->getDataGenerator()->create_user(['email' => 'annuaire@example.org']);
        foreach ([$homonym, $directoryhomonym] as $user) {
            $this->assertFalse(these_user_in_jury($this->dossier->id, $user->id));
            $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, $user->id));
            $this->assert_no_secret(these_subject_field($this->dossier, 'title', $this->context, $user->id), 'homonyme');
        }
        $this->assertTrue(these_user_knows_subject($this->dossier, $this->context, $juror->id));

        // Compte rattaché suspendu, puis rétabli.
        $DB->set_field('user', 'suspended', 1, ['id' => $juror->id]);
        $this->assertFalse(these_user_in_jury($this->dossier->id, $juror->id));
        $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, $juror->id));
        $DB->set_field('user', 'suspended', 0, ['id' => $juror->id]);
        $this->assertTrue(these_user_knows_subject($this->dossier, $this->context, $juror->id));

        // Compte rattaché supprimé.
        delete_user($directoryjuror);
        $this->assertFalse(these_user_in_jury($this->dossier->id, $directoryjuror->id));
        $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, $directoryjuror->id));
    }

    /**
     * Le sujet se perd avec le rôle : ancien directeur, membre retiré du jury.
     */
    public function test_access_follows_the_current_roles(): void {
        $this->setup_confidential();
        $juror = $this->allowed['juror'];

        $jury = array_values(array_filter(these_get_jury($this->dossier->id), function ($member) {
            return $member->juryrole !== 'president';
        }));
        these_save_jury($this->dossier->id, $jury);
        $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, $juror->id));

        $this->dossier->directorid = $this->denied['otherdirector']->id;
        $this->assertTrue(these_user_knows_subject($this->dossier, $this->context, $this->denied['otherdirector']->id));
        $this->assertFalse(these_user_knows_subject($this->dossier, $this->context, $this->director->id));

        // Une thèse qui n'est pas (ou plus) confidentielle est connue de tous ceux qui voient le dossier.
        $this->dossier->confidential = 0;
        foreach ($this->denied as $name => $user) {
            $this->assertTrue(these_user_knows_subject($this->dossier, $this->context, $user->id), $name);
        }
    }

    /**
     * Fichiers et documents signés : rien de ce qui porte le titre pour qui ne connaît pas le sujet,
     * sauf le manuscrit et le manuscrit corrigé ; tout pour la DEVE et la bibliothèque.
     */
    public function test_files_and_documents(): void {
        $this->setup_confidential();
        $this->assertNotContains('sealed', THESE_FILEAREAS, 'Les documents scellés ne passent pas par pluginfile.');
        $this->assertNotContains('signature', THESE_FILEAREAS, 'Les signatures ne passent pas par pluginfile.');

        $rights = these_get_rights($this->context, $this->dossier, $this->denied['viewall']->id);
        foreach (THESE_FILEAREAS as $filearea) {
            $expected = in_array($filearea, ['manuscript', 'corrections'], true);
            $this->assertSame($expected, these_can_view_filearea($filearea, $rights), "viewall : $filearea");
        }
        foreach (array_keys(these_document_definitions()) as $doctype) {
            $this->assertFalse(these_can_view_document($doctype, $rights), "viewall : $doctype");
        }
        foreach (['otherdirector', 'otherstudent', 'formerdirector', 'outsider'] as $name) {
            $rights = these_get_rights($this->context, $this->dossier, $this->denied[$name]->id);
            foreach (THESE_FILEAREAS as $filearea) {
                $this->assertFalse(these_can_view_filearea($filearea, $rights), "$name : $filearea");
            }
            foreach (array_keys(these_document_definitions()) as $doctype) {
                $this->assertFalse(these_can_view_document($doctype, $rights), "$name : $doctype");
            }
        }

        // Ceux qui connaissent le sujet gardent leurs accès habituels.
        $deve = these_get_rights($this->context, $this->dossier, $this->deve->id);
        $library = these_get_rights($this->context, $this->dossier, $this->librarian->id);
        $director = these_get_rights($this->context, $this->dossier, $this->director->id);
        $student = these_get_rights($this->context, $this->dossier, $this->student->id);
        foreach (THESE_FILEAREAS as $filearea) {
            $this->assertTrue(these_can_view_filearea($filearea, $deve), "deve : $filearea");
            $this->assertTrue(these_can_view_filearea($filearea, $library), "library : $filearea");
            $this->assertTrue(these_can_view_filearea($filearea, $director), "director : $filearea");
            $this->assertSame($filearea !== 'directorreport', these_can_view_filearea($filearea, $student), "student : $filearea");
        }
        foreach (array_keys(these_document_definitions()) as $doctype) {
            $this->assertTrue(these_can_view_document($doctype, $deve), "deve : $doctype");
            $this->assertTrue(these_can_view_document($doctype, $library), "library : $doctype");
        }

        // Les modèles disposent d'un titre public pour un document destiné à d'autres personnes.
        $data = these_document_context($this->these, $this->context, $this->dossier, 'conformity');
        $this->assertSame(get_string('confidentialtitle', 'mod_these'), $data['thesis']['publictitle']);
    }

    /**
     * Page du dossier : aucun marqueur pour le détenteur de viewall ; tout pour la DEVE, la
     * bibliothèque et le directeur.
     */
    public function test_dossier_page(): void {
        global $PAGE;
        $this->setup_confidential();
        $PAGE->set_url(new \moodle_url('/mod/these/dossier.php'));

        foreach (['viewall' => $this->denied['viewall']] as $name => $user) {
            $this->setUser($user);
            $rights = these_get_rights($this->context, $this->dossier, $user->id);
            $html = these_render_dossier($this->these, $this->cm, $this->context, $this->dossier, $rights);
            $this->assert_no_secret($html, $name);
            $this->assertStringContainsString(get_string('confidentialtitle', 'mod_these'), $html, $name);
            // Le manuscrit reste listé ; aucun autre fichier (le manuscrit corrigé n'est listé
            // qu'avec des corrections demandées).
            foreach (THESE_FILEAREAS as $filearea) {
                $listed = strpos($html, $filearea . '.pdf') !== false;
                $this->assertSame($filearea === 'manuscript', $listed, "$name : $filearea");
            }
        }

        foreach (['deve' => $this->deve, 'library' => $this->librarian, 'director' => $this->director] as $name => $user) {
            $this->setUser($user);
            $rights = these_get_rights($this->context, $this->dossier, $user->id);
            $html = these_render_dossier($this->these, $this->cm, $this->context, $this->dossier, $rights);
            foreach (['title', 'titleen', 'summary', 'keywords', 'oldtitle', 'answer', 'embargoreason'] as $field) {
                $this->assertStringContainsString(self::SECRETS[$field], $html, "$name : $field");
            }
        }
    }

    /**
     * Listes, recherche, export du registre et planning (page, tableur, PDF).
     */
    public function test_lists_search_export_and_schedule(): void {
        $this->setup_confidential();
        $student = \core_user::get_user($this->student->id);
        $director = \core_user::get_user($this->director->id);
        $monday = these_planning_week_start(time());
        $sources = function ($user) {
            return [these_planning_source($this->these, $this->cm, $this->context, $user->id)];
        };

        // Recherche du tableau de bord : jamais par le titre d'une thèse confidentielle, sauf demande
        // explicite de qui connaît tous les sujets.
        foreach (['SECRETTITRE', 'maladie de Carré'] as $search) {
            $this->assertCount(0, these_get_dossiers($this->these->id, ['search' => $search]), $search);
            $this->assertCount(1, these_get_dossiers($this->these->id, ['search' => $search, 'confidentialsubjects' => true]));
        }
        $this->assertCount(1, these_get_dossiers($this->these->id, ['search' => '2026-099']));

        foreach (['viewall' => $this->denied['viewall']] as $name => $user) {
            $this->setUser($user);
            $dossiers = these_get_dossiers($this->these->id);
            $this->assert_no_secret(these_render_dossier_table($dossiers, $this->cm), "$name (liste)");
            $this->assert_no_secret(
                implode(' ', these_register_row($this->dossier, $this->context, $student, $director)),
                "$name (export)"
            );

            $defenses = these_get_defenses($sources($user), $monday, these_planning_shift_week($monday, 1), $user->id);
            $this->assertCount(1, $defenses, $name);
            $this->assert_no_secret(implode(' ', array_map('strval', these_defense_row($defenses[0]))), "$name (planning)");
            $this->assert_no_secret(these_render_planning($defenses, false), "$name (planning)");
            $pdfdata = these_planning_document_context($defenses, 'semaine', $this->these, null, false);
            $this->assert_no_secret(json_encode($pdfdata, JSON_UNESCAPED_UNICODE), "$name (planning PDF)");
        }

        // La DEVE, la bibliothèque et le directeur voient le titre partout.
        foreach (['deve' => $this->deve, 'library' => $this->librarian, 'director' => $this->director] as $name => $user) {
            $this->setUser($user);
            $this->assertStringContainsString(
                self::SECRETS['title'],
                these_render_dossier_table(these_get_dossiers($this->these->id), $this->cm),
                $name
            );
            $this->assertStringContainsString(
                self::SECRETS['title'],
                these_register_row($this->dossier, $this->context, $student, $director)['title'],
                $name
            );
            $defenses = these_get_defenses($sources($user), $monday, these_planning_shift_week($monday, 1), $user->id);
            $this->assertStringContainsString(self::SECRETS['title'], these_defense_row($defenses[0])['title'], $name);
        }

        // Ni l'autre directeur ni l'étudiant d'un autre dossier ne voient la soutenance au planning.
        foreach (['otherdirector', 'otherstudent'] as $name) {
            $user = $this->denied[$name];
            $week = these_get_defenses($sources($user), $monday, these_planning_shift_week($monday, 1), $user->id);
            $this->assertSame([], $week, $name);
        }
    }

    /**
     * Courriels : le titre n'est cité qu'aux destinataires qui connaissent le sujet, comptes ou
     * boîtes de service.
     */
    public function test_emails(): void {
        global $DB;
        $this->setup_confidential();
        $bodies = function ($sink) {
            $bodies = [];
            foreach ($sink->get_messages() as $message) {
                $bodies[$message->to][] = $message->subject . ' ' . quoted_printable_decode($message->body);
            }
            $sink->clear();
            return $bodies;
        };
        $sink = $this->redirectEmails();

        // Étape de la bibliothèque : notification puis relance, avec le titre.
        $dossier = clone $this->dossier;
        $dossier->status = THESE_STATUS_SUBJECT_REGISTERED;
        these_notify_actor($this->these, $this->cm, $dossier);
        these_send_reminder($this->these, $this->cm, $dossier);
        $received = $bodies($sink);
        $this->assertCount(2, $received[$this->librarian->email]);
        foreach ($received[$this->librarian->email] as $body) {
            $this->assertStringContainsString(self::SECRETS['title'], $body);
        }

        // Boîtes de service de la DEVE et de la bibliothèque : avec le titre.
        $this->these->deveemail = 'deve@example.org';
        $this->these->libraryemail = 'bibliotheque@example.org';
        $DB->update_record('these', $this->these);
        foreach ([THESE_STATUS_SUBJECT_REGISTERED, THESE_STATUS_SUBJECT_APPROVED] as $status) {
            $dossier->status = $status;
            these_notify_actor($this->these, $this->cm, $dossier);
        }
        $received = $bodies($sink);
        $this->assertCount(1, $received['bibliotheque@example.org']);
        $this->assertStringContainsString(self::SECRETS['title'], $received['bibliotheque@example.org'][0]);
        $this->assertCount(1, $received['deve@example.org']);
        $this->assertStringContainsString(self::SECRETS['title'], $received['deve@example.org'][0]);

        // Envoi direct à chaque profil : titre pour les uns, jamais pour les autres.
        $vars = these_email_base_vars($this->these, $this->cm, $this->dossier) + ['status' => 'x', 'oldtitle' =>
            self::SECRETS['oldtitle']];
        $recipients = array_merge(array_values($this->allowed), array_values($this->denied), [
            these_make_external_user('deve@example.org'),
            these_make_external_user('bibliotheque@example.org'),
            these_make_external_user('inconnu@example.org'),
        ]);
        these_send_email($this->these, 'subjectchanged', $recipients, $vars, '', '', $this->dossier);
        $received = $bodies($sink);
        foreach ($this->allowed as $name => $user) {
            $this->assertStringContainsString(self::SECRETS['title'], implode(' ', $received[$user->email]), $name);
        }
        $this->assertStringContainsString(self::SECRETS['title'], implode(' ', $received['deve@example.org']));
        $this->assertStringContainsString(self::SECRETS['title'], implode(' ', $received['bibliotheque@example.org']));
        foreach ($this->denied as $name => $user) {
            $this->assert_no_secret(implode(' ', $received[$user->email]), $name);
        }
        $this->assert_no_secret(implode(' ', $received['inconnu@example.org']), 'adresse inconnue');
        $sink->close();
    }

    /**
     * Sans compte, seules les adresses qui doivent connaître le sujet reçoivent le titre : membres du
     * jury, université, directeur extérieur et signataires de l'établissement ; la désignation du
     * jury et les demandes de signature le citent donc même pour une thèse confidentielle.
     */
    public function test_external_recipients_who_must_know_the_subject(): void {
        global $DB;
        $this->setup_confidential();
        $this->these->universityemail = 'archives@universite.example.org';
        $DB->update_record('these', $this->these);
        \mod_these\local\scopesettings::set(these_settings_scope($this->these), ['ccememail' => 'ccem@example.org']);
        $DB->update_record('these_dossier', (object) ['id' => $this->dossier->id, 'externaldirector' => 1,
            'extdirectorname' => 'Dr Externe', 'extdirectoremail' => 'Externe@example.org']);
        $dossier = $this->reload($this->dossier->id);
        $knows = function (string $email) use ($dossier) {
            return these_recipient_knows_subject($this->these, $dossier, these_make_external_user($email));
        };
        $this->assertTrue($knows('president@example.com'));
        $this->assertTrue($knows('ARCHIVES@universite.example.org'));
        $this->assertTrue($knows('externe@example.org'));
        $this->assertTrue($knows('ccem@example.org'));
        $this->assertFalse($knows('inconnu@example.org'));
        $this->assertFalse($knows(''));

        $sink = $this->redirectEmails();
        these_notify_jury($this->these, $this->cm, $dossier, true);
        these_request_signature($this->these, $this->cm, $dossier, 'extdirector', ['subject']);
        $bodies = [];
        foreach ($sink->get_messages() as $message) {
            $bodies[$message->to][] = $message->subject . ' ' . quoted_printable_decode($message->body);
        }
        $sink->close();
        $this->assertStringContainsString(self::SECRETS['title'], implode(' ', $bodies['president@example.com']));
        $this->assertStringContainsString(self::SECRETS['title'], implode(' ', $bodies['Externe@example.org']));
    }
}
