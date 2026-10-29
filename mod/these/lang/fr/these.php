<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French strings for mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Gestion des thèses';
$string['modulename'] = 'Gestion des thèses';
$string['modulenameplural'] = 'Gestions des thèses';
$string['modulename_help'] = "Ce module porte le circuit des thèses d'exercice vétérinaire, du dépôt du sujet jusqu'au procès-verbal "
    . "de soutenance : l'étudiant dépose son sujet puis ses documents de soutenance, le directeur de thèse les valide, la DEVE "
    . "enregistre le sujet et valide le dossier complet, la bibliothèque inscrit le sujet au référentiel documentaire puis "
    . "atteste la conformité du manuscrit, et la DEVE génère et transmet les documents de soutenance.";
$string['pluginadministration'] = 'Administration de la gestion des thèses';
$string['thesename'] = "Nom de l'activité";

// Capacités.
$string['these:addinstance'] = 'Ajouter une activité de gestion des thèses';
$string['these:view'] = "Voir l'activité de gestion des thèses";
$string['these:submit'] = 'Déposer son sujet et ses documents de soutenance';
$string['these:direct'] = 'Diriger des thèses et valider les dossiers dirigés';
$string['these:managedeve'] = 'Gérer les dossiers de thèse (DEVE)';
$string['these:managelibrary'] = 'Enregistrer les sujets et attester la conformité des manuscrits (bibliothèque)';
$string['these:viewall'] = 'Voir tous les dossiers de thèse';

// Paramètres de l'activité.
$string['establishment'] = 'Établissement (documents générés)';
$string['establishmentname'] = "Nom de l'établissement";
$string['establishmentaddress'] = "Adresse de l'établissement";
$string['establishmentcity'] = 'Ville (« Fait à »)';
$string['signatoryname'] = 'Signataire des convocations';
$string['signatorytitle'] = 'Qualité du signataire';
$string['defaultlocation'] = 'Lieu de soutenance proposé par défaut';
$string['workflowsettings'] = 'Circuit et notifications';
$string['minjury'] = 'Membres votants minimum du jury';
$string['minjury_help'] = "Nombre minimal de membres votants (président compris, directeur de thèse et invités exclus) exigé pour la composition du jury, "
    . "à la proposition de l'étudiant comme à la validation de la DEVE.";
$string['minjuryinvalid'] = 'Indiquez un nombre supérieur ou égal à 1.';
$string['deveemail'] = 'Adresse de service de la DEVE';
$string['deveemail_help'] = "Si elle est renseignée, les notifications destinées à la DEVE (dossier à enregistrer, à valider, documents "
    . "à générer, relances) sont envoyées à cette adresse. Sinon, chaque utilisateur inscrit qui détient le droit de gérer les "
    . "dossiers (DEVE) les reçoit.";
$string['libraryemail'] = 'Adresse de service de la bibliothèque';
$string['libraryemail_help'] = "Si elle est renseignée, les notifications destinées à la bibliothèque (sujet à enregistrer, manuscrit à "
    . "contrôler, relances) sont envoyées à cette adresse. Sinon, chaque utilisateur inscrit qui détient le droit bibliothèque les "
    . "reçoit.";
$string['universityemail'] = "Adresse de l'université";
$string['universityemail_help'] = "Destinataire, à la transmission des documents de soutenance, d'une archive contenant l'ensemble des "
    . "documents générés (convocations, feuille de jury, procès-verbal). Laisser vide pour ne rien envoyer à l'université.";
$string['reminderdays'] = 'Délai de relance (jours)';
$string['reminderdays_help'] = "Lorsqu'un dossier attend à la même étape depuis ce nombre de jours, l'acteur attendu (directeur, DEVE, "
    . "bibliothèque, ou étudiant dont le dossier a été renvoyé) est relancé par courriel, puis de nouveau à chaque délai écoulé. "
    . "Les brouillons et les dossiers actifs (« dormants ») ne sont jamais relancés. 0 désactive les relances.";
$string['reminderdaysinvalid'] = 'Indiquez un nombre de jours positif ou nul.';
$string['resetdossiers'] = 'Supprimer tous les dossiers de thèse';
$string['resetdossiers_help'] = "Supprime tous les dossiers, leur jury, leur historique et leurs fichiers. Un dossier de thèse reste "
    . "ouvert plusieurs années : l'option n'est pas cochée par défaut.";

// Acteurs.
$string['actorstudent'] = 'Étudiant';
$string['actordirector'] = 'Directeur de thèse';
$string['actordeve'] = 'DEVE';
$string['actorlibrary'] = 'Bibliothèque';

// Statuts.
$string['statusdraft'] = 'Sujet en préparation';
$string['statussubjectsubmitted'] = 'Sujet soumis au directeur';
$string['statussubjectreturned'] = "Sujet renvoyé à l'étudiant";
$string['statussubjectapproved'] = 'Sujet validé par le directeur';
$string['statussubjectregistered'] = 'Sujet enregistré par la DEVE';
$string['statusactive'] = 'Dossier actif';
$string['statusdefensesubmitted'] = 'Soutenance proposée au directeur';
$string['statusdefensereturned'] = "Dossier de soutenance renvoyé à l'étudiant";
$string['statusdefenseapproved'] = 'Soutenance validée par le directeur';
$string['statusdefensevalidated'] = 'Dossier validé par la DEVE';
$string['statusconform'] = 'Manuscrit conforme';
$string['statusscheduled'] = 'Soutenance programmée';
$string['statusclosed'] = 'Dossier clôturé';
$string['statuscancelled'] = 'Dossier abandonné';
$string['allstatuses'] = 'Tous les statuts';
$string['status'] = 'Statut';
$string['waitingfor'] = 'En attente : {$a}';
$string['since'] = 'Depuis {$a}';
$string['system'] = 'Système';

// Frise de progression.
$string['stepsubjectdeposit'] = 'Dépôt du sujet';
$string['stepdirectorsubject'] = 'Validation du sujet';
$string['stepdevesubject'] = 'Enregistrement DEVE';
$string['steplibrarysubject'] = 'Enregistrement bibliothèque';
$string['stepdefensedeposit'] = 'Dépôt de la soutenance';
$string['stepdirectordefense'] = 'Validation et rapport du directeur';
$string['stepdevedefense'] = 'Validation du dossier complet';
$string['steplibraryconformity'] = 'Conformité du manuscrit';
$string['stepdocuments'] = 'Documents de soutenance';
$string['stepminutes'] = 'Procès-verbal';
$string['progresscurrent'] = 'Étape {$a->position} sur {$a->total} : {$a->label}';
$string['progressall'] = 'Voir toutes les étapes';
$string['dormantphase'] = 'dossier dormant';

// Navigation et listes.
$string['dashboard'] = 'Tableau de bord';
$string['planning'] = 'Planning des soutenances';
$string['backto'] = 'Retour : {$a}';
$string['previouspage'] = 'Page précédente';
$string['planningviewweek'] = 'Par semaine';
$string['planningviewmonth'] = 'Par mois';
$string['planningweekof'] = 'Semaine du {$a->start} au {$a->end}';
$string['planningprevweek'] = 'Semaine précédente';
$string['planningthisweek'] = 'Cette semaine';
$string['planningnextweek'] = 'Semaine suivante';
$string['planningprevmonth'] = 'Mois précédent';
$string['planningthismonth'] = 'Ce mois-ci';
$string['planningnextmonth'] = 'Mois suivant';
$string['planninggoto'] = 'Aller au';
$string['planningcount'] = '{$a} soutenance(s)';
$string['planningnone'] = 'Aucune soutenance sur cette période.';
$string['planningtime'] = 'Heure';
$string['planningpromotion'] = 'Promotion';
$string['planningconfirmed'] = 'Date arrêtée';
$string['planningproposed'] = 'Date proposée';
$string['planningpdfweek'] = 'Imprimer le planning de la semaine (PDF)';
$string['planningpdfmonth'] = 'Imprimer le planning mensuel (PDF)';
$string['planningdownload'] = 'Télécharger le planning de la période';
$string['planningupcoming'] = 'Semaines à venir avec des soutenances';
$string['todo'] = 'À traiter';
$string['nothingtodo'] = "Aucune étape du circuit ne vous est attribuée dans cette activité.";
$string['alldossiers'] = 'Tous les dossiers';
$string['mydirecteddossiers'] = 'Les dossiers que je dirige';
$string['nodossiers'] = 'Aucun dossier.';
$string['searchdossiers'] = 'Étudiant, titre ou numéro';
$string['open'] = 'Ouvrir';
$string['dossier'] = 'Dossier de thèse';
$string['dossierof'] = 'Dossier de thèse de {$a}';
$string['mydossier'] = 'Mon dossier de thèse';
$string['nodossieryet'] = "Vous n'avez pas encore de dossier de thèse. Commencez par proposer votre sujet : vous pourrez l'enregistrer "
    . "en brouillon avant de le soumettre à votre directeur de thèse.";
$string['proposesubject'] = 'Proposer un sujet de thèse';

// Sujet.
$string['subject'] = 'Sujet de thèse';
$string['subjectintro'] = "Renseignez la fiche de sujet et choisissez votre directeur de thèse. « Enregistrer le brouillon » conserve "
    . "votre saisie sans rien transmettre ; la soumission envoie le sujet à votre directeur, qui le validera ou vous le renverra "
    . "avec ses commentaires. Une fois validé, il est enregistré par la DEVE puis par la bibliothèque.";
$string['subjecttitle'] = 'Titre';
$string['subjecttitleen'] = 'Titre en anglais';
$string['subjectsummary'] = 'Résumé';
$string['subjectsummary_help'] = "Présentation du sujet : contexte, objectifs, démarche envisagée (une page environ).";
$string['subjectkeywords'] = 'Mots-clés';
$string['subjectkeywords_help'] = "3 à 5 mots-clés séparés par des points-virgules, choisis de préférence dans la terminologie HeTOP.";
$string['director'] = 'Directeur de thèse';
$string['director_help'] = "Enseignant qui dirige la thèse. Seuls les enseignants inscrits dans ce cours et autorisés à diriger des "
    . "thèses sont proposés : si le vôtre n'apparaît pas, contactez la DEVE.";
$string['codirector'] = 'Co-directeur';
$string['codirector_help'] = "Facultatif. Co-directeur éventuel : un enseignant de l'établissement, à choisir dans la liste, ou une "
    . "personne extérieure, dont vous indiquez le nom et la qualité. Il ne valide pas le dossier dans le module.";
$string['codirectornone'] = 'Aucun';
$string['codirectorinternal'] = "Interne à l'établissement";
$string['codirectorexternal'] = "Extérieur à l'établissement";
$string['codirectorjuror'] = 'Co-directeur interne';
$string['codirectorname'] = 'Co-directeur extérieur : titre, nom et qualité';
$string['subjectdocs'] = 'Pièces jointes au sujet';
$string['subjectdocs_help'] = 'Facultatif : bibliographie, protocole, accord de partenaire...';
$string['savedraft'] = 'Enregistrer le brouillon';
$string['submitsubject'] = 'Soumettre au directeur de thèse';
$string['resubmitsubject'] = 'Soumettre de nouveau';
$string['draftsaved'] = 'Brouillon enregistré.';
$string['subjectsubmittedmsg'] = 'Sujet soumis.';
$string['subjectnoteditable'] = "Le sujet n'est plus modifiable : il a été soumis. Il le redeviendra si le dossier vous est renvoyé.";
$string['editsubject'] = 'Modifier le sujet';
$string['registration'] = 'Enregistrements';
$string['devenumber'] = "Numéro d'enregistrement DEVE";
$string['devenumber_help'] = "Numéro d'ordre attribué à la thèse après le dépôt de la soutenance. Le numéro proposé est le suivant de l'année en cours (format AAAA-NNN) ; "
    . "il peut être modifié, mais doit rester unique dans l'activité.";
$string['devenumbertaken'] = 'Ce numéro est déjà attribué à un autre dossier.';
$string['libraryref'] = 'Référence de la notice bibliographique';

// Soutenance.
$string['defense'] = 'Soutenance';
$string['depositdefense'] = 'Déposer les documents de soutenance';
$string['defenseprogress'] = 'Avancement du dépôt : {$a->complete} rubrique(s) complète(s) sur {$a->total}. Les rubriques à compléter sont dépliées ci-dessous.';
$string['defensesectioncomplete'] = 'Complète';
$string['defensesectionmissing'] = '{$a} champ(s) à compléter';
$string['defenseintro'] = "Déposez votre manuscrit et les documents de soutenance, et proposez une date, un lieu et la composition du "
    . "jury (au moins {\$a} membres votants, président compris ; votre directeur siège sans voter). Vous pouvez préparer ce dépôt en plusieurs fois "
    . "(« Enregistrer le brouillon ») ; la soumission l'envoie à votre directeur, qui le validera en y joignant son rapport.";
$string['defensedocuments'] = 'Documents de soutenance';
$string['manuscript'] = 'Manuscrit (PDF)';
$string['manuscript_help'] = 'Version complète du manuscrit, en un seul fichier PDF.';
$string['manuscriptrequired'] = 'Le manuscrit est obligatoire pour soumettre.';
$string['defensedocs'] = 'Autres documents de soutenance';
$string['defensedocs_help'] = 'Facultatif : résumé, autorisation de diffusion, annexes...';
$string['defenseproposal'] = 'Proposition de soutenance';
$string['proposeddaterequired'] = 'Cochez « Activer » puis choisissez la date et l\'heure proposées.';
$string['defenseroomshelp'] = 'Salles proposées : {$a}. Vous pouvez aussi saisir un autre lieu.';
$string['proposeddate'] = 'Date proposée';
$string['defensedate'] = 'Date de soutenance';
$string['defenselocation'] = 'Lieu de soutenance';
$string['datemustbefuture'] = 'La date de soutenance doit être postérieure à aujourd\'hui.';
$string['submitdefense'] = 'Soumettre au directeur de thèse';
$string['resubmitdefense'] = 'Soumettre de nouveau';
$string['defensesubmittedmsg'] = 'Dossier de soutenance soumis.';
$string['defensenoteditable'] = "Le dépôt des documents de soutenance n'est possible qu'une fois le sujet enregistré, ou si le dossier "
    . "de soutenance vous a été renvoyé.";
