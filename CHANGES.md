# Évolutions

Format : une section par version publiée de `mod_these` (et de `mod_thesesynthesis`
quand il change), de la plus récente à la plus ancienne. Une version publiée est
étiquetée `vX.Y.Z` : la CI crée l'étiquette et la publication GitHub, avec le
guide PDF, quand une nouvelle valeur de `release` arrive sur `main`.

**Première version publiée : `v0.11.3`** (`mod_these` et `mod_thesesynthesis`
2026102501). Le module n'ayant jamais été installé auparavant, les versions de
développement (0.9.0 à 0.11.2) sont regroupées dans celle-ci : une seule
structure de base (`db/install.xml`), sans étape de mise à jour, et des numéros
de version recalés sur la date. Les numéros entre parenthèses des sections de
développement ne correspondent à aucune version installable ; 0.11.2 n'a jamais
été étiquetée.

## mod_these 0.19.0 (2026102708) — direction générale signée par délégation des agents DEVE, relance des agents, agents choisis parmi les inscrits, plus de rôles des services, matière des enseignants

- La direction générale ne signe jamais elle-même : ses documents (fiche
  signalétique, contrat de diffusion, conclusions) sont toujours signés par
  délégation, par les agents DEVE signataires. Ils leur sont demandés avec
  l'agrément administratif, à la validation du dossier de soutenance, dans un
  seul courriel « Documents à signer », et apparaissent dans « Mes
  signatures » avec « (Direction générale, par délégation) » ; la signature
  porte le nom de l'agent suivi de « (par délégation) ». Plus de lien ni
  d'adresse pour la direction générale (paramètre « Direction générale
  (courriel) » retiré ; nom et qualité restent imprimés). Sans agent désigné,
  ses documents se signent sur place ou par scan, toujours par délégation :
  la case « Signature par délégation » disparaît de la signature sur place et
  des scans. Les agents DEVE signataires deviennent un réglage attendu de
  « Réglages à compléter ».
- Relance des agents DEVE : bouton « Relancer » (au lieu de « Envoyer le
  lien ») sur la ligne des agents du suivi des signatures, message « Relance
  envoyée aux agents DEVE. ». La relance, comme la relance automatique et
  l'action groupée « Relancer les signataires », porte sur leurs signatures et
  sur celles de la direction générale.
- Agents DEVE signataires et agents de la bibliothèque choisis dans une liste
  (choix multiple avec recherche) au lieu d'être saisis : membres de la DEVE
  inscrits aux cours des activités de la portée (droit ou rôle de la DEVE)
  pour les premiers ; personnels inscrits (hors étudiants) et détenteurs du
  rôle de la bibliothèque pour les seconds. Un agent déjà désigné reste
  proposé. Le paramètre garde son format (identifiants, un par ligne) : les
  réglages existants, le transfert et la sauvegarde ne changent pas ; un
  identifiant qui est une adresse de courriel désigne désormais bien son
  compte (`\mod_these\local\scopesettings::candidate_users()`).
- Plus de rôles des services ni de rôle « Bibliothèque (thèses) » : la DEVE est
  reconnue à la capacité `mod/these:managedeve` (rôle « Enseignant »), la
  bibliothèque à la liste de ses agents (ou à `mod/these:managelibrary`).
  Section « Rôles des services » retirée des paramètres du circuit ; le rôle
  n'est plus créé à l'installation. Mise à jour 2026102707 : les détenteurs du
  rôle choisi pour la bibliothèque sont ajoutés aux agents de la bibliothèque
  (droits et courriels conservés), puis les réglages `deverole` et
  `libraryrole` sont supprimés ; le rôle existant n'est pas supprimé. Un site
  qui avait choisi pour la DEVE un rôle sans la capacité `managedeve` doit la
  lui accorder.
