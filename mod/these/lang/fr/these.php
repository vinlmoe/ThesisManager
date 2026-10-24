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
$string['minjury_help'] = "Nombre minimal de membres votants (président compris, invités exclus) exigé pour la composition du jury, "
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
$string['dormantphase'] = 'dossier dormant';

// Navigation et listes.
$string['dashboard'] = 'Tableau de bord';
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
$string['codirector_help'] = "Facultatif. Nom et qualité du co-directeur éventuel, qui peut être extérieur à l'établissement. Il ne "
    . "valide pas le dossier dans le module.";
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
$string['devenumber_help'] = "Numéro d'ordre attribué au sujet. Le numéro proposé est le suivant de l'année en cours (format AAAA-NNN) ; "
    . "il peut être modifié, mais doit rester unique dans l'activité.";
$string['devenumbertaken'] = 'Ce numéro est déjà attribué à un autre dossier.';
$string['libraryref'] = 'Référence de la notice bibliographique';

// Soutenance.
$string['defense'] = 'Soutenance';
$string['depositdefense'] = 'Déposer les documents de soutenance';
$string['defenseintro'] = "Déposez votre manuscrit et les documents de soutenance, et proposez une date, un lieu et la composition du "
    . "jury (un président et au moins {\$a} membres votants, président compris). Vous pouvez préparer ce dépôt en plusieurs fois "
    . "(« Enregistrer le brouillon ») ; la soumission l'envoie à votre directeur, qui le validera en y joignant son rapport.";
$string['defensedocuments'] = 'Documents de soutenance';
$string['manuscript'] = 'Manuscrit (PDF)';
$string['manuscript_help'] = 'Version complète du manuscrit, en un seul fichier PDF.';
$string['manuscriptrequired'] = 'Le manuscrit est obligatoire pour soumettre.';
$string['defensedocs'] = 'Autres documents de soutenance';
$string['defensedocs_help'] = 'Facultatif : résumé, autorisation de diffusion, annexes...';
$string['defenseproposal'] = 'Proposition de soutenance';
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
$string['mention_help'] = 'Facultatif : mention ou félicitations du jury, telles que portées au procès-verbal.';
$string['minutes'] = 'Procès-verbal de soutenance';

// Jury.
$string['jury'] = 'Jury';
$string['juryhelp'] = "Une ligne par membre. Choisissez de préférence l'enseignant dans l'annuaire du jury : son titre, son "
    . "statut, son établissement et son adresse en sont repris. Pour un membre extérieur ou un invité, choisissez « Membre extérieur "
    . "ou invité » et renseignez la ligne (le statut est demandé pour un membre votant). Seuls les maîtres de conférences HDR, les "
    . "professeurs et les émérites peuvent présider. La DEVE valide ensuite l'éligibilité de chaque membre ; les membres sont alors "
    . "prévenus par courriel et reçoivent un lien d'accès aux documents du dossier.";
$string['juryrole'] = 'Rôle';
$string['juryrolepresident'] = 'Président';
$string['juryroledirector'] = 'Directeur (assesseur)';
$string['juryrolemember'] = 'Assesseur';
$string['juryroleguest'] = 'Membre invité';
$string['juryname'] = 'Nom';
$string['jurytitle'] = 'Titre ou qualité';
$string['juryinstitution'] = 'Établissement';
$string['juryemail'] = 'Courriel';
$string['juryaddmembers'] = 'Ajouter des lignes';
$string['juryonepresident'] = 'Le jury doit comporter un et un seul président.';
$string['juryminmembers'] = 'Le jury doit comporter au moins {$a} membres votants (président compris, invités exclus).';
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
$string['juryeligiblehelp'] = "Cochez l'éligibilité de chaque membre votant après vérification (déjà cochée pour les enseignants "
    . "de l'annuaire dont le statut convient à leur rôle). À la validation, les membres du jury sont prévenus par courriel et "
    . "reçoivent un lien d'accès aux documents du dossier.";
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
$string['juryaccessinvalid'] = "Ce lien d'accès n'est pas (ou plus) valide. Contactez la DEVE.";
$string['juryaccessdocuments'] = 'Documents';
$string['juryaccessnodocuments'] = "Aucun document n'est encore disponible.";

// Annuaire du jury.
$string['jurors'] = 'Annuaire du jury';
$string['jurors_desc'] = "Enseignants pouvant diriger une thèse ou siéger au jury, communs à toutes les activités et à toutes les "
    . "promotions. Les formulaires de jury y puisent pour être préremplis. Un enseignant retiré (inactif) n'est plus proposé mais "
    . "reste dans les jurys qui l'ont retenu.";
