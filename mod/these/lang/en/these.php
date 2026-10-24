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
$string['subjectkeywords_help'] = '3 to 5 keywords separated by semicolons, preferably from the HeTOP terminology.';
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

// Service roles (site settings).
$string['settingsroles'] = 'Service roles';
$string['settingsroles_desc'] = 'Just as the registrar is recognised by the "Teacher" role, the library is recognised by the role chosen '
    . 'here. The role can be assigned in the course, its category or at site level (it then gives access to all thesis activities '
    . 'without enrolment). The module capabilities are also taken into account.';
$string['deverole'] = 'Registrar role';
$string['deverole_desc'] = 'Role whose holders act as the registrar in thesis activities.';
$string['libraryrole'] = 'Library role';
$string['libraryrole_desc'] = 'Role whose holders act as the library. By default, the internal "Library (theses)" role created when '
    . 'the module is installed.';
$string['libraryrolename'] = 'Library (theses)';
$string['libraryroledescription'] = 'Internal role of the thesis management module: recording subjects in the library catalogue and '
    . 'certifying manuscripts. Assign it at site, category or course level.';

// Documents (site settings).
$string['settingsdocuments'] = 'Documents and signatories';
$string['settingsdocuments_desc'] = 'Header and signatories of the institution documents produced by the module. Signatories without '
    . 'a Moodle account (general management, CCEM, university presidency) receive a signing link by email if their address is set; '
    . 'otherwise the registrar collects their signature in person.';
$string['doctplimages'] = 'Template images';
$string['doctplimages_help'] = 'Images (PNG or JPEG) usable in the document templates as {{img.name}}, name being the file name in '
    . 'lower case, without extension or special characters. An image with the same name as one provided with the module '
    . '(marianne, vetagrosup, lyon1, bulyon1, abes) replaces it in the documents of this activity.';
$string['settingsdevename'] = 'Head of studies: name';
$string['settingsdevename_desc'] = 'Name of the head of studies and student life (agreement form).';
$string['settingsdevetitle'] = 'Head of studies: title';
$string['settingsdevetitle_desc'] = 'Title printed on the agreement form (without article).';
$string['settingscontracttemplate'] = 'Electronic distribution contract template (PDF)';
$string['settingscontracttemplate_desc'] = 'The official contract (ENS-INI-P308-E04), as is. The module adds before it a page with the '
    . 'author\'s data and choices, and after it a signature page.';
$string['settingsinstitution'] = 'Institution';
$string['settingsinstitution_desc'] = 'Short name used in the documents.';
$string['settingshetopurl'] = 'HeTOP link';
$string['settingshetopurl_desc'] = 'Reference terminology for subject keywords.';
$string['settingsdgtitle'] = 'General management (title)';
$string['settingsdgtitle_desc'] = '';
$string['settingsdgname'] = 'General management (name)';
$string['settingsdgname_desc'] = '';
$string['settingsdgemail'] = 'General management (email)';
$string['settingsdgemail_desc'] = 'Address receiving signing links; empty: signature collected in person by the registrar.';
$string['settingsvisaplace'] = 'Place of approval';
$string['settingsvisaplace_desc'] = '"Seen, at …" of the conclusions, "Done at" of the distribution contract.';
$string['settingsccemname'] = 'CCEM (name)';
$string['settingsccemname_desc'] = '';
$string['settingsccememail'] = 'CCEM (email)';
$string['settingsccememail_desc'] = 'Address receiving signing links; empty: signature collected in person by the registrar.';
$string['settingsucbltitle'] = 'University presidency (title)';
$string['settingsucbltitle_desc'] = '';
$string['settingsucblname'] = 'University presidency (name)';
$string['settingsucblname_desc'] = '';
$string['settingsucblemail'] = 'University presidency (email)';
$string['settingsucblemail_desc'] = 'Address receiving signing links; empty: signature collected in person by the registrar.';

// Subject form.
$string['studyyear'] = 'Year of study';
$string['confidential'] = 'Confidential thesis, do not disclose the title';
$string['hetoplink'] = 'Search keywords in HeTOP';
$string['keywordscount'] = 'Enter 3 to 5 keywords, separated by semicolons.';
$string['subjectapproval'] = 'Subject approval';
$string['externaldirector'] = 'The thesis director is external to the institution';
$string['externaldirector_help'] = 'The external director, without a Moodle account, signs the form from a link received by email. '
    . 'Then choose below, as director in the module, the member of the institution who follows the thesis: this member validates '
    . 'the steps and signs as member.';
