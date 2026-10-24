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

Chaque document est produit à partir d'un **modèle modifiable par la DEVE**
(bouton *Modèles des documents* de la barre de navigation, comme les courriels).
Si une activité *Suivi des thèses* suit l'activité, les modèles et leurs images,
la grille de conformité et le questionnaire préalable au jury se définissent
dans l'activité de suivi et valent pour toutes les promotions suivies (la plus
ancienne activité de suivi l'emporte) ; sinon, chaque activité a les siens,
sauvegardés et restaurés avec elle. Un modèle est du HTML (interprété par TCPDF) avec des balises Mustache
(`{{student.lastname}}`, `{{{sig.director}}}`, `{{#jury}}…{{/jury}}`) ; la page
de modification liste toutes les variables, propose un aperçu PDF avec des
données fictives et refuse un modèle qui ne produit pas de document. Un modèle
modifié peut être ramené à son origine (`doctemplates/<document>.html`). Les
logos fournis (`doctemplates/images/`) se remplacent en déposant, dans la même
page, une image du même nom (*Images des modèles*).

Chaque signature (tracé, nom, date, empreinte SHA-256 du contenu signé et de sa
mise en forme) est annulée si le contenu signé change, ou si le modèle du
document change avant que toutes les signatures soient apposées. Un document
entièrement signé est **scellé** : son PDF est conservé tel quel, l'empreinte
SHA-256 de ses octets est inscrite à l'historique du dossier, et une
modification ultérieure d'un modèle, d'une image ou du contrat type ne le change
plus. Le contrat de diffusion n'est scellé qu'avec un contrat type lisible ; à
défaut, le dossier affiche un avertissement à la DEVE.

Les signataires extérieurs reçoivent un lien personnel par courriel, valable
60 jours ; la DEVE suit les signatures (*Suivi des signatures*), relance (un
nouveau lien remplace le précédent) ou fait signer sur place. Le lien d'accès du
jury vaut jusqu'à 60 jours après la soutenance et cesse à la clôture ou à
l'abandon du dossier ; un membre du jury qui a un compte Moodle à la même adresse
consulte aussi les documents depuis *Suivi des thèses*, sans lien. Seule
l'empreinte des jetons de ces liens est conservée en base.

**Valeur des signatures.** Le tracé apposé dans le module est une signature
électronique *simple* au sens du règlement eIDAS : elle identifie le signataire
(compte Moodle ou lien personnel reçu à son adresse), date la signature et la lie
au contenu signé par son empreinte, mais ne repose pas sur un certificat
qualifié. Il revient à l'établissement de décider pour quels documents elle
suffit (procès-verbal, contrat de diffusion) ; les documents qui exigent une
signature avancée ou qualifiée doivent être signés hors du module et déposés
signés.

## 3 bis (suite). Grille de conformité, conclusions

- **Grille de conformité** (DEVE, *Grille de conformité*) : les éléments de la
  grille remplie par le directeur se modifient dans chaque activité (libellé,
  rubrique, élément facultatif, ajout, suppression). La grille en PDF suit les
  éléments en vigueur ; elle n'est produite qu'à partir du formulaire rempli
  par le directeur.
- **Conclusions** : elles doivent tenir sur une page avec les signatures
  (2 200 caractères au plus, et contrôle de la mise en page réelle au dépôt).

## 3 bis (fin). Annuaire du jury, éligibilité et accès du jury

- **Annuaire du jury** (DEVE, *Annuaire du jury*, ou depuis l'activité *Suivi
  des thèses*) : enseignants pouvant diriger une thèse ou siéger au jury, communs
  à toutes les activités, avec leur titre (Dr ou Pr) et leur statut :
  ingénieur de recherche, maître de conférences (stagiaire, HDR, émérite),
  professeur (stagiaire, émérite). Tous peuvent diriger ou être membre ; seuls
  les maîtres de conférences HDR, les professeurs et les émérites peuvent
  présider. Import CSV possible (`title, lastname, firstname, email, status,
  institution`).
- **Jury** : chaque ligne choisit un enseignant de l'annuaire (titre, statut,
  établissement et adresse repris) ou décrit un membre extérieur ou un invité.
  Le formulaire refuse un président inéligible et un membre extérieur votant
  sans statut.
- **Éligibilité** : à la validation du dossier de soutenance, la DEVE coche
  l'éligibilité de chaque membre votant. Les membres sont alors prévenus par
  courriel (modèle *jurydesignation*, personnalisable) avec un lien personnel
  vers les documents du dossier (manuscrit, rapport, grille, conclusions, fiche
  signalétique), sans compte Moodle. La DEVE peut renvoyer cet accès depuis le
  dossier.
- **Suivi multi-promotions** : voir le module `mod_thesesynthesis`.

## 3 bis (compléments). Thèse confidentielle, embargo

