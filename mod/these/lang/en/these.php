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
 * English strings for mod_these.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Thesis management';
$string['modulename'] = 'Thesis management';
$string['modulenameplural'] = 'Thesis managements';
$string['modulename_help'] = "This module handles the veterinary thesis workflow, from the subject proposal to the defence minutes: "
    . "the student submits a subject and later the defence documents, the thesis director approves them, the registrar's office "
    . "(DEVE) registers the subject and validates the complete file, the library records the subject and certifies the manuscript, "
    . "and the registrar's office generates and sends the defence documents.";
$string['pluginadministration'] = 'Thesis management administration';
$string['thesename'] = 'Activity name';

// Capabilities.
$string['these:addinstance'] = 'Add a thesis management activity';
$string['these:view'] = 'View the thesis management activity';
$string['these:submit'] = 'Submit own thesis subject and defence documents';
$string['these:direct'] = 'Direct theses and approve the directed files';
$string['these:managedeve'] = 'Manage thesis files (registrar)';
$string['these:managelibrary'] = 'Record subjects and certify manuscripts (library)';
$string['these:viewall'] = 'View all thesis files';

// Activity settings.
$string['establishment'] = 'Institution (generated documents)';
$string['establishmentname'] = 'Institution name';
$string['establishmentaddress'] = 'Institution address';
$string['establishmentcity'] = 'City ("Done at")';
$string['signatoryname'] = 'Signatory of the notices';
$string['signatorytitle'] = 'Signatory position';
$string['defaultlocation'] = 'Default defence location';
$string['workflowsettings'] = 'Workflow and notifications';
$string['minjury'] = 'Minimum voting jury members';
$string['minjury_help'] = 'Minimum number of voting members (chair included, guests excluded) required in the jury, both when the '
    . 'student proposes it and when the registrar validates it.';
$string['minjuryinvalid'] = 'Enter a number greater than or equal to 1.';
$string['deveemail'] = 'Registrar service address';
$string['deveemail_help'] = 'If set, notifications for the registrar (file to register, to validate, documents to generate, '
    . 'reminders) are sent to this address. Otherwise every enrolled user holding the registrar capability receives them.';
$string['libraryemail'] = 'Library service address';
$string['libraryemail_help'] = 'If set, notifications for the library (subject to record, manuscript to check, reminders) are sent '
    . 'to this address. Otherwise every enrolled user holding the library capability receives them.';
$string['universityemail'] = 'University address';
$string['universityemail_help'] = 'When the defence documents are sent, this address receives an archive of all generated documents '
    . '(notices, jury sheet, minutes). Leave empty to send nothing to the university.';
$string['reminderdays'] = 'Reminder delay (days)';
$string['reminderdays_help'] = 'When a file has been waiting at the same step for this number of days, the expected actor (director, '
    . 'registrar, library, or student whose file was returned) receives a reminder, then again after each delay. Drafts and active '
    . '("dormant") files are never reminded. 0 disables reminders.';
$string['reminderdaysinvalid'] = 'Enter a positive number of days or zero.';
$string['resetdossiers'] = 'Delete all thesis files';
$string['resetdossiers_help'] = 'Deletes all files, their jury, history and documents. A thesis file stays open for several years: '
    . 'the option is not ticked by default.';

// Actors.
$string['actorstudent'] = 'Student';
$string['actordirector'] = 'Thesis director';
$string['actordeve'] = 'Registrar';
$string['actorlibrary'] = 'Library';

// Statuses.
$string['statusdraft'] = 'Subject in preparation';
$string['statussubjectsubmitted'] = 'Subject submitted to the director';
$string['statussubjectreturned'] = 'Subject returned to the student';
$string['statussubjectapproved'] = 'Subject approved by the director';
$string['statussubjectregistered'] = 'Subject registered by the registrar';
$string['statusactive'] = 'Active file';
$string['statusdefensesubmitted'] = 'Defence proposed to the director';
$string['statusdefensereturned'] = 'Defence file returned to the student';
$string['statusdefenseapproved'] = 'Defence approved by the director';
$string['statusdefensevalidated'] = 'File validated by the registrar';
$string['statusconform'] = 'Manuscript compliant';
$string['statusscheduled'] = 'Defence scheduled';
$string['statusclosed'] = 'File closed';
$string['statuscancelled'] = 'File abandoned';
$string['allstatuses'] = 'All statuses';
$string['status'] = 'Status';
$string['waitingfor'] = 'Waiting for: {$a}';
$string['since'] = 'Since {$a}';
$string['system'] = 'System';