- Annuaire du jury : matière enseignée (champ `discipline` de `these_juror`,
  mise à jour 2026102708), saisie dans la fiche de l'enseignant, affichée dans
  l'annuaire, importable (colonne CSV facultative `discipline`, la matière
  connue étant gardée sans elle) et déclarée pour la vie privée. Liste du
  corps enseignant en PDF : chaque section sur trois colonnes aussi égales que
  possible (première colonne d'abord), la matière sous chaque nom. Une liste dont le modèle a été
  personnalisé garde sa mise en page : la rétablir depuis les modèles pour en
  profiter (variable `rows` de chaque groupe, lignes de `cells` avec leur
  `member`, et `discipline` de chaque enseignant).

## mod_these 0.18.0 (2026102703) — directeur non votant, liste du corps enseignant, aides pour la DEVE

- Validation de l'A6 saisie par la DEVE, plus par l'étudiant (le dépôt de la
  soutenance la rappelle) : à la validation du dossier de soutenance, dans
  « Modifier un dossier », en lot depuis le tableau de bord (action
  « Enregistrer la validation de l'A6 », date et école) ou par import d'un
  fichier Excel, OpenDocument ou CSV (`a6import.php`, colonnes etudiant, date,
  ecole, avec simulation). Elle est liée à l'étudiant, pas au dossier :
  nouvelle table `these_a6` (une ligne par étudiant, commune à toutes les
  activités), enregistrable avant la création du dossier, valable pour tous
  ses dossiers ; reprise par l'agrément administratif et la fiche
  signalétique, inscrite à l'historique de ses dossiers, déclarée pour la vie
  privée (export, suppression). Mise à jour 2026102703 : création de la table,
  reprise des saisies déjà faites dans l'agrément ; à défaut de saisie, la
  déclaration antérieure de l'étudiant (`\mod_these\local\a6`).
- Relance automatique des signatures : la tâche quotidienne redemande les
  signatures en attente depuis plus que le délai de relance de l'activité
  (nouveau lien aux signataires sans compte qui ont une adresse, rappel aux
  agents DEVE) ; le délai repart à zéro à chaque relance.
- Récapitulatif hebdomadaire de la DEVE (tâche du lundi, 7 h 30) : dossiers
  qui l'attendent, signatures en attente depuis plus d'une semaine,
  soutenances des sept prochains jours, conflits de jury ou de salle des
  quinze prochains jours ; courriel personnalisable « Récapitulatif
  hebdomadaire de la DEVE », désactivable dans les paramètres du circuit
  (`weeklydigest`). Rien n'est envoyé sans rien à signaler.
- Calendrier Moodle : un événement de cours par soutenance dont la date est
  arrêtée (étudiant, date, durée, salle, jury ; titre sauf thèse
  confidentielle), déplacé avec la soutenance, supprimé à l'abandon ou à
  l'ajournement (`\mod_these\local\calendar`, synchronisé à chaque
  changement du dossier et chaque jour).
- « Modifier un dossier » allégé : sujet et enregistrements repliés, jury en
  lecture comme à la validation (31 champs visibles ramenés à 8).
- Téléphone : le tableau du jury présenté en lecture défile dans sa colonne
  au lieu d'élargir la page de 300 pixels.

- Jury : le directeur de thèse siège sans voter (rôle « Directeur de thèse
  (non votant) »). Il faut au moins deux membres votants, président compris :
  minimum par défaut de l'activité ramené de 3 à 2, et activités restées à 3
  passées à 2 à la mise à jour. Le directeur n'a pas d'éligibilité à valider
  et ne signe plus le procès-verbal parmi les membres votants.
- Liste du corps enseignant en PDF, à inclure dans les thèses : enseignants
  actifs de l'annuaire dont l'établissement est celui des paramètres du
  circuit, groupés par statut (professeurs d'abord), à jour au jour du
  téléchargement. Nouveau modèle de document « Liste du corps enseignant »,
  modifiable par la DEVE ; téléchargeable depuis l'annuaire du jury et, pour
  l'étudiant, depuis le dépôt de la soutenance (`teachinglist.php`).
- Mise à jour 2026102700 : valeur par défaut de `minjury` à 2.

## mod_these 0.17.0 (2026102609) et mod_thesesynthesis 0.6.0 (2026102609) — signature sur place de l'étudiant et du directeur, vérification d'identité préalable, grille remplie par l'étudiant, ergonomie, simplifications pour la DEVE

- Simplifications pour la DEVE :
  - validation du dossier de soutenance : jury présenté en tableau ; seules
    les cases d'éligibilité à vérifier (membres extérieurs votants) restent,
    au nom du membre ; « Modifier la composition du jury » rouvre la saisie
    complète. Date et lieu proposés repris, salles proposées ;
  - « À traiter » : chaque dossier propose directement l'action attendue
    (validation de l'étape, documents, procès-verbal), à côté d'« Ouvrir » ;
  - actions groupées sur les dossiers cochés du tableau de bord : relancer
    les signataires, générer les documents de soutenance, prévenir les
    membres du jury pas encore prévenus (`bulk.php`, `\mod_these\local\bulk`) ;
  - conflits de salle : une autre soutenance dans la même salle sur un
    créneau qui chevauche est signalée (planning, dossier, modification,
    validation) ;
  - « Réglages à compléter » en tête des paramètres du circuit (signataires,
    contrat type, images des modèles, salles…), rappelé sur le tableau de
    bord de la DEVE tant qu'il manque quelque chose ;
  - agents de la bibliothèque : listés dans les paramètres du circuit
    (identifiants ou adresses), sans rôle à attribuer ; ils ont les droits de
    la bibliothèque et seulement ceux-là (ni boutons ni pages de la DEVE, ni
    gestion de l'activité de suivi, même avec un rôle d'enseignant), ne sont
    pas proposés comme directeurs et reçoivent les courriels de la
    bibliothèque ;
  - moins de texte : explications des pages de la DEVE repliées sous
    « Aide », descriptions des paramètres du circuit derrière l'icône d'aide,
    aides du jury et des validations de la DEVE ramenées à une ligne.

- Dépôt de la soutenance (audit, amélioration 3) : encadré « Avancement du
  dépôt » en tête du formulaire, rubrique par rubrique, avec le nombre de
  champs à compléter (les contrôles de la soumission appliqués aux données
  enregistrées) ; rubriques complètes repliées, rubriques à compléter
  dépliées. La date proposée n'est plus préremplie avec l'heure courante :
  l'étudiant l'active et la choisit.
- Téléphone (amélioration 4) : la frise ne montre que l'étape en cours
  (« Étape 5 sur 11 », acteur attendu) ; la frise complète est repliée sous
  « Voir toutes les étapes ».
- Navigation de la DEVE (amélioration 6) : boutons des pages de travail
  (tableau de bord, planning, création, import, export, Mes signatures) et
  menu « Paramètres » (courriels, annuaire du jury, transfert, paramètres du
  circuit, modèles, grille, questionnaire). Dans une activité suivie, les
  quatre pages qui ne faisaient que renvoyer à l'activité de suivi laissent la
  place à un seul lien vers ses paramètres. Même menu dans l'activité de suivi
  (`mod_thesesynthesis` 0.6.0).
- Jury : les erreurs de composition (président, membres votants, éligibilité)
  et l'avertissement des conflits s'affichent en tête de la rubrique « Jury »
  des formulaires (dépôt, modification, validation par la DEVE) et au-dessus
  du tableau du jury dans le dossier, et non plus sur la ligne du premier
  membre ou en haut de la page.
- Salles de soutenance : nouveau paramètre du circuit (rubrique
  « Soutenances », une salle par ligne), proposé à la saisie du lieu (liste
  de suggestions, un autre lieu reste possible) ; la première salle est
  proposée par défaut à l'étudiant. Le « lieu de soutenance proposé par
  défaut » disparaît des réglages de l'activité ; sa valeur sert encore tant
  qu'aucune salle n'est saisie.

- Grille de conformité remplie par l'étudiant : au dépôt de la soutenance, il
  renseigne le numéro d'avis du comité d'éthique, coche les éléments de la
  grille (ceux qui ne sont pas « selon la thèse » sont obligatoires) et atteste
  qu'elle correspond à son manuscrit. Le directeur la reçoit préremplie
  (« Grille préremplie et attestée par l'étudiant »), la vérifie, la corrige au besoin et
  la signe à sa validation. Un brouillon enregistré conserve la grille.

- Signature sur place ouverte à l'étudiant et au directeur (suivi des
  signatures, « Signatures à recueillir ») pour les documents dont la signature
  a commencé ; la signature est enregistrée sous leur compte.
- Vérification d'identité préalable : la DEVE coche « Je certifie avoir vérifié
  l'identité du signataire », puis la page de signature s'ouvre. La
  vérification est inscrite à l'historique à son nom (« Identité du signataire
  vérifiée par la DEVE »), conservée avec la signature et imprimée sous elle.
- Page de signature : les informations du document (qualité du signataire,
  étudiant, titre, n° DEVE, soutenance, documents à consulter) sont affichées
  au-dessus de la signature.
- Scan signé : le nom du signataire n'est plus demandé. Chaque signataire coché
  porte le nom connu du module (étudiant, directeur, signataires des paramètres,
  président du jury), sinon le libellé de l'emplacement ; une signature par
  délégation de la direction générale porte « Direction générale (par
  délégation) ». Le nom des fichiers reprend les emplacements signés.
- Planification du jury : un membre déjà engagé dans un autre jury sur le même
  créneau (soutenances, ou dates proposées, qui se chevauchent ; toutes
  promotions confondues, dossiers abandonnés exclus) est signalé. La durée
  d'une soutenance est un nouveau paramètre du circuit (rubrique
  « Soutenances », 60 minutes par défaut). Badge
  « Conflit » sous le jury dans le planning (personnel qui voit tous les
  dossiers), avertissement en tête du dossier, de sa modification et de sa
  validation. Un même membre est reconnu à l'enseignant de l'annuaire, à son
  adresse ou, à défaut, à son nom.
- Ergonomie (audit du 26 octobre 2026, `docs/qualite/`) :
  - pages de validation (directeur, DEVE, bibliothèque) : résumé du dossier
    (étudiant, titre, statut, directeur, n° DEVE, soutenance, manuscrit)
    au-dessus de la décision, dossier complet replié en dessous ; pages deux
    fois moins longues ;
  - « Renvoyer le dossier » devient un bouton secondaire, en rouge, distinct
    de « Valider » (commentaire toujours obligatoire) ;
  - accueil et tableau de bord : le nom et la description de l'activité ne
    sont plus affichés deux fois (l'en-tête d'activité de Moodle les montre) ;
  - « Mes signatures » : documents groupés par dossier (étudiant, titre,
    n° DEVE), aucune case cochée d'office.
- Sans changement de schéma.

## mod_these 0.16.0 (2026102603) — signataires des scans, délégation, vérification d'identité

- Scan d'un document signé hors module : la DEVE coche les signataires dont la
  signature figure sur le scan, parmi les signatures encore attendues du
  document choisi. Ils sont inscrits comme ayant signé à la main à la date du
  document : le PDF porte « Signé à la main par … le …, scan joint au dossier
  par la DEVE (Prénom Nom) », le suivi des signatures est mis à jour et le
  document est scellé une fois toutes ses signatures réunies.
- Direction générale : signature par délégation, par le lien, sur place ou par
  scan (case « Signature par délégation de la direction générale ») ; « (par
  délégation) » suit le nom de la personne qui signe, sur le document, dans le
  suivi et à l'historique.
- Signature sur place recueillie par la DEVE (suivi des signatures) : case
  obligatoire certifiant que l'identité du signataire a été vérifiée. Le compte
  DEVE est conservé avec la signature, inscrit à l'historique et imprimé sous la
  signature (« identité vérifiée par la DEVE (Prénom Nom) »).
- Table `these_signature` : champs `method` (online, inperson, scan),
  `delegated` et `verifiedby` (étape `db/upgrade.php` 2026102603), sauvegardés
  et restaurés.

## mod_these 0.15.0 (2026102602) et mod_thesesynthesis 0.5.0 (2026102600) — plus aucun réglage du site, modèles des documents sans valeur propre à un établissement

- Plus aucun réglage du site (page d'administration du module supprimée). Rôles
  DEVE et Bibliothèque, agents DEVE signataires, établissement, université,
  signataires (direction générale, CCEM, présidence de l'université), lien
  HeTOP, lieu du visa, direction des études et contrat type de diffusion sont
  des **paramètres du circuit** : bouton « Paramètres du circuit » de l'activité
  « Suivi des thèses », valables pour toutes les promotions qu'elle suit ; une
  activité « Gestion des thèses » qu'aucune activité de suivi ne suit a les
  siens, sous le même bouton. Nouvelle table `these_setting` ; le contrat type
  est rangé dans la zone `contracttemplate` de l'activité. La DEVE,
  responsable de l'activité, règle tout, rôles des services compris.
- Mise à jour (`db/upgrade.php`, 2026102602) : les anciens réglages du site et
  le contrat type sont recopiés dans chaque activité « Suivi des thèses » et
  « Gestion des thèses », puis supprimés du site.
- Sauvegarde et restauration : les paramètres du circuit et le contrat type
  suivent l'activité qui les porte.

- Modèles d'origine des documents PDF : plus aucun nom d'établissement, de
  personne ou d'adresse écrit en dur (VetAgro Sup, Université Claude Bernard
  Lyon 1, Marcy-l'Étoile, adresse de la DEVE, centre CICV, Vetagrotice), ni
  référence de formulaire (ENS-INI-P308-…). Ces valeurs viennent des réglages
  du circuit (Établissement, nouvelle Université, intitulés de la direction
  générale et de la présidence), de la ville et de l'adresse DEVE de
  l'activité. Ils n'ont plus de valeur par défaut nominative ; sur un site
  déjà installé, les valeurs enregistrées sont reprises.
- Images : le module n'en fournit plus aucune. Les modèles d'origine appellent
  `logo`, `ministere`, `universite`, `bibliotheque` et `abes`, à déposer par la
  DEVE dans *Images des modèles* ; la page indique celles qui manquent, et une
  image absente est omise du document. Les anciennes images correspondent à :
  `vetagrosup` → `logo`, `marianne` → `ministere`, `lyon1` → `universite`,
  `bulyon1` → `bibliotheque`. Un modèle modifié qui appelle encore un ancien
  nom ne l'affiche plus.
- Mention « Signature » retirée là où la signature électronique est apposée
  (dépôt du sujet, grille de conformité, rapport du directeur) ; « Signature
  de l'Auteur » devient « L'Auteur » sur le contrat de diffusion. La feuille de
  jury et le procès-verbal, signés à la main, gardent la leur.
- À la mise à jour : les modèles d'origine changent, donc les signatures déjà
  apposées sur un document **pas encore entièrement signé** qui utilise un
  modèle d'origine sont annulées (contrôle du modèle signé) et doivent être
  données de nouveau. Les documents scellés ne sont pas touchés.
- Guide : images des modèles, captures de l'annuaire du jury (bouton de
  préremplissage), de la page des modèles et des documents PDF refaites.
- Sans changement de schéma.

## mod_these 0.14.0 (2026102600) — annuaire prérempli, compatibilité musl

- Annuaire du jury : bouton « Préremplir avec les enseignants du cours ». Il
  ajoute les enseignants non éditeurs inscrits au cours de l'activité
  (capacité `mod/these:direct`) qui ne sont pas encore dans l'annuaire (même
  adresse, ou mêmes nom et prénom). La DEVE (enseignant éditeur, capacité ou
  rôle DEVE) et la bibliothèque sont exclues. Une page de confirmation liste les
  enseignants ajoutés ; ils sont créés avec le titre Dr, le statut maître de
  conférences et l'établissement par défaut, à vérifier par la DEVE. Les
  enseignants déjà présents ne sont pas modifiés. Disponible aussi depuis
  l'activité « Suivi des thèses ».
- Compatibilité avec les PHP compilés avec musl (Alpine) : suppression de
  `GLOB_BRACE`. Les images des modèles de documents (`doctemplates/images/`)
  sont lues par un appel à `glob()` par extension. Le module s'arrêtait sur
  « Undefined constant "mod_these\local\GLOB_BRACE" » sur ces installations
  (image Docker `erseco/alpine-moodle`). La CI échoue si `GLOB_BRACE`
  réapparaît.
- Sans changement de schéma.

## mod_these 0.13.0 (2026102504) — scans signés, agents DEVE signataires

- Documents générés : le rapport du directeur et la grille de conformité,
  réservés au personnel, n'étaient plus masqués à l'étudiant une fois les
  documents de soutenance générés (zone « Documents générés », consultable par
  lui). Ils n'y sont plus rangés ; l'archive transmise à l'université les
  contient toujours, rendus dans leur état de signature au moment de l'envoi.
  Première étape de `db/upgrade.php` : elle retire de cette zone les
  documents réservés déjà déposés par les générations précédentes.
- Plusieurs agents DEVE signataires : le réglage « Agents DEVE signataires »
  accepte une liste d'identifiants ou d'adresses (virgules, points-virgules ou
  un par ligne). Chaque agent est prévenu et voit les documents en attente dans
  « Mes signatures » ; le premier qui signe appose la signature. Avec un seul
  agent, comportement inchangé. Un agent retiré du réglage ne voit plus les
  documents en attente.
- Scans signés hors module : la DEVE joint au dossier le scan d'un document
  signé à la main par un signataire sans compte (contrat de diffusion, fiche
  d'agréments, fiche signalétique, conclusions, dépôt de sujet). Page
  `scan.php`, zone de fichiers `signedscan`, empreinte SHA-256 de chaque
  fichier inscrite à l'historique. Aucune signature du module n'en est modifiée.
  Sans changement de schéma.
- Planning : le champ « Aller au » garde le zéro initial du jour (« 2026-11-04 ») ;
  il s'affichait vide du 1er au 9 du mois, la valeur « 2026-11-4 » n'étant pas
  une date valide pour le navigateur.
- Style Moodle : quatre fichiers de la version précédente remis au standard
  (la vérification « Style Moodle » échouait sur `main`).

## mod_these 0.12.0, mod_thesesynthesis 0.4.0 (2026102502) — rendu par gabarits Mustache

Passage du rendu HTML à l'architecture Moodle standard (composants `renderable`
et `templatable`, gabarits Mustache, renderer de plugin), en quatre demandes de
fusion. Affichage inchangé, sans changement de schéma. Hors champ : documents
PDF (TCPDF) et formulaires `moodleform`.

- Étape 1, vue du dossier : renderer `mod_these\output\renderer` ; composants
  `status_badge`, `file_links`, `actions`, `detail_section`, `progress`,
  `jury`, `history`, `embargo`, `subject_history`, `signed_documents`,
  `dossier_table` et `dossier` dans `classes/output/`, chacun avec son gabarit
  dans `templates/` (partiels communs `table` et `part`, au balisage de
  `html_writer::table()`). Les fonctions `these_render_*()` et les méthodes de
  `mod_these\local\rendering` gardent leur signature et rendent ces composants.
  Les décisions d'accès (sujet d'une thèse confidentielle, motifs masqués,
  documents et zones de fichiers visibles) restent dans `export_for_template()` :
  le gabarit ne reçoit jamais le sujet masqué.