$string['directorreport'] = 'Rapport du directeur';
$string['directorreport_help'] = "Rapport du directeur de thèse sur le manuscrit, obligatoire pour valider la proposition de soutenance. "
    . "Il est communiqué à la DEVE, à la bibliothèque et au jury, mais pas à l'étudiant.";
$string['directorreportrequired'] = 'Le rapport du directeur est obligatoire pour valider.';
$string['generateddocuments'] = 'Documents générés';
$string['defenseresult'] = 'Résultat';
$string['resultadmitted'] = 'Admis';
$string['resultadjourned'] = 'Ajourné';
$string['mention'] = 'Mention';
$string['mention_help'] = 'Mention décernée par le jury à une thèse admise : honorable, très honorable, très honorable avec félicitations du jury.';
$string['minutes'] = 'Procès-verbal de soutenance';

// Jury.
$string['jury'] = 'Jury';
$string['juryproblem'] = 'Composition du jury à corriger : {$a}';
$string['juryedit'] = 'Modifier la composition du jury';
$string['juryeligiblecheckof'] = 'Éligibilité de {$a} vérifiée (DEVE)';
$string['roomconflicts'] = 'Salle déjà occupée sur ce créneau (soutenances qui se chevauchent) :';
$string['roomconflict'] = '{$a->location} : soutenance de {$a->student} le {$a->date} ({$a->activity}).';
$string['roomconflictshort'] = 'Salle occupée : {$a->student}, {$a->time}';
$string['juryhelpdeve'] = "Une ligne par membre, choisi de préférence dans l'annuaire du jury.";
$string['juryhelp'] = "Une ligne par membre. Choisissez de préférence l'enseignant dans l'annuaire du jury : son titre, son "
    . "statut, son établissement et son adresse en sont repris. Pour un membre extérieur ou un invité, choisissez « Membre extérieur "
    . "ou invité » et renseignez la ligne (le statut est demandé pour un membre votant ; le directeur de thèse siège sans voter). Seuls les maîtres de conférences HDR, les "
    . "professeurs et les émérites peuvent présider. La DEVE valide ensuite l'éligibilité de chaque membre ; les membres sont alors "
    . "prévenus par courriel et reçoivent un lien d'accès aux documents du dossier.";
$string['juryrole'] = 'Rôle';
$string['juryrolepresident'] = 'Président';
$string['juryroledirector'] = 'Directeur de thèse (non votant)';
$string['juryrolemember'] = 'Membre du jury';
$string['juryroleguest'] = 'Membre invité';
$string['juryname'] = 'Nom';
$string['jurytitle'] = 'Titre ou qualité';
$string['juryinstitution'] = 'Établissement';
$string['juryemail'] = 'Courriel';
$string['juryaddmembers'] = 'Ajouter des lignes';
$string['juryonepresident'] = 'Le jury doit comporter un et un seul président.';
$string['juryminmembers'] = 'Le jury doit comporter au moins {$a} membres votants (président compris, directeur de thèse et invités exclus).';
$string['juryinvalidemail'] = 'Adresse de courriel invalide pour {$a}.';
$string['juryjuror'] = "Enseignant de l'annuaire";
$string['juryjuror_help'] = "Enseignant choisi dans l'annuaire du jury tenu par la DEVE. Pour un membre extérieur (autre "
    . "établissement, praticien) ou un invité, choisissez « Membre extérieur ou invité » et renseignez les champs qui apparaissent.";
$string['juryexternal'] = '— Membre extérieur ou invité (à renseigner) —';
$string['jurystatus'] = 'Statut';
$string['jurystatus_help'] = "Corps de l'enseignant. Tous peuvent diriger une thèse ou être membre du jury ; seuls les maîtres de "
    . "conférences HDR, les professeurs et les émérites (professeurs et maîtres de conférences) peuvent présider.";
$string['jurystatusir'] = 'Ingénieur de recherche';
$string['jurystatusmcf'] = 'Maître de conférences';
$string['jurystatusmcfhdr'] = 'Maître de conférences HDR';
$string['jurystatusmcfstag'] = 'Maître de conférences stagiaire';
$string['jurystatusprstag'] = 'Professeur stagiaire';
$string['jurystatuspr'] = 'Professeur';
$string['jurystatuspremerite'] = 'Professeur émérite';
$string['jurystatusmcfemerite'] = 'Maître de conférences émérite';
$string['jurystatusother'] = 'Autre (praticien, chercheur...)';
$string['jurystatusrequired'] = 'Indiquez le statut de {$a} (membre extérieur).';
$string['juryduplicate'] = '{$a} figure deux fois dans le jury.';
$string['jurypresidentnoteligible'] = "{\$a} ne peut pas présider le jury : seuls les maîtres de conférences HDR, les professeurs et "
    . "les émérites le peuvent.";
$string['juryeligiblecheck'] = 'Éligibilité vérifiée (DEVE)';
$string['juryeligiblehelp'] = "Cochez l'éligibilité de chaque membre votant vérifié (déjà cochée pour l'annuaire).";
$string['juryeligiblerequired'] = "L'éligibilité de {\$a} n'est pas validée.";
$string['jurynotified'] = '{$a} membre(s) du jury prévenu(s).';
$string['jurynotifiedmissing'] = 'Sans adresse, à prévenir par un autre moyen : {$a}.';
$string['juryeligibilityok'] = 'Éligible';
$string['juryeligibilitytocheck'] = 'À vérifier par la DEVE';
$string['juryeligibilityko'] = 'Non éligible à ce rôle';
$string['juryeligibilityvalidated'] = 'Éligibilité validée';
$string['jurynotifiedon'] = 'Prévenu le {$a}';
$string['juryresendaccess'] = "Renvoyer l'accès au jury";
$string['juryaccessresent'] = '{$a} membre(s) du jury prévenu(s) de nouveau.';
$string['juryaccess'] = 'Accès du jury';
$string['juryaccessintro'] = 'Bonjour {$a->name}, vous êtes désigné(e) comme {$a->role} du jury de cette thèse. Vous trouverez '
    . 'ci-dessous les informations de la soutenance et les documents du dossier.';
$string['juryaccessinvalid'] = "Ce lien d'accès n'est pas (ou plus) valide : il a été remplacé par un nouvel envoi, la "
    . "soutenance date de plus de 60 jours, ou le dossier est clos. Contactez la DEVE.";
$string['juryaccessdocuments'] = 'Documents';
$string['juryaccessnodocuments'] = "Aucun document n'est encore disponible.";

// Annuaire du jury.
$string['jurors'] = 'Annuaire du jury';
$string['juryconflicts'] = 'Membre du jury déjà engagé dans un autre jury sur ce créneau (soutenances qui se chevauchent) :';
$string['juryconflict'] = '{$a->member} siège au jury de la soutenance de {$a->student} le {$a->date} ({$a->activity}).';
$string['juryconflictshort'] = 'Conflit : {$a->member} déjà au jury de {$a->student} à {$a->time}';
$string['jurors_desc'] = "Enseignants pouvant diriger une thèse ou siéger au jury, communs à toutes les activités et à toutes les "
    . "promotions. Les formulaires de jury y puisent pour être préremplis. Un enseignant retiré (inactif) n'est plus proposé mais "
    . "reste dans les jurys qui l'ont retenu.";
$string['jurortitle'] = 'Titre';
$string['juroractive'] = 'Proposé dans les formulaires de jury';
$string['juroractiveyes'] = 'Actif';
$string['juroractiveno'] = 'Retiré';
$string['jurorpresident'] = 'Peut présider';
$string['jurordiscipline'] = 'Matière enseignée';
$string['jurordiscipline_help'] = 'Matière ou discipline enseignée, indiquée à côté du nom dans la liste du corps enseignant.';
$string['juroradd'] = 'Ajouter un enseignant';
$string['juroredit'] = 'Modifier un enseignant';
$string['jurorsaved'] = "L'enseignant est enregistré.";
$string['jurordeleted'] = "L'enseignant est supprimé de l'annuaire.";
$string['jurordeleteconfirm'] = "Supprimer {\$a} de l'annuaire ? Les jurys qui l'ont retenu le gardent.";
$string['jurorsnone'] = "L'annuaire est vide.";
$string['jurordirectory'] = 'Annuaire des enseignants';
$string['jurordirectory_desc'] = "Enseignants pouvant diriger une thèse ou siéger au jury, de l'établissement et extérieurs, avec "
    . "leur statut et leur matière. La colonne « Peut présider » indique ceux qui peuvent présider un jury : professeurs, "
    . "professeurs émérites, maîtres de conférences HDR ou émérites ; leur adresse est donnée pour les contacter.";
$string['jurordirectorysearch'] = 'Nom, matière, établissement';
$string['jurordirectorypresidents'] = 'Seulement ceux pouvant présider un jury';
$string['jurordirectorycount'] = '{$a} enseignant(s)';
$string['jurordirectorynone'] = 'Aucun enseignant ne correspond à la recherche.';
$string['jurorimport'] = 'Importer un fichier CSV';
$string['jurorimportfile'] = 'Fichier CSV';
$string['jurorimportfile_help'] = "Colonnes : title (Dr ou Pr), lastname, firstname, email, status, institution et, "
    . "facultativement, discipline (matière enseignée), séparées par des virgules ou des points-virgules. Le statut est la "
    . "clé (ir, mcf, mcfhdr, mcfstag, prstag, pr, premerite, mcfemerite) ou le libellé. Un enseignant déjà présent (même "
    . "adresse, ou mêmes nom et prénom) est mis à jour ; sans colonne discipline, sa matière est gardée.";
$string['jurorimportdone'] = '{$a->created} enseignant(s) ajouté(s), {$a->updated} mis à jour.';
$string['jurorimportbadline'] = 'Ligne {$a} ignorée : titre, nom, prénom, statut ou adresse incorrect.';
$string['jurorprefill'] = 'Préremplir avec les enseignants du cours';
$string['jurorprefillconfirm'] = "Ajouter à l'annuaire {\$a} enseignant(s) non éditeur(s) inscrit(s) au cours ? Ils sont créés avec le titre "
    . "Dr, le statut maître de conférences et l'établissement par défaut : vérifiez-les ensuite. La DEVE et la bibliothèque ne sont "
    . "pas reprises, ni les enseignants déjà présents (même adresse, ou mêmes nom et prénom).";
$string['jurorprefilldone'] = "{\$a} enseignant(s) ajouté(s) à l'annuaire. Vérifiez leur titre et leur statut.";
$string['jurorprefillnone'] = "Tous les enseignants non éditeurs inscrits au cours sont déjà dans l'annuaire.";
$string['jurysheet'] = 'Feuille de jury';

// Étapes de validation.
$string['reviewdirectorsubject'] = 'Examiner la proposition de sujet';
$string['reviewdirectorsubject_help'] = "Validez le sujet pour qu'il soit enregistré par la DEVE, ou renvoyez-le à l'étudiant avec vos "
    . "commentaires : il pourra le corriger et vous le soumettre de nouveau.";
$string['reviewdevesubject'] = 'Enregistrer le sujet (DEVE)';
$string['reviewdevesubject_help'] = "Vérifiez le sujet : il passe ensuite à la bibliothèque.";
$string['reviewlibrarysubject'] = 'Enregistrer le sujet (bibliothèque)';
$string['reviewlibrarysubject_help'] = "Indiquez la référence de la notice créée dans le référentiel documentaire. L'enregistrement ouvre "
    . "le dossier, qui reste actif jusqu'au dépôt des documents de soutenance.";
$string['reviewdirectordefense'] = 'Valider la soutenance et déposer le rapport';
$string['reviewdirectordefense_help'] = "Validez la proposition de soutenance (date, jury) après avoir rédigé votre rapport sur le "
    . "manuscrit (« Rédiger le rapport ») : votre signature est apposée sur le rapport à la validation. Vous pouvez aussi renvoyer le dossier à l'étudiant avec vos commentaires.";
$string['reviewdevedefense'] = 'Valider le dossier de soutenance (DEVE)';
$string['reviewdevedefense_help'] = "Attribuez le numéro, arrêtez la date, le lieu et le jury : le manuscrit passe ensuite à la bibliothèque.";
$string['reviewlibraryconformity'] = 'Contrôler la conformité du manuscrit';
$string['reviewlibraryconformity_help'] = "Vérifiez la forme du manuscrit, les normes de présentation et les conditions du dépôt légal. "
    . "La validation délivre l'attestation de conformité ; un manuscrit non conforme est renvoyé à l'étudiant avec le motif.";
$string['validatedirectorsubject'] = 'Valider le sujet';
$string['validatedevesubject'] = 'Enregistrer le sujet';
$string['validatelibrarysubject'] = 'Enregistrer la notice';
$string['validatedirectordefense'] = 'Valider la soutenance';
$string['validatedevedefense'] = 'Valider le dossier';
$string['validatelibraryconformity'] = 'Attester la conformité';
$string['conformitychecked'] = 'Le manuscrit est conforme (forme, dépôt légal, normes de présentation)';
$string['conformitycheckrequired'] = 'Cochez la case pour attester la conformité, ou renvoyez le dossier.';
$string['reviewremark'] = 'Commentaire';
$string['reviewremark_help'] = "Facultatif à la validation (il est inscrit à l'historique du dossier). Obligatoire pour un renvoi : "
    . "c'est le motif transmis au destinataire.";
$string['returntarget'] = 'Renvoyer à';
$string['returndossier'] = 'Renvoyer le dossier';
$string['returncommentrequired'] = 'Le motif du renvoi est obligatoire.';
$string['returncomment'] = 'Motif du renvoi';
$string['returnedby'] = 'Dossier renvoyé par {$a->by} le {$a->date} :';
$string['dossierreturned'] = 'Dossier renvoyé ({$a}).';
$string['stepvalidated'] = 'Étape validée.';
$string['nostepwaiting'] = "Ce dossier n'attend aucune validation.";
$string['notyourstep'] = "Cette étape du circuit ne vous est pas attribuée.";

