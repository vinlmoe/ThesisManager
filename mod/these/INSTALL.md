# Gestion des thèses (mod_these)

Module d'activité Moodle pour le circuit des thèses d'exercice vétérinaire :
dépôt et validation du sujet, puis dépôt, validation et soutenance.

- **Prérequis** : Moodle 4.0 ou supérieur, PHP 7.4+.
- **Dépendances** : aucune (les PDF sont produits avec TCPDF, fourni par Moodle).

## 1. Installation

Copier le dossier `mod/these` dans `<moodle>/mod/these`, puis :

```bash
php admin/cli/upgrade.php
```

Ou se connecter en administrateur : Moodle détecte le plugin et propose de
l'installer.

## 2. Rôles et capacités

| Acteur | Rôle Moodle | Capacités |
|---|---|---|
| Étudiant | Étudiant (`student`) | `mod/these:submit` — proposer son sujet, déposer sa soutenance |
| Directeur de thèse | Enseignant non éditeur (`teacher`) | `mod/these:direct` — être choisi comme directeur, valider les dossiers qu'il dirige |
| DEVE | Enseignant (`editingteacher`) | `mod/these:managedeve`, `mod/these:viewall` |
| Bibliothèque | Rôle dédié « Bibliothèque » | `mod/these:managelibrary`, `mod/these:viewall` |
| Administration | Manager | Tous les droits |

Comme pour `mod_stage`, la DEVE utilise le rôle standard « Enseignant ».

**Rôle Bibliothèque** : *Administration du site > Utilisateurs > Permissions >
Définition des rôles > Ajouter un rôle*, archétype « Enseignant non éditeur »,
contexte d'attribution « Cours ». Autoriser `mod/these:managelibrary` et
`mod/these:viewall`, **interdire** `mod/these:direct` (sinon les bibliothécaires
seraient proposés comme directeurs de thèse). Inscrire ensuite les
bibliothécaires au cours avec ce rôle.

Un directeur de thèse ne voit que les dossiers qu'il dirige. Seuls les
enseignants **inscrits au cours** avec `mod/these:direct` sont proposés à
l'étudiant comme directeurs.

## 3. Mise en place

1. Créer un cours permanent (par exemple « Thèses vétérinaires ») : un dossier
   dure plusieurs années, au-delà d'une promotion. Y inscrire les étudiants
   en thèse, les directeurs potentiels, la DEVE et la bibliothèque.
2. Ajouter l'activité « Gestion des thèses » et renseigner :
   - **Établissement** : nom, adresse, ville, signataire et sa qualité (en-tête
     et signature des documents générés), lieu de soutenance par défaut ;
   - **Membres votants minimum du jury** (3 par défaut, président compris,
     invités exclus) ;
   - **Adresses de service** DEVE et bibliothèque (facultatives : à défaut,
     chaque utilisateur inscrit détenant la capacité est notifié) et adresse de
     l'**université** (destinataire des documents de soutenance) ;
   - **Délai de relance** en jours (14 par défaut, 0 pour désactiver).
3. Reprendre les sujets déjà enregistrés : *Importer des dossiers* (CSV,
   colonnes `student`, `title`, `director`, `devenumber`, `libraryref`,
   `titleen`, `summary`, `keywords`, `codirector` ; étudiant et directeur
   désignés par identifiant, courriel ou numéro d'identification). Les dossiers
   importés sont créés « actifs ». Une simulation est proposée par défaut.
4. Personnaliser au besoin les courriels (*Courriels*).

La réinitialisation du cours ne supprime les dossiers que si l'option est
cochée explicitement (elle ne l'est pas par défaut).

## 4. Statuts

| Valeur | Statut | En attente de |
|---|---|---|
| 0 | Sujet en préparation | Étudiant |
| 10 | Sujet soumis au directeur | Directeur |
| 15 | Sujet renvoyé à l'étudiant | Étudiant |
| 20 | Sujet validé par le directeur | DEVE |
| 30 | Sujet enregistré par la DEVE | Bibliothèque |
| 40 | Dossier actif (dormant) | Étudiant |
| 50 | Soutenance proposée au directeur | Directeur |
| 55 | Dossier de soutenance renvoyé | Étudiant |
| 60 | Soutenance validée par le directeur | DEVE |
| 70 | Dossier validé par la DEVE | Bibliothèque |
| 80 | Manuscrit conforme | DEVE (documents) |
| 90 | Soutenance programmée | DEVE (procès-verbal) |
| 100 | Dossier clôturé | — |
| -1 | Dossier abandonné | — |

## 5. Tâche planifiée

`mod_these\task\send_reminders`, chaque jour à 7 h 15 : relance l'acteur
attendu quand un dossier attend à la même étape depuis le délai de relance de
son activité, puis à chaque nouveau délai écoulé. Brouillons et dossiers actifs
ne sont jamais relancés.

## 6. Développement

```bash
phpcs --standard=phpcs.xml                         # depuis la racine du dépôt
vendor/bin/phpunit --testsuite mod_these_testsuite # depuis la racine de Moodle
```