// Progress.
$string['stepsubjectdeposit'] = 'Subject submission';
$string['stepdirectorsubject'] = 'Subject approval';
$string['stepdevesubject'] = 'Registrar registration';
$string['steplibrarysubject'] = 'Library registration';
$string['stepdefensedeposit'] = 'Defence submission';
$string['stepdirectordefense'] = 'Director approval and report';
$string['stepdevedefense'] = 'Complete file validation';
$string['steplibraryconformity'] = 'Manuscript compliance';
$string['stepdocuments'] = 'Defence documents';
$string['stepminutes'] = 'Minutes';
$string['dormantphase'] = 'dormant file';

// Navigation and lists.
$string['dashboard'] = 'Dashboard';
$string['todo'] = 'To do';
$string['nothingtodo'] = 'No workflow step is assigned to you in this activity.';
$string['alldossiers'] = 'All files';
$string['mydirecteddossiers'] = 'Files I direct';
$string['nodossiers'] = 'No file.';
$string['searchdossiers'] = 'Student, title or number';
$string['open'] = 'Open';
$string['dossier'] = 'Thesis file';
$string['dossierof'] = 'Thesis file of {$a}';
$string['mydossier'] = 'My thesis file';
$string['nodossieryet'] = 'You do not have a thesis file yet. Start by proposing your subject: you can save it as a draft before '
    . 'submitting it to your thesis director.';
$string['proposesubject'] = 'Propose a thesis subject';

// Subject.
$string['subject'] = 'Thesis subject';
$string['subjectintro'] = 'Fill in the subject form and choose your thesis director. "Save draft" keeps your input without sending '
    . 'anything; submitting sends the subject to your director, who will approve it or return it with comments. Once approved, it '
    . 'is registered by the registrar and then by the library.';
$string['subjecttitle'] = 'Title';
$string['subjecttitleen'] = 'English title';
$string['subjectsummary'] = 'Abstract';
$string['subjectsummary_help'] = 'Presentation of the subject: background, objectives, planned approach (about one page).';
$string['subjectkeywords'] = 'Keywords';
$string['subjectkeywords_help'] = 'Keywords separated by commas or semicolons.';
$string['director'] = 'Thesis director';
$string['director_help'] = 'Teacher directing the thesis. Only teachers enrolled in this course and allowed to direct theses are '
    . 'listed: if yours is missing, contact the registrar.';
$string['codirector'] = 'Co-director';
$string['codirector_help'] = 'Optional. Name and position of a possible co-director, who may be external. The co-director does not '
    . 'approve the file in the module.';
$string['subjectdocs'] = 'Subject attachments';
$string['subjectdocs_help'] = 'Optional: bibliography, protocol, partner agreement...';
$string['savedraft'] = 'Save draft';
$string['submitsubject'] = 'Submit to the thesis director';
$string['resubmitsubject'] = 'Submit again';
$string['draftsaved'] = 'Draft saved.';
$string['subjectsubmittedmsg'] = 'Subject submitted.';
$string['subjectnoteditable'] = 'The subject can no longer be edited: it has been submitted. It becomes editable again if the file '
    . 'is returned to you.';
$string['editsubject'] = 'Edit the subject';
$string['subjectsheet'] = 'Subject sheet (PDF)';
$string['registration'] = 'Registrations';
$string['devenumber'] = 'Registrar number';
$string['devenumber_help'] = 'Registration number of the subject. The suggested number is the next one of the current year '
    . '(YYYY-NNN format); it can be changed but must remain unique in the activity.';
$string['devenumbertaken'] = 'This number is already used by another file.';
$string['libraryref'] = 'Bibliographic record reference';

// Defence.
$string['defense'] = 'Defence';
$string['depositdefense'] = 'Submit the defence documents';
$string['defenseintro'] = 'Upload your manuscript and defence documents and propose a date, a location and the jury (a chair and '
    . 'at least {$a} voting members, chair included). You can prepare this submission in several steps ("Save draft"); submitting '
    . 'sends it to your director, who will approve it and attach a report.';