// Documents de soutenance et procès-verbal.
$string['documents'] = 'Documents de soutenance';
$string['documentsintro'] = "Générez les convocations (étudiant et chaque membre du jury), la feuille de jury et le procès-verbal à "
    . "signer, vérifiez-les, puis transmettez-les : chaque membre du jury reçoit sa convocation, l'étudiant la sienne, et "
    . "l'université l'ensemble des documents. La première transmission programme la soutenance.";
$string['generatedocuments'] = 'Générer les documents';
$string['regeneratedocuments'] = 'Régénérer les documents';
$string['transmitdocuments'] = 'Transmettre au jury et à l\'université';
$string['retransmitdocuments'] = 'Transmettre de nouveau';
$string['documentsgenerated'] = 'Documents générés.';
$string['documentsgeneratedon'] = 'Documents générés le {$a}.';
$string['documentssenton'] = 'Dernière transmission le {$a}.';
$string['documentsstale'] = "Le dossier a été modifié (date, lieu ou jury) depuis la génération des documents : régénérez-les avant "
    . "de les transmettre.";
$string['documentsnotavailable'] = "Les documents de soutenance sont disponibles une fois le manuscrit déclaré conforme.";
$string['documentnotavailable'] = "Ce document n'est pas encore disponible pour ce dossier.";
$string['generatefirst'] = 'Générez les documents avant de les transmettre.';
$string['transmitsummary'] = '{$a->sent} courriel(s) envoyé(s), {$a->failed} échec(s).';
$string['transmitmissing'] = 'Sans adresse de courriel, à convoquer par un autre moyen : {$a}.';
$string['nouniversityemail'] = "Aucune adresse universitaire n'est renseignée dans les paramètres de l'activité : rien ne sera envoyé à "
    . "l'université.";
$string['convocation'] = 'Convocation à la soutenance de thèse';
$string['depositminutes'] = 'Déposer le procès-verbal';
$string['minutesrequired'] = 'Le procès-verbal signé est obligatoire.';
$string['minutesnotavailable'] = "Le procès-verbal se dépose une fois la soutenance programmée.";
$string['closedossier'] = 'Déposer et clôturer le dossier';
$string['dossierclosed'] = 'Procès-verbal déposé : dossier clôturé.';

// Gestion DEVE.
$string['editdossier'] = 'Modifier le dossier';
$string['editdossierhelp'] = "Sans effet sur le statut. Si la date, le lieu ou le jury changent, régénérez les documents de soutenance.";
$string['dossiersaved'] = 'Dossier enregistré.';
$string['canceldossier'] = 'Abandonner le dossier';
$string['cancelhelp'] = "Le dossier est conservé tel quel et peut être rouvert plus tard dans le statut qu'il avait atteint.";
$string['dossiercancelled'] = 'Dossier abandonné.';
$string['reopendossier'] = 'Rouvrir le dossier';
$string['reopenhelp'] = "Le dossier reprend le statut qu'il avait atteint lors de son abandon.";
$string['dossierreopened'] = 'Dossier rouvert.';
$string['createdossier'] = 'Créer un dossier';
$string['createdossierhelp'] = "Crée un dossier en brouillon pour un étudiant inscrit qui n'en a pas encore. Vous pourrez ensuite "
    . "compléter son sujet ; l'étudiant pourra le soumettre lui-même.";
$string['dossiercreated'] = 'Dossier créé.';
$string['student'] = 'Étudiant';

// Import.
$string['importdossiers'] = 'Importer des dossiers';
$string['import'] = 'Importer';
$string['importhelp'] = "Reprise de dossiers déjà engagés hors du module, depuis un fichier CSV, Excel (.xlsx, .xls) ou "
    . "OpenDocument (.ods) dont la première ligne porte le nom des colonnes : <code>student</code> et <code>title</code> "
    . "(obligatoires), <code>titleen</code>, <code>summary</code>, <code>keywords</code>, <code>director</code>, "
    . "<code>codirector</code>, <code>devenumber</code>, <code>libraryref</code>, <code>status</code>, <code>defensedate</code>, "
    . "<code>defenselocation</code>, <code>mention</code>, <code>corrections</code>. Étudiant et directeur sont désignés par "
    . "identifiant de connexion, adresse de courriel ou numéro d'identification. Un co-directeur dont le nom (« Claire Martin », "
    . "« Dr MARTIN Claire ») est celui d'un enseignant interne de l'annuaire y est rattaché ; sinon il est noté comme extérieur. "
    . "<code>status</code> : vide ou <code>active</code> (sujet enregistré), <code>corrections</code> (thèse soutenue, "
    . "corrections en cours) ou <code>closed</code> (dossier clôturé) ; les deux derniers exigent <code>defensedate</code> "
    . "(jj/mm/aaaa [hh:mm], aaaa-mm-jj ou date du tableur) et admettent une <code>mention</code> (honorable, très honorable, "
    . "félicitations) et les <code>corrections</code> demandées. Séparateur et encodage ne servent qu'aux fichiers CSV.";
$string['csvfile'] = "Fichier (CSV, Excel ou OpenDocument)";
$string['importdryrun'] = 'Simulation (ne rien créer)';
$string['importline'] = 'Ligne';
$string['importusernotfound'] = 'Utilisateur introuvable : {$a}';
$string['importdossierexists'] = 'Cet étudiant a déjà un dossier.';
$string['importtitlemissing'] = 'Titre manquant.';
$string['importcreated'] = 'Dossier créé';
$string['importwouldcreate'] = 'Prêt à importer';
$string['importresult'] = '{$a->ok} dossier(s) importé(s), {$a->errors} ligne(s) en erreur.';
$string['importdryrunresult'] = 'Simulation : {$a->ok} dossier(s) importable(s), {$a->errors} ligne(s) en erreur.';
$string['importinvalidfile'] = 'Fichier illisible : {$a}';
$string['importmissingcolumn'] = 'Colonne obligatoire absente : {$a}';
$string['importbadstatus'] = 'Statut non importable : {$a} (active, corrections ou closed).';
$string['importbaddefensedate'] = 'Date de soutenance manquante ou illisible : {$a}';
$string['importbadmention'] = 'Mention inconnue : {$a}';
$string['importcodirectorinternal'] = 'co-directeur interne : {$a}';

// Export.
$string['export'] = 'Export du registre';
$string['exporthelp'] = "Un dossier par ligne : numéro, étudiant, sujet, directeur, statut, soutenance, jury et résultat, puis la validation "
    . "de l'A6, le type de co-directeur, les signatures qui manquent pour passer à l'étape suivante, les pièces pour l'université "
    . "et le dernier refus d'un agent DEVE.";
$string['exportformat'] = 'Format';

// Historique.
$string['history'] = 'Historique';
$string['historyaction'] = 'Action';
$string['historyby'] = 'Par';
$string['historyremark'] = 'Commentaire';
$string['logcreated'] = 'Dossier créé';
$string['logimported'] = 'Dossier importé';
$string['logsubjectsaved'] = 'Sujet enregistré (brouillon)';
$string['logsubjectsubmitted'] = 'Sujet soumis';
$string['logsubjectresubmitted'] = 'Sujet soumis de nouveau';
$string['logdefensesaved'] = 'Dossier de soutenance enregistré (brouillon)';
$string['logdefensesubmitted'] = 'Dossier de soutenance soumis';
$string['logdefenseresubmitted'] = 'Dossier de soutenance soumis de nouveau';
$string['logvalidateddirectorsubject'] = 'Sujet validé par le directeur';
$string['logvalidateddevesubject'] = 'Sujet enregistré par la DEVE';
$string['logvalidatedlibrarysubject'] = 'Sujet enregistré par la bibliothèque';
$string['logvalidateddirectordefense'] = 'Soutenance validée et rapport déposé';
$string['logvalidateddevedefense'] = 'Dossier complet validé par la DEVE';
$string['logvalidatedlibraryconformity'] = 'Conformité du manuscrit attestée';
$string['logreturnedtostudent'] = "Renvoyé à l'étudiant";
$string['logreturnedtodirector'] = 'Renvoyé au directeur';
$string['logdocumentsgenerated'] = 'Documents de soutenance générés';
$string['logdocumentssent'] = 'Documents transmis';
$string['logdocumentsresent'] = 'Documents transmis de nouveau';
$string['logclosed'] = 'Procès-verbal déposé';
$string['logcancelled'] = 'Dossier abandonné';
$string['logreopened'] = 'Dossier rouvert';
$string['logedited'] = 'Dossier modifié par la DEVE';
$string['logreminded'] = 'Relance envoyée';

// Courriels.
$string['notifications'] = 'Courriels';
$string['notificationshelp'] = "Pour chaque courriel, un sujet et/ou un corps renseignés ici remplacent le texte par défaut. Les variables "
    . "entre doubles accolades sont remplacées à l'envoi. Videz les deux champs pour revenir au texte par défaut.";
$string['emailvars'] = 'Variables disponibles';
$string['emaildefault'] = 'Texte par défaut';
$string['emailsubject'] = 'Sujet personnalisé';
$string['emailbody'] = 'Corps personnalisé';
$string['emailkeyactionrequired'] = 'Dossier en attente (directeur, DEVE, bibliothèque)';
$string['emailkeyreturned'] = 'Dossier renvoyé (étudiant ou directeur)';
$string['emailkeysubjectactive'] = 'Sujet enregistré, dossier ouvert (étudiant et directeur)';
$string['emailkeyconvocation'] = 'Convocation (étudiant et membres du jury)';
$string['emailkeyuniversity'] = "Transmission à l'université";
$string['emailkeyclosed'] = 'Dossier clôturé (étudiant et directeur)';
$string['emailkeyreminder'] = 'Relance';
$string['emailactionrequiredsubject'] = 'Thèse de {$a->student} : {$a->status}';
$string['emailactionrequiredbody'] = 'Bonjour,

Le dossier de thèse de {$a->student} ({$a->activity}) attend votre action : {$a->status}.

Sujet : {$a->title}

Accéder au dossier : {$a->url}';
$string['emailreturnedsubject'] = 'Thèse de {$a->student} : dossier renvoyé';
$string['emailreturnedbody'] = 'Bonjour,

Le dossier de thèse de {$a->student} ({$a->activity}) vous est renvoyé pour le motif suivant :

{$a->comment}

Sujet : {$a->title}

Accéder au dossier : {$a->url}';
$string['emailsubjectactivesubject'] = 'Thèse de {$a->student} : sujet enregistré';
$string['emailsubjectactivebody'] = 'Bonjour,

Le sujet de thèse de {$a->student} est enregistré par la DEVE et par la bibliothèque. Le dossier est ouvert : il restera actif jusqu\'au dépôt des documents de soutenance.

Sujet : {$a->title}

Accéder au dossier : {$a->url}';
$string['emailconvocationsubject'] = 'Convocation à la soutenance de thèse de {$a->student}';
$string['emailconvocationbody'] = 'Bonjour {$a->name},

Vous êtes convoqué(e) à la soutenance de la thèse de {$a->student}, intitulée « {$a->title} », le {$a->date}, {$a->location}.

Vous trouverez votre convocation en pièce jointe.';
$string['emailuniversitysubject'] = 'Soutenance de thèse de {$a->student}';
$string['emailuniversitybody'] = 'Bonjour,

Veuillez trouver ci-joint les documents de la soutenance de thèse de {$a->student} (dossier {$a->devenumber}), intitulée « {$a->title} », prévue le {$a->date}, {$a->location}.';
$string['emailclosedsubject'] = 'Thèse de {$a->student} : dossier clôturé';
$string['emailclosedbody'] = 'Bonjour,

Le procès-verbal de la soutenance de thèse de {$a->student} a été déposé. Résultat : {$a->result}.

Sujet : {$a->title}

Accéder au dossier : {$a->url}';
$string['emailremindersubject'] = 'Relance : thèse de {$a->student}';
$string['emailreminderbody'] = 'Bonjour,

Le dossier de thèse de {$a->student} ({$a->activity}) attend votre action depuis {$a->days} jours : {$a->status}.

Sujet : {$a->title}

Accéder au dossier : {$a->url}';

// Tâches et données personnelles.
$string['taskreminders'] = 'Relances des dossiers de thèse en attente';
$string['taskdigest'] = 'Récapitulatif hebdomadaire de la DEVE';
$string['emailkeydigest'] = 'Récapitulatif hebdomadaire de la DEVE';
$string['emaildigestsubject'] = 'Thèses : récapitulatif de la semaine ({$a->activity})';
$string['emaildigestbody'] = 'Bonjour,

Voici le récapitulatif de la semaine pour {$a->activity}.

Dossiers qui attendent la DEVE :
{$a->todo}

Signatures en attente depuis plus d\'une semaine :
{$a->signatures}

Soutenances des sept prochains jours :
{$a->defenses}

Conflits de jury ou de salle (quinze prochains jours) :
{$a->conflicts}