$string['directortitle'] = 'Director title';
$string['directortitle_help'] = 'Dr, Pr… as it must appear on the documents.';
$string['extdirectorname'] = 'External director: title, name';
$string['extdirectorgrade'] = 'Grade';
$string['extdirectorinstitution'] = 'Institution';
$string['extdirectoremail'] = 'Email address';
$string['commitments'] = 'Student commitments';
$string['commitguide'] = 'I have read the thesis guide';
$string['commitduplicate'] = 'I have checked that the subject has not been the subject of a veterinary thesis in one of the schools '
    . 'and is not that of a thesis being written';
$string['commitethics'] = 'If necessary: submit the protocol to the ethics committee before starting the work';
$string['commitrequired'] = 'This commitment is required.';
$string['subjectnotice'] = 'A veterinary clinical research centre can support you (methodology, regulations, logistics) for clinical '
    . 'research. For theses in humanities and social sciences, supervision must involve a specialist of the field, who will be a '
    . 'jury member.';

// Signature.
$string['signature'] = 'Signature';
$string['signaturedraw'] = 'Draw your signature';
$string['signatureclear'] = 'Clear';
$string['signername'] = 'Signatory name';
$string['signatureconsent'] = 'I certify the information is accurate and electronically sign the document(s) above.';
$string['signatureconsentrequired'] = 'Tick the box to sign.';
$string['signaturemissing'] = 'Draw your signature in the box above.';
$string['signsubjectintro'] = 'Submitting signs the thesis subject form.';
$string['signdefenseintro'] = 'Submitting signs the thesis record form and the electronic distribution contract.';
$string['signreviewintro'] = 'Validation signs: {$a}.';
$string['signexternalintro'] = 'Read each document (links above) before signing: your signature will be applied to all of them.';
$string['signaturepending'] = 'Pending';
$string['signaturerequestedon'] = 'Requested on {$a}';
$string['signedby'] = 'Signed by {$a->name} on {$a->date}';
$string['signeddocuments'] = 'Signed documents';
$string['signatures'] = 'Signatures';
$string['document'] = 'Document';
$string['downloadpdf'] = 'PDF';
$string['managesignatures'] = 'Signature tracking';
$string['externalsigners'] = 'Signatories without an account';
$string['signer'] = 'Signatory';
$string['noemail'] = 'no address';
$string['documentstosign'] = 'Documents to sign';
$string['resendsignature'] = 'Send the link';
$string['signinperson'] = 'Sign in person';
$string['signaturerequestsent'] = 'Signing link sent.';
$string['signaturesrecorded'] = 'Signature recorded. Thank you.';
$string['signpagetitle'] = 'Signing thesis documents';
$string['signpageintro'] = 'Signing as {$a->slot} the documents of the thesis of {$a->student}: "{$a->title}".';
$string['signinvalidtoken'] = 'This link is no longer valid: the documents have already been signed, or a new invitation was sent.';
$string['signnothingpending'] = 'No document is waiting for a signature.';
$string['signdocuments'] = 'Sign the documents';

// Documents and signature slots.
$string['docsubject'] = 'Thesis subject form';
$string['docbordereau'] = 'Thesis record form (ABES)';
$string['docreport'] = 'Director report';
$string['docgrid'] = 'Thesis compliance grid';
$string['docagreement'] = 'Administrative and scientific approval form';
$string['docsignaletique'] = 'Student information form';
$string['doccontract'] = 'Electronic distribution contract';
$string['docconformity'] = 'Compliance certificate (library)';
$string['docconclusions'] = 'Conclusions';
$string['slotstudent'] = 'Student';
$string['slotdirector'] = 'Thesis director';
$string['slotextdirector'] = 'External thesis director';
$string['slotdeve'] = 'Registrar';
$string['slotdgrep'] = 'General management (represented by the registrar)';
$string['slotlibrary'] = 'Library';
$string['slotpresident'] = 'Jury chair';
$string['slotdg'] = 'General management';
$string['slotccem'] = 'CCEM president';
$string['slotucbl'] = 'University presidency';

// Defence submission: student data.
$string['visio'] = 'Videoconference';
$string['identity'] = 'Identity and contact details';
$string['identityhelp'] = 'This information feeds the thesis record form, the student information form and the distribution '
    . 'contract.';