$string['defensedocuments'] = 'Defence documents';
$string['manuscript'] = 'Manuscript (PDF)';
$string['manuscript_help'] = 'Complete version of the manuscript, as a single PDF file.';
$string['manuscriptrequired'] = 'The manuscript is required to submit.';
$string['defensedocs'] = 'Other defence documents';
$string['defensedocs_help'] = 'Optional: abstract, dissemination authorisation, appendices...';
$string['defenseproposal'] = 'Defence proposal';
$string['proposeddate'] = 'Proposed date';
$string['defensedate'] = 'Defence date';
$string['defenselocation'] = 'Defence location';
$string['datemustbefuture'] = 'The defence date must be in the future.';
$string['submitdefense'] = 'Submit to the thesis director';
$string['resubmitdefense'] = 'Submit again';
$string['defensesubmittedmsg'] = 'Defence file submitted.';
$string['defensenoteditable'] = 'Defence documents can be submitted once the subject is registered, or when the defence file has '
    . 'been returned to you.';
$string['directorreport'] = 'Director report';
$string['directorreport_help'] = 'Report of the thesis director on the manuscript, required to approve the defence proposal. It is '
    . 'shared with the registrar, the library and the jury, but not with the student.';
$string['directorreportrequired'] = 'The director report is required to approve.';
$string['generateddocuments'] = 'Generated documents';
$string['defenseresult'] = 'Result';
$string['resultadmitted'] = 'Admitted';
$string['resultadjourned'] = 'Adjourned';
$string['mention'] = 'Distinction';
$string['mention_help'] = 'Optional: distinction or jury congratulations, as written in the minutes.';
$string['minutes'] = 'Defence minutes';

// Jury.
$string['jury'] = 'Jury';
$string['juryhelp'] = 'One row per member. Leave the name empty to ignore a row. The email address is used to send each member a '
    . 'notice; without an address, the notice must be delivered by other means.';
$string['juryrole'] = 'Role';
$string['juryrolepresident'] = 'Chair';
$string['juryroledirector'] = 'Director (assessor)';
$string['juryrolemember'] = 'Assessor';
$string['juryroleguest'] = 'Guest member';
$string['juryname'] = 'Name';
$string['jurytitle'] = 'Title or position';
$string['juryinstitution'] = 'Institution';
$string['juryemail'] = 'Email';
$string['juryaddmembers'] = 'Add rows';
$string['juryonepresident'] = 'The jury must have exactly one chair.';
$string['juryminmembers'] = 'The jury must have at least {$a} voting members (chair included, guests excluded).';
$string['juryinvalidemail'] = 'Invalid email address for {$a}.';
$string['jurysheet'] = 'Jury sheet';

// Review steps.
$string['reviewdirectorsubject'] = 'Review the subject proposal';
$string['reviewdirectorsubject_help'] = 'Approve the subject so that the registrar can register it, or return it to the student with '
    . 'your comments: the student can correct it and submit it again.';
$string['reviewdevesubject'] = 'Register the subject (registrar)';
$string['reviewdevesubject_help'] = 'Assign the registration number: the subject is then sent to the library. A return can be '
    . 'addressed to the student or to the director; when resubmitted, the file comes straight back here.';
$string['reviewlibrarysubject'] = 'Record the subject (library)';
$string['reviewlibrarysubject_help'] = 'Enter the reference of the record created in the library catalogue. This opens the file, '
    . 'which stays active until the defence documents are submitted.';
$string['reviewdirectordefense'] = 'Approve the defence and upload the report';
$string['reviewdirectordefense_help'] = 'Approve the defence proposal (date, jury) by uploading your report on the manuscript, in a '
    . 'single step. You can also return the file to the student with your comments.';
$string['reviewdevedefense'] = 'Validate the defence file (registrar)';
$string['reviewdevedefense_help'] = 'Check the complete file (manuscript, documents, director report) and set the date, location '
    . 'and jury. Validation sends the manuscript to the library for the compliance check.';
$string['reviewlibraryconformity'] = 'Check manuscript compliance';
$string['reviewlibraryconformity_help'] = 'Check the manuscript format, presentation standards and legal deposit requirements. '
    . 'Validation issues the compliance certificate; a non-compliant manuscript is returned to the student with the reason.';
$string['validatedirectorsubject'] = 'Approve the subject';
$string['validatedevesubject'] = 'Register the subject';
$string['validatelibrarysubject'] = 'Record the subject';
$string['validatedirectordefense'] = 'Approve the defence';
$string['validatedevedefense'] = 'Validate the file';
$string['validatelibraryconformity'] = 'Certify compliance';
$string['conformitychecked'] = 'The manuscript is compliant (format, legal deposit, presentation standards)';
$string['conformitycheckrequired'] = 'Tick the box to certify compliance, or return the file.';
$string['conformitycertificate'] = 'Compliance certificate (PDF)';
$string['reviewremark'] = 'Comment';
$string['reviewremark_help'] = 'Optional when validating (it is recorded in the file history). Required to return the file: it is the '
    . 'reason sent to the recipient.';