Tableau de bord : {$a->url}';
$string['digestnone'] = 'aucun';
$string['digestconflicts'] = 'conflits de jury : {$a->jury}, de salle : {$a->rooms}';
$string['settingsdigest'] = 'Récapitulatif hebdomadaire';
$string['settingsweeklydigest'] = 'Récapitulatif hebdomadaire';
$string['settingsweeklydigestlabel'] = 'Envoyer chaque lundi un récapitulatif à la DEVE';
$string['settingsweeklydigest_help'] = "Chaque lundi matin, un seul courriel à la DEVE (adresse de service ou membres de la DEVE) : dossiers qui l'attendent, signatures en attente depuis plus d'une semaine, soutenances des sept prochains jours et conflits de jury ou de salle des quinze prochains jours. Rien n'est envoyé s'il n'y a rien à signaler.";
$string['calendardefense'] = 'Soutenance de thèse : {$a}';
$string['staffactions'] = 'Interventions dans les dossiers de thèse';
$string['privacy:metadata:these_dossier'] = "Dossier de thèse d'un étudiant : sujet, soutenance et état du circuit.";
$string['privacy:metadata:these_dossier:userid'] = "Étudiant titulaire du dossier.";
$string['privacy:metadata:these_dossier:status'] = 'Étape du circuit atteinte.';
$string['privacy:metadata:these_dossier:title'] = 'Titre du sujet.';
$string['privacy:metadata:these_dossier:summary'] = 'Résumé du sujet.';
$string['privacy:metadata:these_dossier:keywords'] = 'Mots-clés du sujet.';
$string['privacy:metadata:these_dossier:directorid'] = 'Directeur de thèse.';
$string['privacy:metadata:these_dossier:devenumber'] = "Numéro d'enregistrement DEVE.";
$string['privacy:metadata:these_dossier:defensedate'] = 'Date de soutenance.';
$string['privacy:metadata:these_dossier:defenseresult'] = 'Résultat de la soutenance.';
$string['privacy:metadata:these_dossier:returncomment'] = 'Motif du dernier renvoi.';
$string['privacy:metadata:these_dossier:returnedby'] = 'Auteur du dernier renvoi.';
$string['privacy:metadata:these_dossier:timecreated'] = 'Date de création du dossier.';
$string['privacy:metadata:these_jury'] = 'Membres du jury proposés ou retenus pour une soutenance.';
$string['privacy:metadata:these_jury:fullname'] = 'Nom du membre du jury.';
$string['privacy:metadata:these_jury:email'] = 'Adresse de courriel du membre du jury.';
$string['privacy:metadata:these_jury:institution'] = 'Établissement du membre du jury.';
$string['privacy:metadata:these_jury:jurystatus'] = 'Statut (corps) du membre du jury.';
$string['privacy:metadata:these_a6'] = "Validation de l'A6 d'un étudiant, saisie par la DEVE et commune à toutes les activités.";
$string['privacy:metadata:these_a6:userid'] = "Étudiant.";
$string['privacy:metadata:these_a6:a6date'] = "Date de validation de l'A6 (0 si elle n'est pas validée).";
$string['privacy:metadata:these_a6:a6school'] = "École où l'A6 a été validée.";
$string['privacy:metadata:these_a6:usermodified'] = "Compte de la DEVE qui l'a saisie.";
$string['privacy:metadata:these_a6:timemodified'] = "Date de la saisie.";
$string['privacy:metadata:these_juror'] = "Annuaire des enseignants pouvant diriger une thèse ou siéger au jury, tenu par la DEVE (sans lien avec un compte Moodle).";
$string['privacy:metadata:these_juror:title'] = "Titre de l'enseignant (Dr ou Pr).";
$string['privacy:metadata:these_juror:lastname'] = "Nom de l'enseignant.";
$string['privacy:metadata:these_juror:firstname'] = "Prénom de l'enseignant.";
$string['privacy:metadata:these_juror:email'] = "Adresse de courriel de l'enseignant.";
$string['privacy:metadata:these_juror:status'] = "Statut (corps) de l'enseignant.";
$string['privacy:metadata:these_juror:institution'] = "Établissement de l'enseignant.";
$string['privacy:metadata:these_juror:discipline'] = "Matière enseignée par l'enseignant.";
$string['privacy:metadata:these_answer'] = "Réponses de l'étudiant au questionnaire préalable au jury.";
$string['privacy:metadata:these_answer:questionid'] = 'Question.';
$string['privacy:metadata:these_answer:value'] = 'Réponse.';
$string['privacy:metadata:these_answer:timemodified'] = 'Date de la réponse.';
$string['privacy:metadata:these_log'] = 'Historique des actions sur un dossier.';
$string['privacy:metadata:these_log:userid'] = "Auteur de l'action.";
$string['privacy:metadata:these_log:logaction'] = 'Action effectuée.';
$string['privacy:metadata:these_log:remark'] = 'Commentaire saisi avec l\'action.';
$string['privacy:metadata:these_log:timecreated'] = "Date de l'action.";
$string['privacy:metadata:core_files'] = 'Fichiers des dossiers : pièces jointes au sujet, manuscrit, documents de soutenance, rapport du directeur, documents générés, procès-verbal, manuscrit corrigé, signature enregistrée de l\'utilisateur.';
$string['privacy:metadata:jury'] = "Les membres du jury reçoivent par courriel leur convocation, qui mentionne l'étudiant, le sujet et la date de soutenance.";
$string['privacy:metadata:jury:student'] = "Nom de l'étudiant.";
$string['privacy:metadata:jury:title'] = 'Titre de la thèse.';
$string['privacy:metadata:jury:defensedate'] = 'Date et lieu de la soutenance.';

// Paramètres du circuit (activité « Suivi des thèses », sinon l'activité elle-même ; aucun réglage du site).
$string['settingsmenu'] = 'Paramètres';
$string['sharedsettingslink'] = 'Paramètres du circuit, modèles, grille et questionnaire ({$a})';
$string['bulkremind'] = 'Relancer les signataires';
$string['bulkgenerate'] = 'Générer les documents de soutenance';
$string['bulknotifyjury'] = 'Prévenir les membres du jury pas encore prévenus';
$string['bulknothing'] = 'Cochez au moins un dossier et choisissez une action.';
$string['bulkdone'] = '{$a->action} : {$a->done} dossier(s) traité(s), {$a->skipped} ignoré(s) (étape qui ne s\'y prête pas, ou rien en attente).';
$string['bulkfor'] = 'Pour les dossiers cochés :';
$string['bulkapply'] = 'Appliquer';
$string['settingschecktitle'] = 'Réglages à compléter : {$a}';
$string['settingscheckcomplete'] = 'Réglages complets.';
$string['settingscheckdone'] = 'Fait';
$string['settingschecktodo'] = 'À compléter';
$string['settingscheckoptional'] = 'Facultatif';
$string['settingscheckinstitution'] = 'Établissement et université';
$string['settingscheckdg'] = 'Direction générale : intitulé et nom';
$string['settingscheckccem'] = 'Président du CCEM';
$string['settingscheckucbl'] = 'Présidence de l\'université : intitulé et nom';
$string['settingscheckdeve'] = 'Nom de la direction des études';
$string['settingscheckcontract'] = 'Contrat type de diffusion électronique';
$string['settingscheckrooms'] = 'Salles de soutenance';
$string['settingscheckagents'] = 'Agents DEVE signataires';
$string['settingscheckquestions'] = 'Questionnaire préalable au jury';
$string['settingscheckimages'] = 'Images des modèles des documents';
$string['settingscheckimagesmissing'] = 'Images des modèles des documents (manquent : {$a})';
$string['settingsmissingnotice'] = 'Réglages du circuit à compléter : {$a}.';
$string['settingslibraryagent'] = 'Agents de la bibliothèque';
$string['settingslibraryagent_help'] = "Agents de la bibliothèque, choisis parmi les personnels inscrits aux cours des activités suivies (inscrits qui ne déposent pas de thèse) et les détenteurs du rôle de la bibliothèque : tapez un nom pour le retrouver. Sans rôle à attribuer, ils ont les droits de la bibliothèque (notice du sujet, conformité du manuscrit, corrections) et seulement ceux-là : ni boutons ni pages de la DEVE, même si leur rôle dans le cours les leur donnerait. Ils reçoivent les courriels adressés à la bibliothèque. Une personne absente de la liste doit d'abord être inscrite au cours.";
$string['settingsuniversitysigning'] = "Signatures de l'université";
$string['settingsuniversitypaper'] = "Mode de signature de l'université";
$string['settingsuniversitypaper_help'] = "Pour le président du CCEM et la présidence de l'université, sauf s'ils ont des délégataires internes (ci-dessous).<br>"
    . "<strong>Tout sur papier</strong> : aucun lien de signature ne leur est envoyé. Dès qu'un document n'attend plus que l'université, la DEVE le scelle et télécharge les pièces à envoyer ; au retour, elle dépose les scans signés, que le procès-verbal attend.<br>"
    . "<strong>En ligne</strong> : un lien de signature est envoyé par courriel à l'adresse indiquée dans les signataires des documents ; la DEVE peut toujours déposer un scan ou recueillir la signature sur place.";
$string['settingsuniversitypaperyes'] = 'Tout sur papier (pièces scellées envoyées, scans déposés au retour)';
$string['settingsuniversitypaperno'] = 'En ligne (lien de signature par courriel)';
$string['settingsccemagent'] = 'Délégataires internes du président du CCEM';
$string['settingsccemagent_help'] = "Comptes internes qui signent, par délégation, à la place du président du CCEM, depuis « Mes signatures », comme les agents DEVE pour la direction générale : la demande leur part à la validation du dossier de soutenance et n'importe lequel d'entre eux signe. Ils priment sur le mode de signature choisi ci-dessus. Laissez vide s'il n'y en a pas.";
$string['settingsucblagent'] = "Délégataires internes de la présidence de l'université";
$string['settingsucblagent_help'] = "Comptes internes qui signent, par délégation, à la place de la présidence de l'université, depuis « Mes signatures », comme les agents DEVE pour la direction générale : la demande leur part à la validation du dossier de soutenance et n'importe lequel d'entre eux signe. Ils priment sur le mode de signature choisi ci-dessus. Laissez vide s'il n'y en a pas.";
$string['agentuniversity'] = 'Délégataires — {$a}';
$string['settingsagentsnone'] = 'Aucun agent choisi';
$string['settingschecklibraryagents'] = 'Agents de la bibliothèque';
$string['scopesettings'] = 'Paramètres du circuit';
$string['scopesettings_desc'] = "Agents DEVE signataires et de la bibliothèque, établissement, université, signataires des documents et "
    . "contrat type de diffusion de cette activité. Aucun n'est un réglage du site.";
$string['scopesettingsshared_desc'] = "Agents DEVE signataires et de la bibliothèque, établissement, université, signataires des documents "
    . "et contrat type de diffusion communs à toutes les activités « Gestion des thèses » suivies. Aucun n'est un réglage du site.";

// Documents (paramètres du circuit).
$string['settingsdefenses'] = 'Soutenances';
$string['settingsdefenseduration'] = "Durée d'une soutenance (minutes)";
$string['settingsdefenseduration_help'] = "Sert à détecter les conflits de jury : un membre qui siège dans deux soutenances qui se chevauchent "
    . "est signalé dans le planning et sur le dossier. 60 minutes par défaut.";
$string['settingsdefenserooms'] = 'Salles de soutenance';
$string['settingsdefenserooms_help'] = 'Une salle par ligne. Elles sont proposées à la saisie du lieu (dépôt de l\'étudiant, validation et modification par la DEVE) ; la première est proposée par défaut. Un autre lieu reste possible.';
$string['settingsdefensedurationinvalid'] = 'Indiquez une durée d\'au moins une minute.';
$string['settingsdocuments'] = 'Documents et signataires';
$string['doctplimages'] = 'Images des modèles';
$string['doctplimages_help'] = "Images (PNG ou JPEG) utilisables dans les modèles de documents par {{img.nom}}, nom étant le nom du "
    . "fichier en minuscules, sans extension ni caractère spécial. Le module n'en fournit aucune : les modèles d'origine appellent "
    . "logo, ministere, universite, bibliotheque et abes, et une image non déposée est simplement omise.";
$string['doctplimageslots'] = "Images appelées par les modèles d'origine (PNG à fond transparent conseillé, environ 300 points par pouce) :";
$string['doctplimageslot'] = '{$a->file} : {$a->role} — {$a->state}';
$string['doctplimageslotlogo'] = "logo de l'établissement, en tête de tous les documents et du planning";
$string['doctplimageslotministere'] = 'bloc-marque Marianne du ministère de tutelle, en tête des documents officiels';
$string['doctplimageslotuniversite'] = "logo de l'université qui délivre le diplôme (fiche d'agréments, fiche signalétique)";
$string['doctplimageslotbibliotheque'] = 'logo de la bibliothèque universitaire (contrat de diffusion)';
$string['doctplimageslotabes'] = "logo de l'ABES (bordereau de thèse)";
$string['doctplimagepresent'] = 'déposée';
$string['doctplimagemissing'] = 'à déposer';
$string['settingsdevename'] = 'Direction des études : nom';
$string['settingsdevename_help'] = "Nom de la directrice ou du directeur des études et de la vie étudiante (fiche d'agréments).";
$string['settingsdevetitle'] = 'Direction des études : qualité';
$string['settingsdevetitle_help'] = "Qualité imprimée sur la fiche d'agréments (sans article).";
$string['settingscontracttemplate'] = 'Contrat type de diffusion électronique (PDF)';
$string['settingscontracttemplate_help'] = "Le contrat officiel (ENS-INI-P308-E04), tel quel. Le module le fait précéder d'une page "
    . "reprenant les données et les choix de l'auteur et suivre d'une page de signatures.";
$string['settingsinstitution'] = 'Établissement';
$string['settingsinstitution_help'] = 'Nom court utilisé dans les documents (« Visa de la Directrice de … », « Membre de … »).'
    . ' ' . "En-tête et signataires des documents de l'établissement produits par le module. Les signataires "
    . "sans compte Moodle (direction générale, CCEM, présidence de l'université) reçoivent par courriel un lien de signature si leur "
    . "adresse est renseignée ; sinon la DEVE recueille leur signature sur place.";
$string['settingsuniversity'] = 'Université';
$string['settingsuniversity_help'] = "Université qui délivre le diplôme, précédée de son article (« l'Université … ») : « Pour le président "
    . "de … et par délégation », bibliothèques universitaires du contrat de diffusion.";
$string['settingshetopurl'] = 'Lien HeTOP';
$string['settingshetopurl_help'] = 'Terminologie de référence pour les mots-clés du sujet.';
$string['settingsdgtitle'] = 'Direction générale (intitulé)';
$string['settingsdgtitle_help'] = 'Intitulé complet imprimé au-dessus de sa signature, par exemple « La Directrice générale de … ».';
$string['settingsdgname'] = 'Direction générale (nom)';
$string['settingsvisaplace'] = 'Lieu du visa';
$string['settingsvisaplace_help'] = '« Vu, … le » des conclusions, « Fait à » du contrat de diffusion.';
$string['settingsccemname'] = 'CCEM (nom)';
$string['settingsccememail'] = 'CCEM (courriel)';
$string['settingsccememail_help'] = 'Adresse à laquelle envoyer les liens de signature ; vide : signature recueillie sur place par la DEVE.';
$string['settingsucbltitle'] = 'Présidence de l\'université (intitulé)';
$string['settingsucbltitle_help'] = 'Intitulé complet imprimé au-dessus de sa signature (contrat de diffusion), par exemple « Le Président de l\'Université … ».';
$string['settingsucblname'] = 'Présidence de l\'université (nom)';
$string['settingsucblemail'] = 'Présidence de l\'université (courriel)';
$string['settingsucblemail_help'] = 'Adresse à laquelle envoyer les liens de signature ; vide : signature recueillie sur place par la DEVE.';