$string['bbirthname'] = 'Birth name';
$string['bfirstnames'] = 'First name(s)';
$string['busagename'] = 'Usual name';
$string['bpublicationname'] = 'Name on the publication (if different)';
$string['bbirthdate'] = 'Date of birth';
$string['bbirthplace'] = 'Place of birth';
$string['bbirthdept'] = 'Department no.';
$string['bnationality'] = 'Nationality';
$string['bstreet'] = 'Permanent address (number and street)';
$string['bpostcode'] = 'Postcode';
$string['bcity'] = 'City';
$string['bcountry'] = 'Country';
$string['bphone'] = 'Phone';
$string['bpersonalemail'] = 'Personal email';
$string['bpersonalemail_help'] = 'Personal, long-lasting address, different from the institution one.';
$string['bemailconsent'] = 'I agree that my email may be used for accreditation purposes and shared with institutional partners';
$string['cursus'] = 'Curriculum';
$string['bdefvdate'] = 'DEFV obtained on';
$string['bschool'] = 'at';
$string['schoolother'] = 'Other institution';
$string['ba6date'] = 'Final year (A6) validated on';
$string['ba6notyet'] = 'I have not yet validated my final year (A6)';
$string['a6required'] = 'Enter the A6 validation date, or tick the box if it is not validated yet.';
$string['bnbvolumes'] = 'Number of volumes';
$string['bnbpages'] = 'Total number of pages';
$string['bnbrefs'] = 'Number of references';
$string['bsummaryfr'] = 'Abstract in French';
$string['bsummaryen'] = 'Abstract in English (optional)';
$string['bsummary'] = 'Abstract';
$string['bsummary_help'] = '1700 characters maximum, spaces included.';
$string['summarytoolong'] = 'The abstract exceeds {$a} characters.';
$string['bkeywords'] = 'Keywords (in French)';
$string['bkeywords_help'] = 'Identical to those of the back cover, 8 maximum.';
$string['conclusionstext'] = 'Conclusions text';
$string['conclusionstext_help'] = 'The conclusions as they will appear in the manuscript (two pages maximum). They will be signed by '
    . 'the director, the jury chair, the general management and the CCEM president.';
$string['contracthelp'] = 'Author choices for the electronic distribution contract (articles 2 and 4). A confidential thesis (box '
    . 'ticked with the subject) stays confidential until the date given.';
$string['bconfidentialuntil'] = 'Confidential until';
$string['bconfidentialuntil_help'] = 'Only for theses declared confidential with the subject.';
$string['bembargo'] = 'Thesis under embargo (delayed distribution)';
$string['bembargountil'] = 'Embargo until';
$string['bdiffusion'] = 'Thesis distribution';
$string['bdiffusion_help'] = 'Full text on the internet (DUMAS, Sudoc, library catalogues), or paper copy in veterinary school '
    . 'libraries and university intranet only.';
$string['diffusioninternet'] = 'I authorise the full-text distribution of the thesis on the internet';
$string['diffusionrestricted'] = 'I do not authorise internet distribution, only paper consultation and the intranet';

// Director and registrar steps.
$string['reporttext'] = 'Director report';
$string['reporttext_help'] = 'One-page argument presenting the thesis work (work done, main results, student involvement…).';
$string['reportfavorable'] = 'I give a favourable opinion for the defence and the award of the Doctor of Veterinary Medicine title';
$string['reportfavorablerequired'] = 'A favourable opinion is required to approve the defence.';
$string['ethicsnumber'] = 'Ethics committee approval number (if any)';
$string['gridsectionfond'] = 'Content compliance';
$string['gridsectionforme'] = 'Form compliance';
$string['gridrequired'] = 'Item to validate.';
$string['gridquestion'] = 'The scientific question is clearly stated and identified.';
$string['gridanswer'] = 'The bibliographic or experimental answer is satisfactory.';
$string['gridbiblio'] = 'The bibliography is complete, relevant and up to date.';
$string['gridcritique'] = 'The critical analysis of the bibliography is satisfactory.';
$string['gridownwork'] = 'The work presented is the student\'s own.';
$string['gridtool'] = 'If the thesis is about developing a tool, teaching material, website etc.: it is not restricted to the '
    . 'technical description of the work.';
$string['gridfrench'] = 'Satisfactory French expression and spelling.';
$string['gridlanguage'] = 'Written in French. Or, if written in English, at least 15% of the manuscript in French and French '
    . 'summaries of each part.';
$string['gridtyping'] = 'Correct typing and page numbering.';
$string['gridthanks'] = 'Acknowledgements to the jury members and sober personal acknowledgements.';
$string['gridtoc'] = 'A table of contents is present.';
$string['gridstaff'] = 'The up-to-date list of teaching staff is present.';
$string['gridlists'] = 'Lists of abbreviations, figures, tables and appendices are present.';
$string['gridcovers'] = 'Compliant cover pages.';
$string['gridcitations'] = 'References cited in the text (university library format).';
$string['gridbibliography'] = 'A correctly formatted final bibliography is present.';
$string['gridfigures'] = 'Satisfactory numbering, titles and captions, quality and readability of figures and tables.';
$string['gridintroduction'] = 'An introduction sets out the problem and the outline of the manuscript.';
$string['gridconclusion'] = 'A conclusion sums up the key points and suggests perspectives.';
$string['gridabstract'] = 'The abstract is not a copy of the conclusion.';
$string['gridstructure'] = 'For non-bibliographic theses, the work is presented as a scientific document: introduction, material and '
    . 'methods, results, discussion and conclusion.';
