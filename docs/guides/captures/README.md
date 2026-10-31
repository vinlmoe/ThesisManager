# Captures du guide

Les images de `docs/guides/images/` citées par le guide sont produites sur un Moodle de test,
à partir d'un jeu de données où chaque dossier est amené à son étape par les fonctions du
circuit (dépôt, validations, signatures, rapport, documents, procès-verbal).

## Prérequis

- Un Moodle 4.5 de test sur PostgreSQL, avec `mod_these` et `mod_thesesynthesis` copiés dans
  `mod/` (copie, pas lien symbolique : les pages incluent `../../config.php`). La base de ce
  Moodle (lue dans `config.php`) est supprimée et recréée par `reset.sh` : ne pas la partager
  avec un autre site, par exemple celui des tests PHPUnit. Le répertoire `vendor/` de Moodle n'est
  pas nécessaire. Dans `config.php` :

  ```php
  $CFG->noemailever = true;
  // Avertissements PHP affichés, que shots.js relève ; pas les messages du niveau développeur
  // (celui de noemailever, à chaque courriel, remplacerait chaque redirection par une page
  // intermédiaire). DEBUG_ALL n'est pas encore défini dans config.php.
  $CFG->debug = E_ALL & ~E_STRICT;
  $CFG->debugdisplay = 1;
  // Moodle ne peut pas déduire l'adresse « noreply » de localhost.
  $CFG->noreplyaddress = 'noreply@example.com';
  ```
- Le site servi sur `http://localhost:8080` (par exemple `php -S localhost:8080 -t <moodle>`,
  avec `max_input_vars = 5000`).
- Node.js et Playwright (Chromium), `pdftoppm` et ImageMagick (`convert`).

## Refaire les captures

```sh
sh docs/guides/captures/reset.sh /chemin/vers/moodle   # base remise à zéro + jeu de données
node docs/guides/captures/shots.js                      # toutes les captures, dans l'ordre
node docs/guides/captures/shots.js 62_directeur_valide_sujet 40_suivi_signatures   # quelques-unes
```

Certaines captures font avancer un dossier (rapport enregistré, signature de l'agent DEVE,
transmission des documents, clôture, dépôt d'un scan) : pour tout refaire, relancer d'abord
`reset.sh`.

## Vérification du parcours

Chaque page chargée pendant les captures est contrôlée : code HTTP, page d'erreur de Moodle,
avertissement ou erreur PHP affiché, erreur JavaScript. Les problèmes sont listés à la fin avec
la capture en cours, et le script se termine en erreur ; une capture qui ne trouve pas l'élément
attendu est signalée par `ÉCHEC`. Le jeu de données lui-même est construit par les fonctions du
circuit : une régression du circuit fait échouer `reset.sh`.

## Cadrage

La fenêtre du navigateur est agrandie à toute la hauteur de la page, puis chaque capture est
découpée sur des éléments de la page : fin du contenu principal, d'un formulaire, d'une section
du dossier ou d'un bloc de formulaire. Aucune capture n'est coupée à une hauteur fixe. Les
pages très longues (tableaux de bord, dossier complet, grille de conformité) sont montrées par
rubrique.

## Fichiers produits

`ids.json` (identifiants du jeu de données) et `scan-contrat-signe.png` (scan déposé dans la
capture 98) sont écrits par `seed.php` et ne sont pas suivis par Git.
