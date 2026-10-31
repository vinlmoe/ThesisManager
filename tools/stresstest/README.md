# Test de charge du circuit des thèses

`stress.php` éprouve `mod_these` et `mod_thesesynthesis` avec beaucoup de dossiers, d'opérations
tirées au hasard et de processus simultanés, pour faire émerger ce que les tests unitaires ne
voient pas : exceptions inattendues, avertissements PHP, états incohérents, fuites de sujets
confidentiels, courses entre deux personnes qui agissent sur le même dossier. `perf.php` mesure
ensuite, sur le jeu de données obtenu, le temps et le nombre de requêtes des pages lourdes.

Les deux scripts écrivent dans la base : **ils ne s'utilisent que sur un Moodle de test jetable**,
jamais sur un site réel.

## Prérequis

- Un Moodle 4.5 de test (le dossier `vendor/` n'est pas nécessaire), avec `mod_these` et
  `mod_thesesynthesis` installés, sur PostgreSQL (les verrous de `dossier_operation()` sont ceux
  de la base).
- Dans `config.php` : `$CFG->noemailever = true;` (exigé par `stress.php`, qui envoie des milliers
  de notifications), `$CFG->debug = E_ALL;` et `$CFG->debugdisplay = 1;` (les appels à
  `debugging()` sont comptés comme des erreurs), et, pour `perf.php`, `$CFG->perfdebug = 15;`.
- L'extension PHP `pcntl` pour `--workers` (plusieurs processus).
- Pour `perf.php` : le site servi en HTTP, par exemple
  `php -d max_input_vars=5000 -S localhost:8081 -t /chemin/vers/moodle` avec
  `$CFG->wwwroot = 'http://localhost:8081';`. Les comptes du jeu de données ont le mot de passe
  `Stress1234!` (option `--password`).
- Pour trouver l'origine des requêtes d'une page : `$CFG->dboptions['logall'] = true;` le temps de
  la mesure, puis lire la table `log_queries` (colonne `backtrace`).

## stress.php

```sh
php tools/stresstest/stress.php /chemin/vers/moodle --students=150 --promotions=2 --populate=60 \
    --ops=3000 --seed=11 --workers=6 --latency=20 --json=rapport.json
```

| Option | Défaut | Effet |
|---|---|---|
| `--students=N` | 50 | Étudiants (comptes `stresssN`, mot de passe `Stress1234!`), répartis entre les promotions. |
| `--promotions=P` | 1 | Activités « Gestion des thèses » (cours `STRESS`), toutes suivies par une activité « Suivi des thèses » (cours `STRESSSUIVI`) qui porte les paramètres du circuit. |
| `--populate=K` | 0 | Peuplement : chaque étudiant reçoit un dossier, mené vers une étape tirée au hasard en K opérations au plus (signatures, validations, renvois, changements de sujet, abandons et réouvertures compris). |
| `--ops=K` | 500 | Opérations tirées au hasard après le peuplement, réparties entre les processus. |
| `--seed=S` | 1 | Graine du tirage. Le processus `wN` utilise la graine `S × 100 + N`. |
| `--workers=P` | 1 | Processus simultanés (fork), chacun avec sa connexion à la base, sur les mêmes dossiers. |
| `--focus=N` | 0 | Limite le tirage des opérations aux N premiers dossiers (plus de collisions). |
| `--latency=ms` | 0 | Délai aléatoire (0 à ms) entre la lecture d'une page et son écriture, pour élargir les fenêtres de concurrence. |
| `--norender` | non | Ne rend pas les pages des dossiers confidentiels après chaque opération (plus rapide). |
| `--json=fichier` | | Rapport complet en JSON. |

Le script reprend ce qui existe (cours, activités, comptes, annuaire) : il peut être relancé sur le
même site, avec plus d'étudiants ou d'autres graines. Pour repartir d'un site vierge, restaurer
une copie de la base faite juste après l'installation.

### Jeu de données

Un cours et ses promotions, une activité de suivi (agents DEVE `stressagent1` et `stressagent2`,
bibliothèque `stresslib`, signataires de la direction générale, du CCEM et de l'université, salles,
questionnaire), la DEVE `stressdeve`, un directeur pour 12 étudiants (`stressdirN`), une
enseignante qui ne dirige rien (`stressteacher`), la scolarité (`stressviewall`, rôle avec
`mod/these:viewall`), quatre jurés avec compte (`stressjuryN`) et un annuaire de présidents et
membres sans compte. Un sujet sur quatre est confidentiel, un sur sept a un directeur extérieur.

### Opérations

Chaque opération reproduit une page : elle relit le dossier, agit **en tant que** l'utilisateur de
la page (`\core\session\manager::set_user()`), applique les mêmes contrôles d'accès et de statut,
puis appelle les mêmes fonctions du circuit.

| Opération | Page reproduite |
|---|---|
| `create` | création d'un dossier par la DEVE (`create.php`) |
| `savesubject` | dépôt ou enregistrement du sujet (`subject.php`), création du dossier comprise |
| `review`, `returndossier` | validation ou renvoi d'une étape par son acteur (`review.php`) : directeur, DEVE (numéro, date, salle, jury et éligibilité), bibliothèque ; rapport et DEFV complétés au besoin |
| `changesubject` | changement de sujet d'un dossier actif ou abandonné (`subjectchange.php`) |
| `cancel`, `reopen` | abandon et réouverture (`cancel.php`) |
| `savedefense` | dépôt du dossier de soutenance : manuscrit, questionnaire, jury, bordereau, grille (`defense.php`) |
| `report` | rapport du directeur (`report.php`) |
| `daterequest`, `newdate` | autre date demandée par la DEVE, nouvelle date proposée par l'étudiant |
| `editdossier` | modification du dossier par la DEVE (`edit.php`) |
| `sign` | une signature attendue, par sa voie : agent DEVE (« Mes signatures »), lien à jeton, sur place auprès de la DEVE, scan de l'université, compte |
| `signtoken` | lien de signature renvoyé (l'ancien ne vaut plus), lien expiré |
| `refuse` | refus de signer d'un agent DEVE (`refuse.php`) |
| `documents` | génération et transmission des documents de soutenance (`documents.php`) |
| `minutes` | procès-verbal : admis, admis avec corrections, ajourné (`minutes.php`) |
| `corrections` | dépôt des corrections (`corrections.php`) |
| `notifyjury`, `tokens` | liens d'accès du jury ; jetons inconnus, trop courts |
| `suspend`, `deleteuser` | suspension ou suppression d'un compte (directeur, juré, étudiant), puis contrôle de ses droits |
| `privacy` | demande RGPD : export, effacement d'un étudiant (avec ou sans procès-verbal) ou d'un membre du personnel |
| `illegitimate` | un compte qui n'est pas l'acteur attendu tente une étape, une action de la DEVE, l'action d'un étudiant sur le dossier d'autrui, ou la consultation : le refus est attendu |

### Classement des résultats

- **Succès** : la page aurait enregistré l'action.
- **Refusée** : refus attendu — contrôle de la page (statut, acteur, signatures manquantes,
  nouvelle date attendue…), `moodle_exception` documentée (`dossierchanged`, `dossierbusy`,
  `notyourstep`, `nopermissions`…), compte supprimé ou suspendu.
- **Inattendu** : toute autre exception (`coding_exception`, `dml_exception`, `TypeError`…), tout
  avertissement, notice ou dépréciation PHP (gestionnaire d'erreurs qui les convertit en
  exceptions) et tout appel à `debugging()`. Le rapport donne le processus, la graine, le numéro de
  l'opération, les dossiers touchés et une trace courte.
- **Vides** : aucun dossier dans l'état voulu.

### Invariants

Vérifiés sur les dossiers touchés après chaque opération, puis sur tous les dossiers et la base à
la fin (avec plusieurs processus, un écart n'est retenu que s'il persiste à une seconde lecture : les
lectures d'une vérification ne se font pas dans un même instantané de la base) :

- statut connu ; historique (`these_log`) dont chaque entrée part du statut atteint par la
  précédente, transitions toutes permises par la machine à états, dernier statut de l'historique
  égal au statut du dossier ; statut d'abandon mémorisé seulement sur un dossier abandonné ;
- signatures : emplacement connu, empreinte présente, pas de jeton sur une signature apposée ; pas
  de sujet signé sur un dossier renvoyé à l'étudiant, pas de document de soutenance signé sur un
  dossier actif ou renvoyé ; document scellé seulement s'il est entièrement signé, avec son
  empreinte SHA-256 à l'historique ;
- liens : accès du jury révoqués sur un dossier clos ou abandonné, liens de signature inutilisables
  sur un dossier abandonné (vérifiés avec les jetons envoyés), ancien lien sans effet après un
  nouvel envoi, lien expiré refusé, jeton inconnu refusé ;
- un seul événement de calendrier par soutenance arrêtée, aucun pour un dossier supprimé ;
- numéro DEVE unique dans l'activité ; nouvelle date plus attendue une fois la soutenance déposée de
  nouveau ;
- confidentialité : ni le titre d'une thèse confidentielle, ni celui d'un ancien sujet confidentiel
  n'apparaissent sur la page du dossier rendue pour la scolarité (`viewall`), et le PDF archivé d'un
  ancien sujet confidentiel ne lui est pas accessible ;
- effacement RGPD : procès-verbal (fichiers et décision) conservé intact ;
- base : aucun enregistrement (signature, jury, réponse, historique, ancien sujet), fichier ou
  événement de soutenance sans dossier.

### Lire le rapport

```
Opération     Succès Refusées  Inattendu   Vides Moy. (ms)  Max (ms)
review          1267     1729          0       0        72       594
...
Erreurs inattendues : 0
Invariants violés : 0
```

Beaucoup de refus est normal : le tirage propose des opérations que la page refuserait (signatures
manquantes, mauvais acteur…). Une erreur inattendue ou un invariant violé se rejoue avec la même
graine et le même nombre de processus ; en concurrence, l'ordre exact dépend de l'ordonnancement,
la graine ne fait que reproduire les opérations tirées. Le code de sortie est 1 s'il y a une erreur
inattendue ou un invariant violé.

## perf.php

```sh
php tools/stresstest/perf.php /chemin/vers/moodle --runs=3 --json=perf.json
```

Pour chaque page lourde (tableaux de bord de la DEVE, de la bibliothèque, de la scolarité et du
directeur qui dirige le plus de dossiers, filtre et recherche, planning, export du registre,
« Mes signatures », suivi des thèses et sa recherche, dossier au plus long historique, dossier de
l'étudiant), le script ouvre une session en tant que l'acteur, fait une requête de chauffe puis
`--runs` requêtes, et relève le temps de réponse et les lectures et écritures en base du pied de
page des performances. Comparer deux volumes de dossiers montre ce qui croît avec eux (requêtes en
N+1).

## Résultats de référence

Moodle 4.5, PHP 8.3, PostgreSQL 16, 4 processeurs, site de test vierge.

### Défauts trouvés et corrigés

| Passage | Volume | Opérations | Ce qui est apparu |
|---|---|---|---|
| graine 1, 1 processus | 20 étudiants | 1 037 | ancien sujet confidentiel visible par la scolarité après un changement de sujet ; lien de signature encore utilisable sur un dossier abandonné |
| graine 2 et 7, 4 processus | 40 puis 150 étudiants, 2 promotions | 6 458 | `edit.php` qui remet un dossier dans son ancien statut ; génération des documents pendant leur transmission (erreur `ZipArchive`) ; signatures apposées sur un dossier qui vient de changer de statut ; événement de calendrier d'un dossier effacé |
| graine 11, 6 processus, `--latency=20` | 150 étudiants, 2 promotions | 7 519 | `TypeError` au changement de sujet quand le compte du directeur est supprimé ; nouvelle date encore attendue après un nouveau dépôt ; relance des signatures sur un dossier qui change de statut ; première consultation simultanée de la grille (voir ci-dessous) |
| graine 21, 4 processus | 2 000 étudiants, 3 promotions | 46 887 en 53 min (peuplement : 49 min) | plus d'erreur du module ; les pages et les opérations de signature ralentissaient avec le volume (voir les mesures) |
| graine 31, 4 processus, `--focus=80 --latency=20`, après les corrections | 1 976 dossiers (reprise du passage précédent) | 3 000 en 66 s | aucune erreur inattendue ; seul invariant violé : numéros DEVE en double (voir ci-dessous) |

Restent signalés, sans correction :

- **numéro DEVE en double** quand deux validations de la DEVE sont enregistrées au même instant
  sur deux dossiers : l'unicité n'est vérifiée que par le formulaire (`devenumber_taken()`), hors de
  tout verrou commun aux dossiers ;
- **première consultation simultanée de la grille de conformité** : les éléments d'origine sont
  enregistrés à la première lecture ; deux lectures simultanées, dans deux opérations, se heurtent à
  l'index unique (`dml_write_exception`). Le jeu de données les enregistre avant les opérations ;
- **`mkdir(): File exists`** dans le stockage des fichiers de Moodle (deux processus créent le même
  répertoire de contenu au même instant) : avertissement du cœur de Moodle, sans effet ;
- **fichier déposé deux fois au même instant** (double envoi d'un formulaire) : les pages enregistrent
  leurs fichiers hors du verrou du dossier, l'un des envois échoue (compté comme refus attendu) ;
- **effacement RGPD pendant une opération** sur le même dossier : l'effacement ne prend pas le
  verrou du dossier, une écriture concurrente peut laisser une entrée d'historique orpheline.

### Pages lourdes (2 000 dossiers, 3 promotions)

Lectures en base et temps de réponse (serveur PHP intégré, moyenne de 2 ou 3 requêtes après une
requête de chauffe), avant et après les corrections :

| Page (acteur) | Avant | Après |
|---|---|---|
| Tableau de bord, promotion de 660 dossiers (DEVE) | 6 188 lectures, 1,7 s | 62, 0,35 s |
| Tableau de bord (bibliothèque, scolarité, directeur) | 5 750, 1,5 s | 52 à 60, 0,2 s |
| Planning d'une promotion (DEVE) | 6 378, 1,7 s | 58, 0,22 s |
| Planning du suivi des thèses (3 promotions) | 1 752, 0,53 s | 92, 0,25 s |
| Export du registre, 660 dossiers (CSV) | 4 415 requêtes, 3,2 s | 147, 0,42 s |
| Mes signatures (DEVE, directeur) | 5 730, 1,4 s | 28, 0,15 s |
| Mes signatures (agent DEVE, 300 signatures en attente) | 6 150, 1,7 s | 55, 0,28 s |
| Suivi des thèses (DEVE) | 6 870, 2,5 s | 105, 0,33 s |
| Suivi des thèses (agent DEVE) | 7 171, 2,6 s | 114, 0,32 s |
| Suivi des thèses (directeur) | | 83, 0,21 s |
| Recherche du suivi des thèses (DEVE, un mot) | 93, 1,4 s | 86, 0,27 s |
| Dossier au plus long historique (140 entrées) | 5 869, 1,5 s | 104, 0,27 s |
| Mon dossier (étudiant) | 75, 0,19 s | 66, 0,20 s |

Les 5 700 lectures communes à toutes les pages venaient du lien « Mes signatures (n) » de la barre de
navigation du module : quinze requêtes par signature d'agent en attente sur tout le site.

Le suivi des thèses et sa recherche restaient ensuite proportionnels au nombre de dossiers en temps de
calcul, sans requête par dossier : tableau de tous les dossiers suivis, sans pagination (111 lectures,
1,07 s pour la DEVE ; 120, 1,03 s pour un agent DEVE), recherche filtrée en PHP sur tous les dossiers
(93 lectures, 1,56 s). Ils sont désormais paginés par 50 en base (droits, filtres et total dans la
requête) : 105 lectures et 0,33 s pour la DEVE, quelle que soit la page (page 1 ou 10, filtre de
texte compris), 86 lectures et 0,27 s pour la recherche (page 1 ou 5, ou sans critère). Le texte y
est cherché sans accents par une expression régulière sous PostgreSQL : un repli des accents par
`TRANSLATE()` sur chaque ligne coûtait encore 0,2 s par requête.
