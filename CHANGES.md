# Évolutions

Format : une section par version publiée de `mod_these` (et de `mod_thesesynthesis`
quand il change), de la plus récente à la plus ancienne. Une version publiée est
étiquetée `vX.Y.Z` ; la CI joint alors le guide PDF à la publication GitHub.

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