$string['agreementscientific'] = 'Scientific approval';
$string['agreementdirectortext'] = 'By validating, you declare you have read and corrected the student\'s thesis and approve its '
    . 'content.';
$string['agreementmembertext'] = 'The director being external, you declare as member that you have corrected the thesis and certify its '
    . 'form with the compliance grid; the external director will sign the approval from the link received by email.';
$string['agreementadmin'] = 'Administrative approval';
$string['agdefvdate'] = 'DEFV obtained on';
$string['agmobility'] = 'has validated their international mobility';
$string['aga6notyet'] = 'has not yet validated their final year (A6)';
$string['aga6date'] = 'Final year (A6) validated on';
$string['agauthorized'] = 'The student is authorised to defend their veterinary thesis.';

// Signature history and emails.
$string['logsigned'] = 'Document signed';
$string['logsignaturesinvalidated'] = 'Signatures invalidated (document changed)';
$string['logsignaturerequested'] = 'Signature requested';
$string['logsignedinperson'] = 'Signature collected in person';
$string['emailkeysignaturerequest'] = 'Signature request (signatories without an account)';
$string['emailsignaturerequestsubject'] = 'Thesis of {$a->student}: document(s) to sign';
$string['emailsignaturerequestbody'] = 'Hello {$a->name},

The following documents of the thesis of {$a->student}, entitled "{$a->title}", are waiting for your signature: {$a->document}.

To read and sign them: {$a->url}

This link is personal and valid for one signature only.';

// PDF labels (the documents are official French forms: labels stay close to the originals).
$string['pdfsignedby'] = 'Electronically signed by {$a->name} on {$a->date} — fingerprint {$a->hash}';
$string['pdfreportfavorable'] = 'Favourable opinion for the thesis defence and the award of the Doctor of Veterinary Medicine title.';
$string['privacy:metadata:these_dossier:bordereau'] = 'Personal data entered with the defence (civil status, address, personal email, curriculum, distribution choices).';
$string['privacy:metadata:these_dossier:conclusions'] = 'Thesis conclusions.';
$string['privacy:metadata:these_dossier:reporttext'] = 'Director report.';
$string['privacy:metadata:these_signature'] = 'Electronic signatures applied to the file documents (drawn image kept in the files).';
$string['privacy:metadata:these_signature:userid'] = 'Signatory.';
$string['privacy:metadata:these_signature:signername'] = 'Signatory name.';
$string['privacy:metadata:these_signature:signeremail'] = 'Address of a signatory without an account.';
$string['privacy:metadata:these_signature:ipaddress'] = 'IP address when signing.';
$string['privacy:metadata:these_signature:timesigned'] = 'Signature date.';
$string['directorfunction'] = 'Your position';
$string['directorfunction_help'] = 'Position printed in the scientific approval: "I, the undersigned NAME First name, position, declare…" (for example: Associate professor, Professor).';

// Document templates.
$string['doctemplates'] = 'Document templates';
$string['doctemplates_desc'] = 'Each document produced by this activity is built from a template: HTML in which the record data are '
    . 'inserted with {{...}} tags. The original templates reproduce the institution forms; a modified template can be restored to '
    . 'its original at any time. Documents already produced do not change.';
$string['doctpldocument'] = 'Document';
$string['doctplcustomised'] = 'Modified';
$string['doctploriginal'] = 'Original template';
$string['doctplpreview'] = 'PDF preview';
$string['doctplreset'] = 'Restore original';
$string['doctplresetconfirm'] = 'Restore the original template of the document "{$a}"? Your changes will be lost.';
$string['doctplresetdone'] = 'The original template of the document "{$a}" has been restored.';
$string['doctplsaved'] = 'The template of the document "{$a}" has been saved.';
$string['doctplintro'] = 'The PDF preview opens in a new tab with sample data (complete record, signed in every slot), without saving '
    . 'the template.';
$string['doctplcontent'] = 'Template';
$string['doctplcontent_help'] = 'HTML as interpreted by TCPDF: tables (widths in mm, borders with a "border" style), inline styles, '
    . 'images, page break with &lt;br pagebreak="true" /&gt;.

