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
 * Test de charge du circuit des thèses : jeu de données, opérations tirées au hasard et invariants.
 *
 * Chaque opération reproduit une page du module (review.php, cancel.php, subjectchange.php...) :
 * elle relit le dossier, applique les mêmes contrôles d'accès et de statut que la page, puis appelle
 * les mêmes fonctions du circuit, en tant que l'utilisateur qui agit. Un contrôle de page qui refuse
 * l'action lève stress_refusal (refus attendu) ; toute autre exception, tout avertissement PHP et
 * tout appel à debugging() est une erreur inattendue.
 *
 * Fichier chargé par stress.php et perf.php ; ne pas l'appeler directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');
require_once(__DIR__ . '/refusal.php');
require_once(__DIR__ . '/phperror.php');

use mod_these\local\dossiers;
use mod_these\local\prerequisites;
use mod_these\local\signatures;
use mod_these\local\signatureagent;
use mod_these\local\signaturetokens;

/**
 * Jeu de données, opérations et invariants du test de charge.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stress_runner {
    /** Préfixe des comptes, cours et activités créés. */
    const PREFIX = 'stress';

    /** Codes d'erreur Moodle qui traduisent un refus documenté. */
    const EXPECTED_CODES = ['dossierchanged', 'dossierbusy', 'notyourstep', 'nopermissions', 'refusenothing',
        'requireloginerror', 'invalidaccess', 'accessdenied'];

    /** @var int Graine du tirage. */
    public $seed;
    /** @var int Délai artificiel (millisecondes) entre la lecture d'une page et son écriture. */
    public $latency = 0;
    /** @var bool Rendu des pages après chaque opération (confidentialité). */
    public $render = true;
    /** @var array Activités : [these, cm, context]. */
    public $activities = [];
    /** @var stdClass[] Comptes par rôle : students, directors, deve, agents, library, viewall, teacher, jury. */
    public $users = [];
    /** @var int[] Annuaire du jury : adresse => identifiant. */
    public $jurors = [];
    /** @var array Résultats : type => [ok, refused, unexpected, time, max]. */
    public $stats = [];
    /** @var array Erreurs inattendues. */
    public $errors = [];
    /** @var array Invariants violés. */
    public $violations = [];
    /** @var int Numéro de l'opération courante. */
    public $opnumber = 0;
    /** @var string Nom du processus (concurrence). */
    public $worker = 'main';
    /** @var array Dossiers dont le procès-verbal doit survivre à une suppression RGPD : id => empreintes. */
    protected $keptminutes = [];
    /** @var stdClass Instance de l'activité de suivi. */
    public $synthesis;
    /** @var int[] Dossiers auxquels se limite le tirage (concurrence), vide pour tous. */
    protected $focus = [];
    /** @var stdClass|null Étudiant imposé à la prochaine création de dossier (peuplement). */
    protected $forcestudent = null;

    /**
     * Constructeur.
     *
     * @param int $seed
     */
    public function __construct($seed) {
        $this->seed = (int) $seed;
        mt_srand($this->seed);
    }

    // Jeu de données.

    /**
     * Crée (ou reprend) cours, activités, activité de suivi, comptes et annuaire.
     *
     * @param int $students Nombre d'étudiants.
     * @param int $promotions Nombre d'activités « Gestion des thèses » (promotions).
     * @return void
     */
    public function setup($students, $promotions) {
        global $DB;

        // Courriels de bienvenue des inscriptions : coupés par $CFG->noemailever, sans intérêt ici.
        ob_start();
        try {
            $this->create_dataset($students, $promotions);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Voir setup().
     *
     * @param int $students
     * @param int $promotions
     * @return void
     */
    protected function create_dataset($students, $promotions) {
        global $DB;

        \core\session\manager::set_user(get_admin());
        $gen = new testing_data_generator();
        $prefix = self::PREFIX;
        $course = $DB->get_record('course', ['shortname' => strtoupper($prefix)]);
        if (!$course) {
            $course = $gen->create_course(['fullname' => 'Thèses (test de charge)', 'shortname' => strtoupper($prefix)]);
        }
        $suivi = $DB->get_record('course', ['shortname' => strtoupper($prefix) . 'SUIVI']);
        if (!$suivi) {
            $suivi = $gen->create_course(['fullname' => 'Suivi (test de charge)', 'shortname' => strtoupper($prefix) . 'SUIVI']);
        }
        $synthesis = $DB->get_record('thesesynthesis', ['course' => $suivi->id]);
        if (!$synthesis) {
            $synthesis = $gen->create_module('thesesynthesis', ['course' => $suivi->id, 'name' => 'Suivi des thèses']);
            $synthesis = $DB->get_record('thesesynthesis', ['id' => $synthesis->id]);
        }
        $this->synthesis = $synthesis;
        $existing = array_values($DB->get_records('these', ['course' => $course->id], 'id ASC'));
        for ($p = count($existing); $p < $promotions; $p++) {
            $these = $gen->create_module('these', [
                'course' => $course->id, 'name' => 'Promotion ' . (2026 + $p),
                'establishmentname' => 'École vétérinaire', 'establishmentcity' => 'Lyon',
                'signatoryname' => 'La directrice', 'signatorytitle' => 'Directrice générale',
                'minjury' => 3, 'reminderdays' => 14, 'universityemail' => 'theses@univ.example.org',
            ]);
            $existing[] = $DB->get_record('these', ['id' => $these->id]);
        }
        foreach (array_slice($existing, 0, max(1, $promotions)) as $these) {
            $cm = get_coursemodule_from_instance('these', $these->id);
            if (!$DB->record_exists('thesesynthesis_link', ['synthesisid' => $synthesis->id, 'thesecmid' => $cm->id])) {
                $DB->insert_record('thesesynthesis_link', (object) ['synthesisid' => $synthesis->id, 'thesecmid' => $cm->id,
                    'timecreated' => time()]);
            }
            $this->activities[] = (object) ['these' => $these, 'cm' => $cm, 'context' => context_module::instance($cm->id)];
        }

        $mk = function ($username, $first, $last, $role, $courses) use ($gen, $DB) {
            $user = $DB->get_record('user', ['username' => $username]);
            if (!$user) {
                $user = $gen->create_user(['username' => $username, 'password' => 'Stress1234!', 'firstname' => $first,
                    'lastname' => $last, 'email' => $username . '@example.org']);
            }
            foreach ($courses as $courseid) {
                if ($role && !is_enrolled(context_course::instance($courseid), $user)) {
                    $gen->enrol_user($user->id, $courseid, $role);
                }
            }
            return $user;
        };
        $both = [$course->id, $suivi->id];
        $this->users['deve'] = $mk("{$prefix}deve", 'Sylvie', 'Deve', 'editingteacher', $both);
        $this->users['agents'] = [
            $mk("{$prefix}agent1", 'Anne', 'Agent', 'editingteacher', $both),
            $mk("{$prefix}agent2", 'Paul', 'Agent', 'editingteacher', $both),
        ];
        $this->users['library'] = $mk("{$prefix}lib", 'Bruno', 'Bibliothèque', 'teacher', $both);
        $this->users['teacher'] = $mk("{$prefix}teacher", 'Nadia', 'Enseignante', 'teacher', [$course->id]);
        $this->users['directors'] = [];
        $ndirectors = max(4, (int) ceil($students / 12));
        for ($i = 1; $i <= $ndirectors; $i++) {
            $this->users['directors'][] = $mk("{$prefix}dir{$i}", 'Directeur', 'N' . $i, 'teacher', $both);
        }
        // Membres du jury avec un compte (sans rôle) : rattachés par l'annuaire.
        $this->users['jury'] = [];
        for ($i = 1; $i <= 4; $i++) {
            $this->users['jury'][] = $mk("{$prefix}jury{$i}", 'Juré', 'N' . $i, null, []);
        }
        $this->users['viewall'] = $mk("{$prefix}viewall", 'Sophie', 'Scolarité', null, []);
        $roleid = $DB->get_field('role', 'id', ['shortname' => "{$prefix}viewall"]);
        if (!$roleid) {
            $roleid = $gen->create_role(['shortname' => "{$prefix}viewall", 'name' => 'Scolarité (thèses)']);
            assign_capability('mod/these:view', CAP_ALLOW, $roleid, context_system::instance()->id);
            assign_capability('mod/these:viewall', CAP_ALLOW, $roleid, context_system::instance()->id);
        }
        if (!is_enrolled(context_course::instance($course->id), $this->users['viewall'])) {
            $gen->enrol_user($this->users['viewall']->id, $course->id, "{$prefix}viewall");
        }
        $this->users['students'] = [];
        $have = $DB->get_records_select('user', $DB->sql_like('username', ':u'), ['u' => "{$prefix}s%"], 'id ASC');
        foreach ($have as $user) {
            if (preg_match('/^' . $prefix . 's\d+$/', $user->username)) {
                $this->users['students'][] = $user;
            }
        }
        for ($i = count($this->users['students']) + 1; $i <= $students; $i++) {
            $this->users['students'][] = $mk("{$prefix}s{$i}", 'Étudiant', 'N' . $i, 'student', [$course->id]);
        }
        $this->users['students'] = array_slice($this->users['students'], 0, max($students, 1));

        // Annuaire : présidents (professeurs), membres, directeurs et jurés avec compte.
        $directory = [];
        for ($i = 1; $i <= 6; $i++) {
            $directory[] = ['Pr', 'PRESIDENT' . $i, 'Paul', "president{$i}@example.org", 'pr'];
        }
        for ($i = 1; $i <= 6; $i++) {
            $directory[] = ['Dr', 'MEMBRE' . $i, 'Marie', "membre{$i}@example.org", 'mcf'];
        }
        foreach (array_merge($this->users['directors'], $this->users['jury']) as $user) {
            $directory[] = ['Dr', strtoupper($user->lastname), $user->firstname, $user->email, 'mcfhdr'];
        }
        foreach ($directory as [$title, $last, $first, $email, $status]) {
            $id = $DB->get_field('these_juror', 'id', ['email' => $email]);
            if (!$id) {
                $id = $DB->insert_record('these_juror', (object) ['title' => $title, 'lastname' => $last, 'firstname' => $first,
                    'email' => $email, 'status' => $status, 'institution' => 'École vétérinaire', 'active' => 1,
                    'timecreated' => time(), 'timemodified' => time()]);
            }
            $this->jurors[$email] = (int) $id;
        }

        // Paramètres du circuit, portés par l'activité de suivi : agents, bibliothèque, signataires.
        $scope = these_settings_scope($this->activities[0]->these);
        \mod_these\local\scopesettings::reset_cache();
        \mod_these\local\scopesettings::set($scope, [
            'deveagent' => "{$prefix}agent1, {$prefix}agent2", 'libraryagent' => "{$prefix}lib",
            'institution' => 'École vétérinaire', 'university' => "l'Université", 'visaplace' => 'Lyon',
            'devename' => 'Anne AGENT', 'dgtitle' => 'La Directrice générale', 'ucbltitle' => "Le Président de l'Université",
            'dgname' => 'Pr Directrice', 'dgemail' => 'dg@example.org', 'ccemname' => 'Pr Ccem', 'ccememail' => 'ccem@example.org',
            'ucblname' => 'M. Président', 'ucblemail' => 'presidence@example.org',
            'defenserooms' => "Amphithéâtre A\nSalle du conseil\nSalle 12",
        ]);
        if (!these_get_questions($scope)) {
            these_save_question($scope, (object) ['qtype' => 'list', 'label' => 'Espèce', 'options' => "Chien\nChat\nCheval",
                'required' => 1]);
            these_save_question($scope, (object) ['qtype' => 'text', 'label' => 'Laboratoire', 'options' => '', 'required' => 0]);
        }
        accesslib_clear_all_caches(true);
        \mod_these\local\scopesettings::reset_cache();
    }

    // Outils.

    /**
     * Entier tiré au hasard (graine reproductible).
     *
     * @param int $min
     * @param int $max
     * @return int
     */
    public function rand($min, $max) {
        return mt_rand($min, $max);
    }

    /**
     * Élément tiré au hasard.
     *
     * @param array $items
     * @return mixed
     */
    public function pick(array $items) {
        $items = array_values($items);
        return $items ? $items[mt_rand(0, count($items) - 1)] : null;
    }

    /**
     * Vrai avec la probabilité donnée (en pourcentage).
     *
     * @param int $percent
     * @return bool
     */
    public function chance($percent) {
        return mt_rand(1, 100) <= $percent;
    }

    /**
     * Agit en tant qu'un utilisateur (relu depuis la base, comme à sa connexion).
     *
     * @param stdClass $user
     * @return stdClass
     */
    protected function act_as(stdClass $user) {
        global $DB;
        $fresh = $DB->get_record('user', ['id' => $user->id]);
        if (!$fresh || $fresh->deleted || $fresh->suspended) {
            // Compte supprimé ou suspendu : la connexion est refusée.
            \core\session\manager::set_user(get_admin());
            throw new stress_refusal('connexion impossible (compte supprimé ou suspendu)');
        }
        \core\session\manager::set_user($fresh);
        return $fresh;
    }

    /**
     * Délai artificiel entre lecture et écriture, pour élargir les fenêtres de concurrence.
     *
     * @return void
     */
    protected function think() {
        if ($this->latency > 0) {
            usleep(mt_rand(0, $this->latency * 1000));
        }
    }

    /**
     * Relit un dossier.
     *
     * @param int $id
     * @return stdClass|null
     */
    public function reload($id) {
        global $DB;
        return $DB->get_record('these_dossier', ['id' => $id]) ?: null;
    }

    /**
     * Activité d'un dossier.
     *
     * @param stdClass $dossier
     * @return stdClass
     */
    public function activity(stdClass $dossier) {
        foreach ($this->activities as $activity) {
            if ((int) $activity->these->id === (int) $dossier->theseid) {
                return $activity;
            }
        }
        throw new coding_exception('Activité inconnue ' . $dossier->theseid);
    }

    /**
     * Activité (promotion) d'un étudiant.
     *
     * @param stdClass $student
     * @return stdClass
     */
    protected function student_activity(stdClass $student) {
        return $this->activities[(int) $student->id % count($this->activities)];
    }

    /**
     * Dossier tiré au hasard, éventuellement parmi certains statuts.
     *
     * @param int[]|null $statuses
     * @return stdClass|null
     */
    public function random_dossier(?array $statuses = null) {
        global $DB;
        $theseids = array_map(function ($a) {
            return $a->these->id;
        }, $this->activities);
        [$insql, $params] = $DB->get_in_or_equal($theseids);
        $where = "theseid $insql";
        if ($this->focus) {
            [$fsql, $fparams] = $DB->get_in_or_equal($this->focus);
            $where .= " AND id $fsql";
            $params = array_merge($params, $fparams);
        }
        if ($statuses !== null) {
            [$ssql, $sparams] = $DB->get_in_or_equal($statuses);
            $where .= " AND status $ssql";
            $params = array_merge($params, $sparams);
        }
        $count = $DB->count_records_select('these_dossier', $where, $params);
        if (!$count) {
            return null;
        }
        $records = $DB->get_records_select('these_dossier', $where, $params, 'id ASC', '*', mt_rand(0, $count - 1), 1);
        return reset($records) ?: null;
    }

    /**
     * Signature tracée (image d'exemple).
     *
     * @param stdClass $user
     * @return stdClass
     */
    protected function signature(stdClass $user) {
        return (object) ['name' => fullname($user), 'png' => these_sample_signature_image()];
    }

    /**
     * Fiche de sujet tirée au hasard. Le titre porte un marqueur unique, cherché dans les pages pour
     * vérifier la confidentialité.
     *
     * @param stdClass|null $director
     * @return stdClass
     */
    protected function subject_data(?stdClass $director = null) {
        $marker = 'SUJET' . strtoupper(random_string(10));
        $external = $this->chance(15);
        $director = $director ?? $this->pick($this->users['directors']);
        return (object) [
            'title' => "Étude $marker sur la santé animale", 'titleen' => $this->chance(50) ? "Study $marker" : '',
            'summary' => "Résumé de l'étude $marker.", 'keywords' => 'chien ; chat', 'studyyear' => 'A5',
            'directorid' => $director->id, 'confidential' => $this->chance(25) ? 1 : 0,
            'externaldirector' => $external ? 1 : 0,
            'extdirectorname' => $external ? 'Dr Externe' : '', 'extdirectorgrade' => $external ? 'PU-PH' : '',
            'extdirectorinstitution' => $external ? 'CHU' : '', 'extdirectoremail' => $external ? 'externe@example.org' : '',
            'commitguide' => 1, 'commitduplicate' => 1, 'commitethics' => $this->chance(50) ? 1 : 0,
        ];
    }

    /**
     * Jury proposé par l'étudiant : président et membre de l'annuaire, directeur, parfois un juré
     * avec compte et un invité sans adresse.
     *
     * @param stdClass $dossier
     * @return array Membres (voir these_save_jury()).
     */
    protected function jury_members(stdClass $dossier) {
        global $DB;
        $members = [];
        $president = 'president' . $this->rand(1, 6) . '@example.org';
        $members[] = (object) ['juryrole' => 'president', 'fullname' => 'Paul PRESIDENT', 'jurytitle' => 'Pr',
            'jurystatus' => 'pr', 'institution' => 'Université', 'email' => $president, 'jurorid' => $this->jurors[$president]];
        $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;
        if ($director && isset($this->jurors[$director->email])) {
            $members[] = (object) ['juryrole' => 'director', 'fullname' => fullname($director), 'jurytitle' => 'Dr',
                'jurystatus' => 'mcfhdr', 'institution' => 'École vétérinaire', 'email' => $director->email,
                'jurorid' => $this->jurors[$director->email]];
        }
        $member = 'membre' . $this->rand(1, 6) . '@example.org';
        $members[] = (object) ['juryrole' => 'member', 'fullname' => 'Marie MEMBRE', 'jurytitle' => 'Dr', 'jurystatus' => 'mcf',
            'institution' => 'École vétérinaire', 'email' => $member, 'jurorid' => $this->jurors[$member]];
        if ($this->chance(50)) {
            $juror = $this->pick($this->users['jury']);
            $members[] = (object) ['juryrole' => 'member', 'fullname' => fullname($juror), 'jurytitle' => 'Dr',
                'jurystatus' => 'mcfhdr', 'institution' => 'École vétérinaire', 'email' => $juror->email,
                'jurorid' => $this->jurors[$juror->email]];
        }
        if ($this->chance(30)) {
            $members[] = (object) ['juryrole' => 'guest', 'fullname' => 'Iris Invitée', 'jurytitle' => 'Dr',
                'jurystatus' => 'other', 'institution' => 'Clinique', 'email' => ''];
        }
        return $members;
    }

    /**
     * Dépose un fichier PDF minimal dans une zone d'un dossier.
     *
     * @param context $context
     * @param string $filearea
     * @param int $dossierid
     * @param string $filename
     * @return void
     */
    protected function put_file(context $context, $filearea, $dossierid, $filename) {
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_these', $filearea, $dossierid);
        $fs->create_file_from_string(
            ['contextid' => $context->id, 'component' => 'mod_these', 'filearea' => $filearea,
            'itemid' => $dossierid, 'filepath' => '/', 'filename' => $filename],
            "%PDF-1.4\n% " . $filearea . ' ' . random_string(16) . "\n%%EOF\n"
        );
    }

    /**
     * Acteur attendu d'une étape (compte), pour un dossier.
     *
     * @param stdClass $dossier
     * @param string $actor
     * @return stdClass|null
     */
    protected function actor_user(stdClass $dossier, $actor) {
        global $DB;
        switch ($actor) {
            case 'student':
                return $DB->get_record('user', ['id' => $dossier->userid]) ?: null;
            case 'director':
                return $dossier->directorid ? ($DB->get_record('user', ['id' => $dossier->directorid]) ?: null) : null;
            case 'deve':
                return $this->chance(80) ? $this->users['deve'] : $this->pick($this->users['agents']);
            case 'library':
                return $this->users['library'];
        }
        return null;
    }

    /**
     * Compte qui n'est pas l'acteur attendu (pour vérifier le refus).
     *
     * @param stdClass $dossier
     * @param string $actor
     * @return stdClass
     */
    protected function wrong_user(stdClass $dossier, $actor) {
        $candidates = [$this->users['teacher'], $this->users['viewall'], $this->pick($this->users['students'])];
        if ($actor !== 'director') {
            $candidates[] = $this->pick($this->users['directors']);
        }
        if ($actor !== 'library' && $actor !== 'deve') {
            $candidates[] = $this->users['library'];
        }
        $candidates = array_filter($candidates, function ($user) use ($dossier) {
            return (int) $user->id !== (int) $dossier->directorid && (int) $user->id !== (int) $dossier->userid;
        });
        return $this->pick($candidates);
    }

    // Opérations.

    /**
     * Opérations disponibles et leur poids dans le tirage.
     *
     * @return array nom => poids
     */
    public function operations() {
        return [
            'create' => 6, 'savesubject' => 10, 'review' => 30, 'returndossier' => 6, 'changesubject' => 5,
            'cancel' => 4, 'reopen' => 3, 'savedefense' => 10, 'report' => 5, 'daterequest' => 3, 'newdate' => 3,
            'editdossier' => 4, 'sign' => 25, 'signtoken' => 6, 'refuse' => 3, 'documents' => 6, 'minutes' => 6,
            'corrections' => 5, 'notifyjury' => 2, 'tokens' => 2, 'suspend' => 1, 'deleteuser' => 1, 'privacy' => 2,
            'illegitimate' => 8,
        ];
    }

    /**
     * Tire une opération au hasard selon les poids.
     *
     * @return string
     */
    public function draw_operation() {
        $ops = $this->operations();
        $n = mt_rand(1, array_sum($ops));
        foreach ($ops as $name => $weight) {
            $n -= $weight;
            if ($n <= 0) {
                return $name;
            }
        }
        return 'review';
    }

    /**
     * Exécute une opération et classe son résultat ; vérifie ensuite les invariants des dossiers touchés.
     *
     * @param string $name
     * @param int|null $dossierid Dossier imposé (sinon tiré au hasard par l'opération).
     * @return string ok, refused ou unexpected
     */
    public function run_operation($name, $dossierid = null) {
        global $CFG;

        $this->opnumber++;
        $touched = [];
        $start = microtime(true);
        $outcome = 'ok';
        $message = '';
        $previoushandler = set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            throw new stress_php_error($errstr, 0, $errno, $errfile, $errline);
        });
        ob_start();
        try {
            $method = 'op_' . $name;
            $result = $this->$method($dossierid, $touched);
            if ($result === false) {
                $outcome = 'skipped';
            }
        } catch (stress_refusal $e) {
            $outcome = 'refused';
            $message = $e->getMessage();
        } catch (moodle_exception $e) {
            if (
                !($e instanceof coding_exception) && !($e instanceof dml_exception)
                && in_array($e->errorcode, self::EXPECTED_CODES, true)
            ) {
                $outcome = 'refused';
                $message = $e->errorcode;
            } else {
                $outcome = 'unexpected';
                $this->record_error($name, $e, $touched);
            }
        } catch (Throwable $e) {
            $outcome = 'unexpected';
            $this->record_error($name, $e, $touched);
        } finally {
            $output = ob_get_clean();
            set_error_handler($previoushandler);
            \core\session\manager::set_user(get_admin());
        }
        // Appels à debugging() : affichés en ligne de commande entre « ++ ».
        if (preg_match_all('/^\+\+ (.+?) \+\+$/ms', (string) $output, $matches)) {
            foreach ($matches[1] as $message) {
                if (strpos($message, 'noemailever') !== false) {
                    // Envois coupés par $CFG->noemailever, exigé par stress.php.
                    continue;
                }
                $outcome = 'unexpected';
                $this->record_error($name, new stress_php_error('debugging() : ' . $message), $touched);
            }
        }
        $elapsed = microtime(true) - $start;
        $stat = &$this->stats[$name];
        $stat = ($stat ?? ['ok' => 0, 'refused' => 0, 'unexpected' => 0, 'skipped' => 0, 'time' => 0.0, 'max' => 0.0]);
        $stat[$outcome]++;
        $stat['time'] += $elapsed;
        $stat['max'] = max($stat['max'], $elapsed);
        unset($stat);
        foreach (array_unique($touched) as $id) {
            foreach ($this->check_dossier($id) as $violation) {
                $this->violation($violation, $name, $id);
            }
        }
        return $outcome;
    }

    /**
     * Mémorise une erreur inattendue.
     *
     * @param string $name Opération.
     * @param Throwable $e
     * @param int[] $touched Dossiers touchés.
     * @return void
     */
    protected function record_error($name, Throwable $e, array $touched) {
        $trace = [];
        foreach (array_slice($e->getTrace(), 0, 6) as $frame) {
            $trace[] = (isset($frame['file']) ? basename($frame['file']) . ':' . $frame['line'] : '?')
                . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'];
        }
        $debuginfo = $e instanceof moodle_exception && !empty($e->debuginfo) ? ' [' . $e->debuginfo . ']' : '';
        $this->errors[] = [
            'worker' => $this->worker, 'seed' => $this->seed, 'op' => $this->opnumber, 'name' => $name,
            'dossiers' => implode(',', array_unique($touched)),
            'error' => get_class($e) . ': ' . $e->getMessage() . $debuginfo,
            'where' => basename($e->getFile()) . ':' . $e->getLine(), 'trace' => $trace,
        ];
    }

    /**
     * Mémorise un invariant violé.
     *
     * @param string $message
     * @param string $name Opération.
     * @param int $dossierid
     * @return void
     */
    protected function violation($message, $name, $dossierid) {
        $this->violations[] = ['worker' => $this->worker, 'seed' => $this->seed, 'op' => $this->opnumber, 'name' => $name,
            'dossier' => $dossierid, 'message' => $message];
    }

    /**
     * Création d'un dossier par la DEVE (create.php) pour un étudiant qui n'en a pas.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_create($dossierid, array &$touched) {
        global $DB;
        $student = $this->forcestudent ?? $this->pick($this->users['students']);
        $activity = $this->student_activity($student);
        $user = $this->act_as($this->chance(90) ? $this->users['deve'] : $this->pick($this->users['agents']));
        these_require_service($activity->context, 'deve');
        if ($DB->record_exists('these_dossier', ['theseid' => $activity->these->id, 'userid' => $student->id])) {
            // Le formulaire ne propose que les étudiants sans dossier.
            throw new stress_refusal('dossier existant');
        }
        if (!$DB->record_exists('user', ['id' => $student->id, 'deleted' => 0])) {
            throw new stress_refusal('étudiant supprimé');
        }
        $this->think();
        $dossier = these_create_dossier($activity->these->id, $student->id, $user->id);
        $touched[] = $dossier->id;
        return true;
    }

    /**
     * Enregistrement ou soumission du sujet par l'étudiant (subject.php), avec création du dossier.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_savesubject($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid)
            : $this->random_dossier([THESE_STATUS_DRAFT, THESE_STATUS_SUBJECT_RETURNED]);
        if ($dossier === null || $this->chance(20)) {
            $student = $this->pick($this->users['students']);
            $activity = $this->student_activity($student);
        } else {
            $activity = $this->activity($dossier);
            $student = (object) ['id' => $dossier->userid];
        }
        $student = $this->act_as($student);
        require_capability('mod/these:submit', $activity->context);
        $dossier = these_get_user_dossier($activity->these->id, $student->id);
        if ($dossier && !these_subject_editable($dossier)) {
            throw new stress_refusal('sujet non modifiable');
        }
        $data = $this->subject_data();
        $submit = $this->chance(75);
        $this->think();
        if (!$dossier) {
            $dossier = these_create_dossier($activity->these->id, $student->id, $student->id);
        }
        $touched[] = $dossier->id;
        these_save_subject(
            $activity->these,
            $activity->cm,
            $dossier,
            $data,
            $submit,
            $student->id,
            $submit ? $this->signature($student) : null
        );
        return true;
    }

    /**
     * Validation d'une étape par son acteur (review.php), en complétant au besoin ce que la page
     * exige (signatures déjà apposées, DEFV, rapport, éligibilité du jury).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_review($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier(array_keys(these_review_steps()));
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $step = these_review_steps()[(int) $dossier->status] ?? null;
        if ($step === null) {
            throw new stress_refusal('aucune étape');
        }
        $activity = $this->activity($dossier);
        $actor = $this->actor_user($dossier, $step['actor']);
        if (!$actor) {
            throw new stress_refusal('acteur absent');
        }
        if ($step['key'] === 'directordefense' && empty($dossier->reportfavorable) && $this->chance(70)) {
            // Rapport du directeur déposé avant la validation.
            $this->op_report($dossier->id, $touched);
            $dossier = $this->reload($dossier->id);
        }
        if ($step['key'] === 'devedefense' && $this->chance(80)) {
            $a6 = \mod_these\local\a6::get($dossier);
            if (empty($a6['defvdate'])) {
                \mod_these\local\a6::set(
                    $dossier,
                    $a6['date'],
                    '',
                    $this->users['deve']->id,
                    ['date' => time() - 30 * DAYSECS, 'school' => 'lyon']
                );
            }
        }
        $user = $this->act_as($actor);
        $context = $activity->context;
        these_require_view($context);
        $dossier = $this->reload($dossier->id);
        $rights = these_get_rights($context, $dossier, $user->id);
        if (!these_can_view_dossier($rights)) {
            throw new stress_refusal('dossier non visible');
        }
        $step = these_review_steps()[(int) $dossier->status] ?? null;
        if ($step === null) {
            throw new stress_refusal('plus d\'étape');
        }
        if (empty($rights->{$step['actor']})) {
            throw new moodle_exception('notyourstep', 'mod_these');
        }
        if (prerequisites::missing($dossier)) {
            throw new stress_refusal('signatures manquantes');
        }
        if ($step['key'] === 'devedefense' && \mod_these\local\daterequest::pending($dossier)) {
            throw new stress_refusal('nouvelle date attendue');
        }
        $fields = [];
        switch ($step['key']) {
            case 'librarysubject':
                $fields['libraryref'] = 'SUDOC-' . $dossier->id;
                break;
            case 'directordefense':
                if (
                    empty(\mod_these\local\files::get_files($context, 'directorreport', $dossier->id))
                        || empty($dossier->reportfavorable)
                ) {
                    // Le formulaire exige le rapport favorable.
                    throw new stress_refusal('rapport absent');
                }
                $grid = [];
                foreach (these_get_grid_items(these_settings_scope($activity->these)) as $items) {
                    foreach (array_keys($items) as $item) {
                        $grid[$item] = 1;
                    }
                }
                $fields['conformitygrid'] = json_encode($grid);
                $fields['agreement'] = json_encode(array_merge(
                    these_get_agreement($dossier),
                    ['directorfunction' => 'Maître de conférences']
                ));
                break;
            case 'devedefense':
                if (empty(\mod_these\local\a6::get($dossier)['defvdate'])) {
                    throw new stress_refusal('DEFV non saisi');
                }
                $fields['devenumber'] = these_suggest_devenumber($activity->these->id);
                $fields['defensedate'] = (int) ($dossier->defensedate ?: $dossier->proposeddate);
                $fields['defenselocation'] = $this->pick(['Amphithéâtre A', 'Salle du conseil', 'Salle 12']);
                $fields['agreement'] = json_encode(array_merge(these_get_agreement($dossier), [
                    'defvdate' => (int) \mod_these\local\a6::get($dossier)['defvdate'], 'mobility' => 0]));
                $jury = these_get_jury($dossier->id);
                $form = these_jury_form_data($jury);
                foreach (array_keys($form['juryeligible']) as $i) {
                    $form['juryeligible'][$i] = 1;
                }
                $members = these_extract_submitted_jury((object) $form, true);
                if (these_validate_jury($members, $activity->these->minjury)) {
                    throw new stress_refusal('jury incomplet');
                }
                $this->think();
                these_save_jury($dossier->id, $members, $user->id);
                break;
        }
        $signs = false;
        foreach ($step['sign'] as $slot => $doctypes) {
            if (these_signs_slot_at_validation($slot, $user->id, $activity->these)) {
                $signs = true;
            }
        }
        $this->think();
        these_validate_step(
            $activity->these,
            $activity->cm,
            $dossier,
            $user->id,
            $fields,
            '',
            $signs ? $this->signature($user) : null
        );
        if ($step['key'] === 'devedefense') {
            these_notify_jury($activity->these, $activity->cm, $this->reload($dossier->id));
        }
        return true;
    }

    /**
     * Renvoi d'un dossier à l'étudiant ou au directeur (review.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_returndossier($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier(array_keys(these_review_steps()));
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $step = these_review_steps()[(int) $dossier->status] ?? null;
        if (!$step) {
            throw new stress_refusal('aucune étape');
        }
        $activity = $this->activity($dossier);
        $actor = $this->actor_user($dossier, $step['actor']);
        if (!$actor) {
            throw new stress_refusal('acteur absent');
        }
        $user = $this->act_as($actor);
        $dossier = $this->reload($dossier->id);
        $rights = these_get_rights($activity->context, $dossier, $user->id);
        $step = these_review_steps()[(int) $dossier->status] ?? null;
        if (!$step || empty($rights->{$step['actor']})) {
            throw new moodle_exception('notyourstep', 'mod_these');
        }
        $this->think();
        these_return_dossier(
            $activity->these,
            $activity->cm,
            $dossier,
            $user->id,
            $this->pick($step['returnto']),
            'Motif de renvoi ' . random_string(6)
        );
        return true;
    }

    /**
     * Changement de sujet par l'étudiant (subjectchange.php), dossier actif ou abandonné.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_changesubject($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid)
            : $this->random_dossier($this->chance(80) ? [THESE_STATUS_ACTIVE, THESE_STATUS_CANCELLED] : null);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $student = $this->act_as((object) ['id' => $dossier->userid]);
        require_capability('mod/these:submit', $activity->context);
        $dossier = these_get_user_dossier($activity->these->id, $student->id);
        if (!$dossier || !these_subject_changeable($dossier)) {
            throw new stress_refusal('changement de sujet impossible');
        }
        // Nouveau directeur une fois sur deux.
        $director = $this->chance(50) || !$dossier->directorid ? null : (object) ['id' => $dossier->directorid];
        $data = $this->subject_data($director);
        $this->think();
        these_change_subject(
            $activity->these,
            $activity->cm,
            $dossier,
            $data,
            $student->id,
            'Changement de terrain ' . random_string(4),
            $this->signature($student)
        );
        return true;
    }

    /**
     * Abandon d'un dossier par la DEVE (cancel.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_cancel($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier();
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $user = $this->act_as($this->users['deve']);
        these_require_service($activity->context, 'deve');
        $dossier = $this->reload($dossier->id);
        if (in_array((int) $dossier->status, [THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED], true)) {
            throw new stress_refusal('dossier clos ou abandonné');
        }
        $this->think();
        these_cancel_dossier($dossier, $user->id, 'Abandon ' . random_string(4));
        return true;
    }

    /**
     * Réouverture d'un dossier abandonné (cancel.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_reopen($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_CANCELLED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $user = $this->act_as($this->users['deve']);
        these_require_service($activity->context, 'deve');
        $dossier = $this->reload($dossier->id);
        if ((int) $dossier->status !== THESE_STATUS_CANCELLED) {
            throw new stress_refusal('dossier non abandonné');
        }
        $this->think();
        these_reopen_dossier($dossier, $user->id, 'Réouverture');
        return true;
    }

    /**
     * Dépôt (ou enregistrement) du dossier de soutenance par l'étudiant (defense.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_savedefense($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid)
            : $this->random_dossier([THESE_STATUS_ACTIVE, THESE_STATUS_DEFENSE_RETURNED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $student = $this->act_as((object) ['id' => $dossier->userid]);
        require_capability('mod/these:submit', $activity->context);
        $dossier = these_get_user_dossier($activity->these->id, $student->id);
        if (!$dossier || !these_defense_editable($dossier)) {
            throw new stress_refusal('soutenance non modifiable');
        }
        $scope = these_settings_scope($activity->these);
        $questions = these_get_questions($scope);
        $answers = [];
        foreach ($questions as $question) {
            $answers['q' . $question->id] = $question->qtype === 'list' ? $this->pick(['Chien', 'Chat', 'Cheval'])
                : 'Laboratoire ' . random_string(4);
        }
        $date = time() + $this->rand(20, 200) * DAYSECS + $this->rand(8, 17) * HOURSECS;
        $data = (object) array_merge(these_jury_form_data($this->jury_members($dossier)), [
            'proposeddate' => $date, 'titleen' => $dossier->titleen,
            'conclusions' => "Conclusions de l'étude.\n\nPerspectives.",
            'bbirthname' => 'NOM', 'bfirstnames' => 'Prénom', 'bnbvolumes' => 1, 'bnbpages' => 100, 'bnbrefs' => 50,
            'bsummaryfr' => 'Résumé', 'bsummaryen' => 'Summary', 'bdiffusion' => 1, 'bembargo' => 0, 'bembargoreason' => '',
            'ethicsnumber' => '',
        ]);
        foreach (these_get_grid_items($scope) as $items) {
            foreach (array_keys($items) as $item) {
                $data->{'grid_' . $item} = 1;
            }
        }
        $submit = $this->chance(80);
        $this->think();
        $this->put_file($activity->context, 'manuscript', $dossier->id, 'manuscrit.pdf');
        these_save_answers($dossier, $questions, $answers);
        these_save_defense(
            $activity->these,
            $activity->cm,
            $dossier,
            $data,
            $submit,
            $student->id,
            $submit ? $this->signature($student) : null
        );
        return true;
    }

    /**
     * Rapport du directeur (report.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_report($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_DEFENSE_SUBMITTED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $director = $this->actor_user($dossier, 'director');
        if (!$director) {
            throw new stress_refusal('pas de directeur');
        }
        $user = $this->act_as($director);
        $dossier = $this->reload($dossier->id);
        $rights = these_get_rights($activity->context, $dossier, $user->id);
        if (!$rights->director) {
            throw new moodle_exception('notyourstep', 'mod_these');
        }
        if (!these_report_editable($dossier)) {
            throw new stress_refusal('rapport non modifiable');
        }
        $this->think();
        $this->put_file($activity->context, 'directorreport', $dossier->id, 'rapport.pdf');
        these_save_report($dossier, $user->id, $this->chance(90));
        return true;
    }

    /**
     * Demande d'une autre date par la DEVE (daterequest.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_daterequest($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier(\mod_these\local\daterequest::statuses());
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $user = $this->act_as($this->users['deve']);
        these_require_service($activity->context, 'deve');
        $dossier = $this->reload($dossier->id);
        if (!\mod_these\local\daterequest::allowed($dossier)) {
            throw new stress_refusal('demande de date impossible');
        }
        $this->think();
        \mod_these\local\daterequest::request($activity->these, $activity->cm, $dossier, $user->id, 'Salle indisponible');
        return true;
    }

    /**
     * Nouvelle date proposée par l'étudiant (newdate.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_newdate($dossierid, array &$touched) {
        global $DB;
        $dossier = $dossierid ? $this->reload($dossierid)
            : ($this->chance(70) ? $this->random_dossier(\mod_these\local\daterequest::statuses()) : $this->random_dossier());
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $student = $this->act_as((object) ['id' => $dossier->userid]);
        require_capability('mod/these:submit', $activity->context);
        $dossier = these_get_user_dossier($activity->these->id, $student->id);
        if (!$dossier || !\mod_these\local\daterequest::pending($dossier)) {
            throw new stress_refusal('nouvelle date non attendue');
        }
        $this->think();
        \mod_these\local\daterequest::propose(
            $activity->these,
            $activity->cm,
            $dossier,
            $student->id,
            time() + $this->rand(30, 120) * DAYSECS
        );
        return (bool) $DB;
    }

    /**
     * Modification d'un dossier par la DEVE (edit.php) : titre, directeur, lieu, date, jury.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_editdossier($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier();
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $user = $this->act_as($this->users['deve']);
        these_require_service($activity->context, 'deve');
        $dossier = $this->reload($dossier->id);
        $jury = these_get_jury($dossier->id);
        $fields = [];
        foreach (['title', 'titleen', 'summary', 'keywords', 'devenumber', 'libraryref', 'defenselocation'] as $field) {
            $fields[$field] = trim((string) $dossier->$field);
        }
        if ($this->chance(30)) {
            $fields['defenselocation'] = 'Salle du conseil';
        }
        $fields['directorid'] = (int) $dossier->directorid;
        $fields['defensedate'] = (int) $dossier->defensedate;
        $this->think();
        $this->edit_dossier($activity, $dossier, $fields, $jury, $user->id);
        return true;
    }

    /**
     * Enregistrement de edit.php (repris tel quel de la page).
     *
     * @param stdClass $activity
     * @param stdClass $dossier
     * @param array $fields
     * @param array $newjury
     * @param int $userid
     * @return void
     */
    protected function edit_dossier(stdClass $activity, stdClass $dossier, array $fields, array $newjury, $userid) {
        if (method_exists(dossiers::class, 'edit_dossier')) {
            dossiers::edit_dossier($activity->these, $activity->cm, $dossier, $fields, $newjury, $userid);
            return;
        }
        if ($fields['defenselocation'] !== (string) $dossier->defenselocation && !empty($dossier->documentstime)) {
            $fields['documentstime'] = 0;
        }
        these_set_status($dossier, $dossier->status, $userid, 'edited', '', $fields);
    }

    /**
     * Signature d'une pièce attendue, par la voie légitime : compte (étudiant, directeur,
     * bibliothèque), « Mes signatures » (agents DEVE), sur place auprès de la DEVE, lien à jeton
     * (signataires sans compte) ou scan déposé (université).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_sign($dossierid, array &$touched) {
        global $DB;
        $dossier = $dossierid ? $this->reload($dossierid) : null;
        if (!$dossier) {
            // Un dossier qui attend des signatures.
            for ($i = 0; $i < 5 && !$dossier; $i++) {
                $candidate = $this->random_dossier();
                if ($candidate && prerequisites::missing($candidate)) {
                    $dossier = $candidate;
                }
            }
        }
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $missing = prerequisites::missing($dossier);
        if (!$missing) {
            throw new stress_refusal('rien à signer');
        }
        $item = $this->pick($missing);
        $activity = $this->activity($dossier);
        $context = $activity->context;
        [$doctype, $slot] = [$item['doctype'], $item['slot']];
        if (in_array($slot, ['deve', 'dgrep', 'dg'], true) && signatureagent::slots_for($activity->these)) {
            // Agent DEVE, depuis « Mes signatures ».
            $agent = $this->act_as($this->pick($this->users['agents']));
            $pending = array_filter(these_get_user_pending_signatures($agent), function ($s) use ($dossier, $doctype, $slot) {
                return (int) $s->dossierid === (int) $dossier->id && $s->doctype === $doctype && $s->slot === $slot;
            });
            if (!$pending) {
                if (in_array($slot, ['deve', 'dgrep'], true) && $this->chance(50)) {
                    // Relance des agents par la DEVE (signatures.php), puis nouvel essai au tirage suivant.
                    $this->act_as($this->users['deve']);
                    $available = array_values(array_filter(
                        array_keys(these_document_definitions()),
                        function ($d) use ($dossier) {
                            return these_document_available($dossier, $d);
                        }
                    ));
                    these_request_agent_signatures($activity->these, $activity->cm, $dossier, $available);
                }
                throw new stress_refusal('signature d\'agent non demandée');
            }
            $signature = reset($pending);
            $this->think();
            these_sign_document(
                context_module::instance($signature->cm->id),
                $signature->dossier,
                $doctype,
                $slot,
                $agent->id,
                fullname($agent),
                these_sample_signature_image(),
                (string) $signature->signeremail,
                ['delegated' => signatureagent::delegated_slot($slot)]
            );
            return true;
        }
        if (in_array($slot, prerequisites::UNIVERSITY_SLOTS, true)) {
            // Scan déposé par la DEVE (scan.php).
            $deve = $this->act_as($this->users['deve']);
            these_require_service($context, 'deve');
            if (!\mod_these\local\scans::allowed($dossier)) {
                throw new stress_refusal('scan non autorisé');
            }
            $date = time() - DAYSECS;
            $fs = get_file_storage();
            $filename = userdate($date, '%Y-%m-%d') . "-$doctype-$slot-" . random_string(4) . '.pdf';
            $this->think();
            $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_these', 'filearea' => 'signedscan',
                'itemid' => $dossier->id, 'filepath' => '/', 'filename' => $filename], '%PDF-1.4 scan');
            these_sign_document(
                $context,
                $dossier,
                $doctype,
                $slot,
                0,
                \mod_these\local\scans::slot_signer_name($dossier, $slot),
                '',
                '',
                ['method' => 'scan', 'verifiedby' => $deve->id, 'time' => $date]
            );
            return true;
        }
        if (in_array($slot, these_external_slots(), true) && $this->chance(60)) {
            // Lien à jeton : demandé au signataire, puis signé depuis sign.php.
            $this->act_as($this->users['deve']);
            $token = these_request_signature($activity->these, $activity->cm, $dossier, $slot, [$doctype]);
            if ($token === null) {
                throw new stress_refusal('pas de lien pour ' . $slot);
            }
            \core\session\manager::set_user(guest_user());
            $this->think();
            $pending = these_get_signatures_by_token($token);
            if (!$pending) {
                throw new coding_exception('Jeton neuf refusé');
            }
            $fresh = $DB->get_record('these_dossier', ['id' => $pending[0]->dossierid], '*', MUST_EXIST);
            these_refresh_signatures($context, $fresh);
            foreach (these_pending_documents_for_slot($fresh, $slot, array_column($pending, 'doctype')) as $d) {
                if (these_document_available($fresh, $d)) {
                    these_sign_document(
                        $context,
                        $fresh,
                        $d,
                        $slot,
                        0,
                        'Signataire ' . $slot,
                        these_sample_signature_image(),
                        (string) $pending[0]->signeremail,
                        ['method' => 'online', 'delegated' => $slot === 'dg']
                    );
                }
            }
            return true;
        }
        if (in_array($slot, ['student', 'director', 'library'], true) && $this->chance(50) && $slot !== 'library') {
            // Sur place, identité vérifiée par la DEVE (sign.php?slot=).
            $deve = $this->act_as($this->users['deve']);
            these_require_service($context, 'deve');
            these_refresh_signatures($context, $dossier);
            $signer = these_inperson_signer($dossier, $slot);
            $this->think();
            foreach (these_pending_documents_for_slot($dossier, $slot) as $d) {
                if (these_document_available($dossier, $d)) {
                    these_sign_document(
                        $context,
                        $dossier,
                        $d,
                        $slot,
                        $signer['userid'],
                        $signer['name'] ?: 'Signataire',
                        these_sample_signature_image(),
                        $signer['email'],
                        ['method' => 'inperson', 'verifiedby' => $deve->id]
                    );
                }
            }
            these_log($dossier->id, $deve->id, 'signedinperson', $dossier->status, $dossier->status, $slot);
            return true;
        }
        if (in_array($slot, these_inperson_slots(), true)) {
            // Signataire sans compte, sur place.
            $deve = $this->act_as($this->users['deve']);
            $signer = these_inperson_signer($dossier, $slot);
            $this->think();
            these_sign_document(
                $context,
                $dossier,
                $doctype,
                $slot,
                $signer['userid'],
                $signer['name'] ?: 'Signataire',
                these_sample_signature_image(),
                $signer['email'],
                ['method' => 'inperson', 'verifiedby' => $deve->id,
                'delegated' => $slot === 'dg']
            );
            return true;
        }
        // Bibliothèque ou DEVE sans agent : signature apposée par son compte (à la validation, en pratique).
        $user = $this->act_as($slot === 'library' ? $this->users['library'] : $this->users['deve']);
        $this->think();
        these_sign_document($context, $dossier, $doctype, $slot, $user->id, fullname($user), these_sample_signature_image());
        return true;
    }

    /**
     * Lien de signature à jeton, puis utilisation tardive : après un renvoi ou une révocation, le lien
     * ne doit plus rien signer.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_signtoken($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_DEFENSE_VALIDATED,
            THESE_STATUS_SUBJECT_APPROVED, THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $this->act_as($this->users['deve']);
        $slot = $this->pick(['president', 'extdirector', 'ccem']);
        $token = these_request_signature($activity->these, $activity->cm, $dossier, $slot);
        if ($token === null) {
            throw new stress_refusal('aucun document pour ' . $slot);
        }
        if (!these_get_signatures_by_token($token)) {
            throw new coding_exception('Jeton neuf refusé');
        }
        // Un second lien remplace le premier.
        $second = these_request_signature($activity->these, $activity->cm, $this->reload($dossier->id), $slot);
        if ($second !== null && these_get_signatures_by_token($token)) {
            $this->violation('ancien jeton encore valide après un nouvel envoi', 'signtoken', $dossier->id);
        }
        $token = $second ?? $token;
        if ($this->chance(50)) {
            // Jeton expiré.
            global $DB;
            $DB->set_field_select(
                'these_signature',
                'requesttime',
                time() - \mod_these\local\rights::TOKEN_LIFETIME - 10,
                'dossierid = ? AND slot = ? AND timesigned = 0',
                [$dossier->id, $slot]
            );
            if (these_get_signatures_by_token($token)) {
                $this->violation('jeton expiré encore valide', 'signtoken', $dossier->id);
            }
        }
        return true;
    }

    /**
     * Refus de signer d'un agent DEVE (refuse.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_refuse($dossierid, array &$touched) {
        $agent = $this->act_as($this->pick($this->users['agents']));
        $signatures = array_filter(these_get_user_pending_signatures($agent), function ($s) use ($dossierid) {
            return (!$dossierid || (int) $s->dossierid === (int) $dossierid)
                && \mod_these\local\refusal::refusable($s, $s->dossier);
        });
        if (!$signatures) {
            throw new stress_refusal('rien à refuser');
        }
        $first = $this->pick($signatures);
        $touched[] = $first->dossierid;
        $chosen = array_values(array_filter($signatures, function ($s) use ($first) {
            return (int) $s->dossierid === (int) $first->dossierid;
        }));
        $this->think();
        \mod_these\local\refusal::refuse(
            $first->these,
            $first->cm,
            $first->dossier,
            $chosen,
            $agent->id,
            'Refus ' . random_string(5)
        );
        return true;
    }

    /**
     * Génération et transmission des documents de soutenance (documents.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_documents($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $user = $this->act_as($this->users['deve']);
        these_require_service($activity->context, 'deve');
        $dossier = $this->reload($dossier->id);
        if (!in_array((int) $dossier->status, [THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED], true)) {
            throw new stress_refusal('documents non disponibles');
        }
        $this->think();
        if (empty($dossier->documentstime) || $this->chance(30)) {
            these_generate_documents($activity->these, $activity->context, $dossier, $user->id);
            $dossier = $this->reload($dossier->id);
        }
        $unsigned = (int) $dossier->status === THESE_STATUS_CONFORM ? prerequisites::missing($dossier) : [];
        if ($unsigned) {
            throw new stress_refusal('signatures manquantes');
        }
        these_transmit_documents($activity->these, $activity->cm, $activity->context, $dossier, $user->id);
        return true;
    }

    /**
     * Procès-verbal (minutes.php) : admis, admis avec corrections, ajourné.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_minutes($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_SCHEDULED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $user = $this->act_as($this->users['deve']);
        these_require_service($activity->context, 'deve');
        $dossier = $this->reload($dossier->id);
        if ((int) $dossier->status !== THESE_STATUS_SCHEDULED) {
            throw new stress_refusal('pas de soutenance programmée');
        }
        if (prerequisites::missing($dossier)) {
            throw new stress_refusal('signatures manquantes');
        }
        $roll = $this->rand(1, 100);
        $result = $roll <= 20 ? THESE_RESULT_ADJOURNED : THESE_RESULT_ADMITTED;
        $this->think();
        $this->put_file($activity->context, 'pv', $dossier->id, 'pv-' . random_string(4) . '.pdf');
        these_record_minutes($activity->these, $activity->cm, $dossier, $user->id, (object) [
            'result' => $result, 'mention' => $this->pick(['', 'honorable', 'treshonorable']),
            'prizeproposal' => $this->chance(10), 'correctionsrequested' => $roll > 60,
            'correctionsnote' => 'Corriger la discussion.',
        ], 'PV');
        return true;
    }

    /**
     * Dépôt des corrections par l'étudiant (corrections.php).
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_corrections($dossierid, array &$touched) {
        global $DB;
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_CORRECTIONS]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $student = $this->act_as((object) ['id' => $dossier->userid]);
        require_capability('mod/these:submit', $activity->context);
        $dossier = $DB->get_record('these_dossier', ['id' => $dossier->id, 'theseid' => $activity->these->id,
            'userid' => $student->id], '*', MUST_EXIST);
        if ((int) $dossier->status !== THESE_STATUS_CORRECTIONS) {
            throw new stress_refusal('corrections non attendues');
        }
        $this->think();
        $this->put_file($activity->context, 'corrections', $dossier->id, 'corrige.pdf');
        these_submit_corrections($activity->these, $activity->cm, $dossier, $student->id, 'Corrections faites.');
        return true;
    }

    /**
     * Accès du jury : nouvel envoi des liens, accès par jeton et par compte.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_notifyjury($dossierid, array &$touched) {
        global $DB;
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier([THESE_STATUS_DEFENSE_VALIDATED,
            THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED, THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED]);
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $this->act_as($this->users['deve']);
        if ($dossier->status < THESE_STATUS_DEFENSE_VALIDATED || $dossier->status > THESE_STATUS_SCHEDULED) {
            // Lien d'accès d'un dossier clos ou abandonné : refusé.
            foreach (these_get_jury($dossier->id) as $member) {
                if (!empty($member->accesstoken)) {
                    $this->violation('lien d\'accès du jury encore actif', 'notifyjury', $dossier->id);
                }
            }
            throw new stress_refusal('jury non convocable');
        }
        these_notify_jury($activity->these, $activity->cm, $dossier, true);
        $member = $this->pick(these_get_jury($dossier->id));
        if ($member) {
            $token = these_new_token();
            $DB->set_field('these_jury', 'accesstoken', these_token_hash($token), ['id' => $member->id]);
            if (!these_get_jury_access($token)) {
                $this->violation('lien d\'accès du jury refusé', 'notifyjury', $dossier->id);
            }
            if ($member->userid) {
                $user = $DB->get_record('user', ['id' => $member->userid]);
                $access = these_get_jury_member_access($member->id, $user);
                if ($access && ($user->deleted || $user->suspended)) {
                    $this->violation('accès du jury ouvert à un compte supprimé ou suspendu', 'notifyjury', $dossier->id);
                }
            }
        }
        return true;
    }

    /**
     * Jetons inconnus, trop courts ou révoqués : aucun accès.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_tokens($dossierid, array &$touched) {
        \core\session\manager::set_user(guest_user());
        foreach (['', 'abc', random_string(48), str_repeat('0', 64)] as $token) {
            if (these_get_signatures_by_token($token) || these_get_jury_access($token)) {
                $this->violation('jeton inconnu accepté : ' . $token, 'tokens', 0);
            }
        }
        return true;
    }

    /**
     * Suspension (ou réactivation) d'un compte : directeur, juré, étudiant.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_suspend($dossierid, array &$touched) {
        global $DB;
        $user = $this->pick(array_merge($this->users['directors'], $this->users['jury'], $this->users['students']));
        $fresh = $DB->get_record('user', ['id' => $user->id]);
        if ($fresh->deleted) {
            throw new stress_refusal('compte supprimé');
        }
        $DB->set_field('user', 'suspended', $fresh->suspended ? 0 : 1, ['id' => $user->id]);
        \core\session\manager::destroy_user_sessions($user->id);
        // Un compte suspendu ne siège plus au jury (accès par son compte).
        if (!$fresh->suspended) {
            foreach ($DB->get_records('these_jury', ['userid' => $user->id]) as $member) {
                if (\mod_these\local\rights::user_in_jury($member->dossierid, $user->id)) {
                    $this->violation('compte suspendu reconnu au jury', 'suspend', $member->dossierid);
                }
            }
        }
        return true;
    }

    /**
     * Suppression d'un compte (directeur, juré, agent ou étudiant), puis contrôle de ses droits.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_deleteuser($dossierid, array &$touched) {
        global $DB;
        $pool = array_merge(array_slice($this->users['directors'], 1), $this->users['jury'], $this->users['students']);
        $user = $DB->get_record('user', ['id' => $this->pick($pool)->id]);
        if ($user->deleted) {
            throw new stress_refusal('déjà supprimé');
        }
        foreach ($DB->get_records('these_dossier', ['directorid' => $user->id]) as $d) {
            $touched[] = $d->id;
        }
        foreach ($DB->get_records('these_dossier', ['userid' => $user->id]) as $d) {
            $touched[] = $d->id;
            $this->remember_minutes($d);
        }
        delete_user($user);
        foreach ($this->activities as $activity) {
            $rights = these_get_rights($activity->context, null, $user->id);
            if ($rights->deve || $rights->library || $rights->viewall) {
                $this->violation('compte supprimé avec des droits de service', 'deleteuser', 0);
            }
        }
        foreach ($DB->get_records('these_dossier', ['directorid' => $user->id]) as $d) {
            $rights = these_get_rights($this->activity($d)->context, $d, $user->id);
            if ($rights->director) {
                $this->violation('directeur supprimé encore reconnu', 'deleteuser', $d->id);
            }
        }
        return true;
    }

    /**
     * Demande RGPD : export, ou effacement d'un étudiant (avec ou sans procès-verbal) ou d'un membre
     * du personnel.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_privacy($dossierid, array &$touched) {
        global $DB;
        $dossier = $dossierid ? $this->reload($dossierid)
            : ($this->chance(50) ? $this->random_dossier([THESE_STATUS_CLOSED, THESE_STATUS_CORRECTIONS,
                THESE_STATUS_ACTIVE, THESE_STATUS_SCHEDULED]) : $this->random_dossier());
        $staff = $this->chance(30);
        if ($staff) {
            $user = $this->pick(array_merge($this->users['directors'], $this->users['jury'], $this->users['agents']));
        } else if ($dossier) {
            $user = $DB->get_record('user', ['id' => $dossier->userid]);
        } else {
            return false;
        }
        $user = $DB->get_record('user', ['id' => $user->id]);
        $contextlist = \mod_these\privacy\provider::get_contexts_for_userid($user->id);
        foreach ($DB->get_records('these_dossier', ['userid' => $user->id]) as $d) {
            $touched[] = $d->id;
        }
        foreach ($DB->get_records('these_dossier', ['directorid' => $user->id]) as $d) {
            $touched[] = $d->id;
        }
        if ($this->chance(40)) {
            // Export.
            $approved = new \core_privacy\local\request\approved_contextlist($user, 'mod_these', $contextlist->get_contextids());
            \mod_these\privacy\provider::export_user_data($approved);
            return true;
        }
        foreach ($DB->get_records('these_dossier', ['userid' => $user->id]) as $d) {
            $this->remember_minutes($d);
        }
        $approved = new \core_privacy\local\request\approved_contextlist($user, 'mod_these', $contextlist->get_contextids());
        \mod_these\privacy\provider::delete_data_for_user($approved);
        foreach ($this->keptminutes as $id => $kept) {
            if (!$staff && $this->reload($id) && (int) $this->reload($id)->userid === (int) $user->id) {
                $this->keptminutes[$id]['erased'] = true;
                $touched[] = $id;
            }
        }
        return true;
    }

    /**
     * Mémorise le procès-verbal d'un dossier, qui doit survivre à l'effacement de l'étudiant.
     *
     * @param stdClass $dossier
     * @return void
     */
    protected function remember_minutes(stdClass $dossier) {
        $context = $this->activity($dossier)->context;
        $fs = get_file_storage();
        $hashes = [];
        foreach (['pv', 'pvadjourned'] as $area) {
            foreach ($fs->get_area_files($context->id, 'mod_these', $area, $dossier->id, 'filename', false) as $file) {
                $hashes[] = $area . '/' . $file->get_filename() . '=' . $file->get_contenthash();
            }
        }
        if ($hashes || (int) $dossier->defenseresult !== 0) {
            sort($hashes);
            $this->keptminutes[$dossier->id] = ['files' => $hashes, 'result' => (int) $dossier->defenseresult,
                'mention' => (string) $dossier->mention, 'erased' => false];
        }
    }

    /**
     * Acteur illégitime : un compte qui n'est pas l'acteur attendu tente l'action d'une étape, l'abandon,
     * la modification d'un dossier ou le changement de sujet d'autrui. Le refus est attendu.
     *
     * @param int|null $dossierid
     * @param int[] $touched
     * @return bool
     */
    protected function op_illegitimate($dossierid, array &$touched) {
        $dossier = $dossierid ? $this->reload($dossierid) : $this->random_dossier();
        if (!$dossier) {
            return false;
        }
        $touched[] = $dossier->id;
        $activity = $this->activity($dossier);
        $step = these_review_steps()[(int) $dossier->status] ?? null;
        $kind = $this->pick(['review', 'service', 'student', 'view']);
        $wrong = $this->wrong_user($dossier, $step['actor'] ?? 'deve');
        try {
            $user = $this->act_as($wrong);
        } catch (stress_refusal $e) {
            throw $e;
        }
        $rights = these_get_rights($activity->context, $dossier, $user->id);
        $accepted = false;
        switch ($kind) {
            case 'review':
                $accepted = $step && !empty($rights->{$step['actor']});
                break;
            case 'service':
                try {
                    these_require_service($activity->context, 'deve');
                    $accepted = true;
                } catch (required_capability_exception $e) {
                    $accepted = false;
                }
                break;
            case 'student':
                // subjectchange.php, subject.php, defense.php : le dossier est celui du compte connecté.
                $own = these_get_user_dossier($activity->these->id, $user->id);
                $accepted = $own && (int) $own->id === (int) $dossier->id;
                break;
            case 'view':
                $accepted = these_can_view_dossier($rights) && !$rights->viewall;
                break;
        }
        if ($accepted) {
            $this->violation("acteur illégitime accepté ($kind) : " . $user->username, 'illegitimate', $dossier->id);
        }
        throw new stress_refusal('refusé');
    }

    // Peuplement, concurrence et résultats.

    /**
     * Mène les dossiers de tous les étudiants vers une étape tirée au hasard, par les opérations du
     * circuit (signatures, validations, renvois, changements de sujet, abandons et réouvertures compris).
     *
     * @param int $maxsteps Opérations au plus par dossier.
     * @param int $part Part des étudiants traitée par ce processus (0 à $parts - 1).
     * @param int $parts Nombre de processus.
     * @return int Dossiers menés.
     */
    public function populate($maxsteps, $part = 0, $parts = 1) {
        global $DB;
        $done = 0;
        foreach ($this->users['students'] as $index => $student) {
            if ($index % $parts !== $part) {
                continue;
            }
            $activity = $this->student_activity($student);
            $dossier = $DB->get_record('these_dossier', ['theseid' => $activity->these->id, 'userid' => $student->id]);
            if (!$dossier) {
                $this->forcestudent = $student;
                $this->run_operation('create');
                $this->forcestudent = null;
                $dossier = $DB->get_record('these_dossier', ['theseid' => $activity->these->id, 'userid' => $student->id]);
                if (!$dossier) {
                    continue;
                }
            }
            // Étape visée : la plupart des dossiers restent en phase 1 ou actifs, quelques-uns vont au bout.
            $target = $this->pick([THESE_STATUS_SUBJECT_SUBMITTED, THESE_STATUS_SUBJECT_APPROVED, THESE_STATUS_ACTIVE,
                THESE_STATUS_ACTIVE, THESE_STATUS_ACTIVE, THESE_STATUS_DEFENSE_SUBMITTED, THESE_STATUS_DEFENSE_VALIDATED,
                THESE_STATUS_CONFORM, THESE_STATUS_SCHEDULED, THESE_STATUS_CORRECTIONS, THESE_STATUS_CLOSED, THESE_STATUS_CLOSED]);
            $this->drive($dossier->id, $target, $maxsteps);
            $done++;
            if ($done % 100 === 0) {
                mtrace("  $done dossiers...");
            }
        }
        return $done;
    }

    /**
     * Mène un dossier vers une étape, en tirant à chaque pas l'opération qu'attend son statut.
     *
     * @param int $id
     * @param int $target
     * @param int $maxsteps
     * @return void
     */
    public function drive($id, $target, $maxsteps) {
        for ($i = 0; $i < $maxsteps; $i++) {
            $dossier = $this->reload($id);
            if (!$dossier) {
                return;
            }
            $status = (int) $dossier->status;
            if (these_status_rank($status) >= these_status_rank($target) && $status !== THESE_STATUS_CANCELLED) {
                return;
            }
            $op = null;
            if ($status === THESE_STATUS_CANCELLED) {
                $op = $this->chance(60) ? 'reopen' : 'changesubject';
            } else if ($this->chance(3)) {
                $op = 'cancel';
            } else if (in_array($status, [THESE_STATUS_DRAFT, THESE_STATUS_SUBJECT_RETURNED], true)) {
                $op = 'savesubject';
            } else if ($status === THESE_STATUS_ACTIVE) {
                $op = $this->chance(10) ? 'changesubject' : 'savedefense';
            } else if ($status === THESE_STATUS_DEFENSE_RETURNED) {
                $op = 'savedefense';
            } else if ($status === THESE_STATUS_CONFORM) {
                $op = prerequisites::missing($dossier) ? 'sign' : 'documents';
            } else if ($status === THESE_STATUS_SCHEDULED) {
                $op = prerequisites::missing($dossier) ? 'sign' : 'minutes';
            } else if ($status === THESE_STATUS_CORRECTIONS) {
                $op = 'corrections';
            } else if (isset(these_review_steps()[$status])) {
                if (prerequisites::missing($dossier)) {
                    $op = $this->chance(85) ? 'sign' : 'refuse';
                } else if ($this->chance(6)) {
                    $op = 'returndossier';
                } else if ($status === THESE_STATUS_DEFENSE_APPROVED && $this->chance(10)) {
                    $op = \mod_these\local\daterequest::pending($dossier) ? 'newdate' : 'daterequest';
                } else {
                    $op = 'review';
                }
                if ($status === THESE_STATUS_DEFENSE_APPROVED && \mod_these\local\daterequest::pending($dossier)) {
                    $op = 'newdate';
                }
            }
            if ($op === null) {
                return;
            }
            $this->run_operation($op, $id);
        }
    }

    /**
     * Limite le tirage des dossiers aux premiers dossiers existants (concurrence sur peu de dossiers).
     *
     * @param int $count
     * @return void
     */
    public function focus_on($count) {
        $this->focus = array_slice($this->dossier_ids(), 0, $count);
    }

    /**
     * Dossiers des activités du test.
     *
     * @return int[]
     */
    public function dossier_ids() {
        global $DB;
        $theseids = array_map(function ($a) {
            return $a->these->id;
        }, $this->activities);
        [$insql, $params] = $DB->get_in_or_equal($theseids);
        return array_map('intval', $DB->get_fieldset_select('these_dossier', 'id', "theseid $insql ORDER BY id", $params));
    }

    /**
     * Remet à zéro les résultats (nouvelle phase, nouveau processus).
     *
     * @return void
     */
    public function reset_results() {
        $this->stats = [];
        $this->errors = [];
        $this->violations = [];
        $this->opnumber = 0;
    }

    /**
     * Résultats de ce processus.
     *
     * @return array
     */
    public function results() {
        return ['stats' => $this->stats, 'errors' => $this->errors, 'violations' => $this->violations,
            'kept' => $this->keptminutes];
    }

    /**
     * Reprend les procès-verbaux mémorisés par les processus (vérification finale).
     *
     * @param array $results
     * @return void
     */
    public function import_kept(array $results) {
        foreach ($results as $result) {
            foreach ($result['kept'] ?? [] as $id => $kept) {
                if (!isset($this->keptminutes[$id]) || !empty($kept['erased'])) {
                    $this->keptminutes[$id] = $kept;
                }
            }
        }
    }

    // Invariants.

    /**
     * Invariants d'un dossier.
     *
     * @param int $id
     * @return string[] Violations.
     */
    public function check_dossier($id) {
        global $DB;
        $violations = [];
        $dossier = $this->reload($id);
        if (!$dossier) {
            foreach (['these_signature', 'these_jury', 'these_answer', 'these_subjecthistory', 'these_log'] as $table) {
                if ($DB->record_exists($table, ['dossierid' => $id])) {
                    $violations[] = "enregistrement orphelin dans $table";
                }
            }
            if ($DB->record_exists('event', ['modulename' => 'these', 'uuid' => 'these-dossier-' . $id])) {
                $violations[] = 'événement de calendrier d\'un dossier supprimé';
            }
            return $violations;
        }
        $status = (int) $dossier->status;
        if (!in_array($status, these_all_statuses(), true)) {
            $violations[] = "statut inconnu $status";
        }
        $logs = array_values($DB->get_records('these_log', ['dossierid' => $id], 'id ASC'));
        // Dossier réduit à son procès-verbal par un effacement RGPD (ici ou dans un autre processus).
        $kept = !empty($this->keptminutes[$id]['erased']) || (!$logs && (int) $dossier->defenseresult !== 0)
            || (!$logs && !get_file_storage()->is_area_empty($this->activity($dossier)->context->id, 'mod_these', 'pv', $id));
        if ($kept && empty($this->keptminutes[$id]['erased'])) {
            // Effacé par un autre processus : l'état d'avant n'est pas connu ici.
            unset($this->keptminutes[$id]);
        }
        if ($kept && isset($this->keptminutes[$id])) {
            $violations = array_merge($violations, $this->check_kept_minutes($dossier));
        }
        // Historique : chaque entrée part du statut atteint par la précédente, chaque changement est permis.
        if (!$logs && !$kept) {
            $violations[] = 'historique vide';
        }
        $current = null;
        foreach ($logs as $log) {
            if ($current !== null && (int) $log->fromstatus !== $current) {
                $violations[] = "historique incohérent : « {$log->logaction} » part de {$log->fromstatus} alors que le dossier"
                    . " était en $current (entrée {$log->id})";
                break;
            }
            if (!these_transition_allowed($log->fromstatus, $log->tostatus)) {
                $violations[] = "transition interdite à l'historique : {$log->fromstatus} vers {$log->tostatus}"
                    . " ({$log->logaction})";
            }
            $current = (int) $log->tostatus;
        }
        if ($current !== null && $current !== $status && !$kept) {
            $violations[] = "statut $status différent du dernier statut de l'historique ($current)";
        }
        if ($status === THESE_STATUS_CANCELLED) {
            if ((int) $dossier->cancelledfromstatus === THESE_STATUS_CANCELLED) {
                $violations[] = 'abandonné depuis le statut abandonné';
            }
        } else if ((int) $dossier->cancelledfromstatus !== 0) {
            $violations[] = 'statut d\'abandon mémorisé sur un dossier non abandonné';
        }

        // Signatures et scellés.
        $activity = $this->activity($dossier);
        $context = $activity->context;
        $signatures = these_get_signatures($id);
        $definitions = these_document_definitions();
        foreach ($signatures as $key => $signature) {
            $slots = $definitions[$signature->doctype]['slots'] ?? [];
            if (!in_array($signature->slot, $slots, true)) {
                $violations[] = "signature d'un emplacement inconnu $key";
            }
            if (!empty($signature->timesigned) && $signature->token !== null) {
                $violations[] = "signature apposée avec un jeton encore actif $key";
            }
            if (!empty($signature->timesigned) && empty($signature->contenthash)) {
                $violations[] = "signature sans empreinte $key";
            }
        }
        $signed = array_filter($signatures, function ($s) {
            return !empty($s->timesigned);
        });
        $phase2 = array_filter($signed, function ($s) {
            return $s->doctype !== 'subject';
        });
        if (in_array($status, [THESE_STATUS_DRAFT, THESE_STATUS_SUBJECT_RETURNED], true)) {
            $subject = array_filter($signed, function ($s) {
                return $s->doctype === 'subject';
            });
            if ($subject && $status === THESE_STATUS_SUBJECT_RETURNED) {
                $violations[] = 'sujet renvoyé à l\'étudiant encore signé : ' . implode(', ', array_keys($subject));
            }
        }
        if (in_array($status, [THESE_STATUS_ACTIVE, THESE_STATUS_DEFENSE_RETURNED], true) && $phase2) {
            $violations[] = 'documents de soutenance signés alors que le dossier est au statut ' . $status . ' : '
                . implode(', ', array_keys($phase2));
        }
        $fs = get_file_storage();
        $sealedlogs = [];
        foreach ($logs as $log) {
            if ($log->logaction === 'documentsealed') {
                $sealedlogs[] = $log->remark;
            }
        }
        foreach (array_keys($definitions) as $doctype) {
            $sealed = $fs->get_file($context->id, 'mod_these', 'sealed', $id, '/', $doctype . '.pdf');
            if (!$sealed) {
                continue;
            }
            if (!\mod_these\local\seals::document_fully_signed($dossier, $doctype)) {
                $violations[] = "document scellé sans toutes ses signatures : $doctype";
            }
            $hash = hash('sha256', $sealed->get_content());
            $label = these_document_label($doctype) . ' — SHA-256 ';
            $last = null;
            foreach ($sealedlogs as $remark) {
                if (strpos($remark, $label) === 0) {
                    $last = substr($remark, strlen($label));
                }
            }
            if (!$kept && $last !== $hash) {
                $violations[] = "empreinte du document scellé $doctype absente de l'historique";
            }
        }
        // Accès du jury révoqués pour un dossier clos ou abandonné.
        if (in_array($status, [THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED], true)) {
            if ($DB->record_exists_select('these_jury', 'dossierid = ? AND accesstoken IS NOT NULL', [$id])) {
                $violations[] = 'lien d\'accès du jury actif sur un dossier clos ou abandonné';
            }
        }
        foreach ($signatures as $key => $signature) {
            if (
                $signature->token !== null && $status === THESE_STATUS_CANCELLED
                    && self::signatures_by_token_hash($signature->token)
            ) {
                $violations[] = "lien de signature utilisable sur un dossier $status : $key";
            }
        }
        // Calendrier : un événement exactement pour une soutenance arrêtée.
        $events = $DB->count_records('event', ['modulename' => 'these', 'uuid' => 'these-dossier-' . $id]);
        $expected = \mod_these\local\calendar::has_event($dossier) ? 1 : 0;
        if ($events !== $expected) {
            $violations[] = "événements de calendrier : $events au lieu de $expected";
        }
        // Numéro DEVE unique dans l'activité.
        if (trim((string) $dossier->devenumber) !== '' && these_devenumber_taken($dossier->theseid, $dossier->devenumber, $id)) {
            $violations[] = 'numéro DEVE en double : ' . $dossier->devenumber;
        }
        if ($this->render) {
            $violations = array_merge($violations, $this->check_confidentiality($dossier, $activity));
        }
        return $violations;
    }

    /**
     * Procès-verbal conservé intact après l'effacement RGPD de l'étudiant.
     *
     * @param stdClass $dossier
     * @return string[]
     */
    protected function check_kept_minutes(stdClass $dossier) {
        global $DB;
        $before = $this->keptminutes[$dossier->id];
        $context = $this->activity($dossier)->context;
        $fs = get_file_storage();
        $hashes = [];
        foreach (['pv', 'pvadjourned'] as $area) {
            foreach ($fs->get_area_files($context->id, 'mod_these', $area, $dossier->id, 'filename', false) as $file) {
                $hashes[] = $area . '/' . $file->get_filename() . '=' . $file->get_contenthash();
            }
        }
        sort($hashes);
        $violations = [];
        if ($hashes !== $before['files']) {
            $violations[] = 'procès-verbal modifié par l\'effacement RGPD';
        }
        if ((int) $dossier->defenseresult !== $before['result'] || (string) $dossier->mention !== $before['mention']) {
            $violations[] = 'décision du jury modifiée par l\'effacement RGPD';
        }
        return $violations;
    }

    /**
     * Thèse confidentielle (ou qui l'a été) : ni son titre, ni un ancien titre confidentiel, ne doivent
     * apparaître sur le dossier tel que le voient la scolarité (mod/these:viewall) et un directeur
     * étranger au dossier.
     *
     * @param stdClass $dossier
     * @param stdClass $activity
     * @return string[]
     */
    protected function check_confidentiality(stdClass $dossier, stdClass $activity) {
        global $DB, $PAGE;
        $secrets = [];
        if (!empty($dossier->confidential) && preg_match('/SUJET[A-Z0-9]{10}/', $dossier->title, $m)) {
            $secrets[] = $m[0];
        }
        foreach ($DB->get_records('these_subjecthistory', ['dossierid' => $dossier->id, 'confidential' => 1]) as $entry) {
            if (preg_match('/SUJET[A-Z0-9]{10}/', $entry->title, $m) && strpos((string) $dossier->title, $m[0]) === false) {
                $secrets[] = $m[0];
            }
        }
        if (!$secrets) {
            return [];
        }
        $violations = [];
        $outsiders = [$this->users['viewall']];
        foreach ($outsiders as $outsider) {
            $user = $DB->get_record('user', ['id' => $outsider->id, 'deleted' => 0]);
            if (!$user) {
                continue;
            }
            \core\session\manager::set_user($user);
            $rights = these_get_rights($activity->context, $dossier, $user->id);
            if (!these_can_view_dossier($rights)) {
                continue;
            }
            $PAGE = new moodle_page();
            $PAGE->set_context($activity->context);
            $PAGE->set_url('/mod/these/dossier.php', ['id' => $activity->cm->id, 'dossierid' => $dossier->id]);
            $PAGE->set_cm($activity->cm);
            $html = these_render_dossier($activity->these, $activity->cm, $activity->context, $dossier, $rights);
            foreach ($secrets as $secret) {
                if (strpos($html, $secret) !== false) {
                    $violations[] = "sujet confidentiel ($secret) visible par {$user->username} sur la page du dossier";
                }
            }
            // Fichiers lisibles : l'archive d'un ancien sujet confidentiel reproduit son titre.
            if (these_can_view_filearea('subjectarchive', $rights)) {
                foreach ($DB->get_records('these_subjecthistory', ['dossierid' => $dossier->id, 'confidential' => 1]) as $e) {
                    if (\mod_these\local\files::get_files($activity->context, 'subjectarchive', $dossier->id)) {
                        $violations[] = "archive d'un ancien sujet confidentiel accessible à {$user->username}";
                        break;
                    }
                }
            }
        }
        \core\session\manager::set_user(get_admin());
        return array_values(array_unique($violations));
    }

    /**
     * Invariants globaux : enregistrements et fichiers orphelins, événements sans dossier.
     *
     * @return string[]
     */
    public function check_global() {
        global $DB;
        $violations = [];
        foreach (['these_signature', 'these_jury', 'these_answer', 'these_subjecthistory', 'these_log'] as $table) {
            $count = $DB->count_records_sql("SELECT COUNT(1) FROM {{$table}} t
                                              LEFT JOIN {these_dossier} d ON d.id = t.dossierid
                                             WHERE d.id IS NULL");
            if ($count) {
                $violations[] = "$count enregistrement(s) orphelin(s) dans $table";
            }
        }
        $count = $DB->count_records_sql("SELECT COUNT(1) FROM {files} f
                                          LEFT JOIN {these_dossier} d ON d.id = f.itemid
                                         WHERE f.component = 'mod_these' AND f.filearea NOT IN ('signature', 'doctemplate',
                                               'contracttemplate', 'doctemplateimage', 'reporttemplate')
                                               AND f.filename <> '.' AND f.itemid > 0 AND d.id IS NULL");
        if ($count) {
            $violations[] = "$count fichier(s) de dossier orphelin(s)";
        }
        $count = $DB->count_records_sql("SELECT COUNT(1) FROM {files} f
                                          LEFT JOIN {these_signature} s ON s.id = f.itemid
                                         WHERE f.component = 'mod_these' AND f.filearea = 'signature'
                                               AND f.filename <> '.' AND s.id IS NULL");
        if ($count) {
            $violations[] = "$count image(s) de signature orpheline(s)";
        }
        $events = $DB->get_records_select('event', "modulename = 'these' AND eventtype = 'defense'", [], '', 'id, uuid');
        $orphans = 0;
        foreach ($events as $event) {
            if (
                preg_match('/^these-dossier-(\d+)$/', (string) $event->uuid, $m) && !$DB->record_exists(
                    'these_dossier',
                    ['id' => $m[1]]
                )
            ) {
                $orphans++;
            }
        }
        if ($orphans) {
            $violations[] = "$orphans événement(s) de soutenance sans dossier";
        }
        return $violations;
    }

    /**
     * Signatures en attente pour une empreinte de jeton (sans le contrôle du statut du dossier), pour
     * vérifier qu'un lien d'un dossier abandonné ne mène plus à rien (voir these_get_signatures_by_token()).
     *
     * @param string $hash
     * @return stdClass[]
     */
    protected static function signatures_by_token_hash($hash) {
        global $DB;
        return $DB->get_records_select(
            'these_signature',
            'token = :token AND timesigned = 0 AND requesttime > :since',
            ['token' => $hash, 'since' => time() - \mod_these\local\rights::TOKEN_LIFETIME]
        );
    }
}
