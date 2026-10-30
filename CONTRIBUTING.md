# Contribuer

## Flux de travail

1. Partir de `main` à jour et créer une branche par fonctionnalité ou correction.
2. Faire des commits courts et cohérents : un commit = un changement que l'on
   peut relire, annuler ou retrouver par bissection. Ne pas mélanger plusieurs
   fonctionnalités, ni un déplacement de code avec une modification de ce code.
3. Ouvrir une demande de fusion vers `main` en suivant le modèle proposé.
4. Fusionner seulement quand la CI est verte **et** qu'une autre personne a relu
   et approuvé la demande.

`main` ne reçoit jamais de poussée directe. Pour l'imposer, le propriétaire du
dépôt active une règle de protection de la branche (GitHub, *Settings* →
*Rules* → *Rulesets*, ou *Branches* → *Branch protection rules*) :

- demande de fusion obligatoire, avec au moins une approbation ;
- vérifications obligatoires : *Style Moodle*, les trois tâches
  *Moodle … · PHP … · …* (4.1, 4.5 et 5.0) et *Guide PDF* ;
- branche à jour avant fusion ; poussées forcées et suppression interdites.

## Vérifications locales

- Style : `phpcs --standard=phpcs.xml` (standard `moodlehq/moodle-cs`), sans
  avertissement.
- Tests : `vendor/bin/phpunit --testsuite mod_these_testsuite` et
  `mod_thesesynthesis_testsuite` depuis un Moodle de développement où les deux
  modules sont copiés dans `mod/` (pas de lien symbolique : les pages incluent
  `__DIR__ . '/../../config.php'`).
- Behat : `vendor/bin/behat --config <behat_dataroot>/behatrun/behat/behat.yml
  --tags @mod_these`.

## Organisation du code

Le code métier de `mod_these` est dans les classes autochargées de
`mod/these/classes/local/` (une par domaine). Les fonctions `these_*()` de
`mod/these/locallib/` n'en sont que des façades : une nouvelle fonction s'écrit
comme méthode de la classe du domaine, sans façade ; les appels existants aux
façades peuvent être remplacés progressivement par des appels aux classes.

## Base de données

Tout changement de schéma passe par `db/install.xml` (éditeur XMLDB), une étape
de `db/upgrade.php` avec son `upgrade_mod_savepoint()`, et l'augmentation de
`$plugin->version`. La montée de version depuis la version publiée précédente
doit être essayée sur une copie de base avant la fusion.

Numéros de version : `AAAAMMJJXX` à la date du jour du changement (`XX` de 00 à
99 pour plusieurs changements le même jour), jamais une date future. La première
version publiée est 2026102501 ; toute modification de schéma à partir de là
passe par une étape de `db/upgrade.php` (à recréer à la première modification).

## Versions

Une version se publie par une demande de fusion : nouvelle section en tête de
`CHANGES.md`, `version` et `release` augmentés dans les `version.php` (et la
dépendance de `mod_thesesynthesis` si elle change). Une fois fusionnée et la CI
verte sur `main`, la CI étiquette `vX.Y.Z` (d'après `release` de `mod_these`) et
crée la publication GitHub, avec la section de `CHANGES.md` pour notes et le
guide PDF joint. Une version déjà étiquetée n'est pas republiée ; si la première
section de `CHANGES.md` n'est pas celle de la version lue dans `version.php`, la
publication échoue avec un message explicite.

## Guide

Le guide se modifie dans `docs/guides/guide-these.tex` ; le PDF n'est pas
versionné. Pour le produire localement : `pdflatex guide-these.tex` deux fois
dans `docs/guides/` (paquets TeX Live `latex-recommended`, `latex-extra`,
`lang-french`, `pictures`).
