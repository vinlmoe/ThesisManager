# Évolutions

Format : une section par version publiée de `mod_these` (et de `mod_thesesynthesis`
quand il change), de la plus récente à la plus ancienne. Une version publiée est
étiquetée `vX.Y.Z` : la CI crée l'étiquette et la publication GitHub, avec le
guide PDF, quand une nouvelle valeur de `release` arrive sur `main`.

**Première version publiée : `v0.11.3`** (`mod_these` et `mod_thesesynthesis`
2026102501). Le module n'ayant jamais été installé auparavant, les versions de
développement (0.9.0 à 0.11.2) sont regroupées dans celle-ci : une seule
structure de base (`db/install.xml`), sans étape de mise à jour, et des numéros
de version recalés sur la date. Les numéros entre parenthèses des sections de
développement ne correspondent à aucune version installable ; 0.11.2 n'a jamais
été étiquetée.

## mod_these 0.12.0, mod_thesesynthesis 0.4.0 (2026102502) — rendu par gabarits Mustache

Passage du rendu HTML à l'architecture Moodle standard (composants `renderable`
et `templatable`, gabarits Mustache, renderer de plugin), en quatre demandes de
fusion. Affichage inchangé, sans changement de schéma. Hors champ : documents
PDF (TCPDF) et formulaires `moodleform`.

- Étape 1, vue du dossier : renderer `mod_these\output\renderer` ; composants
  `status_badge`, `file_links`, `actions`, `detail_section`, `progress`,
  `jury`, `history`, `embargo`, `subject_history`, `signed_documents`,
  `dossier_table` et `dossier` dans `classes/output/`, chacun avec son gabarit
  dans `templates/` (partiels communs `table` et `part`, au balisage de
  `html_writer::table()`). Les fonctions `these_render_*()` et les méthodes de
  `mod_these\local\rendering` gardent leur signature et rendent ces composants.
  Les décisions d'accès (sujet d'une thèse confidentielle, motifs masqués,
  documents et zones de fichiers visibles) restent dans `export_for_template()` :
  le gabarit ne reçoit jamais le sujet masqué.
- Étape 2, planning des soutenances : composants `planning` (soutenances de la
  période, un tableau par jour), `planning_nav` (vue par semaine ou par mois,
  période précédente, courante et suivante, accès à une date), `planning_print`
  (planning imprimable) et `planning_upcoming` (semaines à venir) ;
  `these_render_planning()` et la page du planning (activité et suivi des
  thèses) les rendent. Le titre d'une thèse confidentielle reste masqué par
  `these_defense_row()` avant d'entrer dans le contexte.
- Étape 3, `mod_thesesynthesis` : renderer `mod_thesesynthesis\output\renderer` ;
  composants `navlinks`, `message`, `dossier_table` (dossiers à traiter et
  suivis), `jury_table` (mes jurys), `dossier_filters`, `search_form` et
  `search_results`, avec les partiels `select` (balisage de
  `html_writer::select()`) et `search_field`. Les pages du tableau de bord et de
  la recherche les rendent ; `thesesynthesis_render_navlinks()` et
  `thesesynthesis_render_search_results()` gardent leur signature. Le titre, les
  mots-clés, le résumé et les réponses d'une thèse confidentielle n'entrent dans
  le contexte que pour qui connaît le sujet. Nouveau fragment `pdetails` (bloc
  dépliable) dans le partiel `mod_these/part`.
- Étape 4, pages restantes : barre de navigation (`navlinks`) ; tableau de bord
  (`status_counts`, `dossier_filter`) ; suivi des signatures
  (`external_signers`) ; modèles de documents (`doctemplate_list`,
  `doctemplate_variables`) ; questionnaire (`question_list`) ; annuaire du jury
  (`juror_list`, `single_button_block`) ; mes signatures (`saved_signature`) ;
  promotions suivies de `mod_thesesynthesis` (`links_form`). Composants
  génériques pour les autres pages : `message`, `link`, `fragment`,
  `bullet_list`, `table`. Les partiels `select` et `message` passent de
  `mod_thesesynthesis` à `mod_these`, qui les partage ; nouveaux fragments
  `pcode` et `pcheckbox`, cible des liens. `html_writer` ne sert plus qu'à la
  construction des formulaires `moodleform`.
- Les noms de personnes (étudiant, directeur, auteur d'une entrée d'historique)
  sont désormais échappés à l'affichage, comme le reste du texte.
