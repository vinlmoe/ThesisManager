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
 * Mesure des pages lourdes sur le jeu de données de stress.php : chaque page est demandée par HTTP au
 * serveur du Moodle de test, connecté en tant que l'acteur qui la consulte ; le temps et le nombre de
 * lectures en base sont relevés dans le pied de page des performances ($CFG->perfdebug = 15).
 *
 * Usage : php tools/stresstest/perf.php /chemin/vers/moodle [--runs=3] [--json=perf.json]
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Moodle de test visé : premier argument (voir README.md).
require(rtrim($argv[1] ?? '.', '/') . '/config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(['runs' => 3, 'json' => '', 'password' => 'Stress1234!', 'help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Mesure des pages lourdes (voir tools/stresstest/README.md).\n";
    exit(0);
}
if (empty($CFG->perfdebug) || $CFG->perfdebug < 15) {
    cli_error('$CFG->perfdebug = 15 est requis dans config.php : le nombre de lectures est lu dans le pied de page.');
}

$course = $DB->get_record('course', ['shortname' => 'STRESS'], '*', MUST_EXIST);
$these = array_values($DB->get_records('these', ['course' => $course->id], 'id ASC'));
$cms = array_map(function ($t) {
    return get_coursemodule_from_instance('these', $t->id)->id;
}, $these);
$suivi = $DB->get_record('course', ['shortname' => 'STRESSSUIVI'], '*', MUST_EXIST);
$synthesis = $DB->get_record('thesesynthesis', ['course' => $suivi->id], '*', MUST_EXIST);
$scm = get_coursemodule_from_instance('thesesynthesis', $synthesis->id)->id;
[$insql, $params] = $DB->get_in_or_equal(array_column($these, 'id'));
$count = $DB->count_records_select('these_dossier', "theseid $insql", $params);