$string['returntarget'] = 'Return to';
$string['returndossier'] = 'Return the file';
$string['returncommentrequired'] = 'A reason is required to return the file.';
$string['returncomment'] = 'Reason for return';
$string['returnedby'] = 'File returned by {$a->by} on {$a->date}:';
$string['dossierreturned'] = 'File returned ({$a}).';
$string['stepvalidated'] = 'Step validated.';
$string['nostepwaiting'] = 'This file is not waiting for any validation.';
$string['notyourstep'] = 'This workflow step is not assigned to you.';

// Defence documents and minutes.
$string['documents'] = 'Defence documents';
$string['documentsintro'] = 'Generate the notices (student and each jury member), the jury sheet and the minutes to sign, check '
    . 'them, then send them: each jury member receives a notice, the student too, and the university receives all documents. '
    . 'The first sending schedules the defence.';
$string['generatedocuments'] = 'Generate documents';
$string['regeneratedocuments'] = 'Regenerate documents';
$string['transmitdocuments'] = 'Send to the jury and the university';
$string['retransmitdocuments'] = 'Send again';
$string['documentsgenerated'] = 'Documents generated.';
$string['documentsgeneratedon'] = 'Documents generated on {$a}.';
$string['documentssenton'] = 'Last sent on {$a}.';
$string['documentsstale'] = 'The file has changed (date, location or jury) since the documents were generated: regenerate them '
    . 'before sending.';
$string['documentsnotavailable'] = 'Defence documents are available once the manuscript is certified compliant.';
$string['documentnotavailable'] = 'This document is not available yet for this file.';
$string['generatefirst'] = 'Generate the documents before sending them.';
$string['transmitsummary'] = '{$a->sent} email(s) sent, {$a->failed} failure(s).';
$string['transmitmissing'] = 'No email address, to be notified by other means: {$a}.';
$string['nouniversityemail'] = 'No university address is set in the activity settings: nothing will be sent to the university.';
$string['convocation'] = 'Thesis defence notice';
$string['depositminutes'] = 'Upload the minutes';
$string['minutesrequired'] = 'The signed minutes are required.';
$string['minutesnotavailable'] = 'Minutes can be uploaded once the defence is scheduled.';
$string['closedossier'] = 'Upload and close the file';
$string['dossierclosed'] = 'Minutes uploaded: file closed.';

// Registrar management.
$string['editdossier'] = 'Edit the file';
$string['editdossierhelp'] = 'Changes do not affect the file status. If the date, location or jury change after the defence '
    . 'documents were generated, they will have to be regenerated.';
$string['dossiersaved'] = 'File saved.';
$string['canceldossier'] = 'Abandon the file';
$string['cancelhelp'] = 'The file is kept as is and can be reopened later at the status it had reached.';
$string['dossiercancelled'] = 'File abandoned.';
$string['reopendossier'] = 'Reopen the file';
$string['reopenhelp'] = 'The file returns to the status it had reached when it was abandoned.';
$string['dossierreopened'] = 'File reopened.';
$string['createdossier'] = 'Create a file';
$string['createdossierhelp'] = 'Creates a draft file for an enrolled student who has none yet. You can then complete the subject; '
    . 'the student can submit it.';
$string['dossiercreated'] = 'File created.';
$string['student'] = 'Student';

// Import.
$string['importdossiers'] = 'Import files';
$string['import'] = 'Import';
$string['importhelp'] = 'Import of subjects already registered outside the module: each row creates an active file. Recognised '
    . 'columns (header row): <code>student</code> and <code>title</code> (required), <code>titleen</code>, <code>summary</code>, '
    . '<code>keywords</code>, <code>director</code>, <code>codirector</code>, <code>devenumber</code>, <code>libraryref</code>. '
    . 'Student and director are identified by username, email address or ID number.';