- Étape 2, planning des soutenances : composants `planning` (soutenances de la
  période, un tableau par jour), `planning_nav` (vue par semaine ou par mois,
  période précédente, courante et suivante, accès à une date), `planning_print`
  (planning imprimable) et `planning_upcoming` (semaines à venir) ;
  `these_render_planning()` et la page du planning (activité et suivi des
  thèses) les rendent. Le titre d'une thèse confidentielle reste masqué par
  `these_defense_row()` avant d'entrer dans le contexte.
- Étape 3, `mod_thesesynthesis` : renderer `mod_thesesynthesis\output\renderer` ;
  composants `navlinks`, `message`, `dossier_table` (dossiers à traiter et
  suivis), `jury_table` (mes jurys), `dossier_filters`, `search_form` et
  `search_results`, avec les partiels `select` (balisage de
  `html_writer::select()`) et `search_field`. Les pages du tableau de bord et de
  la recherche les rendent ; `thesesynthesis_render_navlinks()` et
  `thesesynthesis_render_search_results()` gardent leur signature. Le titre, les
  mots-clés, le résumé et les réponses d'une thèse confidentielle n'entrent dans
  le contexte que pour qui connaît le sujet. Nouveau fragment `pdetails` (bloc
  dépliable) dans le partiel `mod_these/part`.