$string['jurortitle'] = 'Titre';
$string['juroractive'] = 'Proposé dans les formulaires de jury';
$string['juroractiveyes'] = 'Actif';
$string['juroractiveno'] = 'Retiré';
$string['jurorpresident'] = 'Peut présider';
$string['juroradd'] = 'Ajouter un enseignant';
$string['juroredit'] = 'Modifier un enseignant';
$string['jurorsaved'] = "L'enseignant est enregistré.";
$string['jurordeleted'] = "L'enseignant est supprimé de l'annuaire.";
$string['jurordeleteconfirm'] = "Supprimer {\$a} de l'annuaire ? Les jurys qui l'ont retenu le gardent.";
$string['jurorsnone'] = "L'annuaire est vide.";
$string['jurorimport'] = 'Importer un fichier CSV';
$string['jurorimportfile'] = 'Fichier CSV';
$string['jurorimportfile_help'] = "Colonnes : title (Dr ou Pr), lastname, firstname, email, status, institution, séparées par des "
    . "virgules ou des points-virgules. Le statut est la clé (ir, mcf, mcfhdr, mcfstag, prstag, pr, premerite, mcfemerite) ou le "
    . "libellé. Un enseignant déjà présent (même adresse, ou mêmes nom et prénom) est mis à jour.";
$string['jurorimportdone'] = '{$a->created} enseignant(s) ajouté(s), {$a->updated} mis à jour.';
$string['jurorimportbadline'] = 'Ligne {$a} ignorée : titre, nom, prénom, statut ou adresse incorrect.';
$string['jurysheet'] = 'Feuille de jury';

// Étapes de validation.
$string['reviewdirectorsubject'] = 'Examiner la proposition de sujet';
$string['reviewdirectorsubject_help'] = "Validez le sujet pour qu'il soit enregistré par la DEVE, ou renvoyez-le à l'étudiant avec vos "
    . "commentaires : il pourra le corriger et vous le soumettre de nouveau.";
$string['reviewdevesubject'] = 'Enregistrer le sujet (DEVE)';
$string['reviewdevesubject_help'] = "Attribuez le numéro d'enregistrement : le sujet est ensuite transmis à la bibliothèque. Un renvoi "
    . "peut être adressé à l'étudiant ou au directeur ; à sa nouvelle soumission, le dossier revient directement ici.";
$string['reviewlibrarysubject'] = 'Enregistrer le sujet (bibliothèque)';
$string['reviewlibrarysubject_help'] = "Indiquez la référence de la notice créée dans le référentiel documentaire. L'enregistrement ouvre "
    . "le dossier, qui reste actif jusqu'au dépôt des documents de soutenance.";
$string['reviewdirectordefense'] = 'Valider la soutenance et déposer le rapport';
$string['reviewdirectordefense_help'] = "Validez la proposition de soutenance (date, jury) en déposant votre rapport sur le manuscrit, en "
    . "une seule étape. Vous pouvez aussi renvoyer le dossier à l'étudiant avec vos commentaires.";
$string['reviewdevedefense'] = 'Valider le dossier de soutenance (DEVE)';
$string['reviewdevedefense_help'] = "Vérifiez le dossier complet (manuscrit, documents, rapport du directeur) et arrêtez la date, le lieu "
    . "et le jury. La validation transmet le manuscrit à la bibliothèque pour contrôle de conformité.";
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
$string['editdossierhelp'] = "Les modifications n'ont aucun effet sur le statut du dossier. Si la date, le lieu ou le jury changent "
    . "après la génération des documents de soutenance, ceux-ci seront à régénérer.";
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
$string['importhelp'] = "Reprise de sujets déjà enregistrés hors du module : chaque ligne crée un dossier actif. Colonnes reconnues "
    . "(ligne d'en-tête) : <code>student</code> et <code>title</code> (obligatoires), <code>titleen</code>, <code>summary</code>, "
    . "<code>keywords</code>, <code>director</code>, <code>codirector</code>, <code>devenumber</code>, <code>libraryref</code>. "
    . "Étudiant et directeur sont désignés par identifiant de connexion, adresse de courriel ou numéro d'identification.";
$string['csvfile'] = 'Fichier CSV';
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

// Export.
$string['export'] = 'Export du registre';
$string['exporthelp'] = 'Un dossier par ligne : numéro, étudiant, sujet, directeur, statut, soutenance, jury et résultat.';
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

