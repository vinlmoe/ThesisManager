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
 * Peuplement léger d'un serveur de test : comptes, cours, activité « Gestion des thèses », agents et
 * annuaire du jury, à partir d'un fichier de comptes (voir comptes-exemple.csv et README.md).
 *
 * Aucun dossier n'est créé, aucun réglage du site n'est modifié et aucun courriel n'est envoyé. Le
 * script peut être relancé : un compte, un cours, une activité ou un enseignant de l'annuaire déjà
 * présents sont repris (le mot de passe d'un compte existant n'est pas changé).
 *
 * Usage : php tools/testserver/seed.php /chemin/vers/moodle [comptes.csv] [--course=THESES]
 *         [--password=Test1234!] [--institution="VetAgro Sup"]
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$args = array_values(array_filter(array_slice($argv, 1), function ($arg) {
    return strpos($arg, '--') !== 0;
}));
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $arg, $match)) {
        $options[$match[1]] = $match[2];
    }
}
if (empty($args[0]) || !is_file($args[0] . '/config.php')) {
    fwrite(STDERR, "Usage : php seed.php /chemin/vers/moodle [comptes.csv] [--course=THESES] [--password=...] "
        . "[--institution=...]\n");
    exit(1);
}
require($args[0] . '/config.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

$csvfile = $args[1] ?? __DIR__ . '/comptes-exemple.csv';
$shortname = $options['course'] ?? 'THESES';
$password = $options['password'] ?? 'Test1234!';
$institution = $options['institution'] ?? 'VetAgro Sup';

// Rôles du fichier : rôle Moodle dans le cours, et liste d'agents éventuelle des paramètres du circuit.
$roles = [
    'deve' => ['editingteacher', null],
    'agent' => ['editingteacher', 'deveagent'],
    'library' => ['teacher', 'libraryagent'],
    'director' => ['teacher', null],
    'student' => ['student', null],
    'juror' => [null, null],
];

\core\session\manager::set_user(get_admin());
// Aucun courriel (bienvenue dans le cours...) pendant le peuplement : réglage en mémoire seulement.
$CFG->noemailever = true;
$gen = new testing_data_generator();

// Fichier de comptes : séparateur point-virgule, première ligne d'en-tête.
$lines = array_values(array_filter(preg_split('/\R/', preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($csvfile))),
    function ($line) {
        return trim($line) !== '' && strpos(ltrim($line), '#') !== 0;
    }));
$header = array_map('trim', str_getcsv(array_shift($lines), ';'));
$rows = [];
foreach ($lines as $index => $line) {
    $row = array_map('trim', array_combine($header, array_pad(str_getcsv($line, ';'), count($header), '')));
    if (!isset($roles[$row['role'] ?? ''])) {
        fwrite(STDERR, 'Ligne ' . ($index + 2) . ' ignorée : rôle inconnu « ' . ($row['role'] ?? '') . " ».\n");
        continue;
    }
    $rows[] = $row;
}

// Cours et activité.
$course = $DB->get_record('course', ['shortname' => $shortname]);
if (!$course) {
    $course = $gen->create_course(['fullname' => 'Thèses (test)', 'shortname' => $shortname, 'numsections' => 1]);
    echo "Cours {$shortname} créé.\n";
}
$these = $DB->get_record('these', ['course' => $course->id], '*', IGNORE_MULTIPLE);
if (!$these) {
    $these = $gen->create_module('these', [
        'course' => $course->id,
        'name' => 'Gestion des thèses',
        'establishmentname' => $institution,
        'minjury' => 2,
        'reminderdays' => 14,
    ]);
    $these = $DB->get_record('these', ['id' => $these->id]);
    echo "Activité « Gestion des thèses » créée.\n";
}

// Comptes, inscriptions et annuaire.
$counts = ['created' => 0, 'kept' => 0, 'jurors' => 0];
$agents = ['deveagent' => [], 'libraryagent' => []];
foreach ($rows as $row) {
    [$rolename, $agentsetting] = $roles[$row['role']];
    $user = null;
    if ($rolename !== null) {
        $username = core_text::strtolower($row['username']);
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
        if ($user) {
            $counts['kept']++;
        } else {
            $user = $gen->create_user([
                'username' => $username,
                'password' => $row['password'] !== '' ? $row['password'] : $password,
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'] !== '' ? $row['email'] : $username . '@example.org',
            ]);
            $counts['created']++;
        }
        if (!is_enrolled(context_course::instance($course->id), $user)) {
            $gen->enrol_user($user->id, $course->id, $rolename);
        }
        if ($agentsetting) {
            $agents[$agentsetting][] = $user->username;
        }
    }
    // Enseignants de l'annuaire : ceux qui ont un statut (directeurs, membres de la DEVE, extérieurs).
    if (($row['status'] ?? '') !== '') {
        $email = $user->email ?? $row['email'];
        $existing = \mod_these\local\jurors::find_existing($email, $row['lastname'], $row['firstname']);
        \mod_these\local\jurors::save((object) [
            'id' => $existing->id ?? 0,
            'title' => $row['title'] !== '' ? $row['title'] : 'Dr',
            'lastname' => $row['lastname'],
            'firstname' => $row['firstname'],
            'email' => $email,
            'status' => $row['status'],
            'institution' => $row['institution'] !== '' ? $row['institution'] : $institution,
            'discipline' => $row['discipline'] ?? '',
            'active' => 1,
        ]);
        $counts['jurors']++;
    }
}

// Paramètres du circuit : agents ajoutés à ceux déjà désignés, établissement s'il manque.
$scope = these_settings_scope($these);
$settings = \mod_these\local\scopesettings::get_all($scope);
$values = [];
foreach ($agents as $name => $usernames) {
    $current = preg_split('/[\s,;]+/', (string) $settings[$name], -1, PREG_SPLIT_NO_EMPTY);
    $values[$name] = implode("\n", array_unique(array_merge($current, $usernames)));
}
if (trim((string) $settings['institution']) === '') {
    $values['institution'] = $institution;
}
\mod_these\local\scopesettings::set($scope, $values);

$cm = get_coursemodule_from_instance('these', $these->id);
echo "Comptes : {$counts['created']} créé(s), {$counts['kept']} déjà présent(s) ; annuaire : {$counts['jurors']} enseignant(s).\n";
echo 'Activité : ' . (new moodle_url('/mod/these/view.php', ['id' => $cm->id]))->out(false) . "\n";
