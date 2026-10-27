---
name: these-dev
description: Conventions et procédures pour modifier les modules Moodle mod_these (Gestion des thèses) et mod_thesesynthesis (Suivi des thèses) de ce dépôt. À charger avant toute modification du code PHP, du schéma de base, des chaînes de langue, des modèles de documents, des courriels, des tests, de CHANGES.md ou du guide LaTeX, et avant de préparer une version ou une demande de fusion.
---

# Développer ThesisManager

Deux modules d'activité Moodle (4.0+, PHP 8.0 à 8.2) :

- `mod/these` (`mod_these`) : le circuit d'une thèse d'exercice vétérinaire, du sujet au
  procès-verbal ;
- `mod/thesesynthesis` (`mod_thesesynthesis`) : suivi de plusieurs promotions, annuaire du jury,
  paramètres partagés (modèles, grille, questionnaire, rôles) imposés aux activités suivies.

Lire `README.md` pour le circuit et `CONTRIBUTING.md` pour les règles ; ce skill les résume et
ajoute ce que le code impose.

## Langue et style

- Tout est en **français** : commentaires, PHPDoc, messages de commit, `CHANGES.md`, guide.
  Orthographe complète (capitales accentuées, guillemets « … »). La règle phpcs
  `InlineComment.NotCapital` est désactivée pour cette raison ; ne pas écrire « Ecran ».
- Standard `moodlehq/moodle-cs` **sans aucun avertissement** (`phpcs.xml`). PHPDoc complet sur
  chaque fonction, classe et propriété (`moodle-plugin-ci phpdoc --max-warnings=0`).
- En-tête GPL et bloc `@package mod_these` / `@copyright 2026 Sébastien Lefebvre` /
  `@license` sur chaque nouveau fichier, comme les fichiers voisins.
- Pas de `GLOB_BRACE` (absent des PHP musl, vérifié par la CI).
- Le code de `mod/these/thirdparty/` n'est pas modifié.
- Commits courts et cohérents, un changement relisible par commit, titre français à
  l'impératif nominal (ex. « Validation de l'A6 liée à l'étudiant et non au dossier »).

## Organisation du code

- Code métier : classes autochargées `mod/these/classes/local/<domaine>.php`
  (`\mod_these\local\workflow`, `rights`, `dossiers`, `jury`, `signatures`, `doctemplates`,
  `notifications`, `planning`, `a6`, `calendar`…), méthodes `public static`.
- `mod/these/locallib/*.php` : façades `these_*()` d'une ligne vers ces classes, gardées pour
  les appelants existants. **Une nouvelle fonction s'écrit comme méthode de la classe du domaine,
  sans façade.** `locallib.php` reste le seul fichier à inclure (il définit les constantes
  `THESE_STATUS_*`).
- Pages : un script PHP par écran à la racine du module, formulaires dans `classes/form/`,
  rendu par `classes/output/` + gabarits `templates/*.mustache` (vérifiés par la CI).
- Statuts et transitions : `workflow::all_statuses()`, `status_transitions()`,
  `step_actor()`, `phase()`. Un nouveau statut ou une nouvelle transition passe par ces tables ;
  ne jamais écrire le statut d'un dossier en dehors de `these_set_status()`.
- Droits : `classes/local/rights.php` et `db/access.php`. Toute page vérifie
  `require_login` puis le droit du domaine ; penser aux **thèses confidentielles** (sujet masqué
  sauf étudiant, DEVE, bibliothèque, directeur et jury) et aux liens à jeton des signataires
  extérieurs.

## Ajouts fréquents

### Chaîne de langue

Ajouter la chaîne **dans `lang/fr/these.php` et `lang/en/these.php`**, sous la même rubrique
commentée, avec la même clé. Pas de chaîne en dur dans le code ni dans les gabarits.

### Changement de schéma

