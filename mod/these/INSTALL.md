# Gestion des thèses (mod_these)

Module d'activité Moodle pour le circuit des thèses d'exercice vétérinaire :
dépôt et validation du sujet, puis dépôt, validation et soutenance.

- **Prérequis** : Moodle 4.0 ou supérieur, PHP 7.4+.
- **Dépendances** : aucune à installer. Les PDF sont produits avec TCPDF (fourni
  par Moodle) et FPDI 2.6 (fourni dans `thirdparty/`, comme pour `mod_stage`).

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

Les rôles des services se choisissent dans *Administration du site > Plugins >
Modules d'activité > Gestion des thèses* :

- **Rôle DEVE** (« Enseignant » par défaut, comme pour `mod_stage`) ;
- **Rôle Bibliothèque** : le module crée à l'installation le rôle « Bibliothèque
  (thèses) » (`theselibrary`), attribuable au niveau système, catégorie ou
  cours, avec `mod/these:view`, `mod/these:managelibrary`, `mod/these:viewall`
  et `moodle/course:view`. Il suffit de l'attribuer aux bibliothécaires (au
  niveau du site ou du cours) ; ils ne sont jamais proposés comme directeurs.

Un utilisateur détient les droits d'un service s'il a la capacité
correspondante **ou** le rôle choisi dans le contexte de l'activité ou un
contexte parent.

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

## 3 bis. Documents officiels et signatures électroniques

Réglages du site, rubrique *Documents* : **modèle PDF du contrat de diffusion**
(E04 en vigueur), établissement, lien HeTOP, lieu du visa, direction des études
(fiche d'agréments), puis nom, qualité et courriel des signataires extérieurs
(direction générale, président de la CCEM, université).

Les formulaires E01 (sujet), E02 (agréments), E03 (fiche signalétique), le
bordereau ABES, le rapport, la grille de conformité et les conclusions sont
**reconstitués dans la mise en forme des documents de l'établissement** (logos,
cadres, libellés, cases à cocher) et remplis avec les données du dossier. Le
contrat de diffusion est assemblé ainsi : page de données et choix, modèle
déposé par l'administrateur tel quel, page de signatures.

Chaque document est produit à partir d'un **modèle modifiable par la DEVE dans
l'activité** (bouton *Modèles des documents* de la barre de navigation, comme les
courriels). Chaque activité a ses propres modèles, sauvegardés et restaurés avec
elle. Un modèle est du HTML (interprété par TCPDF) avec des balises Mustache
(`{{student.lastname}}`, `{{{sig.director}}}`, `{{#jury}}…{{/jury}}`) ; la page
de modification liste toutes les variables, propose un aperçu PDF avec des
données fictives et refuse un modèle qui ne produit pas de document. Un modèle
modifié peut être ramené à son origine (`doctemplates/<document>.html`). Les
logos fournis (`doctemplates/images/`) se remplacent en déposant, dans la même
page, une image du même nom (*Images des modèles*).

Chaque signature (tracé, nom, date, empreinte SHA-256 du contenu signé) est
annulée si le contenu signé change ; modifier un modèle ne l'annule pas. Les
signataires extérieurs reçoivent un lien personnel par courriel ; la DEVE suit
les signatures (*Suivi des signatures*), relance ou fait signer sur place.

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