- Étape 4, pages restantes : barre de navigation (`navlinks`) ; tableau de bord
  (`status_counts`, `dossier_filter`) ; suivi des signatures
  (`external_signers`) ; modèles de documents (`doctemplate_list`,
  `doctemplate_variables`) ; questionnaire (`question_list`) ; annuaire du jury
  (`juror_list`, `single_button_block`) ; mes signatures (`saved_signature`) ;
  promotions suivies de `mod_thesesynthesis` (`links_form`). Composants
  génériques pour les autres pages : `message`, `link`, `fragment`,
  `bullet_list`, `table`. Les partiels `select` et `message` passent de
  `mod_thesesynthesis` à `mod_these`, qui les partage ; nouveaux fragments
  `pcode` et `pcheckbox`, cible des liens. `html_writer` ne sert plus qu'à la
  construction des formulaires `moodleform`.
- Les noms de personnes (étudiant, directeur, auteur d'une entrée d'historique)
  sont désormais échappés à l'affichage, comme le reste du texte.
- Tests : un test par composant (contexte exporté, rendu du gabarit), dont un
  contrôle sur le contexte entier sérialisé en JSON pour une thèse
  confidentielle.

## mod_these 0.11.3, mod_thesesynthesis 0.3.3 (2026102501) — première version publiée