// Dossier au plus long historique, et son étudiant ; directeur qui dirige le plus de dossiers.
$long = $DB->get_record_sql("SELECT d.id, d.theseid, d.userid, COUNT(l.id) AS logs
                               FROM {these_dossier} d
                               JOIN {these_log} l ON l.dossierid = d.id
                               JOIN {user} u ON u.id = d.userid AND u.deleted = 0 AND u.suspended = 0
                              WHERE d.theseid $insql
                           GROUP BY d.id, d.theseid, d.userid
                           ORDER BY COUNT(l.id) DESC", $params, IGNORE_MULTIPLE);
$director = $DB->get_record_sql("SELECT u.username, COUNT(d.id) AS n
                                   FROM {these_dossier} d
                                   JOIN {user} u ON u.id = d.directorid AND u.deleted = 0 AND u.suspended = 0
                                  WHERE d.theseid $insql
                               GROUP BY u.username
                               ORDER BY COUNT(d.id) DESC", $params, IGNORE_MULTIPLE);
$student = $DB->get_field('user', 'username', ['id' => $long->userid]);
$longcm = get_coursemodule_from_instance('these', $long->theseid)->id;
$cm = $cms[0];

$pages = [
    ['stressdeve', "Tableau de bord DEVE (promotion 1)", "/mod/these/dashboard.php?id=$cm"],
    ['stressdeve', 'Tableau de bord DEVE, filtre « actif »', "/mod/these/dashboard.php?id=$cm&status=40"],
    ['stressdeve', 'Tableau de bord DEVE, recherche', "/mod/these/dashboard.php?id=$cm&search=Etude"],
    ['stressdeve', 'Planning', "/mod/these/planning.php?id=$cm"],
    ['stressdeve', 'Export du registre (CSV)', "/mod/these/export.php?id=$cm&dataformat=csv"],
    ['stressdeve', 'Mes signatures (DEVE)', '/mod/these/mysignatures.php'],
    ['stressdeve', "Dossier au plus long historique ({$long->logs} entrées)",
        "/mod/these/dossier.php?id=$longcm&dossierid={$long->id}"],
    ['stressdeve', 'Suivi des thèses', "/mod/thesesynthesis/view.php?id=$scm"],
    ['stressdeve', 'Suivi des thèses, recherche', "/mod/thesesynthesis/search.php?id=$scm&q=Etude&search=1"],
    ['stressdeve', 'Suivi : planning', "/mod/thesesynthesis/planning.php?id=$scm"],
    ['stressagent1', 'Mes signatures (agent DEVE)', '/mod/these/mysignatures.php'],
    ['stressagent1', 'Suivi des thèses (agent DEVE)', "/mod/thesesynthesis/view.php?id=$scm"],
    ['stresslib', 'Tableau de bord bibliothèque', "/mod/these/dashboard.php?id=$cm"],
    ['stressviewall', 'Tableau de bord scolarité (viewall)', "/mod/these/dashboard.php?id=$cm"],
    [$director->username, "Tableau de bord directeur ({$director->n} dossiers)", "/mod/these/dashboard.php?id=$cm"],
    [$director->username, 'Mes signatures (directeur)', '/mod/these/mysignatures.php'],
    [$director->username, 'Suivi des thèses (directeur)', "/mod/thesesynthesis/view.php?id=$scm"],
    [$student, 'Mon dossier (étudiant au plus long historique)', "/mod/these/view.php?id=$longcm"],
];

mtrace("$count dossiers, " . count($these) . " promotion(s). Moyenne de {$options['runs']} essai(s) après une requête de chauffe.");
mtrace(sprintf('%-52s %9s %9s %8s', 'Page', 'Temps (s)', 'Lectures', 'Écrit.'));
$results = [];
$sessions = [];
foreach ($pages as [$username, $label, $path]) {
    if (!isset($sessions[$username])) {
        $sessions[$username] = perf_login($CFG->wwwroot, $username, $options['password']);
    }
    $runs = [];
    for ($i = 0; $i <= (int) $options['runs']; $i++) {
        $measure = perf_fetch($CFG->wwwroot . $path, $sessions[$username]);
        if ($i > 0) {
            $runs[] = $measure;
        }
    }
    $time = array_sum(array_column($runs, 'time')) / count($runs);
    $reads = $runs[0]['reads'];
    $writes = $runs[0]['writes'];
    $results[] = ['user' => $username, 'page' => $label, 'path' => $path, 'time' => $time, 'reads' => $reads,
        'writes' => $writes, 'status' => $runs[0]['status']];
    mtrace(sprintf(
        '%-52s %9.2f %9s %8s%s',
        core_text::substr($label, 0, 52),
        $time,
        $reads ?? '-',
        $writes ?? '-',
        $runs[0]['status'] !== 200 ? '  (HTTP ' . $runs[0]['status'] . ')' : ''
    ));
}
if ($options['json'] !== '') {
    file_put_contents($options['json'], json_encode(['dossiers' => $count, 'pages' => $results], JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE));
}

/**
 * Ouvre une session : page de connexion (jeton), puis envoi de l'identifiant et du mot de passe.
 *
 * @param string $wwwroot
 * @param string $username
 * @param string $password
 * @return string Fichier des témoins de la session.
 */
function perf_login($wwwroot, $username, $password) {
    $jar = make_request_directory() . '/' . $username . '.cookies';
    $page = perf_curl($wwwroot . '/login/index.php', $jar);
    if (!preg_match('/name="logintoken" value="([^"]+)"/', $page['body'], $match)) {
        cli_error('Page de connexion inattendue pour ' . $username);
    }
    $page = perf_curl($wwwroot . '/login/index.php', $jar, ['username' => $username, 'password' => $password,
        'logintoken' => $match[1]]);
    if (strpos($page['body'], '/login/logout.php') === false) {
        cli_error('Connexion impossible pour ' . $username . ' (HTTP ' . $page['status'] . ') : voir README.md, '
            . '$CFG->disablelogintoken.');
    }
    return $jar;
}

/**
 * Demande une page et relève son temps de réponse et ses accès à la base (pied de page des performances).
 *
 * @param string $url
 * @param string $jar
 * @return array {time, reads, writes, status}
 */
function perf_fetch($url, $jar) {
    $start = microtime(true);
    $page = perf_curl($url, $jar);
    $time = microtime(true) - $start;
    $reads = $writes = null;
    if (preg_match('#DB reads/writes: (\d+)/(\d+)#', $page['body'], $match)) {
        [$reads, $writes] = [(int) $match[1], (int) $match[2]];
    }
    return ['time' => $time, 'reads' => $reads, 'writes' => $writes, 'status' => $page['status']];
}

/**
 * Requête HTTP avec témoins.
 *
 * @param string $url
 * @param string $jar
 * @param array|null $post
 * @return array {status, body}
 */
function perf_curl($url, $jar, ?array $post = null) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 300, CURLOPT_NOPROXY => '*',
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post, '', '&'));
    }
    $body = (string) curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ['status' => $status, 'body' => $body];
}