Le sujet de thèse de {$a->student} est enregistré par la DEVE (numéro {$a->devenumber}) et par la bibliothèque. Le dossier est ouvert : il restera actif jusqu\'au dépôt des documents de soutenance.

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
$string['privacy:metadata:these_juror'] = "Annuaire des enseignants pouvant diriger une thèse ou siéger au jury, tenu par la DEVE (sans lien avec un compte Moodle).";
$string['privacy:metadata:these_juror:title'] = "Titre de l'enseignant (Dr ou Pr).";
$string['privacy:metadata:these_juror:lastname'] = "Nom de l'enseignant.";
$string['privacy:metadata:these_juror:firstname'] = "Prénom de l'enseignant.";
$string['privacy:metadata:these_juror:email'] = "Adresse de courriel de l'enseignant.";
$string['privacy:metadata:these_juror:status'] = "Statut (corps) de l'enseignant.";
$string['privacy:metadata:these_juror:institution'] = "Établissement de l'enseignant.";
$string['privacy:metadata:these_answer'] = "Réponses de l'étudiant au questionnaire préalable au jury.";
$string['privacy:metadata:these_answer:questionid'] = 'Question.';
$string['privacy:metadata:these_answer:value'] = 'Réponse.';
$string['privacy:metadata:these_answer:timemodified'] = 'Date de la réponse.';
$string['privacy:metadata:these_log'] = 'Historique des actions sur un dossier.';
$string['privacy:metadata:these_log:userid'] = "Auteur de l'action.";
$string['privacy:metadata:these_log:logaction'] = 'Action effectuée.';
$string['privacy:metadata:these_log:remark'] = 'Commentaire saisi avec l\'action.';
$string['privacy:metadata:these_log:timecreated'] = "Date de l'action.";
$string['privacy:metadata:core_files'] = 'Fichiers des dossiers : pièces jointes au sujet, manuscrit, documents de soutenance, rapport du directeur, documents générés, procès-verbal.';
$string['privacy:metadata:jury'] = "Les membres du jury reçoivent par courriel leur convocation, qui mentionne l'étudiant, le sujet et la date de soutenance.";
$string['privacy:metadata:jury:student'] = "Nom de l'étudiant.";
$string['privacy:metadata:jury:title'] = 'Titre de la thèse.';
$string['privacy:metadata:jury:defensedate'] = 'Date et lieu de la soutenance.';

// Rôles des services (réglages du site).
$string['settingsroles'] = 'Rôles des services';
$string['settingsroles_desc'] = "Comme la DEVE est reconnue au rôle « Enseignant », la bibliothèque est reconnue au rôle choisi ici. "
    . "Le rôle peut être attribué dans le cours, dans sa catégorie ou au niveau du site (il donne alors accès à toutes les activités "
    . "de gestion des thèses sans inscription). Les capacités du module restent également prises en compte.";
$string['deverole'] = 'Rôle DEVE';
$string['deverole_desc'] = 'Rôle dont les détenteurs agissent comme DEVE dans les activités de gestion des thèses.';
$string['libraryrole'] = 'Rôle Bibliothèque';
$string['libraryrole_desc'] = "Rôle dont les détenteurs agissent comme bibliothèque. Par défaut, le rôle interne « Bibliothèque "
    . "(thèses) » créé à l'installation du module.";
$string['libraryrolename'] = 'Bibliothèque (thèses)';
$string['libraryroledescription'] = "Rôle interne du module Gestion des thèses : enregistrement des sujets au référentiel documentaire et "
    . "attestation de conformité des manuscrits. À attribuer au niveau du site, d'une catégorie ou d'un cours.";

// Documents (réglages du site).
$string['settingsdocuments'] = 'Documents et signataires';
$string['settingsdocuments_desc'] = "En-tête et signataires des documents de l'établissement produits par le module. Les signataires "
    . "sans compte Moodle (direction générale, CCEM, présidence de l'université) reçoivent par courriel un lien de signature si leur "
    . "adresse est renseignée ; sinon la DEVE recueille leur signature sur place.";
$string['doctplimages'] = 'Images des modèles';
$string['doctplimages_help'] = "Images (PNG ou JPEG) utilisables dans les modèles de documents par {{img.nom}}, nom étant le nom du "
    . "fichier en minuscules, sans extension ni caractère spécial. Une image du même nom qu'une image fournie avec le module "
    . "(marianne, vetagrosup, lyon1, bulyon1, abes) la remplace dans les documents de cette activité.";
$string['settingsdevename'] = 'Direction des études : nom';
$string['settingsdevename_desc'] = "Nom de la directrice ou du directeur des études et de la vie étudiante (fiche d'agréments).";
$string['settingsdevetitle'] = 'Direction des études : qualité';
$string['settingsdevetitle_desc'] = "Qualité imprimée sur la fiche d'agréments (sans article).";
$string['settingscontracttemplate'] = 'Contrat type de diffusion électronique (PDF)';
$string['settingscontracttemplate_desc'] = "Le contrat officiel (ENS-INI-P308-E04), tel quel. Le module le fait précéder d'une page "
    . "reprenant les données et les choix de l'auteur et suivre d'une page de signatures.";