$string['csvfile'] = 'CSV file';
$string['importdryrun'] = 'Dry run (create nothing)';
$string['importline'] = 'Line';
$string['importusernotfound'] = 'User not found: {$a}';
$string['importdossierexists'] = 'This student already has a file.';
$string['importtitlemissing'] = 'Missing title.';
$string['importcreated'] = 'File created';
$string['importwouldcreate'] = 'Ready to import';
$string['importresult'] = '{$a->ok} file(s) imported, {$a->errors} line(s) with errors.';
$string['importdryrunresult'] = 'Dry run: {$a->ok} file(s) importable, {$a->errors} line(s) with errors.';
$string['importinvalidfile'] = 'Unreadable file: {$a}';
$string['importmissingcolumn'] = 'Missing required column: {$a}';

// Export.
$string['export'] = 'Register export';
$string['exporthelp'] = 'One file per row: number, student, subject, director, status, defence, jury and result.';
$string['exportformat'] = 'Format';

// History.
$string['history'] = 'History';
$string['historyaction'] = 'Action';
$string['historyby'] = 'By';
$string['historyremark'] = 'Comment';
$string['logcreated'] = 'File created';
$string['logimported'] = 'File imported';
$string['logsubjectsaved'] = 'Subject saved (draft)';
$string['logsubjectsubmitted'] = 'Subject submitted';
$string['logsubjectresubmitted'] = 'Subject submitted again';
$string['logdefensesaved'] = 'Defence file saved (draft)';
$string['logdefensesubmitted'] = 'Defence file submitted';
$string['logdefenseresubmitted'] = 'Defence file submitted again';
$string['logvalidateddirectorsubject'] = 'Subject approved by the director';
$string['logvalidateddevesubject'] = 'Subject registered by the registrar';
$string['logvalidatedlibrarysubject'] = 'Subject recorded by the library';
$string['logvalidateddirectordefense'] = 'Defence approved and report uploaded';
$string['logvalidateddevedefense'] = 'Complete file validated by the registrar';
$string['logvalidatedlibraryconformity'] = 'Manuscript compliance certified';
$string['logreturnedtostudent'] = 'Returned to the student';
$string['logreturnedtodirector'] = 'Returned to the director';
$string['logdocumentsgenerated'] = 'Defence documents generated';
$string['logdocumentssent'] = 'Documents sent';
$string['logdocumentsresent'] = 'Documents sent again';
$string['logclosed'] = 'Minutes uploaded';
$string['logcancelled'] = 'File abandoned';
$string['logreopened'] = 'File reopened';
$string['logedited'] = 'File edited by the registrar';
$string['logreminded'] = 'Reminder sent';

// Emails.
$string['notifications'] = 'Emails';
$string['notificationshelp'] = 'For each email, a subject and/or body entered here replace the default text. Variables between '
    . 'double braces are replaced when sending. Empty both fields to restore the default text.';
$string['emailvars'] = 'Available variables';
$string['emaildefault'] = 'Default text';
$string['emailsubject'] = 'Custom subject';
$string['emailbody'] = 'Custom body';
$string['emailkeyactionrequired'] = 'File waiting (director, registrar, library)';
$string['emailkeyreturned'] = 'File returned (student or director)';
$string['emailkeysubjectactive'] = 'Subject registered, file open (student and director)';
$string['emailkeyconvocation'] = 'Notice (student and jury members)';
$string['emailkeyuniversity'] = 'Sending to the university';
$string['emailkeyclosed'] = 'File closed (student and director)';
$string['emailkeyreminder'] = 'Reminder';
$string['emailactionrequiredsubject'] = 'Thesis of {$a->student}: {$a->status}';
$string['emailactionrequiredbody'] = 'Hello,

The thesis file of {$a->student} ({$a->activity}) is waiting for your action: {$a->status}.

Subject: {$a->title}

Open the file: {$a->url}';
$string['emailreturnedsubject'] = 'Thesis of {$a->student}: file returned';
$string['emailreturnedbody'] = 'Hello,

The thesis file of {$a->student} ({$a->activity}) has been returned to you for the following reason:

{$a->comment}

Subject: {$a->title}

Open the file: {$a->url}';
$string['emailsubjectactivesubject'] = 'Thesis of {$a->student}: subject registered';
$string['emailsubjectactivebody'] = 'Hello,

The thesis subject of {$a->student} has been registered by the registrar (number {$a->devenumber}) and by the library. The file is open and stays active until the defence documents are submitted.

Subject: {$a->title}

Open the file: {$a->url}';
$string['emailconvocationsubject'] = 'Notice for the thesis defence of {$a->student}';
$string['emailconvocationbody'] = 'Hello {$a->name},

