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
 * Test de charge du circuit des thèses, sur un Moodle de test jetable (voir README.md).
 *
 * Usage : php tools/stresstest/stress.php /chemin/vers/moodle [--students=50] [--promotions=1] [--ops=500]
 *         [--seed=1] [--workers=1] [--focus=0] [--latency=0] [--populate=0] [--norender] [--json=rapport.json]
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Moodle de test visé : premier argument (voir README.md).
require(rtrim($argv[1] ?? '.', '/') . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/runner.php');

[$options] = cli_get_params([
    'students' => 50, 'promotions' => 1, 'ops' => 500, 'seed' => 1, 'workers' => 1, 'focus' => 0, 'latency' => 0,
    'populate' => 0, 'norender' => false, 'json' => '', 'help' => false,
], ['h' => 'help']);
if ($options['help']) {
    echo "Test de charge du circuit des thèses (voir tools/stresstest/README.md).\n";
    exit(0);
}
if (empty($CFG->noemailever)) {
    cli_error('$CFG->noemailever doit être activé : ce script envoie des centaines de notifications.');
}

$runner = new stress_runner((int) $options['seed']);
$runner->latency = (int) $options['latency'];
$runner->render = empty($options['norender']);
$start = microtime(true);
$runner->setup((int) $options['students'], max(1, (int) $options['promotions']));
mtrace(sprintf(
    'Jeu de données prêt : %d étudiants, %d promotion(s) (%.1f s).',
    count($runner->users['students']),
    count($runner->activities),
    microtime(true) - $start
));

$workers = max(1, (int) $options['workers']);
$results = [];

// Peuplement : chaque étudiant reçoit un dossier, mené à une étape tirée au hasard par les opérations du
// circuit. Avec plusieurs processus, chacun mène les dossiers d'une part des étudiants.
$start = microtime(true);
if ((int) $options['populate'] > 0) {
    $populate = function (stress_runner $runner, $w, $workers) use ($options) {
        $runner->populate((int) $options['populate'], $w - 1, $workers);
    };
    $results = array_merge($results, stress_run($runner, $workers, $populate));
    mtrace(sprintf('Peuplement : %d dossier(s) (%.1f s).', count($runner->dossier_ids()), microtime(true) - $start));
}

$focus = (int) $options['focus'];
if ($focus > 0) {
    $runner->focus_on($focus);
}

// Opérations tirées au hasard, réparties entre les processus.
$ops = (int) $options['ops'];
$random = function (stress_runner $runner, $w, $workers) use ($ops) {
    $share = intdiv($ops, $workers) + ($w <= $ops % $workers ? 1 : 0);
    for ($i = 0; $i < $share; $i++) {
        $runner->run_operation($runner->draw_operation());
    }
};
$results = array_merge($results, stress_run($runner, $workers, $random));
$elapsed = microtime(true) - $start;

// Invariants finaux : tous les dossiers, puis la base entière.
$final = new stress_runner((int) $options['seed']);
$final->activities = $runner->activities;
$final->users = $runner->users;
$final->render = $runner->render;
$final->worker = 'final';
$final->import_kept($results);
foreach ($final->dossier_ids() as $id) {
    foreach ($final->check_dossier($id) as $violation) {
        $final->violations[] = ['worker' => 'final', 'seed' => $final->seed, 'op' => 0, 'name' => 'final', 'dossier' => $id,
            'message' => $violation];
    }
}
foreach ($final->check_global() as $violation) {
    $final->violations[] = ['worker' => 'final', 'seed' => $final->seed, 'op' => 0, 'name' => 'final', 'dossier' => 0,
        'message' => $violation];
}
$results[] = $final->results();

$report = stress_merge($results);
$report['elapsed'] = $elapsed;
$report['options'] = $options;
$report['dossiers'] = $DB->count_records('these_dossier');
stress_print($report);
if ($options['json'] !== '') {
    file_put_contents($options['json'], json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
exit($report['unexpected'] || $report['violations'] ? 1 : 0);

/**
 * Exécute une tâche dans ce processus, ou dans plusieurs processus qui agissent en même temps sur les
 * mêmes dossiers. Chaque processus a sa propre connexion à la base et sa propre graine (graine × 100 +
 * numéro du processus).
 *
 * @param stress_runner $runner
 * @param int $workers
 * @param callable $task function(stress_runner $runner, int $w, int $workers)
 * @return array Résultats de chaque processus.
 */
function stress_run(stress_runner $runner, $workers, callable $task) {
    global $DB;

    if ($workers === 1) {
        $part = clone $runner;
        $part->reset_results();
        $task($part, 1, 1);
        return [$part->results()];
    }
    if (!function_exists('pcntl_fork')) {
        cli_error('Extension pcntl requise pour --workers.');
    }
    $dir = make_request_directory();
    // La connexion ne doit pas être partagée entre processus : fermée avant, rouverte par chacun.
    $DB->dispose();
    $pids = [];
    for ($w = 1; $w <= $workers; $w++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            cli_error('Échec de pcntl_fork.');
        }
        if ($pid === 0) {
            unset($GLOBALS['DB']);
            setup_DB();
            $runner->worker = 'w' . $w;
            $runner->seed = $runner->seed * 100 + $w;
            $runner->reset_results();
            mt_srand($runner->seed);
            $task($runner, $w, $workers);
            file_put_contents("$dir/w$w.json", json_encode($runner->results()));
            $DB->dispose();
            // Sortie immédiate : pas de fonctions d'arrêt de Moodle dans le processus fils.
            posix_kill(getmypid(), SIGKILL);
        }
        $pids[] = $pid;
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
    unset($GLOBALS['DB']);
    setup_DB();
    $results = [];
    for ($w = 1; $w <= $workers; $w++) {
        $file = "$dir/w$w.json";
        $results[] = is_file($file) ? json_decode(file_get_contents($file), true)
            : ['stats' => [], 'errors' => [['worker' => "w$w", 'seed' => 0, 'op' => 0, 'name' => '-', 'dossiers' => '',
                'error' => 'processus interrompu sans résultat', 'where' => '', 'trace' => []]], 'violations' => [], 'kept' => []];
    }
    return $results;
}

/**
 * Fusionne les résultats des processus.
 *
 * @param array $results
 * @return array
 */
function stress_merge(array $results) {
    $report = ['stats' => [], 'errors' => [], 'violations' => [], 'unexpected' => 0, 'operations' => 0];
    foreach ($results as $result) {
        foreach ($result['stats'] as $name => $stat) {
            $merged = $report['stats'][$name] ?? ['ok' => 0, 'refused' => 0, 'unexpected' => 0, 'skipped' => 0,
                'time' => 0.0, 'max' => 0.0];
            foreach (['ok', 'refused', 'unexpected', 'skipped', 'time'] as $key) {
                $merged[$key] += $stat[$key];
            }
            $merged['max'] = max($merged['max'], $stat['max']);
            $report['stats'][$name] = $merged;
        }
        $report['errors'] = array_merge($report['errors'], $result['errors']);
        $report['violations'] = array_merge($report['violations'], $result['violations']);
    }
    ksort($report['stats']);
    foreach ($report['stats'] as $stat) {
        $report['unexpected'] += $stat['unexpected'];
        $report['operations'] += $stat['ok'] + $stat['refused'] + $stat['unexpected'] + $stat['skipped'];
    }
    $report['unexpected'] = max($report['unexpected'], count($report['errors']));
    return $report;
}

/**
 * Affiche le rapport.
 *
 * @param array $report
 * @return void
 */
function stress_print(array $report) {
    mtrace('');
    mtrace(sprintf(
        '%d opérations en %.1f s, %d dossiers en base.',
        $report['operations'],
        $report['elapsed'],
        $report['dossiers']
    ));
    mtrace('');
    mtrace(sprintf(
        '%-14s %6s %8s %10s %7s %9s %9s',
        'Opération',
        'Succès',
        'Refusées',
        'Inattendu',
        'Vides',
        'Moy. (ms)',
        'Max (ms)'
    ));
    foreach ($report['stats'] as $name => $stat) {
        $count = $stat['ok'] + $stat['refused'] + $stat['unexpected'] + $stat['skipped'];
        mtrace(sprintf(
            '%-14s %6d %8d %10d %7d %9.0f %9.0f',
            $name,
            $stat['ok'],
            $stat['refused'],
            $stat['unexpected'],
            $stat['skipped'],
            $count ? 1000 * $stat['time'] / $count : 0,
            1000 * $stat['max']
        ));
    }
    mtrace('');
    mtrace('Erreurs inattendues : ' . count($report['errors']));
    $seen = [];
    foreach ($report['errors'] as $error) {
        $key = $error['name'] . '|' . $error['where'] . '|' . substr($error['error'], 0, 80);
        $seen[$key] = ($seen[$key] ?? 0) + 1;
        if ($seen[$key] > 3) {
            continue;
        }
        mtrace(sprintf(
            '  [%s graine %d op %d] %s (dossiers %s) : %s @ %s',
            $error['worker'],
            $error['seed'],
            $error['op'],
            $error['name'],
            $error['dossiers'],
            $error['error'],
            $error['where']
        ));
        foreach ($error['trace'] as $frame) {
            mtrace('      ' . $frame);
        }
    }
    foreach ($seen as $key => $count) {
        if ($count > 3) {
            mtrace("  ... $key : $count occurrences au total");
        }
    }
    mtrace('');
    mtrace('Invariants violés : ' . count($report['violations']));
    $seen = [];
    foreach ($report['violations'] as $violation) {
        $key = preg_replace('/\d+/', 'N', $violation['message']);
        $seen[$key] = ($seen[$key] ?? 0) + 1;
        if ($seen[$key] > 3) {
            continue;
        }
        mtrace(sprintf(
            '  [%s graine %d op %d] après %s, dossier %d : %s',
            $violation['worker'],
            $violation['seed'],
            $violation['op'],
            $violation['name'],
            $violation['dossier'],
            $violation['message']
        ));
    }
    foreach ($seen as $key => $count) {
        if ($count > 3) {
            mtrace("  ... $key : $count occurrences au total");
        }
    }
}