* {{variable}} inserts a value (escaped); {{{variable}}} inserts HTML (check boxes box.*, signatures sig.*, *html texts);
* {{#variable}}...{{/variable}} shows the content only if the variable is true or not empty, or repeats it for each item of a list
  (jury...); {{^variable}}...{{/variable}} shows it if it is false or empty;
* the first line may set the layout: &lt;!--@page margins="top,right,bottom,left" header="8" footer="12" font="freesans" size="10" lineheight="1.25"--&gt; (mm, points);
* &lt;!--@header--&gt;, &lt;!--@body--&gt; and &lt;!--@footer--&gt; separate the header and footer, repeated on every page, from the content;
  {{pagenumber}} and {{pagecount}} give the page number and page count there.';
$string['doctplerror'] = 'This template does not produce a document: {$a}';
$string['doctplvariables'] = 'Available variables';
$string['doctplvariables_desc'] = 'Examples taken from the preview sample data. Signatures are empty until the slot is signed; sigsrc.* '
    . 'gives the image source to use in &lt;img src="{{sigsrc.director}}" height="10mm" /&gt;.';
$string['doctplvariable'] = 'Variable';
$string['doctplexample'] = 'Example';
$string['doctplvarimage'] = 'Image, to use in <img src="...">';
$string['doctplvarlist'] = 'List (items: {$a})';
$string['doctplvarbool'] = 'True / false ({$a})';
$string['doctplvarhtml'] = 'HTML, to insert with {{{...}}}';
$string['doctplsubject'] = 'Thesis subject form (E01)';
$string['doctplagreement'] = 'Administrative and scientific agreement form (E02)';
$string['doctplsignaletique'] = 'Identification form (E03)';
$string['doctplbordereau'] = 'Thesis form (ABES)';
$string['doctplreport'] = "Director's report";
$string['doctplgrid'] = 'Conformity grid';
$string['doctplconclusions'] = 'Conclusions';
$string['doctplcontractdata'] = "Distribution contract (E04): author's data and choices page";
$string['doctplcontractsign'] = 'Distribution contract (E04): signature page';
$string['doctplconformity'] = 'Conformity certificate (library)';
$string['doctplconvocation'] = 'Defence notice';
$string['doctpljurysheet'] = 'Jury sheet';
$string['doctplminutes'] = 'Defence minutes';

// Settings transfer.
$string['transfersettings'] = 'Settings transfer';
$string['transfersettings_desc'] = 'Take the settings of another thesis management activity (new course, new year) or of a file '
    . 'exported from another site, or export the settings of this activity. Thesis files are never transferred.';
$string['transferimportheading'] = 'Take settings';
$string['transferexportheading'] = 'Export the settings of this activity';
$string['transferexport_desc'] = 'The file (JSON) can be taken into another activity, on this site or another one.';
$string['transfersource'] = 'Source';
$string['transfersourceactivity'] = 'Another activity on this site';
$string['transfersourcefile'] = 'An exported settings file';
$string['transferactivity'] = 'Activity';
$string['transferfile'] = 'Settings file (.json)';
$string['transfergroups'] = 'Settings';
$string['transfergroups_help'] = 'Each ticked group fully replaces that of the activity: an email or template customised here but not '
    . 'in the source goes back to its original text. Unticked groups do not change.';
$string['transfergroupactivity'] = 'Activity settings (institution, signatory, location, jury, service addresses, reminders)';
$string['transfergroupemails'] = 'Customised emails';
$string['transfergroupdoctemplates'] = 'Document templates';
$string['transfergroupdocimages'] = 'Template images';
$string['transferconfirmreplace'] = 'I confirm the replacement of the ticked settings of this activity';
$string['transferconfirmrequired'] = 'Confirm the replacement to continue.';
$string['transferimport'] = 'Transfer';
$string['transferexport'] = 'Download the settings file';
$string['transfernogroup'] = 'Tick at least one group of settings.';
$string['transferbadfile'] = 'This file is not a thesis management settings file.';
$string['transferdone'] = 'Settings taken from "{$a}":';
$string['transferdoneactivity'] = 'activity settings replaced;';
$string['transferdoneemails'] = '{$a} customised email(s);';
$string['transferdonedoctemplates'] = '{$a} modified document template(s);';
$string['transferdonedocimages'] = '{$a} image(s).';
$string['transferskipped'] = 'Items not taken:';
$string['transferskippedemail'] = 'unknown email "{$a}"';
$string['transferskippeddoctemplate'] = 'unknown document "{$a}"';
$string['transferskippedimage'] = 'unreadable or duplicate image "{$a}"';