$string['settingsinstitution'] = 'Établissement';
$string['settingsinstitution_desc'] = 'Nom court utilisé dans les documents (« Visa de la Directrice de … », « Membre de … »).';
$string['settingshetopurl'] = 'Lien HeTOP';
$string['settingshetopurl_desc'] = 'Terminologie de référence pour les mots-clés du sujet.';
$string['settingsdgtitle'] = 'Direction générale (intitulé)';
$string['settingsdgtitle_desc'] = '';
$string['settingsdgname'] = 'Direction générale (nom)';
$string['settingsdgname_desc'] = '';
$string['settingsdgemail'] = 'Direction générale (courriel)';
$string['settingsdgemail_desc'] = 'Adresse à laquelle envoyer les liens de signature ; vide : signature recueillie sur place par la DEVE.';
$string['settingsvisaplace'] = 'Lieu du visa';
$string['settingsvisaplace_desc'] = '« Vu, … le » des conclusions, « Fait à » du contrat de diffusion.';
$string['settingsccemname'] = 'CCEM (nom)';
$string['settingsccemname_desc'] = '';
$string['settingsccememail'] = 'CCEM (courriel)';
$string['settingsccememail_desc'] = 'Adresse à laquelle envoyer les liens de signature ; vide : signature recueillie sur place par la DEVE.';
$string['settingsucbltitle'] = 'Présidence de l\'université (intitulé)';
$string['settingsucbltitle_desc'] = '';
$string['settingsucblname'] = 'Présidence de l\'université (nom)';
$string['settingsucblname_desc'] = '';
$string['settingsucblemail'] = 'Présidence de l\'université (courriel)';
$string['settingsucblemail_desc'] = 'Adresse à laquelle envoyer les liens de signature ; vide : signature recueillie sur place par la DEVE.';

// Formulaire de dépôt de sujet.
$string['studyyear'] = "Année d'études";
$string['confidential'] = 'Thèse confidentielle, ne pas divulguer le titre';
$string['hetoplink'] = 'Rechercher les mots-clés dans HeTOP';
$string['keywordscount'] = 'Indiquez de 3 à 5 mots-clés, séparés par des points-virgules.';
$string['subjectapproval'] = 'Approbation du sujet';
$string['externaldirector'] = "Le directeur de thèse est extérieur à l'établissement";
$string['externaldirector_help'] = "Le directeur extérieur, sans compte Moodle, signe le formulaire depuis un lien reçu par courriel. "
    . "Choisissez alors ci-dessous, comme directeur dans le module, le membre de l'établissement (enseignant-chercheur) qui suit la "
    . "thèse : c'est lui qui valide les étapes et signe en qualité de membre.";
$string['directortitle'] = 'Titre du directeur';
$string['directortitle_help'] = 'Dr, Pr… tel qu\'il doit figurer sur les documents.';
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
$string['managesignatures'] = 'Suivi des signatures';
$string['externalsigners'] = 'Signataires sans compte';
$string['signer'] = 'Signataire';
$string['noemail'] = 'pas d\'adresse';
$string['documentstosign'] = 'Documents à signer';
$string['resendsignature'] = 'Envoyer le lien';
$string['signinperson'] = 'Signer sur place';
$string['signaturerequestsent'] = 'Lien de signature envoyé.';
$string['signaturesrecorded'] = 'Signature enregistrée. Merci.';
$string['signpagetitle'] = 'Signature de documents de thèse';
$string['signpageintro'] = 'Signature en qualité de {$a->slot} des documents de la thèse de {$a->student} : « {$a->title} ».';
$string['signinvalidtoken'] = "Ce lien n'est plus valide : les documents ont déjà été signés, ou une nouvelle invitation a été envoyée.";
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
$string['bconfidentialuntil'] = "Confidentialité jusqu'au";
$string['bconfidentialuntil_help'] = 'Ne concerne que les thèses déclarées confidentielles au dépôt du sujet.';
$string['bembargo'] = "Thèse soumise à embargo (diffusion différée)";
$string['bembargountil'] = "Embargo jusqu'au";
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
$string['logsignaturesinvalidated'] = 'Signatures invalidées (document modifié)';
$string['logsignaturerequested'] = 'Signature demandée';
$string['logsignedinperson'] = 'Signature recueillie sur place';
$string['emailkeysignaturerequest'] = 'Demande de signature (signataires sans compte)';
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