// Formulaire de dépôt de sujet.
$string['studyyear'] = "Année d'études";
$string['confidential'] = 'Thèse confidentielle, ne pas divulguer le titre';
$string['confidential_help'] = 'Le sujet d\'une thèse confidentielle (titre, titre anglais, résumé, mots-clés) n\'est connu que de l\'étudiant, de la DEVE, de la bibliothèque, du directeur de thèse et des membres du jury. La thèse se trouve et figure au planning comme les autres, mais, pour tous les autres (autres enseignants, personnels qui consultent tous les dossiers sans être la DEVE ni la bibliothèque), son titre, ses mots-clés et son résumé sont remplacés par « Thèse confidentielle » (pages, listes, planning, recherche, export, courriels), elle ne se trouve jamais par son sujet, et les documents qui portent le titre ne leur sont pas accessibles.';
$string['confidentialtitle'] = 'Thèse confidentielle';
$string['confidentialbadge'] = 'Thèse confidentielle : sujet connu de l\'étudiant, de la DEVE, de la bibliothèque, du directeur et du jury seulement';
$string['confidentialitylabel'] = 'Confidentialité';
$string['hetoplink'] = 'Rechercher les mots-clés dans HeTOP';
$string['keywordscount'] = 'Indiquez de 3 à 5 mots-clés, séparés par des points-virgules.';
$string['subjectapproval'] = 'Approbation du sujet';
$string['externaldirector'] = "Le directeur de thèse est extérieur à l'établissement";
$string['externaldirector_help'] = "Le directeur extérieur, sans compte Moodle, signe le formulaire depuis un lien reçu par courriel. "
    . "Choisissez alors ci-dessous, comme directeur dans le module, le membre de l'établissement (enseignant-chercheur) qui suit la "
    . "thèse : c'est lui qui valide les étapes et signe en qualité de membre.";
$string['directortitle'] = 'Titre du directeur';
$string['directortitle_help'] = "Dr, Pr… tel qu'il doit figurer sur les documents. Demandé seulement si le directeur ne figure pas "
    . "dans l'annuaire des enseignants, qui donne sinon son titre.";
$string['extdirectorname'] = 'Directeur extérieur : titre, nom, prénom';
$string['extdirectorgrade'] = 'Grade';
$string['extdirectorinstitution'] = 'Établissement';
$string['extdirectoremail'] = 'Adresse mail';
$string['commitments'] = "Engagement de l'étudiant";
$string['commitguide'] = 'Avoir pris connaissance du Guide des thèses';
$string['commitduplicate'] = "Avoir vérifié que le sujet n'a pas fait l'objet d'une thèse de doctorat vétérinaire dans l'une des écoles "
    . "et n'est pas celui d'une thèse en cours de rédaction";
$string['commitethics'] = "Si nécessaire : soumettre le protocole au Comité d'éthique avant le début des travaux";
$string['commitrequired'] = 'Cet engagement est obligatoire.';
$string['subjectnotice'] = "Un centre d'investigation de recherche clinique vétérinaire (CICV) peut vous proposer un accompagnement "
    . "méthodologique, réglementaire et logistique pour la recherche clinique. Pour les thèses dans le domaine des sciences humaines et "
    . "sociales, l'encadrement doit faire intervenir un spécialiste du domaine, qui participera au jury comme membre.";

// Signature.
$string['signature'] = 'Signature';
$string['signaturedraw'] = 'Tracez votre signature';
$string['signatureclear'] = 'Effacer';
$string['signername'] = 'Nom du signataire';
$string['signatureconsent'] = "Je certifie l'exactitude des informations et signe électroniquement le(s) document(s) ci-dessus.";
$string['signatureconsentrequired'] = 'Cochez la case pour signer.';
$string['signaturemissing'] = 'Tracez votre signature dans le cadre ci-dessus.';
$string['signsubjectintro'] = 'La soumission signe le formulaire de dépôt de sujet de thèse.';
$string['signdefenseintro'] = 'La soumission signe le bordereau de thèse et le contrat de diffusion électronique.';
$string['signreviewintro'] = 'La validation signe : {$a}.';
$string['signexternalintro'] = 'Consultez chaque document (liens ci-dessus) avant de signer : votre signature sera apposée sur tous.';
$string['signaturepending'] = 'En attente';
$string['signaturerequestedon'] = 'Demandée le {$a}';
$string['signedby'] = 'Signé par {$a->name} le {$a->date}';
$string['signeddocuments'] = 'Documents signés';
$string['signatures'] = 'Signatures';
$string['document'] = 'Document';
$string['downloadpdf'] = 'PDF';
$string['downloadscan'] = 'Scan';
$string['managesignatures'] = 'Suivi des signatures';
$string['externalsigners'] = 'Signatures à recueillir';
$string['signer'] = 'Signataire';
$string['noemail'] = 'pas d\'adresse';
$string['documentstosign'] = 'Documents à signer';
$string['resendsignature'] = 'Envoyer le lien';
$string['remindagents'] = 'Relancer';
$string['agentsreminded'] = 'Relance envoyée aux agents DEVE.';
$string['signinperson'] = 'Signer sur place';
$string['signaturerequestsent'] = 'Lien de signature envoyé.';
$string['signaturesrecorded'] = 'Signature enregistrée. Merci.';
$string['signpagetitle'] = 'Signature de documents de thèse';
$string['signinvalidtoken'] = "Ce lien n'est plus valide : les documents ont déjà été signés, une nouvelle invitation a été "
    . "envoyée, ou le lien a expiré (60 jours). Contactez la DEVE pour en recevoir un nouveau.";
$string['signnothingpending'] = 'Aucun document n\'attend de signature.';
$string['signdocuments'] = 'Signer les documents';

// Documents et emplacements de signature.
$string['docsubject'] = 'Dépôt de sujet de thèse';
$string['docbordereau'] = 'Bordereau de thèse (ABES)';
$string['docreport'] = 'Rapport du directeur';
$string['docgrid'] = 'Grille de conformité de la thèse';
$string['docagreement'] = "Fiche d'agréments administratif et scientifique";
$string['docsignaletique'] = 'Fiche signalétique';
$string['doccontract'] = 'Contrat de diffusion électronique';
$string['docconformity'] = 'Attestation de conformité (bibliothèque)';
$string['docconclusions'] = 'Conclusions';
$string['slotstudent'] = 'Étudiant';
$string['slotdirector'] = 'Directeur de thèse';
$string['slotextdirector'] = 'Directeur de thèse extérieur';
$string['slotdeve'] = 'DEVE';
$string['slotdgrep'] = 'Direction générale (représentée par la DEVE)';
$string['slotlibrary'] = 'Bibliothèque';
$string['slotpresident'] = 'Président du jury';
$string['slotdg'] = 'Direction générale';
$string['slotccem'] = 'Président du CCEM';
$string['slotucbl'] = "Présidence de l'université";

// Dépôt de soutenance : données de l'étudiant.
$string['visio'] = 'Visioconférence';
$string['identity'] = 'Identité et coordonnées';
$string['identityhelp'] = 'Ces informations alimentent le bordereau de thèse, la fiche signalétique et le contrat de diffusion.';
$string['bbirthname'] = 'Nom de naissance';
$string['bfirstnames'] = 'Prénom(s)';
$string['busagename'] = "Nom d'usage";
$string['bpublicationname'] = 'Nom figurant sur la publication (si différent)';
$string['bbirthdate'] = 'Date de naissance';
$string['bbirthplace'] = 'Lieu de naissance';
$string['bbirthdept'] = 'N° de département';
$string['bnationality'] = 'Nationalité';
$string['bstreet'] = 'Adresse pérenne (n° et rue)';
$string['bpostcode'] = 'Code postal';
$string['bcity'] = 'Ville';
$string['bcountry'] = 'Pays';
$string['bphone'] = 'Téléphone';
$string['bpersonalemail'] = 'Courriel personnel';
$string['bpersonalemail_help'] = "Adresse personnelle et pérenne, différente de celle de l'établissement.";
$string['bemailconsent'] = "J'accepte que mon courriel soit utilisé dans le cadre des accréditations et diffusé à des partenaires institutionnels";
$string['cursus'] = 'Cursus';
$string['bdefvdate'] = 'Obtention du DEFV le';
$string['bschool'] = 'à';
$string['schoolother'] = 'Autre établissement';
$string['ba6date'] = "Validation de l'année d'approfondissement (A6) le";
$string['ba6notyet'] = "Je n'ai pas encore validé mon année d'approfondissement (A6)";
$string['a6required'] = "Indiquez la date de validation de l'A6, ou cochez la case si elle n'est pas encore validée.";
$string['a6validation'] = "Validation de l'A6";
$string['a6notvalidated'] = 'non validée';
$string['a6bydeve'] = 'Saisie par la DEVE.';
$string['a6bydevestatus'] = '{$a} (saisie par la DEVE)';
$string['loga6recorded'] = "Validation de l'A6 enregistrée";
$string['loguniversitysealed'] = "Pièce scellée pour l'université";
$string['loguniversitydownloaded'] = "Pièces pour l'université téléchargées";
$string['prerequisitesmissing'] = "Étape bloquée : des documents des étapes précédentes attendent encore ces signatures.";
$string['prerequisitesuniversity'] = "Étape bloquée : les pièces envoyées à l'université ne sont pas revenues signées. Déposez leurs scans pour continuer.";
$string['validatedbyname'] = 'validé par {$a}';
$string['refusetitle'] = 'Refuser de signer';
$string['refusesign'] = 'Refuser de signer…';
$string['refuse_help'] = "Les documents refusés ne sont pas signés : le dossier revient, avec votre motif, à l'étape où ils ont été créés (l'étudiant pour le dépôt de sujet, la fiche signalétique, le contrat de diffusion et les conclusions ; la validation de la DEVE pour la fiche d'agréments). Plusieurs documents refusés : la première de ces étapes. L'équipe DEVE et le compte qui avait validé sont prévenus ; les signatures vous seront demandées de nouveau à la prochaine validation.";
$string['refusedocuments'] = 'Documents refusés';
$string['refusereason'] = 'Motif du refus';
$string['refusesubmit'] = 'Refuser et renvoyer le dossier';
$string['refusereturntostudent'] = "renvoi à l'étudiant";
$string['refusereturntodeve'] = 'renvoi à la validation de la DEVE';
$string['refusedone'] = 'Signature refusée : le dossier est maintenant « {$a} ».';
$string['refusenothing'] = "Aucun document de ce dossier ne peut être refusé : il n'attend plus votre signature ou l'étape suivante a déjà avancé.";
$string['refusalremark'] = 'Signature refusée par l\'agent DEVE : {$a->documents}. Motif : {$a->reason}';
$string['logagentrefused'] = "Signature refusée par l'agent DEVE";
$string['emailkeyagentrefused'] = "Signature refusée par l'agent DEVE";
$string['emailagentrefusedsubject'] = 'Thèse de {$a->student} : signature refusée';
$string['emailagentrefusedbody'] = 'Bonjour,

{$a->name} a refusé de signer, pour le dossier de thèse de {$a->student} ({$a->activity}) : {$a->document}.

Motif : {$a->comment}

Le dossier revient à : {$a->target}.

Dossier : {$a->url}';
$string['validatedbynameon'] = 'validé par {$a->name} le {$a->date}';
$string['pdfvalidatedbyon'] = 'documents validés par {$a->name} (DEVE) le {$a->date}';
$string['pdfvalidatedby'] = 'documents validés par {$a} (DEVE)';
$string['universitypieces'] = "Pièces pour l'université";
$string['universitypieces_help'] = "Ces documents n'attendent plus que les signatures de l'université. Téléchargez-les : ils sont scellés tels quels (empreinte inscrite à l'historique) pour être envoyés à l'université. À leur retour, déposez les scans signés : le procès-verbal attend ces signatures.";
$string['universitywaitingfor'] = 'en attente de : {$a}';
$string['universitysealedon'] = 'scellé le {$a}';
$string['universitydownload'] = "Sceller et télécharger les pièces pour l'université";
$string['universitydownloadagain'] = "Télécharger de nouveau les pièces pour l'université";
$string['universityreturn'] = "Pièces envoyées à l'université : à leur retour, déposez les scans signés, en cochant les signataires de l'université qu'ils couvrent.";
$string['universityaddscan'] = "Déposer les scans revenus de l'université";
$string['universitynothing'] = "Aucun document n'attend seulement l'université.";
$string['a6page_desc'] = "La validation de l'A6 est liée à l'étudiant, pas à son dossier : elle peut être saisie avant la création du dossier et vaut pour tous ses dossiers. L'agrément administratif et la fiche signalétique la reprennent. Modifiez un étudiant, cochez-en plusieurs pour leur enregistrer la même validation, ou importez un fichier.";
$string['a6edit'] = "Saisir l'A6";
$string['a6nodossier'] = 'Pas de dossier';
$string['a6stateall'] = 'Tous les étudiants';
$string['a6statenotvalidated'] = 'A6 non validée';
$string['a6statevalidated'] = 'A6 validée';
$string['a6searchstudent'] = 'Nom, courriel, numéro';
$string['a6nostudents'] = 'Aucun étudiant.';
$string['a6bulk'] = 'Pour les étudiants cochés :';
$string['a6bulknotyet'] = 'A6 pas encore validée';
$string['a6bulksubmit'] = 'Enregistrer la validation';
$string['a6bulkdaterequired'] = "Indiquez la date de validation de l'A6, ou cochez « A6 pas encore validée ».";
$string['a6bulkdone'] = 'Validation de l\'A6 enregistrée pour {$a} étudiant(s).';
$string['a6saved'] = "Validation de l'A6 enregistrée.";
$string['a6import'] = "Importer les validations de l'A6";
$string['a6dryrunresult'] = 'Simulation : {$a->ok} étudiant(s) à enregistrer, {$a->errors} ligne(s) en erreur.';
$string['a6result'] = 'Import terminé : {$a->ok} validation(s) enregistrée(s), {$a->errors} ligne(s) en erreur.';
$string['a6import_desc'] = "Un fichier Excel (.xlsx), OpenDocument (.ods) ou CSV, une ligne par étudiant, avec une ligne d'en-tête : colonne « etudiant » (identifiant, adresse de courriel ou numéro d'étudiant), colonne « date » (date de validation de l'A6, jj/mm/aaaa, ou « non » si elle n'est pas validée) et, facultative, colonne « ecole » (Lyon, Alfort, Nantes, Toulouse, Rouen). La validation de l'A6 est liée à l'étudiant, pas à son dossier : tout étudiant inscrit au cours peut figurer, qu'il ait déjà un dossier de thèse ou non, et elle vaudra pour tous ses dossiers. La simulation vérifie le fichier sans rien enregistrer.";
$string['a6file'] = 'Fichier des validations';
$string['a6file_help'] = "Excel (.xlsx), OpenDocument (.ods) ou CSV (séparateur point-virgule ou virgule). En-têtes reconnues : etudiant, date, ecole.";
$string['a6importformat'] = 'Format de fichier non reconnu : Excel (.xlsx), OpenDocument (.ods) ou CSV.';
$string['a6importunreadable'] = 'Le fichier ne peut pas être lu.';
$string['a6notenrolled'] = "L'étudiant n'est pas inscrit au cours de cette activité.";
$string['a6baddate'] = 'Date illisible : « {$a} » (jj/mm/aaaa, ou « non »).';
$string['a6badschool'] = 'École inconnue : « {$a} ».';
$string['a6wouldrecord'] = 'A6 validée le {$a} (simulation)';
$string['a6recordedon'] = 'A6 validée le {$a}';
$string['bnbvolumes'] = 'Nombre de volumes';
$string['bnbpages'] = 'Nombre total de pages';
$string['bnbrefs'] = 'Nombre de références bibliographiques';
$string['bsummaryfr'] = 'Résumé de la thèse en français';
$string['bsummaryen'] = 'Résumé de la thèse en anglais (facultatif)';
$string['bsummary'] = 'Résumé';
$string['bsummary_help'] = '1700 caractères maximum, espaces compris.';
$string['summarytoolong'] = 'Le résumé dépasse {$a} caractères.';
$string['bkeywords'] = 'Mots-clés (en français)';
$string['bkeywords_help'] = 'Identiques à ceux de la 4e page de couverture, 8 au maximum.';
$string['conclusionstext'] = 'Texte des conclusions';
$string['conclusionstext_help'] = "Les conclusions telles qu'elles figureront dans le manuscrit. Elles doivent tenir sur une page avec "
    . "les signatures (environ 2 200 caractères au plus ; séparer les paragraphes par une ligne vide). Elles seront signées par le "
    . "directeur, le président du jury, la direction générale et le président du CCEM.";
