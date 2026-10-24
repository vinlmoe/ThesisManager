# Suivi des thèses (mod_thesesynthesis)

Module d'activité complémentaire à `mod_these` : une vue unique des thèses de
plusieurs promotions, chacune gérée dans sa propre activité « Gestion des
thèses », sans naviguer d'un cours à l'autre. Il reprend le principe du suivi
des stages (`mod_stagesynthesis`).

- **Prérequis** : `mod_these` 0.6.0 ou plus (dépendance déclarée dans
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
5. **Annuaire du jury** : y saisir (ou importer en CSV) les enseignants pouvant
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

L'activité ne stocke ni droit ni donnée de thèse : tout est relu à l'affichage
dans les activités liées, et seulement là où l'utilisateur a toujours ses
droits à la source.

## Capacités

| Capacité | Enseignant non éditeur | Enseignant éditeur | Manager |
|---|:-:|:-:|:-:|
| `mod/thesesynthesis:view` | ✅ | ✅ | ✅ |
| `mod/thesesynthesis:addinstance` | — | ✅ | ✅ |
| `mod/thesesynthesis:managelinks` — choisir les promotions suivies | — | ✅ | ✅ |
| `mod/thesesynthesis:managejurors` — tenir l'annuaire du jury | — | ✅ | ✅ |