- Tests : un test par composant (contexte exporté, rendu du gabarit), dont un
  contrôle sur le contexte entier sérialisé en JSON pour une thèse
  confidentielle.

## mod_these 0.11.3, mod_thesesynthesis 0.3.3 (2026102501) — première version publiée

- Publication automatique : sur `main`, une fois la CI verte, la version de
  `mod_these` (`release` de `version.php`) qui n'a pas encore d'étiquette est
  étiquetée `vX.Y.Z` et publiée sur GitHub, notes tirées de ce fichier et guide
  PDF joint.
- CI : paquet `lmodern` installé pour le guide ; JIT de PHP désactivé (son
  avertissement avec pcov bloquait l'installation de Moodle 4.5) ; test du
  formulaire du jury compatible PHP 8.0 (Moodle 4.1).

## mod_these 0.11.2, mod_thesesynthesis 0.3.2 (2026102500) — non publiée

- Base : les étapes de `db/upgrade.php` (développement) sont supprimées, le
  module n'ayant jamais été installé ; numéros de version recalés sur la date
  (2026102500).

- Planning : le rôle d'un membre du jury suit son nom après un tiret (« Dr DURAND
  Anne – Directeur de thèse (membre du jury) ») au lieu de parenthèses imbriquées.
- Recherche des thèses : texte d'introduction mis à jour (thèses confidentielles
  trouvables, sujet masqué).
- Machine à états explicite : `these_status_transitions()` déclare toutes les
  transitions de statut autorisées, et `these_set_status()` refuse les autres
  (test de cohérence avec les étapes et les renvois).
- CI : couverture des tests mesurée (pcov) sur la configuration Moodle 4.5 ;
  mesure locale : environ 41 % des lignes des fonctions et classes, hors pages.
- Guide : captures du planning (semaine, mois, PDF), d'une thèse confidentielle
  (dossier, recherche), de la demande d'embargo et d'un document scellé.

- Thèse confidentielle **trouvable** : dans « Suivi des thèses », un enseignant
  la trouve comme les autres thèses enregistrées (étudiant, directeur, jury,
  année, promotion), jamais par son sujet ou ses réponses ; elle figure aussi au
  planning des soutenances. Pour qui ne doit pas connaître le sujet, titre,
  titre anglais, mots-clés et résumé sont tous remplacés par « Thèse
  confidentielle » (dossier, recherche, export).
- Correction : les résultats de recherche de « Suivi des thèses » affichaient
  les mots-clés et le résumé d'une thèse confidentielle dont le titre était
  masqué. Le tableau des résultats passe par
  `thesesynthesis_render_search_results()`, testé.

## mod_these 0.11.1 (2026103104)

- Thèse confidentielle : la **bibliothèque** connaît désormais le sujet, comme
  la DEVE, le directeur et le jury (rôle de service, `mod/these:managelibrary`
  ou boîte de service) : titre, documents, recherche, planning, export et
  courriels. Le sujet reste masqué aux détenteurs de `mod/these:viewall` sans
  rôle de service, aux autres enseignants et aux autres étudiants.
- L'attestation de conformité imprime de nouveau le titre.
- Tests d'accès mis à jour : la bibliothèque parmi les profils autorisés, un
  détenteur de `mod/these:viewall` comme profil refusé qui voit le dossier.

## mod_these 0.11.0 (2026103103), mod_thesesynthesis 0.3.1 (2026102903)

### Nouveautés

- **Thèse confidentielle** : le sujet (titre, titre anglais, résumé, mots-clés)
  n'est connu que de l'étudiant, de la DEVE, du directeur et du jury. Pour les
  autres (bibliothèque, autres enseignants, `mod/these:viewall`), il est
  remplacé par « Thèse confidentielle » dans le dossier, les listes, le
  planning, la recherche, l'export et les courriels ; les fichiers et documents
  qui portent le titre leur sont masqués, sauf le manuscrit et l'attestation de
  conformité de la bibliothèque. La case existait mais ne masquait rien.
- **Demande d'embargo de diffusion de 5 ans** au dépôt de la soutenance, motivée ;
  la fin de l'embargo est calculée depuis la date de soutenance et reportée au
  contrat de diffusion, au dossier et à l'export. Elle remplace la date
  d'embargo libre ; une date déjà saisie vaut jusqu'au prochain enregistrement.

### Corrections

- Thèse confidentielle : le rapport du directeur restait téléchargeable par la
  bibliothèque et les détenteurs de `mod/these:viewall` (règle de zone vérifiée
  avant celle de la confidentialité). Relevé par les nouveaux tests d'accès.