$string['conclusionstoolong'] = 'Les conclusions comptent {$a->length} caractères pour {$a->max} au plus : elles doivent tenir sur une page avec les signatures.';
$string['conclusionsonepage'] = "Les conclusions ne tiennent pas sur une page avec les signatures : raccourcissez-les ou réduisez le nombre de paragraphes.";
$string['contracthelp'] = "Choix de l'auteur pour le contrat de diffusion électronique de la thèse (articles 2 et 4). Une thèse "
    . "confidentielle (case cochée au dépôt du sujet) l'est jusqu'à la date indiquée.";
$string['contracttemplatemissing'] = 'Le contrat type de diffusion n\'a pas été déposé dans les paramètres du circuit : le contrat produit ne contient que les pages du module et ne sera pas scellé tant qu\'il manque.';
$string['contracttemplateunreadable'] = 'Le contrat type de diffusion déposé dans les paramètres du circuit n\'a pas pu être lu (format PDF non pris en charge) : le contrat produit ne contient que les pages du module et ne sera pas scellé. Déposez le contrat type enregistré dans un format PDF plus ancien (PDF 1.4).';
$string['bconfidentialuntil'] = "Confidentialité jusqu'au";
$string['bconfidentialuntil_help'] = 'Ne concerne que les thèses déclarées confidentielles au dépôt du sujet.';
$string['bembargo'] = 'Je demande un embargo de diffusion de {$a} ans à compter de la soutenance';
$string['embargo_help'] = 'Pendant l\'embargo, la thèse est enregistrée et signalée, mais son texte intégral n\'est ni diffusé sur internet ni communiqué à la consultation avant son terme, 5 ans après la date de soutenance. La demande doit être motivée (brevet, publication en cours, données sensibles, partenariat...). Elle figure dans le contrat de diffusion, que le directeur de thèse vise.';
$string['bembargoreason'] = 'Motif de la demande d\'embargo';
$string['bembargoreasonrequired'] = 'Indiquez le motif de la demande d\'embargo.';
$string['embargo'] = 'Embargo de diffusion';
$string['embargorequested'] = 'Demandé : {$a->years} ans après la soutenance, jusqu\'au {$a->until}';
$string['embargorequestednodate'] = 'Demandé : {$a} ans après la soutenance (date de soutenance à fixer)';
$string['embargoreason'] = 'Motif de l\'embargo';
$string['embargoexport'] = 'Embargo jusqu\'au';
$string['exportcodirectortype'] = 'Co-directeur interne ou extérieur';
$string['exportmissingsignatures'] = "Signatures manquantes pour passer à l'étape suivante";
$string['exportuniversitytoseal'] = 'à sceller et envoyer';
$string['exportlastrefusal'] = "Dernier refus d'un agent DEVE";
$string['diffusion'] = 'Diffusion';
$string['bdiffusion'] = 'Diffusion de la thèse';
$string['bdiffusion_help'] = "Diffusion en texte intégral sur internet (portail DUMAS, catalogue Sudoc, catalogues des bibliothèques), "
    . "ou consultation de la version papier dans les bibliothèques des écoles vétérinaires et diffusion sur l'intranet de "
    . "l'université uniquement.";
$string['diffusioninternet'] = "J'autorise la diffusion de la thèse en texte intégral sur internet";
$string['diffusionrestricted'] = "Je n'autorise pas la diffusion sur internet, mais uniquement la consultation papier et l'intranet";

// Étapes du directeur et de la DEVE.
$string['reporttext'] = 'Rapport du directeur';
$string['reporttext_help'] = "Argumentaire d'une page au plus présentant le travail de thèse (travail effectué, principaux résultats, "
    . "implication de l'étudiant…).";
$string['reportfavorable'] = "J'émets un avis favorable à la soutenance et à l'obtention du titre de Docteur vétérinaire";
$string['reportfavorablerequired'] = "L'avis favorable est nécessaire pour valider la soutenance.";
$string['ethicsnumber'] = "N° de l'avis favorable du comité d'éthique (si existant)";
$string['gridsectionfond'] = 'Conformité du fond';
$string['gridsectionforme'] = 'Conformité de la forme';
$string['gridsectionpdffond'] = 'Conformité du FOND';
$string['gridsectionpdfforme'] = 'Conformité de la  FORME';
$string['gridconditional'] = '(selon la thèse)';
$string['griditems'] = 'Grille de conformité';
$string['griditems_desc'] = "Éléments de la grille de conformité remplie par le directeur de thèse. Modifier un élément met à jour "
    . "les grilles en PDF (les documents de soutenance déjà générés le seront à leur prochaine génération) ; un élément supprimé "
    . "disparaît des grilles ; un élément ajouté s'applique aux grilles remplies ensuite. « Selon la thèse » : l'élément peut rester "
    . "non coché. Pour supprimer un élément, videz son libellé.";
$string['griditemsection'] = 'Rubrique';
$string['griditemlabel'] = 'Élément';
$string['griditemconditional'] = 'Selon la thèse (facultatif)';
$string['griditemadd'] = 'Ajouter {no} élément(s)';
$string['griditemssaved'] = 'La grille de conformité est enregistrée.';
$string['griditemsreset'] = "Rétablir la grille d'origine";
$string['gridrequired'] = 'Élément à valider.';
$string['gridstudenthelp'] = "Cochez chaque point que respecte votre manuscrit. Votre directeur vérifiera cette grille, la "
    . "complétera au besoin et la signera en validant votre soutenance.";
$string['gridstudentconfirm'] = "J'atteste que mon manuscrit respecte les points cochés de la grille de conformité";
$string['gridstudentconfirmrequired'] = "Attestez la grille de conformité avant de soumettre le dossier.";
$string['gridprefilledbystudent'] = "Grille préremplie et attestée par l'étudiant à son dépôt : vérifiez chaque point avant "
    . "de signer.";
$string['gridquestion'] = 'La question scientifique est clairement posée et identifiée.';
$string['gridanswer'] = 'La réponse bibliographique ou expérimentale est satisfaisante.';
$string['gridbiblio'] = 'La bibliographie est complète, pertinente et actuelle.';
$string['gridcritique'] = "L'analyse critique de la bibliographie est satisfaisante.";
$string['gridownwork'] = "Le travail présenté est bien celui de l'étudiant.";
$string['gridtool'] = "Si la thèse porte sur le développement d'un outil, d'un support pédagogique, d'un site internet etc. : la thèse "
    . "ne se restreint pas à la description technique du travail.";
$string['gridfrench'] = 'Expression française et orthographe satisfaisantes.';
$string['gridlanguage'] = "Rédaction en français. Ou si rédaction en anglais, au moins 15 % du manuscrit en français et existence de "
    . "résumés de chaque partie du manuscrit en français.";
$string['gridtyping'] = 'Dactylographie et pagination correctes.';
$string['gridthanks'] = 'Remerciements aux membres du jury et remerciements personnels sobres.';
$string['gridtoc'] = "Présence d'une table des matières.";
$string['gridstaff'] = 'Présence de la liste à jour du corps enseignant.';
$string['gridlists'] = 'Présence de la liste des abréviations, des figures, des tableaux, des annexes.';
$string['gridcovers'] = 'Pages de couverture conformes.';
$string['gridcitations'] = 'Citation des références dans le texte (au format de la bibliothèque universitaire).';
$string['gridbibliography'] = "Présence d'une bibliographie finale correctement formatée.";
$string['gridfigures'] = 'Numérotation, titres et légendes, qualité et lisibilité des figures et tableaux satisfaisants.';
$string['gridintroduction'] = 'Il existe une introduction exposant la problématique et le plan du manuscrit.';
$string['gridconclusion'] = 'Il existe une conclusion reprenant les points clés et proposant des perspectives.';
$string['gridabstract'] = "Le résumé n'est pas un copier-coller de la conclusion.";
$string['gridstructure'] = "Pour les thèses non bibliographiques, le travail est présenté sous la forme d'un document scientifique : "
    . "introduction, matériel et méthodes, résultats, discussion et conclusion.";
$string['agreementscientific'] = 'Agrément scientifique';
$string['agreementdirectortext'] = "En validant, vous déclarez avoir lu et corrigé la thèse de l'étudiant et en valider le contenu.";
$string['agreementmembertext'] = "Le directeur étant extérieur, vous déclarez en qualité de membre avoir corrigé la thèse et en attester "
    . "la forme par la grille de conformité ; le directeur extérieur signera l'agrément depuis le lien reçu par courriel.";
$string['agreementadmin'] = 'Agrément administratif';
$string['agdefvdate'] = 'DEFV obtenu le';
$string['agmobility'] = 'a validé sa mobilité internationale';
$string['aga6notyet'] = "n'a pas encore validé son année d'approfondissement (A6)";
$string['aga6date'] = "Année d'approfondissement (A6) validée le";
$string['agauthorized'] = "L'étudiant(e) est autorisé(e) à soutenir sa thèse de Doctorat vétérinaire.";

// Historique et courriels des signatures.
$string['logsigned'] = 'Document signé';
$string['logdocumentsealed'] = 'Document scellé';
$string['dossierchanged'] = 'Ce dossier a été modifié entre-temps (par une autre personne ou par un double envoi du formulaire) : l\'action n\'a pas été enregistrée. Rechargez le dossier pour voir son état actuel.';
$string['dossierbusy'] = 'Ce dossier est en cours de traitement par une autre action. Réessayez dans un instant.';
$string['logsignaturesinvalidated'] = 'Signatures invalidées (document modifié)';
$string['logsignaturerequested'] = 'Signature demandée';
$string['logsignedinperson'] = 'Signature recueillie sur place';
$string['logidentityverified'] = "Identité du signataire vérifiée par la DEVE";
$string['emailkeysignaturerequest'] = 'Demande de signature (signataires sans compte)';
$string['emailkeyagentsignature'] = 'Documents à signer par l\'agent DEVE';
$string['emailkeysubjectchanged'] = 'Changement de sujet';
$string['emailkeyadjourned'] = 'Thèse ajournée';
$string['emailkeyjurydesignation'] = 'Désignation d\'un membre du jury';
$string['emailjurydesignationsubject'] = 'Désignation au jury de thèse de {$a->student}';
$string['emailjurydesignationbody'] = 'Bonjour {$a->name},

Vous êtes désigné(e) comme {$a->role} du jury de la thèse d\'exercice vétérinaire de {$a->student}, intitulée « {$a->title} ».

Soutenance prévue : {$a->date}, {$a->location}.

Le manuscrit et les documents du dossier sont accessibles par ce lien personnel : {$a->url}

La convocation officielle vous parviendra séparément.';
$string['emailsignaturerequestsubject'] = 'Thèse de {$a->student} : document(s) à signer';
$string['emailsignaturerequestbody'] = 'Bonjour {$a->name},

Les documents suivants de la thèse de {$a->student}, intitulée « {$a->title} », attendent votre signature : {$a->document}.

Pour les consulter et les signer : {$a->url}

Ce lien vous est personnel et n\'est valable que pour une signature.';

