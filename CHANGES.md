# Évolutions

Format : une section par version publiée de `mod_these` (et de `mod_thesesynthesis`
quand il change), de la plus récente à la plus ancienne. Une version publiée est
étiquetée `vX.Y.Z` ; la CI joint alors le guide PDF à la publication GitHub.

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