- Publication automatique : sur `main`, une fois la CI verte, la version de
  `mod_these` (`release` de `version.php`) qui n'a pas encore d'étiquette est
  étiquetée `vX.Y.Z` et publiée sur GitHub, notes tirées de ce fichier et guide
  PDF joint.
- CI : paquet `lmodern` installé pour le guide ; JIT de PHP désactivé (son
  avertissement avec pcov bloquait l'installation de Moodle 4.5) ; test du
  formulaire du jury compatible PHP 8.0 (Moodle 4.1).

## mod_these 0.11.2, mod_thesesynthesis 0.3.2 (2026102500) — non publiée

- Base : les étapes de `db/upgrade.php` (développement) sont supprimées, le
  module n'ayant jamais été installé ; numéros de version recalés sur la date
  (2026102500).

- Planning : le rôle d'un membre du jury suit son nom après un tiret (« Dr DURAND
  Anne – Directeur de thèse (membre du jury) ») au lieu de parenthèses imbriquées.
- Recherche des thèses : texte d'introduction mis à jour (thèses confidentielles
  trouvables, sujet masqué).
- Machine à états explicite : `these_status_transitions()` déclare toutes les
  transitions de statut autorisées, et `these_set_status()` refuse les autres
  (test de cohérence avec les étapes et les renvois).