// Libellés des documents PDF.
$string['pdfsignedby'] = 'Signé électroniquement par {$a->name} le {$a->date} — empreinte {$a->hash}';
$string['pdfsignedbyinperson'] = 'Signé sur place par {$a->name} le {$a->date}, identité vérifiée par la DEVE ({$a->verifier}) — empreinte {$a->hash}';
$string['pdfsignedbyscan'] = 'Signé à la main par {$a->name} le {$a->date}, scan joint au dossier par la DEVE ({$a->verifier}) — empreinte {$a->hash}';
$string['pdfdelegated'] = '{$a} (par délégation)';
$string['signeddelegated'] = 'par délégation';
$string['signedmethodinperson'] = 'sur place, identité vérifiée par la DEVE';
$string['signedmethodscan'] = 'à la main, scan joint au dossier';
$string['signidentity'] = "Vérification de l'identité du signataire";
$string['signidentity_desc'] = "Vérifiez l'identité de la personne présente (pièce d'identité) avant d'ouvrir la page de signature. "
    . "La vérification est inscrite à l'historique à votre nom, conservée avec la signature et imprimée sous elle.";
$string['signidentityverified'] = "Je certifie avoir vérifié l'identité du signataire (pièce d'identité)";
$string['signidentityopen'] = 'Ouvrir la page de signature';
$string['signinfoslot'] = 'Signature en qualité de';
$string['reviewsummary'] = 'Dossier examiné';
$string['reviewfulldossier'] = 'Dossier complet (cliquer pour déplier)';
$string['mysignaturesdossier'] = '{$a->student} — « {$a->title} »{$a->number}';
$string['signinfostudent'] = 'Étudiant';
$string['signinfotitle'] = 'Titre de la thèse';
$string['signinfonumber'] = 'N° DEVE';
$string['signinfodefense'] = 'Soutenance';
$string['signinfodocuments'] = 'Documents à consulter avant de signer';
$string['signidentityrequired'] = "Vérifiez l'identité du signataire avant d'enregistrer sa signature.";
$string['signidentityverifiedby'] = 'identité vérifiée par {$a}';
$string['scanslots'] = 'Signataires du scan';
$string['scansignaturesrecorded'] = '{$a} signature(s) inscrite(s) comme signée(s) à la main.';
$string['scanslots_desc'] = "Cochez les signatures portées sur le document scanné : elles sont inscrites comme signées à la main à la "
    . "date ci-dessus, le PDF du document et le suivi des signatures sont mis à jour.";
$string['pdfreportfavorable'] = "Avis favorable à la soutenance de la thèse et à l'obtention du titre de Docteur vétérinaire.";
$string['privacy:metadata:these_dossier:bordereau'] = "Données personnelles saisies avec la soutenance (état civil, adresse, courriel personnel, cursus, choix de diffusion).";
$string['privacy:metadata:these_dossier:conclusions'] = 'Conclusions de la thèse.';
$string['privacy:metadata:these_dossier:reporttext'] = 'Rapport du directeur.';
$string['privacy:metadata:these_signature'] = 'Signatures électroniques apposées sur les documents du dossier (image tracée conservée dans les fichiers).';
$string['privacy:metadata:these_signature:userid'] = 'Signataire.';
$string['privacy:metadata:these_signature:signername'] = 'Nom du signataire.';
$string['privacy:metadata:these_signature:signeremail'] = 'Adresse du signataire sans compte.';
$string['privacy:metadata:these_signature:ipaddress'] = 'Adresse IP au moment de la signature.';
$string['privacy:metadata:these_signature:timesigned'] = 'Date de signature.';
$string['privacy:metadata:these_signature:validatedby'] = "Compte de l'équipe DEVE qui a validé les documents signés par un agent.";
$string['directorfunction'] = 'Votre fonction';
$string['directorfunction_help'] = "Fonction imprimée dans l'agrément scientifique : « Je soussigné(e), NOM Prénom, fonction, déclare… » (par exemple : Maître de conférences, Professeur).";

// Modèles des documents.
$string['doctemplates'] = 'Modèles des documents';
$string['doctemplates_desc'] = "Chaque document produit par cette activité est composé à partir d'un modèle : du HTML, dans lequel les "
    . "données du dossier s'insèrent par des balises {{...}}. Les modèles d'origine reproduisent les formulaires de l'établissement ; "
    . "un modèle modifié peut à tout moment être ramené à son origine. Les documents déjà produits ne changent pas.";
$string['doctpldocument'] = 'Document';
$string['doctplcustomised'] = 'Modifié';
$string['doctploriginal'] = "Modèle d'origine";
$string['doctplpreview'] = 'Aperçu PDF';
$string['doctplreset'] = "Rétablir l'origine";
$string['doctplresetconfirm'] = "Rétablir le modèle d'origine du document « {\$a} » ? Les modifications seront perdues.";
$string['doctplresetdone'] = "Le modèle d'origine du document « {\$a} » est rétabli.";
$string['doctplsaved'] = 'Le modèle du document « {$a} » est enregistré.';
$string['doctplintro'] = "L'aperçu PDF s'ouvre dans un nouvel onglet avec des données fictives (dossier complet, signé à tous les "
    . "emplacements), sans enregistrer le modèle.";
$string['doctplcontent'] = 'Modèle';
$string['doctplcontent_help'] = "HTML interprété par TCPDF : tableaux (largeurs en mm, bordures par style « border »), styles en ligne, "
    . "images, saut de page par &lt;br pagebreak=\"true\" /&gt;.