You are invited to the defence of the thesis of {$a->student}, entitled "{$a->title}", on {$a->date}, {$a->location}.

Please find your notice attached.';
$string['emailuniversitysubject'] = 'Thesis defence of {$a->student}';
$string['emailuniversitybody'] = 'Hello,

Please find attached the documents of the thesis defence of {$a->student} (file {$a->devenumber}), entitled "{$a->title}", scheduled on {$a->date}, {$a->location}.';
$string['emailclosedsubject'] = 'Thesis of {$a->student}: file closed';
$string['emailclosedbody'] = 'Hello,

The minutes of the thesis defence of {$a->student} have been uploaded. Result: {$a->result}.

Subject: {$a->title}

Open the file: {$a->url}';
$string['emailremindersubject'] = 'Reminder: thesis of {$a->student}';
$string['emailreminderbody'] = 'Hello,

The thesis file of {$a->student} ({$a->activity}) has been waiting for your action for {$a->days} days: {$a->status}.

Subject: {$a->title}

Open the file: {$a->url}';

// PDF documents.
$string['pdfdoneat'] = 'Done at {$a->city}, on {$a->date}';
$string['pdfconvocationstudent'] = 'Dear student,

You are invited to the public defence of your veterinary thesis on {$a->date} at {$a->time}, {$a->location}, before the jury below.';
$string['pdfconvocationjury'] = 'Dear colleague,

You are invited, as {$a->role}, to the jury of the veterinary thesis defence of {$a->student}, on {$a->date} at {$a->time}, {$a->location}.';
$string['pdfconformitytext'] = 'The library certifies that the thesis manuscript of {$a->student} complies with the presentation standards and legal deposit requirements of the institution, as of {$a->date}.';
$string['pdfsignature'] = 'Signature';
$string['pdfsignatures'] = 'Signatures of the jury members';
$string['pdfdeliberation'] = 'Deliberation';
$string['pdfminutestext'] = 'After deliberation, the jury declares the thesis of {$a->student}:';

// Tasks and privacy.
$string['taskreminders'] = 'Reminders for pending thesis files';
$string['staffactions'] = 'Actions on thesis files';
$string['privacy:metadata:these_dossier'] = 'Thesis file of a student: subject, defence and workflow status.';
$string['privacy:metadata:these_dossier:userid'] = 'Student who owns the file.';
$string['privacy:metadata:these_dossier:status'] = 'Workflow step reached.';
$string['privacy:metadata:these_dossier:title'] = 'Subject title.';
$string['privacy:metadata:these_dossier:summary'] = 'Subject abstract.';
$string['privacy:metadata:these_dossier:keywords'] = 'Subject keywords.';
$string['privacy:metadata:these_dossier:directorid'] = 'Thesis director.';
$string['privacy:metadata:these_dossier:devenumber'] = 'Registrar number.';
$string['privacy:metadata:these_dossier:defensedate'] = 'Defence date.';
$string['privacy:metadata:these_dossier:defenseresult'] = 'Defence result.';
$string['privacy:metadata:these_dossier:returncomment'] = 'Reason for the last return.';
$string['privacy:metadata:these_dossier:returnedby'] = 'Author of the last return.';
$string['privacy:metadata:these_dossier:timecreated'] = 'File creation date.';
$string['privacy:metadata:these_jury'] = 'Jury members proposed or set for a defence.';
$string['privacy:metadata:these_jury:fullname'] = 'Jury member name.';
$string['privacy:metadata:these_jury:email'] = 'Jury member email address.';
$string['privacy:metadata:these_jury:institution'] = 'Jury member institution.';
$string['privacy:metadata:these_log'] = 'History of actions on a file.';
$string['privacy:metadata:these_log:userid'] = 'Author of the action.';
$string['privacy:metadata:these_log:logaction'] = 'Action performed.';
$string['privacy:metadata:these_log:remark'] = 'Comment entered with the action.';
$string['privacy:metadata:these_log:timecreated'] = 'Date of the action.';
$string['privacy:metadata:core_files'] = 'Files of the thesis files: subject attachments, manuscript, defence documents, director report, generated documents, minutes.';
$string['privacy:metadata:jury'] = 'Jury members receive their notice by email, mentioning the student, the subject and the defence date.';
$string['privacy:metadata:jury:student'] = 'Student name.';
$string['privacy:metadata:jury:title'] = 'Thesis title.';
$string['privacy:metadata:jury:defensedate'] = 'Defence date and location.';