- **Thèse confidentielle** (case de la fiche de sujet) : le sujet (titre, titre
  anglais, résumé, mots-clés) n'est connu que de l'étudiant, de la DEVE (rôle ou
  `mod/these:managedeve`), de la bibliothèque (rôle ou
  `mod/these:managelibrary`, et sa boîte de service), du directeur de thèse et
  des membres du jury (reconnus à leur adresse ou à celle de leur fiche de
  l'annuaire). Pour tous les autres, dont les détenteurs de `mod/these:viewall`
  sans rôle de service, il est remplacé par « Thèse confidentielle » dans le
  dossier, les listes, le planning, la recherche (y compris dans *Suivi des
  thèses*), l'export du registre et les courriels ; le questionnaire, l'ancien
  titre d'un changement de sujet et les fichiers ou documents qui portent le
  titre leur sont masqués, sauf le manuscrit et le manuscrit corrigé. La
  variable `{{thesis.publictitle}}` des modèles donne « Thèse confidentielle »
  pour un document destiné à d'autres personnes. Les signataires extérieurs
  (direction générale, CCEM, université) voient les documents qu'ils signent.
- **Embargo de diffusion** : au dépôt de la soutenance, l'étudiant peut demander
  un embargo de 5 ans (`THESE_EMBARGO_YEARS`), motif obligatoire. La fin de
  l'embargo est calculée à partir de la date de soutenance (arrêtée, à défaut
  proposée) et reportée au contrat de diffusion, que le directeur vise ; elle
  figure au dossier (rubrique *Diffusion*) et dans l'export du registre. Le motif
  n'est montré qu'à qui connaît le sujet.

## 3 bis (autres compléments). Questionnaire, rapport, procès-verbal, corrections

- **Questionnaire préalable au jury** (DEVE, *Questionnaire préalable au jury*) :
  réponse courte, réponse longue, liste, QCM ; questions obligatoires exigées au
  dépôt de la soutenance. Réponses visibles au dossier et sur la page du jury,
  utilisées par la recherche des thèses (`mod_thesesynthesis`).
- **Numéro d'enregistrement DEVE** : attribué à la validation du dossier de
  soutenance (après le dépôt de la thèse), et non plus à l'enregistrement du sujet.
- **Rapport du directeur** : rédigé sur sa propre page (*Rédiger le rapport*),
  enregistrable en plusieurs fois, aperçu PDF, une page au plus ; la validation
  de la soutenance exige un rapport rédigé avec avis favorable.
- **Procès-verbal** : PV signé scanné (PDF ou images) et décisions du jury
  (admis/ajourné, mention honorable, très honorable ou très honorable avec
  félicitations du jury, proposition pour un prix de thèse, corrections
  demandées). Avec corrections, l'étudiant dépose son manuscrit corrigé
  (*Déposer les corrections*), validé par le directeur, la bibliothèque puis la
  DEVE, qui clôt le dossier.

- **Ajournement** : le dossier revient en *Dossier actif* (sujet, numéro et
  rapport conservés) ; le PV d'ajournement est archivé, les documents générés et
  les signatures de la soutenance sont supprimés, les accès du jury révoqués ;
  l'étudiant dépose un nouveau dossier de soutenance.
- **Agent DEVE signataire** (réglage du site *Agent DEVE signataire* :
  identifiant ou adresse du compte) : l'équipe DEVE valide sans signer ; l'agent
  reçoit un courriel et signe le visa du sujet et l'agrément administratif depuis
  *Mes signatures* (`/mod/these/mysignatures.php`). Sans agent, la personne qui
  valide signe.
- **Signature enregistrée** : tout utilisateur connecté peut enregistrer sa
  signature (fichier dans son contexte utilisateur, couvert par la confidentialité) ;
  elle est reprise d'office aux signatures suivantes. Un signataire invité
  connecté avec le compte de l'adresse invitée (président du jury, par exemple)
  peut l'utiliser et retrouve ses documents dans *Mes signatures*.

- **Changement de sujet** (*Changer de sujet de thèse*, étudiant, dossier
  actif) : motif obligatoire ; l'ancien sujet est archivé (table
  `these_subjecthistory`, PDF du dépôt de sujet signé dans la zone
  `subjectarchive`) ; l'ancien directeur, la DEVE et la bibliothèque reçoivent
  le courriel *subjectchanged* ; le nouveau sujet repasse la phase 1.

## 3 ter. Planning des soutenances

*Planning des soutenances* (`/mod/these/planning.php`, barre de navigation de
l'activité ; DEVE, bibliothèque et `mod/these:viewall` pour toutes les
soutenances, directeur pour les siennes) présente les soutenances de la semaine
ou du mois, jour par jour, avec la date proposée ou arrêtée, le jury et le lieu.
Une thèse y figure dès le dépôt du dossier de soutenance ; un dossier abandonné
ou une thèse ajournée en sort. On passe d'une période à l'autre, on va à une
date, et la vue semaine liste les semaines à venir qui ont des soutenances. Le
planning de la période s'imprime (PDF en paysage, modèle de document
*Planning des soutenances*, modifiable comme les autres) ou se télécharge en
tableur (CSV, Excel, ODS). L'activité *Suivi des thèses* offre la même page pour
toutes les promotions suivies.

## 3 quater. Transfert des paramètres

*Transfert des paramètres* (DEVE, barre de navigation de l'activité) reprend les
paramètres d'une autre activité dont on est la DEVE, ou d'un fichier de
paramètres (JSON) exporté depuis la même page : paramètres de l'activité
(établissement, signataire, lieu, jury minimum, adresses de service, relances),
courriels personnalisés, grille de conformité, questionnaire, modèles et images des documents. Chaque groupe coché
remplace celui de l'activité ; les dossiers ne sont jamais transférés. Pratique
à l'ouverture d'un nouveau cours, ou pour passer d'une plateforme de test à la
production.

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
| 92 | Corrections demandées par le jury | Étudiant |
| 94 | Corrections déposées | Directeur |
| 96 | Corrections validées par le directeur | Bibliothèque |
| 98 | Corrections validées par la bibliothèque | DEVE (clôture) |
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
