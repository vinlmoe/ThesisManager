# ThesisManager

[![CI](https://github.com/vinlmoe/ThesisManager/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/vinlmoe/ThesisManager/actions/workflows/ci.yml)
[![Moodle 4.0+](https://img.shields.io/badge/Moodle-4.0%2B-f98012?logo=moodle&logoColor=white)](https://moodle.org)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![Licence GPL v3+](https://img.shields.io/badge/licence-GPL%20v3%2B-blue)](https://www.gnu.org/licenses/gpl-3.0)

Module d'activité Moodle pour la gestion des thèses d'exercice vétérinaire, du
dépôt du sujet jusqu'au procès-verbal de soutenance. Il reprend l'architecture
et les conventions de [StageCompagnon](https://github.com/vinlmoe/StageCompagnon)
(`mod_stage`) : la DEVE sur le rôle « Enseignant », des courriels
personnalisables, des documents PDF générés, un historique par dossier, la
sauvegarde/restauration et l'API de confidentialité.

| Module | Rôle | Documentation |
|---|---|---|
| [`mod/these`](mod/these) | **Gestion des thèses** : sujet, soutenance, jury, documents de soutenance, procès-verbal, registre. | [`mod/these/INSTALL.md`](mod/these/INSTALL.md), [guide PDF](https://github.com/vinlmoe/ThesisManager/releases/latest) |
| [`mod/thesesynthesis`](mod/thesesynthesis) | **Suivi des thèses** : vue unique de plusieurs promotions pour les directeurs, la DEVE, la bibliothèque et les membres de jury ; annuaire du jury. | [`mod/thesesynthesis/INSTALL.md`](mod/thesesynthesis/INSTALL.md) |

## Le circuit

Il suit le document de travail « Circuit de dépôt du sujet et de la
soutenance ». Quatre acteurs interviennent :

```
Phase 1 — sujet
Étudiant ──fiche de sujet──▶ Directeur ──validation──▶ DEVE ──enregistrement──▶ Bibliothèque ──notice──▶ dossier actif
   ▲                            │                        │                                │
   └────────── renvoi avec commentaires (le dossier revient ensuite à l'étape qui l'a renvoyé)

        … dossier « dormant », parfois plusieurs années …

Phase 2 — soutenance
Étudiant ──questionnaire, manuscrit, date, jury──▶ Directeur ──rapport rédigé + validation──▶ DEVE ──dossier complet, n° d'enregistrement──▶
Bibliothèque ──conformité du manuscrit──▶ DEVE ──convocations, feuille de jury, PV à signer──▶ jury et université
                                          DEVE ──PV scanné, décisions du jury──▶ dossier clôturé
                                                      └── corrections demandées : Étudiant ──manuscrit corrigé──▶ Directeur ──▶ Bibliothèque ──▶ DEVE ──▶ dossier clôturé
```

- Chaque étape de validation peut **renvoyer** le dossier, motif obligatoire :
  à l'étudiant (à sa nouvelle soumission, le dossier revient directement à
  l'étape qui l'a renvoyé) ou, pour la DEVE, au directeur de thèse.
- Le **numéro d'enregistrement DEVE** est attribué après le dépôt de la thèse,
  à la validation du dossier de soutenance.
- Le directeur **rédige son rapport dans un formulaire** (brouillon, aperçu PDF,
  une page au plus), puis valide la soutenance (avis, grille de conformité,
  signature unique) ; le rapport n'est pas communiqué à l'étudiant.
- **Procès-verbal** : la DEVE dépose le PV scanné et reporte les décisions
  cochées par le jury : admis ou ajourné, mention (honorable, très honorable,
  très honorable avec félicitations du jury), proposition pour un prix de
  thèse, corrections demandées. Des corrections ouvrent un circuit : manuscrit
  corrigé déposé par l'étudiant, validé par le directeur, la bibliothèque et la
  DEVE avant la clôture. Une thèse **ajournée** revient en phase de soutenance :
  nouveau dépôt, nouveau circuit complet, PV d'ajournement archivé.
- **Agent DEVE signataire** : l'équipe de la DEVE vérifie et valide sans signer,
  puis l'agent responsable reçoit un courriel et signe depuis **Mes signatures**
  (tous les documents en attente, toutes promotions). Chaque utilisateur peut
  **enregistrer sa signature**, reprise d'office ensuite (membres du jury ayant
  un compte compris).
- **Changement de sujet** depuis un dossier actif : motif obligatoire, ancien
  sujet archivé (historique et PDF signé), ancien directeur, DEVE et
  bibliothèque prévenus, nouvelle validation complète de la phase 1.
- **Questionnaire préalable au jury** modifiable (réponses courtes ou longues,
  listes, QCM), rempli par l'étudiant avant le dépôt de la soutenance.
- Les **documents officiels** (E01 sujet, E02 agréments, E03 fiche
  signalétique, bordereau ABES, rapport, grille, conclusions) sont reconstitués
  dans la mise en forme de l'établissement et **signés dans le module** (tracé,
  empreinte du contenu, liens de signature pour les signataires extérieurs). Le
  contrat de diffusion reprend le PDF déposé par l'administrateur, entre une
  page de données et choix et une page de signatures.
- Chaque document est produit à partir d'un **modèle modifiable par la DEVE**
  (HTML et balises `{{...}}`, aperçu PDF, retour au modèle d'origine, images).
  Modèles, grille et questionnaire se définissent **dans l'activité de suivi**,
  qui les impose aux promotions suivies ; une activité non suivie garde les
  siens.
- **Transfert des paramètres** d'une activité à une autre, ou par fichier
  (paramètres de l'activité, courriels, grille de conformité, questionnaire,
  modèles et images des documents).
- **Annuaire du jury** commun à toutes les promotions (titre Dr/Pr, statut) :
  les formulaires de jury y puisent ; seuls les maîtres de conférences HDR, les
  professeurs et les émérites peuvent présider ; la DEVE valide l'éligibilité,
  puis les membres sont prévenus et reçoivent un lien d'accès aux documents.
- **Grille de conformité** modifiable par la DEVE ; **conclusions** limitées à
  une page avec les signatures.
- **Suivi des thèses** (`mod_thesesynthesis`) : les thèses de plusieurs
  promotions dans un seul cours, pour chaque enseignant ses jurys, et la
  **recherche des thèses** (texte, année, réponses au questionnaire) pour les
  enseignants, la DEVE et la bibliothèque.
- La bibliothèque dispose d'un rôle propre (« Bibliothèque (thèses) »), et les
  rôles DEVE et Bibliothèque se choisissent dans l'administration du site.
- La DEVE **génère** la convocation de l'étudiant et de chaque membre du jury,
  la feuille de jury et le procès-verbal à signer, puis les **transmet** par
  courriel (chaque membre reçoit sa convocation, l'université une archive de
  tous les documents). Les membres sans adresse sont signalés.
- Chaque acteur est prévenu quand un dossier l'attend ; une tâche quotidienne
  **relance** l'acteur attendu après un délai paramétrable (jamais pendant la
  phase dormante).
- **Thèse confidentielle** : sujet connu de l'étudiant, de la DEVE, du directeur
  et du jury seulement, masqué partout ailleurs. **Embargo de diffusion** de
  5 ans sur demande motivée, reporté au contrat de diffusion.
- Un tableau de bord par acteur (« À traiter », décompte par statut, recherche),
  l'export du registre (CSV, Excel, ODS) et l'**import CSV** des sujets déjà
  enregistrés à la mise en service.
- Un **planning des soutenances** par semaine ou par mois, dans chaque activité
  et, pour toutes les promotions, dans « Suivi des thèses » : navigation d'une
  période à l'autre, planning mensuel imprimable (PDF, modèle modifiable) et
  téléchargeable en tableur.

## Qualité

- Style : standard Moodle (`moodlehq/moodle-cs`), sans avertissement.
- Tests PHPUnit (82 + 5 tests) : circuit complet, corrections, rapport, renvois,
  droits, relances, documents générés, signatures et scellés, modèles,
  paramètres partagés, questionnaire, recherche, transfert des paramètres,
  annuaire et jury, liens à jeton, opérations concurrentes, planning, thèses
  confidentielles et embargo, grille, conclusions,
  suivi multi-promotions, import, confidentialité, sauvegarde/restauration.
- Tests Behat : création et consultation d'un dossier, liens invalides,
  planning des soutenances.
- CI GitHub Actions : phpcs, lint, validation du plugin, savepoints, PHPDoc,
  PHPUnit et Behat sur Moodle 4.1 LTS (PHP 8.0, MariaDB) et Moodle 4.5 LTS
  (PHP 8.2, PostgreSQL) ; production du guide PDF.
- Contribution : voir [`CONTRIBUTING.md`](CONTRIBUTING.md) ; évolutions dans
  [`CHANGES.md`](CHANGES.md).

## Documentation

- [`mod/these/INSTALL.md`](mod/these/INSTALL.md) : installation, rôles,
  paramétrage.
- Guide PDF : circuit, paramétrage et utilisation par acteur, avec captures
  d'écran. Il est joint à chaque
  [version publiée](https://github.com/vinlmoe/ThesisManager/releases/latest)
  et produit par la CI à chaque modification (artefact `guide-these`) depuis
  son source LaTeX [`docs/guides/guide-these.tex`](docs/guides/guide-these.tex).
