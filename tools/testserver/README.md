# Peupler un serveur de test

`seed.php` crée sur un Moodle de test, à partir d'un fichier de comptes :

- les comptes (mot de passe `Test1234!` par défaut), inscrits au cours avec leur rôle ;
- le cours (nom abrégé `THESES` par défaut) et une activité « Gestion des thèses » s'il n'y en
  a pas ;
- les agents DEVE signataires et les agents de la bibliothèque dans les paramètres du circuit ;
- l'annuaire du jury (enseignants qui ont un statut, extérieurs compris), avec leur matière.

Aucun dossier n'est créé, aucun réglage du site n'est modifié, aucun courriel n'est envoyé. Le
script peut être relancé : ce qui existe déjà est repris, sans doublon (le mot de passe d'un
compte existant n'est pas changé). Pour un jeu complet avec des dossiers à toutes les étapes,
voir `docs/guides/captures/seed.php`.

## Utilisation

```sh
php tools/testserver/seed.php /chemin/vers/moodle                       # comptes-exemple.csv
php tools/testserver/seed.php /chemin/vers/moodle mes-comptes.csv \
    --course=THESES --password='Test1234!' --institution='VetAgro Sup'
```

`mod_these` doit être installé sur le Moodle (copié dans `mod/`).

## Fichier de comptes

CSV séparé par des points-virgules, première ligne d'en-tête ; les lignes commençant par `#`
sont ignorées. Voir `comptes-exemple.csv`.

| Colonne | Contenu |
|---|---|
| `role` | `deve` (Enseignant), `agent` (agent DEVE signataire, Enseignant), `library` (agent de la bibliothèque, Enseignant non éditeur), `director` (Enseignant non éditeur), `student` (Étudiant), `juror` (enseignant extérieur : annuaire seulement, sans compte) |
| `username` | identifiant du compte (vide pour `juror`) |
| `firstname`, `lastname`, `email` | identité ; adresse par défaut `identifiant@example.org` |
| `password` | mot de passe du compte créé (vide : celui de `--password`) |
| `title`, `status` | pour l'annuaire du jury : `Dr` ou `Pr`, et statut (`pr`, `premerite`, `mcfhdr`, `mcf`, `mcfemerite`, `mcfstag`, `prstag`, `ir`). Sans statut, la personne n'est pas mise dans l'annuaire. |
| `institution`, `discipline` | établissement (par défaut celui de `--institution`) et matière enseignée |