### Tests

- `confidentiality_access_test` : pour chaque profil (étudiant, DEVE, directeur,
  membre du jury par adresse ou par l'annuaire ; bibliothèque, `viewall`, autre
  directeur, autre étudiant, ancien directeur, ancien membre du jury, utilisateur
  sans rôle), marqueurs dans chaque donnée qui révèle le sujet, vérifiés absents
  ou présents dans les droits, les fichiers de chaque zone, les documents
  signés, la page du dossier, les listes, la recherche, l'export du registre,
  le planning (page, tableur, PDF) et les courriels (comptes et boîtes de
  service). Recherche et liste du suivi multi-promotions.
- L'export du registre passe par `these_register_row()`, testable.

## mod_these 0.10.0 (2026103102), mod_thesesynthesis 0.3.0 (2026102902)

### Nouveautés

- **Planning des soutenances** (`planning.php`, dans chaque activité et dans
  « Suivi des thèses » pour toutes les promotions suivies) : soutenances de la
  semaine ou du mois, jour par jour, avec date proposée ou arrêtée, jury et lieu ;
  navigation d'une période à l'autre, accès à une date, semaines à venir qui ont
  des soutenances ; planning imprimable en PDF (paysage) et téléchargeable en
  tableur. Toutes les soutenances pour la DEVE et la bibliothèque, les siennes
  pour un directeur.
- Nouveau modèle de document « Planning des soutenances », modifiable par la
  DEVE comme les autres ; les modèles acceptent l'orientation paysage
  (`orientation="L"` dans la directive `@page`).

## mod_these 0.9.1 (2026103101), mod_thesesynthesis 0.2.1 (2026102901)

### Sécurité et intégrité

- Documents signés **scellés** : une fois toutes les signatures apposées, le PDF
  est conservé tel quel et son empreinte SHA-256 inscrite à l'historique. Une
  modification ultérieure d'un modèle, d'une image ou du contrat type ne change
  plus un document signé.
- Tant qu'un document attend des signatures, modifier son modèle invalide celles
  déjà apposées (auparavant, une modification de modèle n'annulait rien).
- Le contrat de diffusion n'est pas scellé sans contrat type lisible, et la DEVE
  en est avertie sur le dossier.
- Opérations sur un dossier atomiques : verrou, transaction, refus si le dossier
  a changé de statut entre-temps (double envoi, validations simultanées).
- Liens à jeton : seule l'empreinte est conservée (conversion des jetons
  existants à la mise à jour) ; lien de signature valable 60 jours ; accès du
  jury jusqu'à 60 jours après la soutenance, révoqué à la clôture ou à l'abandon.
- Un membre du jury ayant un compte consulte les documents depuis « Suivi des
  thèses » sans jeton.
- Les modèles de documents ne peuvent plus faire lire à TCPDF un fichier du
  serveur ou une adresse réseau (images limitées à celles du module).

### Corrections

- Page d'accès du jury : contexte défini avant le titre (avertissement Moodle sur
  un lien invalide).
- PHPDoc complétée (le contrôle échouait sans bloquer la CI).

### Code et processus

- `locallib.php` découpé en quinze fichiers par domaine (`mod/these/locallib/`).
- CI : Moodle 4.1 LTS (PHP 8.0, MariaDB) et 4.5 LTS (PHP 8.2, PostgreSQL),
  PHPDoc bloquant, tests Behat, guide PDF produit par la CI au lieu d'être
  versionné.
- `CONTRIBUTING.md`, modèle de demande de fusion, Dependabot pour les actions.

## mod_these 0.9.0 (2026103100), mod_thesesynthesis 0.2.0 (2026102900)

Première version complète, fusionnée par la demande #1 :

- circuit en deux phases (sujet, soutenance) avec renvois motivés, relances,
  historique, courriels personnalisables, import CSV, export du registre ;
- documents officiels reconstitués et signés dans le module, modèles modifiables
  par la DEVE, rôle Bibliothèque, agent DEVE signataire, signature enregistrée ;
- rapport du directeur rédigé dans le module, grille de conformité, conclusions,
  questionnaire préalable au jury, annuaire et éligibilité du jury ;
- numéro DEVE après le dépôt de la thèse, décisions du jury, circuit des
  corrections, ajournement, changement de sujet ;
- activité « Suivi des thèses » : suivi multi-promotions, paramètres partagés,
  recherche des thèses ;
- transfert des paramètres entre activités et par fichier ;
- confidentialité, sauvegarde et restauration.
