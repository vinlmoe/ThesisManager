# Suivi des thèses (mod_thesesynthesis)

Module d'activité complémentaire à `mod_these` : une vue unique des thèses de
plusieurs promotions, chacune gérée dans sa propre activité « Gestion des
thèses », sans naviguer d'un cours à l'autre. Il reprend le principe du suivi
des stages (`mod_stagesynthesis`).

- **Prérequis** : `mod_these` 0.7.0 ou plus (dépendance déclarée dans
  `version.php`).

## Installation

Copier le dossier `mod/thesesynthesis` dans `<moodle>/mod/thesesynthesis`, puis :

```bash
php admin/cli/upgrade.php
```

## Mise en place

1. Créer un cours dédié (par exemple « Suivi des thèses »), distinct des cours
   de promotion.
2. Y inscrire les directeurs de thèse et les enseignants membres de jury
   (Enseignant non éditeur), la DEVE (Enseignant) et la bibliothèque.
3. Ajouter une activité **Suivi des thèses**.
4. Depuis l'activité, **Promotions suivies** : cocher les activités « Gestion
   des thèses » (une par promotion) à suivre. Seules sont proposées celles où
   vous voyez tous les dossiers (DEVE, bibliothèque).
5. **Modèles des documents**, **Grille de conformité**, **Questionnaire
   préalable au jury** : définis ici, ils s'appliquent à toutes les promotions
   suivies (les pages correspondantes des activités suivies renvoient ici).
6. **Annuaire du jury** : y saisir (ou importer en CSV) les enseignants pouvant
   diriger une thèse ou siéger au jury, avec leur titre et leur statut.

## Ce que voit chacun

- **À traiter** : les dossiers qui attendent l'utilisateur, toutes promotions
  confondues (directeur pour ses thèses ; DEVE et bibliothèque selon leurs
  droits dans chaque promotion).
- **Mes jurys** : les jurys où l'utilisateur siège (son adresse, ou celle de sa
  fiche dans l'annuaire), avec la date, le lieu et l'accès aux documents dès
  que la DEVE a validé le jury.
- **Dossiers suivis** : ses thèses dirigées, ou tous les dossiers pour la DEVE
  et la bibliothèque, filtrables par promotion, statut et recherche.
- **Rechercher des thèses** : texte libre (titre, résumé, mots-clés, noms,
  jury, réponses au questionnaire), promotion, année de soutenance, statut et
  réponses au questionnaire. La DEVE et la bibliothèque trouvent tous les
  dossiers des promotions où elles ont leurs droits ; un enseignant, les thèses
  enregistrées et non abandonnées, et celles qu'il dirige. Une thèse
  confidentielle se trouve comme les autres (étudiant, directeur, jury, année,
  promotion), mais, pour qui ne doit pas connaître son sujet, ni son sujet ni
  ses réponses n'entrent dans la recherche, et son titre, ses mots-clés et son
  résumé sont affichés « Thèse confidentielle ».
- **Planning des soutenances** : les soutenances de la semaine ou du mois de
  toutes les promotions suivies, avec la promotion de chaque étudiant ;
  navigation d'une semaine ou d'un mois à l'autre, accès à une date, semaines à
  venir qui ont des soutenances, planning imprimable (PDF, modèle « Planning des
  soutenances » des paramètres partagés) et téléchargeable en tableur. Toutes
  les soutenances pour la DEVE et la bibliothèque, celles des thèses qu'il
  dirige pour un directeur.

L'activité ne stocke ni droit ni donnée de thèse (seulement les paramètres
partagés : modèles, images, grille, questionnaire) : tout est relu à l'affichage
dans les activités liées, et seulement là où l'utilisateur a toujours ses
droits à la source.

## Capacités

| Capacité | Enseignant non éditeur | Enseignant éditeur | Manager |
|---|:-:|:-:|:-:|
| `mod/thesesynthesis:view` | ✅ | ✅ | ✅ |
| `mod/thesesynthesis:addinstance` | — | ✅ | ✅ |
| `mod/thesesynthesis:managelinks` — choisir les promotions suivies | — | ✅ | ✅ |
| `mod/thesesynthesis:managejurors` — tenir l'annuaire du jury | — | ✅ | ✅ |
| `mod/thesesynthesis:managesettings` — modèles, grille, questionnaire partagés | — | ✅ | ✅ |
| `mod/thesesynthesis:search` — rechercher des thèses | ✅ | ✅ | ✅ |
