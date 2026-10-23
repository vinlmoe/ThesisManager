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
$string['subjectkeywords_help'] = 'Mots-clés séparés par des virgules ou des points-virgules.';
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
$string['subjectsheet'] = 'Fiche de sujet (PDF)';
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
$string['juryhelp'] = "Une ligne par membre. Laissez le nom vide pour ignorer une ligne. L'adresse de courriel permet d'envoyer sa "
    . "convocation à chaque membre ; sans adresse, la convocation est à transmettre par un autre moyen.";
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
$string['conformitycertificate'] = 'Attestation de conformité (PDF)';
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

// Documents PDF.
$string['pdfdoneat'] = 'Fait à {$a->city}, le {$a->date}';
$string['pdfconvocationstudent'] = 'Madame, Monsieur,

Vous êtes convoqué(e) à la soutenance publique de votre thèse d\'exercice vétérinaire, le {$a->date} à {$a->time}, {$a->location}, devant le jury ci-dessous.';
$string['pdfconvocationjury'] = 'Madame, Monsieur,

Vous êtes convoqué(e), en qualité de {$a->role}, au jury de la soutenance de thèse d\'exercice vétérinaire de {$a->student}, le {$a->date} à {$a->time}, {$a->location}.';
$string['pdfconformitytext'] = 'La bibliothèque atteste que le manuscrit de la thèse de {$a->student} est conforme aux normes de présentation et aux conditions du dépôt légal en vigueur dans l\'établissement, à la date du {$a->date}.';
$string['pdfsignature'] = 'Signature';
$string['pdfsignatures'] = 'Signatures des membres du jury';
$string['pdfdeliberation'] = 'Délibération';
$string['pdfminutestext'] = 'Après en avoir délibéré, le jury déclare la thèse de {$a->student} :';

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