* {{variable}} insère une valeur (échappée) ; {{{variable}}} insère du HTML (cases à cocher box.*, signatures sig.*, textes *html) ;
* {{#variable}}...{{/variable}} n'affiche le contenu que si la variable est vraie ou non vide, ou le répète pour chaque élément d'une liste
  (jury...) ; {{^variable}}...{{/variable}} l'affiche si elle est fausse ou vide ;
* la première ligne peut fixer la mise en page : &lt;!--@page margins=\"haut,droite,bas,gauche\" header=\"8\" footer=\"12\" font=\"freesans\" size=\"10\" lineheight=\"1.25\"--&gt; (mm, points) ;
* &lt;!--@header--&gt;, &lt;!--@body--&gt; et &lt;!--@footer--&gt; séparent l'en-tête et le pied de page, répétés sur chaque page, du contenu ;
  {{pagenumber}} et {{pagecount}} y donnent le numéro de page et le nombre de pages.";
$string['doctplerror'] = 'Ce modèle ne produit pas de document : {$a}';
$string['doctplvariables'] = 'Variables disponibles';
$string['doctplvariables_desc'] = "Exemples tirés des données fictives de l'aperçu. Les signatures sont vides tant que l'emplacement n'est "
    . "pas signé ; sigsrc.* donne la source d'image à utiliser dans &lt;img src=\"{{sigsrc.director}}\" height=\"10mm\" /&gt;.";
$string['doctplvariable'] = 'Variable';
$string['doctplexample'] = 'Exemple';
$string['doctplvarimage'] = 'Image, à utiliser dans <img src="...">';
$string['doctplvarlist'] = 'Liste (éléments : {$a})';
$string['doctplvarbool'] = 'Vrai / faux ({$a})';
$string['doctplvarhtml'] = 'HTML, à insérer par {{{...}}}';
$string['doctplsubject'] = 'Dépôt de sujet de thèse (E01)';
$string['doctplagreement'] = "Fiche d'agréments administratif et scientifique (E02)";
$string['doctplsignaletique'] = 'Fiche signalétique (E03)';
$string['doctplbordereau'] = 'Bordereau de thèse (ABES)';
$string['doctplreport'] = 'Rapport du directeur';
$string['doctplgrid'] = 'Grille de conformité';
$string['doctplconclusions'] = 'Conclusions';
$string['doctplcontractdata'] = "Contrat de diffusion (E04) : page des données et choix de l'auteur";
$string['doctplcontractsign'] = 'Contrat de diffusion (E04) : page de signatures';
$string['doctplconformity'] = 'Attestation de conformité (bibliothèque)';
$string['doctplconvocation'] = 'Convocation à la soutenance';
$string['doctpljurysheet'] = 'Feuille de jury';
$string['doctplminutes'] = 'Procès-verbal de soutenance';
$string['doctplplanning'] = 'Planning des soutenances';
$string['doctplteachinglist'] = 'Liste du corps enseignant';
$string['teachinglist'] = 'Liste du corps enseignant (PDF)';
$string['teachinglist_help'] = "Enseignants actifs de l'annuaire du jury dont l'établissement est celui des paramètres du circuit, groupés par statut, à jour au jour du téléchargement : la page est à inclure dans le manuscrit de la thèse.";
$string['teachinglistdefense'] = 'Liste à jour du corps enseignant à inclure dans votre manuscrit :';
$string['teachinggrouppr'] = 'Professeurs';
$string['teachinggrouppremerite'] = 'Professeurs émérites';
$string['teachinggroupprstag'] = 'Professeurs stagiaires';
$string['teachinggroupmcfhdr'] = 'Maîtres de conférences HDR';
$string['teachinggroupmcf'] = 'Maîtres de conférences';
$string['teachinggroupmcfemerite'] = 'Maîtres de conférences émérites';
$string['teachinggroupmcfstag'] = 'Maîtres de conférences stagiaires';
$string['teachinggroupir'] = 'Ingénieurs de recherche';

// Transfert des paramètres.
$string['transfersettings'] = 'Transfert des paramètres';
$string['transfersettings_desc'] = "Reprendre les paramètres d'une autre activité « Gestion des thèses » (nouveau cours, nouvelle année) "
    . "ou d'un fichier exporté depuis une autre plateforme, ou exporter ceux de cette activité. Les dossiers ne sont jamais transférés.";
$string['transferimportheading'] = 'Reprendre des paramètres';
$string['transferexportheading'] = 'Exporter les paramètres de cette activité';
$string['transferexport_desc'] = 'Le fichier (JSON) peut être repris dans une autre activité, sur cette plateforme ou une autre.';
$string['transfersource'] = 'Source';
$string['transfersourceactivity'] = 'Une autre activité de cette plateforme';
$string['transfersourcefile'] = 'Un fichier de paramètres exporté';
$string['transferactivity'] = 'Activité';
$string['transferfile'] = 'Fichier de paramètres (.json)';
$string['transfergroups'] = 'Paramètres';
$string['transfergroups_help'] = "Chaque groupe coché remplace en totalité celui de l'activité : un courriel ou un modèle personnalisé "
    . "ici mais pas dans la source revient à son texte d'origine. Les groupes non cochés ne changent pas.";
$string['transfergroupactivity'] = "Paramètres de l'activité (établissement, signataire, lieu, jury, adresses de service, relances)";
$string['transfergroupemails'] = 'Courriels personnalisés';
$string['transfergroupgriditems'] = 'Grille de conformité (éléments)';
$string['transferdonegriditems'] = '{$a} élément(s) de grille de conformité ;';
$string['transfergroupdoctemplates'] = 'Modèles des documents';
$string['transfergroupdocimages'] = 'Images des modèles';
$string['transferconfirmreplace'] = "Je confirme le remplacement des paramètres cochés de cette activité";
$string['transferconfirmrequired'] = 'Confirmez le remplacement pour continuer.';
$string['transferimport'] = 'Transférer';
$string['transferexport'] = 'Télécharger le fichier de paramètres';
$string['transfernogroup'] = 'Cochez au moins un groupe de paramètres.';
$string['transferbadfile'] = "Ce fichier n'est pas un fichier de paramètres de la Gestion des thèses.";
$string['transferdone'] = 'Paramètres repris de « {$a} » :';
$string['transferdoneactivity'] = "paramètres de l'activité remplacés ;";
$string['transferdoneemails'] = '{$a} courriel(s) personnalisé(s) ;';
$string['transferdonedoctemplates'] = '{$a} modèle(s) de document modifié(s) ;';
$string['transferdonedocimages'] = '{$a} image(s).';
$string['transferskipped'] = 'Éléments non repris :';
$string['transferskippedemail'] = 'courriel inconnu « {$a} »';
$string['transferskippeddoctemplate'] = 'document inconnu « {$a} »';
$string['transferskippedimage'] = 'image illisible ou en double « {$a} »';

// Paramètres partagés par une activité « Suivi des thèses ».
$string['sharedsettings'] = "Cette activité est suivie par l'activité « Suivi des thèses » {\$a->name}, où « {\$a->what} » "
    . 'est défini pour toutes les promotions suivies : modifiez-le depuis celle-ci.';
$string['doctemplatesshared_desc'] = 'Modèles des documents communs à toutes les activités « Gestion des thèses » suivies par cette '
    . "activité : chaque promotion produit ses documents à partir d'eux. Les balises et la mise en page sont celles décrites dans "
    . "le guide ; l'aperçu utilise des données fictives.";
$string['griditemsshared_desc'] = 'Éléments de la grille de conformité communs à toutes les activités « Gestion des thèses » suivies '
    . 'par cette activité. Modifier un élément met à jour les grilles PDF produites ensuite.';

// Questionnaire préalable au jury.
$string['questions'] = 'Questionnaire préalable au jury';
$string['questions_desc'] = "Questions posées à l'étudiant avant le dépôt de la soutenance : réponse courte, réponse longue, liste "
    . 'ou choix multiples. Les questions obligatoires conditionnent le dépôt ; les réponses figurent au dossier, sont '
    . 'communiquées au jury et servent à la recherche des thèses.';
$string['questionsshared_desc'] = 'Questions communes à toutes les activités « Gestion des thèses » suivies par cette activité, '
    . "posées à l'étudiant avant le dépôt de la soutenance. Les questions obligatoires conditionnent le dépôt ; les réponses "
    . 'servent à la recherche des thèses.';
$string['questionsnone'] = "Aucune question n'est définie.";
$string['questionadd'] = 'Ajouter une question';
$string['questionedit'] = 'Modifier la question';
$string['questiontype'] = 'Type de question';
$string['questionlabel'] = 'Question';
$string['questionoptions'] = 'Choix proposés';
$string['questionoptions_help'] = "Un choix par ligne. Une liste n'accepte qu'un choix, un QCM en accepte plusieurs.";
$string['questionoptionsrequired'] = 'Indiquez au moins deux choix, un par ligne.';
$string['questionrequired'] = 'Réponse obligatoire';
$string['questionsaved'] = 'Question enregistrée.';
$string['questiondeleted'] = 'Question supprimée.';
$string['questiondeleteconfirm'] = 'Supprimer la question « {$a->label} » et les {$a->answers} réponse(s) déjà données ?';
$string['qtypetext'] = 'Réponse courte';
$string['qtypetextarea'] = 'Réponse longue';
$string['qtypelist'] = 'Liste (un choix)';
$string['qtypemulti'] = 'QCM (plusieurs choix)';
$string['questionnaire'] = 'Questionnaire préalable au jury';
$string['questionnaire_desc'] = 'Répondez à ces questions avant de déposer la soutenance. Vos réponses figurent au dossier et sont '
    . 'communiquées au jury ; elles servent aussi à retrouver les thèses.';
$string['questionnairesaved'] = 'Réponses enregistrées.';
$string['questionnaireunanswered'] = 'Sans réponse';
$string['questionnairemissing'] = 'Questions obligatoires sans réponse : {$a}.';
$string['questionnairecomplete'] = 'Questionnaire rempli.';
$string['questionnairefill'] = 'Remplir le questionnaire';
$string['questionnairerequired'] = 'Remplissez le questionnaire préalable au jury avant de déposer la soutenance.';
$string['transfergroupquestions'] = 'Questionnaire préalable au jury (questions)';
$string['transferdonequestions'] = '{$a} question(s) ;';

// Rapport du directeur rédigé dans le module.
$string['writereport'] = 'Rédiger le rapport';
$string['writereport_desc'] = "Rédigez votre rapport sur le manuscrit : il est imprimé dans le rapport PDF, qui doit tenir sur une "
    . "page avec votre signature. Vous pouvez l'enregistrer en plusieurs fois et le prévisualiser ; vous le signerez en validant la "
    . 'soutenance.';
$string['reportsave'] = 'Enregistrer le rapport';
$string['reportsaved'] = 'Rapport enregistré.';
$string['reportnoteditable'] = "Le rapport ne peut être rédigé qu'avant la validation de la soutenance par le directeur.";
$string['reportnotwritten'] = 'Rapport non rédigé.';
$string['reportwrittenfavorable'] = 'Rapport rédigé, avis favorable.';
$string['reportwrittennotfavorable'] = 'Rapport rédigé, sans avis favorable.';
$string['reporttoolong'] = 'Le rapport compte {$a->length} caractères pour {$a->max} au plus : il doit tenir sur une page avec la signature.';
$string['reportonepage'] = 'Le rapport ne tient pas sur une page avec la signature : raccourcissez-le.';
$string['logreportsaved'] = 'Rapport du directeur enregistré';

// Procès-verbal : décisions du jury.
$string['minutesscan'] = 'Procès-verbal signé (scan)';
$string['minutesscan_help'] = 'Le procès-verbal signé par les membres du jury, scanné (PDF ou images), sur lequel le jury a coché ses '
    . 'décisions.';
$string['jurydecisions'] = 'Décisions du jury';
$string['jurydecisions_help'] = 'Reportez les décisions cochées par le jury sur le procès-verbal. Sans corrections, le dossier est '
    . "clos ; si le jury demande des corrections, l'étudiant dépose son manuscrit corrigé, que le directeur, la bibliothèque puis "
    . "la DEVE valident avant la clôture. Une thèse ajournée revient en phase de soutenance : l'étudiant dépose un nouveau dossier "
    . 'de soutenance, qui suit de nouveau tout le circuit.';
$string['mentionhonorable'] = 'Honorable';
$string['mentiontreshonorable'] = 'Très honorable';
$string['mentionfelicitations'] = 'Très honorable avec félicitations du jury';
$string['prizeproposal'] = 'Proposition pour un prix de thèse';
$string['correctionsrequested'] = 'Corrections demandées par le jury';
$string['recordminutes'] = 'Enregistrer le procès-verbal';
$string['correctionsopened'] = "Procès-verbal enregistré : l'étudiant est invité à déposer son manuscrit corrigé.";

// Circuit des corrections.
$string['corrections'] = 'Corrections';
$string['correctionsnote'] = 'Corrections demandées';
$string['correctionsnote_help'] = 'Corrections demandées par le jury, telles que portées au procès-verbal : elles sont communiquées à '
    . "l'étudiant.";
$string['correctionsdone'] = 'Corrections apportées';
$string['correctionsdone_help'] = 'Décrivez les corrections apportées au manuscrit, point par point.';
$string['correctedmanuscript'] = 'Manuscrit corrigé';
$string['correctedmanuscriptrequired'] = 'Le manuscrit corrigé est obligatoire.';
$string['depositcorrections'] = 'Déposer les corrections';
$string['correctionsnotexpected'] = "Aucune correction n'est attendue pour ce dossier.";
$string['correctionssubmittedmsg'] = 'Corrections déposées : votre directeur de thèse est prévenu.';
$string['statuscorrections'] = 'Corrections demandées par le jury';
$string['statuscorrectionssubmitted'] = 'Corrections déposées, en attente du directeur';
$string['statuscorrectionsapproved'] = 'Corrections validées par le directeur, en attente de la bibliothèque';
$string['statuscorrectionsconform'] = 'Corrections validées par la bibliothèque, en attente de la DEVE';
$string['stepcorrections'] = 'Corrections éventuelles';
$string['reviewdirectorcorrections'] = 'Valider les corrections (directeur)';
$string['reviewdirectorcorrections_help'] = "Vérifiez que le manuscrit corrigé répond aux corrections demandées par le jury. Vous pouvez "
    . "aussi le renvoyer à l'étudiant avec vos commentaires.";
$string['reviewlibrarycorrections'] = 'Valider les corrections (bibliothèque)';
$string['reviewlibrarycorrections_help'] = 'Vérifiez la forme du manuscrit corrigé avant son dépôt définitif. Un manuscrit non conforme '
    . "est renvoyé à l'étudiant avec le motif.";
$string['reviewdevecorrections'] = 'Valider les corrections et clore le dossier (DEVE)';
$string['reviewdevecorrections_help'] = "La validation des corrections clôt le dossier ; l'étudiant et son directeur sont prévenus.";
$string['validatedirectorcorrections'] = 'Valider les corrections';
$string['validatelibrarycorrections'] = 'Valider les corrections';
$string['validatedevecorrections'] = 'Valider et clore le dossier';
$string['logminutescorrections'] = 'Procès-verbal déposé, corrections demandées';
$string['logcorrectionssubmitted'] = 'Manuscrit corrigé déposé';
$string['logcorrectionsresubmitted'] = 'Manuscrit corrigé déposé de nouveau';
$string['logvalidateddirectorcorrections'] = 'Corrections validées par le directeur';
$string['logvalidatedlibrarycorrections'] = 'Corrections validées par la bibliothèque';
$string['logvalidateddevecorrections'] = 'Corrections validées par la DEVE, dossier clos';
$string['privacy:metadata:these_dossier:correctionsdone'] = "Corrections apportées, décrites par l'étudiant.";

// Ajournement.
$string['minutesadjourned'] = "Procès-verbaux d'ajournement";
$string['adjournednotice'] = "Soutenance ajournée le {\$a} : le dossier est revenu en phase de soutenance, en attente d'un nouveau "
    . "dépôt de l'étudiant.";
$string['adjournednoticestudent'] = 'Votre soutenance a été ajournée le {$a}. Déposez un nouveau dossier de soutenance (manuscrit '
    . 'retravaillé, date, jury) : il sera de nouveau validé par votre directeur, la DEVE et la bibliothèque.';
$string['logadjourned'] = 'Procès-verbal déposé, soutenance ajournée : retour en phase de soutenance';
$string['logsignedscan'] = 'Scan signé déposé hors du module';
$string['scanlogremark'] = 'Document : {$a->doctype}. Signataires : {$a->signer}. Empreinte SHA-256 : {$a->files}.';
$string['signedscans'] = 'Scans signés hors module';
$string['addsignedscan'] = 'Ajouter un scan signé';
$string['recordscan'] = 'Enregistrer le scan';
$string['scandoctype'] = 'Document signé';
$string['scandoctypesubject'] = 'Dépôt de sujet (E01)';
$string['scandoctypeagreement'] = "Fiche d'agréments (E02)";
$string['scandoctypesignaletique'] = 'Fiche signalétique (E03)';
$string['scandoctypecontract'] = 'Contrat de diffusion (E04)';
$string['scandoctypeconclusions'] = 'Conclusions';
$string['scansigneddate'] = 'Date de la signature';
$string['scanfiles'] = 'Scans signés (PDF ou images)';
$string['scanfiles_help'] = 'Le document signé à la main par le signataire, scanné. Il est joint au dossier pour conservation : il ne remplace pas la signature du module et ne peut pas être modifié ensuite.';
$string['scanremark'] = 'Commentaire';
$string['scanrequired'] = 'Déposez au moins un scan.';
$string['scanunavailable'] = "Les scans signés se déposent une fois le dossier validé par la DEVE, et tant qu'il n'est pas abandonné.";
$string['scanrecorded'] = '{$a} fichier(s) joint(s) au dossier, empreinte(s) inscrite(s) à l\'historique.';
$string['emailadjournedsubject'] = 'Thèse de {$a->student} : soutenance ajournée';
$string['emailadjournedbody'] = 'Bonjour,

La soutenance de thèse de {$a->student} a été ajournée par le jury. Le dossier revient en phase de soutenance : un nouveau dossier de soutenance (manuscrit retravaillé, date, jury) devra être déposé, puis validé de nouveau par le directeur, la DEVE et la bibliothèque.

Sujet : {$a->title}

Accéder au dossier : {$a->url}';

// Agent DEVE signataire et signature enregistrée.
$string['agentdeve'] = 'Agent responsable de la DEVE';
$string['settingsdeveagent'] = 'Agents DEVE signataires';
$string['settingsdeveagent_help'] = "Agents habilités à signer pour la DEVE, choisis parmi les membres de la DEVE inscrits "
    . "aux cours des activités suivies (droit de la DEVE ou rôle de la DEVE) : tapez un nom pour le retrouver. L'équipe de la "
    . "DEVE remplit et vérifie le dossier puis valide sans signer ; chaque agent reçoit alors un courriel et retrouve dans « Mes "
    . "signatures » le visa du sujet, l'agrément administratif et les documents de la direction générale (fiche signalétique, "
    . "contrat de diffusion, conclusions), qu'il signe par délégation : la direction générale ne signe jamais elle-même. Le "
    . "premier agent qui signe appose la signature, avec sa signature enregistrée. Sans agent, la personne de la DEVE qui valide "
    . "signe le visa et l'agrément, et les documents de la direction générale se signent sur place ou par scan.";
$string['mysignatures'] = 'Mes signatures';
$string['mysignatures_desc'] = 'Documents qui attendent votre signature, toutes promotions confondues. Consultez chaque document en '
    . "PDF, cochez ceux à signer, puis signez une seule fois : votre signature enregistrée est reprise d'office.";
$string['mysignaturescount'] = 'Mes signatures ({$a})';
$string['signmyintro'] = 'Votre signature est apposée sur chaque document coché.';
$string['signnodocument'] = 'Cochez au moins un document.';
$string['signpreview'] = 'voir le PDF';
$string['signaturesrecordedcount'] = '{$a} document(s) signé(s).';
$string['signaturesaved'] = 'Signature enregistrée';
$string['signatureusesaved'] = 'Utiliser ma signature enregistrée';
$string['signaturesave'] = 'Enregistrer cette signature pour mes prochaines signatures';
$string['signaturesaveddelete'] = 'Supprimer ma signature enregistrée';
$string['signaturesaveddeleted'] = 'Signature enregistrée supprimée.';
$string['signaturesavednone'] = "Aucune signature enregistrée : cochez « Enregistrer cette signature » lors de votre prochaine "
    . 'signature pour ne plus avoir à la tracer.';
$string['emailagentsignaturesubject'] = 'Documents à signer : thèse de {$a->student}';
$string['emailagentsignaturebody'] = 'Bonjour {$a->name},

La DEVE a vérifié le dossier de thèse de {$a->student} ({$a->activity}). Les documents suivants attendent votre signature : {$a->document}.

Sujet : {$a->title}

Signer depuis « Mes signatures » : {$a->url}';

// Changement de sujet.
$string['subjectchange'] = 'Changer de sujet de thèse';
$string['subjectchange_desc'] = "Expliquez pourquoi vous changez de sujet. L'ancien sujet est archivé dans le dossier ; votre ancien "
    . 'directeur, la DEVE et la bibliothèque sont prévenus. Le nouveau sujet est de nouveau validé par le directeur (vous pouvez en '
    . 'choisir un autre), enregistré par la DEVE puis par la bibliothèque.';
$string['subjectchangereason'] = 'Motif du changement';
$string['subjectchangesubmit'] = 'Soumettre le nouveau sujet';
$string['subjectchangecurrent'] = 'Sujet actuel : {$a}';
$string['subjectchangenotallowed'] = "Le sujet ne peut être changé que lorsque le dossier est actif (sujet enregistré, soutenance non "
    . 'déposée).';
$string['subjectchangedmsg'] = 'Nouveau sujet soumis : votre directeur de thèse est prévenu, ainsi que la DEVE et la bibliothèque.';
$string['subjectchangelog'] = 'Ancien sujet : {$a->old}. Motif : {$a->reason}';
$string['subjecthistory'] = 'Sujets précédents';
$string['subjectarchive'] = 'Dépôt de sujet archivé';
$string['logsubjectchanged'] = 'Changement de sujet';
$string['emailsubjectchangedsubject'] = 'Thèse de {$a->student} : changement de sujet';
$string['emailsubjectchangedbody'] = 'Bonjour,

{$a->student} ({$a->activity}) a changé de sujet de thèse.

Ancien sujet : {$a->oldtitle}
Nouveau sujet : {$a->title}
Motif : {$a->reason}

L\'ancien sujet est archivé dans le dossier. Le nouveau sujet va être validé par le directeur de thèse, puis enregistré par la DEVE et la bibliothèque.

Accéder au dossier : {$a->url}';
$string['privacy:metadata:these_subjecthistory'] = 'Sujets précédents, archivés à chaque changement de sujet.';
$string['privacy:metadata:these_subjecthistory:title'] = 'Titre du sujet précédent.';
$string['privacy:metadata:these_subjecthistory:summary'] = 'Résumé du sujet précédent.';
$string['privacy:metadata:these_subjecthistory:reason'] = 'Motif du changement.';
$string['privacy:metadata:these_subjecthistory:changedby'] = 'Auteur du changement.';
$string['privacy:metadata:these_subjecthistory:timecreated'] = 'Date du changement.';