- CI : couverture des tests mesurée (pcov) sur la configuration Moodle 4.5 ;
  mesure locale : environ 41 % des lignes des fonctions et classes, hors pages.
- Guide : captures du planning (semaine, mois, PDF), d'une thèse confidentielle
  (dossier, recherche), de la demande d'embargo et d'un document scellé.

- Thèse confidentielle **trouvable** : dans « Suivi des thèses », un enseignant
  la trouve comme les autres thèses enregistrées (étudiant, directeur, jury,
  année, promotion), jamais par son sujet ou ses réponses ; elle figure aussi au
  planning des soutenances. Pour qui ne doit pas connaître le sujet, titre,
  titre anglais, mots-clés et résumé sont tous remplacés par « Thèse
  confidentielle » (dossier, recherche, export).
- Correction : les résultats de recherche de « Suivi des thèses » affichaient
  les mots-clés et le résumé d'une thèse confidentielle dont le titre était
  masqué. Le tableau des résultats passe par
  `thesesynthesis_render_search_results()`, testé.

## mod_these 0.11.1 (2026103104)

- Thèse confidentielle : la **bibliothèque** connaît désormais le sujet, comme
  la DEVE, le directeur et le jury (rôle de service, `mod/these:managelibrary`
  ou boîte de service) : titre, documents, recherche, planning, export et
  courriels. Le sujet reste masqué aux détenteurs de `mod/these:viewall` sans
  rôle de service, aux autres enseignants et aux autres étudiants.
- L'attestation de conformité imprime de nouveau le titre.
- Tests d'accès mis à jour : la bibliothèque parmi les profils autorisés, un
  détenteur de `mod/these:viewall` comme profil refusé qui voit le dossier.

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
