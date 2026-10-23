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
| [`mod/these`](mod/these) | **Gestion des thèses** : sujet, soutenance, jury, documents de soutenance, procès-verbal, registre. | [`mod/these/INSTALL.md`](mod/these/INSTALL.md), [guide PDF](docs/guides/guide-these.pdf) |

## Le circuit

Il suit le document de travail « Circuit de dépôt du sujet et de la
soutenance ». Quatre acteurs interviennent :

```
Phase 1 — sujet
Étudiant ──fiche de sujet──▶ Directeur ──validation──▶ DEVE ──n° d'enregistrement──▶ Bibliothèque ──notice──▶ dossier actif
   ▲                            │                        │                                │
   └────────── renvoi avec commentaires (le dossier revient ensuite à l'étape qui l'a renvoyé)

        … dossier « dormant », parfois plusieurs années …

Phase 2 — soutenance
Étudiant ──manuscrit, date, jury──▶ Directeur ──validation + rapport (une seule étape)──▶ DEVE ──dossier complet──▶
Bibliothèque ──conformité du manuscrit──▶ DEVE ──convocations, feuille de jury, PV à signer──▶ jury et université
                                          DEVE ──dépôt du procès-verbal──▶ dossier clôturé
```

- Chaque étape de validation peut **renvoyer** le dossier, motif obligatoire :
  à l'étudiant (à sa nouvelle soumission, le dossier revient directement à
  l'étape qui l'a renvoyé) ou, pour la DEVE, au directeur de thèse.
- Le directeur **valide la soutenance et dépose son rapport en une seule
  étape** ; le rapport n'est pas communiqué à l'étudiant.
- La DEVE **génère** la convocation de l'étudiant et de chaque membre du jury,
  la feuille de jury et le procès-verbal à signer, puis les **transmet** par
  courriel (chaque membre reçoit sa convocation, l'université une archive de
  tous les documents). Les membres sans adresse sont signalés.
- Chaque acteur est prévenu quand un dossier l'attend ; une tâche quotidienne
  **relance** l'acteur attendu après un délai paramétrable (jamais pendant la
  phase dormante).
- Un tableau de bord par acteur (« À traiter », décompte par statut, recherche),
  l'export du registre (CSV, Excel, ODS) et l'**import CSV** des sujets déjà
  enregistrés à la mise en service.

## Qualité

- Style : standard Moodle (`moodlehq/moodle-cs`), sans avertissement.
- Tests PHPUnit (35 tests) : circuit complet, renvois, droits, relances,
  documents générés, import, confidentialité, sauvegarde/restauration.
- CI GitHub Actions : phpcs, lint, validation du plugin, savepoints, PHPDoc,
  PHPUnit sur Moodle 4.5 / PostgreSQL.

## Documentation

- [`mod/these/INSTALL.md`](mod/these/INSTALL.md) : installation, rôles,
  paramétrage.
- [`docs/guides/guide-these.pdf`](docs/guides/guide-these.pdf) (source LaTeX
  `guide-these.tex`) : circuit, paramétrage et utilisation par acteur, avec
  captures d'écran.