1. `db/install.xml` (structure complète, comme l'éditeur XMLDB la produirait) ;
2. étape `if ($oldversion < AAAAMMJJXX)` dans `db/upgrade.php`, idempotente
   (`table_exists`, `field_exists`), terminée par
   `upgrade_mod_savepoint(true, AAAAMMJJXX, 'these');` ;
3. `$plugin->version` dans `version.php` à la même valeur : date **du jour**, `XX` de 00 à 99,
   jamais une date future, toujours supérieure à la valeur actuelle ;
4. mettre à jour, si la table porte des données d'un dossier ou d'un utilisateur :
   sauvegarde et restauration (`backup/moodle2/*_stepslib.php`), API de confidentialité
   (`classes/privacy/provider.php` : métadonnées, export, suppression), et
   `tests/upgrade_test.php` ;
5. si `mod_thesesynthesis` dépend de la nouvelle version, ajuster sa dépendance dans son
   `version.php`.

### Modèle de document (PDF)

- Modèle d'origine : `mod/these/doctemplates/<type>.html` (HTML et balises `{{...}}`).
- Déclarer le type dans `doctemplates::doctemplate_types()` (avec le document signé dont il
  reprend les signatures, ou `null`), le libellé `doctpl<type>` dans les deux langues, les
  variables dans `build_document_context()` (la liste affichée à la DEVE en est déduite par
  `doctemplate_variables()`).
- Document signé : emplacements dans `signatures::document_definitions()` (`slots`,
  `staffonly` pour les documents réservés au personnel, jamais rangés dans la zone
  `generated` visible de l'étudiant).
- Les modèles se définissent dans l'activité de suivi quand elle existe : passer par la
  portée (`settings_scope()`), pas par l'activité directement.

### Courriel personnalisable

Entrée dans `notifications::get_email_definitions()` (`subjectstring`, `bodystring`, `vars`),
les deux chaînes dans les deux langues, envoi par `notifications::send_email()`. Vérifier
`recipient_knows_subject()` pour les thèses confidentielles.

### Paramètre de l'activité ou du circuit

Les paramètres du circuit sont propres à chaque activité (table `these_setting`, portée
`scopesettings`) et peuvent être imposés par l'activité de suivi : **aucun réglage du site**.
Un nouveau paramètre doit aussi être pris en compte par le transfert des paramètres
(`classes/local/settingstransfer.php`).

## Tests

- PHPUnit : classes de `mod/these/tests/` héritant de `\mod_these\these_testcase`
  (`setup_activity()` crée cours, activité, étudiant, directeur, DEVE, bibliothèque) ; données
  par le générateur `tests/generator/lib.php`. Ajouter ou mettre à jour un test pour chaque
  changement de comportement.
- Behat (`tests/behat/*.feature`, tag `@mod_these`) quand le parcours d'un acteur change.
- Ces tests demandent un Moodle installé : ils ne tournent pas dans ce dépôt seul. Copier
  (pas de lien symbolique) `mod/these` et `mod/thesesynthesis` dans `mod/` d'un Moodle de
  développement, puis :

  ```sh
  vendor/bin/phpunit --testsuite mod_these_testsuite
  vendor/bin/phpunit --testsuite mod_thesesynthesis_testsuite
  vendor/bin/behat --config <behat_dataroot>/behatrun/behat/behat.yml --tags @mod_these
  ```

- À défaut de Moodle local, faire au minimum, avant de pousser :

  ```sh
  phpcs --standard=phpcs.xml -p          # composer global require moodlehq/moodle-cs
  find mod -name '*.php' -not -path '*/thirdparty/*' -print0 | xargs -0 -n1 php -l > /dev/null
  ```

  et le dire dans la demande de fusion ; la CI (`.github/workflows/ci.yml`) fait le reste :
  lint, validation, savepoints, PHPDoc, Mustache, PHPUnit et Behat sur Moodle 4.1
  (PHP 8.0, MariaDB) et 4.5 (PHP 8.2, PostgreSQL), et le guide PDF.

## Documentation

- `CHANGES.md` : chaque changement visible s'ajoute à la section en tête (version en cours de
  préparation), en phrases complètes, avec le nom de la classe ou du paramètre concerné et, pour
  une mise à jour de base, la version et ce qu'elle reprend.
- Guide : `docs/guides/guide-these.tex` (LaTeX, styles communs dans `guide-commun.sty`), à
  mettre à jour quand l'usage change, avec une ligne dans son tableau des versions. Vérifier
  qu'il compile :

  ```sh
  cd docs/guides && pdflatex -interaction=nonstopmode -halt-on-error guide-these.tex \
    && pdflatex -interaction=nonstopmode -halt-on-error guide-these.tex
  ```

  Le PDF n'est pas versionné. Les captures se refont avec `docs/guides/captures/`
  (voir son `README.md`).
- `mod/these/INSTALL.md` : installation, rôles, paramétrage.

## Version et demande de fusion

- `main` ne reçoit jamais de poussée directe : une branche, puis une demande de fusion suivant
  `.github/pull_request_template.md` (cocher honnêtement chaque case : schéma, chaînes FR/EN,
  phpcs, tests, Behat, `CHANGES.md`, guide).
- Publier une version : nouvelle section en tête de `CHANGES.md`
  (`## mod_these X.Y.Z (AAAAMMJJXX) — résumé`), `version` et `release` augmentés dans les
  `version.php` concernés. La CI étiquette `vX.Y.Z` et crée la publication une fois fusionnée
  sur `main` ; ne pas créer l'étiquette à la main.

## Liste de contrôle avant de pousser

1. phpcs sans avertissement, `php -l` propre.
2. Chaînes présentes en français et en anglais.
3. Schéma modifié → `install.xml`, `upgrade.php` + savepoint, `version.php`, sauvegarde,
   confidentialité, test de mise à jour.
4. Droits et confidentialité vérifiés sur chaque nouvelle page ou donnée affichée.
5. Tests ajoutés ; `CHANGES.md` complété ; guide mis à jour et compilé si l'usage change.
