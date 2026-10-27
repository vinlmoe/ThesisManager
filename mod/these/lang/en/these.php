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
$string['minjury_help'] = 'Minimum number of voting members (chair included, thesis director and guests excluded) required in the jury, both when the '
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
$string['progresscurrent'] = 'Step {$a->position} of {$a->total}: {$a->label}';
$string['progressall'] = 'Show all steps';
$string['dormantphase'] = 'dormant file';

// Navigation and lists.
$string['dashboard'] = 'Dashboard';
$string['planning'] = 'Defence schedule';
$string['planningviewweek'] = 'By week';
$string['planningviewmonth'] = 'By month';
$string['planningweekof'] = 'Week of {$a->start} to {$a->end}';
$string['planningprevweek'] = 'Previous week';
$string['planningthisweek'] = 'This week';
$string['planningnextweek'] = 'Next week';
$string['planningprevmonth'] = 'Previous month';
$string['planningthismonth'] = 'This month';
$string['planningnextmonth'] = 'Next month';
$string['planninggoto'] = 'Go to';
$string['planningcount'] = '{$a} defence(s)';
$string['planningnone'] = 'No defence in this period.';
$string['planningtime'] = 'Time';
$string['planningpromotion'] = 'Cohort';
$string['planningconfirmed'] = 'Date confirmed';
$string['planningproposed'] = 'Date proposed';
$string['planningpdfweek'] = 'Print the week\'s schedule (PDF)';
$string['planningpdfmonth'] = 'Print the monthly schedule (PDF)';
$string['planningdownload'] = 'Download the schedule for this period';
$string['planningupcoming'] = 'Upcoming weeks with defences';
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
$string['devenumber_help'] = 'Registration number of the thesis, assigned after the defence submission. The suggested number is the next one of the current year '
    . '(YYYY-NNN format); it can be changed but must remain unique in the activity.';
$string['devenumbertaken'] = 'This number is already used by another file.';
$string['libraryref'] = 'Bibliographic record reference';

// Defence.
$string['defense'] = 'Defence';
$string['depositdefense'] = 'Submit the defence documents';
$string['defenseprogress'] = 'Progress: {$a->complete} of {$a->total} sections complete. Sections still to complete are expanded below.';
$string['defensesectioncomplete'] = 'Complete';
$string['defensesectionmissing'] = '{$a} field(s) to complete';
$string['defenseintro'] = 'Upload your manuscript and defence documents and propose a date, a location and the jury (at least '
    . '{$a} voting members, chair included; your director sits without voting). You can prepare this submission in several steps ("Save draft"); submitting '
    . 'sends it to your director, who will approve it and attach a report.';
$string['defensedocuments'] = 'Defence documents';
$string['manuscript'] = 'Manuscript (PDF)';
$string['manuscript_help'] = 'Complete version of the manuscript, as a single PDF file.';
$string['manuscriptrequired'] = 'The manuscript is required to submit.';
$string['defensedocs'] = 'Other defence documents';
$string['defensedocs_help'] = 'Optional: abstract, dissemination authorisation, appendices...';
$string['defenseproposal'] = 'Defence proposal';
$string['proposeddaterequired'] = 'Tick « Enable », then choose the proposed date and time.';
$string['defenseroomshelp'] = 'Suggested rooms: {$a}. You may also enter another location.';
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
$string['mention_help'] = 'Distinction awarded by the jury to an admitted thesis: honourable, very honourable, very honourable with the jury\'s congratulations.';
$string['minutes'] = 'Defence minutes';

// Jury.
$string['jury'] = 'Jury';
$string['juryproblem'] = 'Jury composition to correct: {$a}';
$string['juryedit'] = 'Change the jury composition';
$string['juryeligiblecheckof'] = 'Eligibility of {$a} checked (registrar)';
$string['roomconflicts'] = 'Room already booked for this slot (overlapping defences):';
$string['roomconflict'] = '{$a->location}: defence of {$a->student} on {$a->date} ({$a->activity}).';
$string['roomconflictshort'] = 'Room booked: {$a->student}, {$a->time}';
$string['juryhelpdeve'] = "One row per member, preferably chosen from the jury directory.";
$string['juryhelp'] = 'One row per member. Preferably choose the teacher from the jury directory: title, status, institution '
    . 'and address are taken from it. For an external member or a guest, choose "External member or guest" and fill in the row '
    . '(the status is required for a voting member; the thesis director sits without voting). Only HDR associate professors, professors and emeriti may chair. The registrar '
    . 'then validates the eligibility of each member; members are then notified by email and receive a link to the file documents.';
$string['juryrole'] = 'Role';
$string['juryrolepresident'] = 'Chair';
$string['juryroledirector'] = 'Thesis director (non-voting)';
$string['juryrolemember'] = 'Jury member';
$string['juryroleguest'] = 'Guest member';
$string['juryname'] = 'Name';
$string['jurytitle'] = 'Title or position';
$string['juryinstitution'] = 'Institution';
$string['juryemail'] = 'Email';
$string['juryaddmembers'] = 'Add rows';
$string['juryonepresident'] = 'The jury must have exactly one chair.';
$string['juryminmembers'] = 'The jury must have at least {$a} voting members (chair included, thesis director and guests excluded).';
$string['juryinvalidemail'] = 'Invalid email address for {$a}.';
$string['juryjuror'] = 'Teacher from the directory';
$string['juryjuror_help'] = 'Teacher chosen from the jury directory kept by the registrar. For an external member (another '
    . 'institution, practitioner) or a guest, choose "External member or guest" and fill in the fields that appear.';
$string['juryexternal'] = '— External member or guest (to fill in) —';
$string['jurystatus'] = 'Status';
$string['jurystatus_help'] = 'Rank of the teacher. All may supervise a thesis or sit on the jury; only HDR associate professors, '
    . 'professors and emeriti (professors and associate professors) may chair.';
$string['jurystatusir'] = 'Research engineer';
$string['jurystatusmcf'] = 'Associate professor';
$string['jurystatusmcfhdr'] = 'Associate professor (HDR)';
$string['jurystatusmcfstag'] = 'Probationary associate professor';
$string['jurystatusprstag'] = 'Probationary professor';
$string['jurystatuspr'] = 'Professor';
$string['jurystatuspremerite'] = 'Emeritus professor';
$string['jurystatusmcfemerite'] = 'Emeritus associate professor';
$string['jurystatusother'] = 'Other (practitioner, researcher...)';
$string['jurystatusrequired'] = 'Give the status of {$a} (external member).';
$string['juryduplicate'] = '{$a} appears twice in the jury.';
$string['jurypresidentnoteligible'] = '{$a} cannot chair the jury: only HDR associate professors, professors and emeriti can.';
$string['juryeligiblecheck'] = 'Eligibility checked (registrar)';
$string['juryeligiblehelp'] = "Tick the eligibility of each checked voting member (already ticked for the directory).";
$string['juryeligiblerequired'] = 'The eligibility of {$a} is not validated.';
$string['jurynotified'] = '{$a} jury member(s) notified.';
$string['jurynotifiedmissing'] = 'Without address, to notify by other means: {$a}.';
$string['juryeligibilityok'] = 'Eligible';
$string['juryeligibilitytocheck'] = 'To be checked by the registrar';
$string['juryeligibilityko'] = 'Not eligible for this role';
$string['juryeligibilityvalidated'] = 'Eligibility validated';
$string['jurynotifiedon'] = 'Notified on {$a}';
$string['juryresendaccess'] = 'Send the jury access again';
$string['juryaccessresent'] = '{$a} jury member(s) notified again.';
$string['juryaccess'] = 'Jury access';
$string['juryaccessintro'] = 'Hello {$a->name}, you are appointed as {$a->role} of the jury of this thesis. Below are the defence '
    . 'details and the file documents.';
$string['juryaccessinvalid'] = 'This access link is not (or no longer) valid: it was replaced by a new one, the defence '
    . 'was more than 60 days ago, or the file is closed. Contact the registrar.';
$string['juryaccessdocuments'] = 'Documents';
$string['juryaccessnodocuments'] = 'No document is available yet.';

// Jury directory.
$string['jurors'] = 'Jury directory';
$string['juryconflicts'] = 'Jury member already sitting on another jury in this slot (overlapping defences):';
$string['juryconflict'] = '{$a->member} sits on the jury of the defence of {$a->student} on {$a->date} ({$a->activity}).';
$string['juryconflictshort'] = 'Conflict: {$a->member} already on the jury of {$a->student} at {$a->time}';
$string['jurors_desc'] = 'Teachers who may supervise a thesis or sit on a jury, shared by all activities and all cohorts. Jury forms '
    . 'draw on it to be prefilled. A withdrawn (inactive) teacher is no longer offered but stays in the juries that chose them.';
$string['jurortitle'] = 'Title';
$string['juroractive'] = 'Offered in jury forms';
$string['juroractiveyes'] = 'Active';
$string['juroractiveno'] = 'Withdrawn';
$string['jurorpresident'] = 'May chair';
$string['juroradd'] = 'Add a teacher';
$string['juroredit'] = 'Edit a teacher';
$string['jurorsaved'] = 'The teacher has been saved.';
$string['jurordeleted'] = 'The teacher has been removed from the directory.';
$string['jurordeleteconfirm'] = 'Remove {$a} from the directory? Juries that chose them keep them.';
$string['jurorsnone'] = 'The directory is empty.';
$string['jurorimport'] = 'Import a CSV file';
$string['jurorimportfile'] = 'CSV file';
$string['jurorimportfile_help'] = 'Columns: title (Dr or Pr), lastname, firstname, email, status, institution, separated by commas '
    . 'or semicolons. The status is the key (ir, mcf, mcfhdr, mcfstag, prstag, pr, premerite, mcfemerite) or the label. A teacher '
    . 'already present (same address, or same names) is updated.';
$string['jurorimportdone'] = '{$a->created} teacher(s) added, {$a->updated} updated.';
$string['jurorimportbadline'] = 'Line {$a} ignored: wrong title, names, status or address.';
$string['jurorprefill'] = 'Prefill with the course teachers';
$string['jurorprefillconfirm'] = 'Add {$a} non-editing teacher(s) enrolled in the course to the directory? They are created with the '
    . 'title Dr, the associate professor status and the default institution: check them afterwards. The registrar and the library are '
    . 'not included, nor teachers already present (same address, or same names).';
$string['jurorprefilldone'] = '{$a} teacher(s) added to the directory. Check their title and status.';
$string['jurorprefillnone'] = 'All non-editing teachers enrolled in the course are already in the directory.';
$string['jurysheet'] = 'Jury sheet';

// Review steps.
$string['reviewdirectorsubject'] = 'Review the subject proposal';
$string['reviewdirectorsubject_help'] = 'Approve the subject so that the registrar can register it, or return it to the student with '
    . 'your comments: the student can correct it and submit it again.';
$string['reviewdevesubject'] = 'Register the subject (registrar)';
$string['reviewdevesubject_help'] = "Check the subject: it then goes to the library.";
$string['reviewlibrarysubject'] = 'Record the subject (library)';
$string['reviewlibrarysubject_help'] = 'Enter the reference of the record created in the library catalogue. This opens the file, '
    . 'which stays active until the defence documents are submitted.';
$string['reviewdirectordefense'] = 'Approve the defence and upload the report';
$string['reviewdirectordefense_help'] = 'Approve the defence proposal (date, jury) once your report on the manuscript is written '
    . '("Write the report"): your signature is applied to the report on approval. You can also return the file to the student with your comments.';
$string['reviewdevedefense'] = 'Validate the defence file (registrar)';
$string['reviewdevedefense_help'] = "Assign the number, set the date, location and jury: the manuscript then goes to the library.";
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
$string['editdossierhelp'] = "No effect on the status. If the date, location or jury change, regenerate the defence documents.";
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

The thesis subject of {$a->student} has been registered by the registrar and by the library. The file is open and stays active until the defence documents are submitted.

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
$string['taskdigest'] = 'Registrar weekly digest';
$string['emailkeydigest'] = 'Registrar weekly digest';
$string['emaildigestsubject'] = 'Theses: weekly digest ({$a->activity})';
$string['emaildigestbody'] = 'Hello,

Here is the weekly digest for {$a->activity}.

Files waiting for the registrar:
{$a->todo}

Signatures pending for more than a week:
{$a->signatures}

Defences in the next seven days:
{$a->defenses}

Jury or room conflicts (next fifteen days):
{$a->conflicts}

Dashboard: {$a->url}';
$string['digestnone'] = 'none';
$string['digestconflicts'] = 'jury conflicts: {$a->jury}, room conflicts: {$a->rooms}';
$string['settingsdigest'] = 'Weekly digest';
$string['settingsweeklydigest'] = 'Weekly digest';
$string['settingsweeklydigestlabel'] = 'Send a digest to the registrar every Monday';
$string['settingsweeklydigest_help'] = 'Every Monday morning, a single email to the registrar (service address or registrar members): files waiting for them, signatures pending for more than a week, defences in the next seven days and jury or room conflicts in the next fifteen days. Nothing is sent when there is nothing to report.';
$string['calendardefense'] = 'Thesis defence: {$a}';
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
$string['privacy:metadata:these_jury:jurystatus'] = 'Status (rank) of the jury member.';
$string['privacy:metadata:these_a6'] = "A student's A6 validation, entered by the registrar and shared by all activities.";
$string['privacy:metadata:these_a6:userid'] = "Student.";
$string['privacy:metadata:these_a6:a6date'] = "A6 validation date (0 if not validated).";
$string['privacy:metadata:these_a6:a6school'] = "School where the A6 was validated.";
$string['privacy:metadata:these_a6:usermodified'] = "Registrar account that entered it.";
$string['privacy:metadata:these_a6:timemodified'] = "Date entered.";
$string['privacy:metadata:these_juror'] = 'Directory of teachers who may supervise a thesis or sit on a jury, kept by the registrar (not linked to a Moodle account).';
$string['privacy:metadata:these_juror:title'] = 'Title of the teacher (Dr or Pr).';
$string['privacy:metadata:these_juror:lastname'] = 'Last name of the teacher.';
$string['privacy:metadata:these_juror:firstname'] = 'First name of the teacher.';
$string['privacy:metadata:these_juror:email'] = 'Email address of the teacher.';
$string['privacy:metadata:these_juror:status'] = 'Status (rank) of the teacher.';
$string['privacy:metadata:these_juror:institution'] = 'Institution of the teacher.';
$string['privacy:metadata:these_answer'] = "Student answers to the pre-jury questionnaire.";
$string['privacy:metadata:these_answer:questionid'] = 'Question.';
$string['privacy:metadata:these_answer:value'] = 'Answer.';
$string['privacy:metadata:these_answer:timemodified'] = 'Time of the answer.';
$string['privacy:metadata:these_log'] = 'History of actions on a file.';
$string['privacy:metadata:these_log:userid'] = 'Author of the action.';
$string['privacy:metadata:these_log:logaction'] = 'Action performed.';
$string['privacy:metadata:these_log:remark'] = 'Comment entered with the action.';
$string['privacy:metadata:these_log:timecreated'] = 'Date of the action.';
$string['privacy:metadata:core_files'] = 'Files of the thesis files: subject attachments, manuscript, defence documents, director report, generated documents, minutes, corrected manuscript, saved signature of the user.';
$string['privacy:metadata:jury'] = 'Jury members receive their notice by email, mentioning the student, the subject and the defence date.';
$string['privacy:metadata:jury:student'] = 'Student name.';
$string['privacy:metadata:jury:title'] = 'Thesis title.';
$string['privacy:metadata:jury:defensedate'] = 'Defence date and location.';

// Workflow settings ("Thesis follow-up" activity, otherwise the activity itself; no site setting).
$string['settingsmenu'] = 'Settings';
$string['sharedsettingslink'] = 'Workflow settings, templates, grid and questionnaire ({$a})';
$string['bulkremind'] = 'Remind signatories';
$string['bulkgenerate'] = 'Generate defence documents';
$string['bulknotifyjury'] = 'Notify jury members not yet notified';
$string['bulknothing'] = 'Tick at least one file and choose an action.';
$string['bulkdone'] = '{$a->action}: {$a->done} file(s) processed, {$a->skipped} skipped (step not suitable, or nothing pending).';
$string['bulkfor'] = 'For the ticked files:';
$string['bulkapply'] = 'Apply';
$string['settingschecktitle'] = 'Settings to complete: {$a}';
$string['settingscheckcomplete'] = 'Settings complete.';
$string['settingscheckdone'] = 'Done';
$string['settingschecktodo'] = 'To complete';
$string['settingscheckoptional'] = 'Optional';
$string['settingscheckinstitution'] = 'Institution and university';
$string['settingscheckdg'] = 'General management: title and name';
$string['settingscheckccem'] = 'CCEM chair';
$string['settingscheckucbl'] = 'University presidency: title and name';
$string['settingscheckdeve'] = 'Name of the director of studies';
$string['settingscheckcontract'] = 'Standard electronic dissemination contract';
$string['settingscheckrooms'] = 'Defence rooms';
$string['settingscheckagents'] = 'Registrar signing agents';
$string['settingscheckquestions'] = 'Pre-jury questionnaire';
$string['settingscheckimages'] = 'Document template images';
$string['settingscheckimagesmissing'] = 'Document template images (missing: {$a})';
$string['settingsmissingnotice'] = 'Workflow settings to complete: {$a}.';
$string['settingslibraryagent'] = 'Library agents';
$string['settingslibraryagent_help'] = "Library agents, chosen among the staff enrolled in the courses of the followed activities (enrolled users who do not submit a thesis) and the holders of the library role: type a name to find them. Without any role to assign, they get the library's rights (subject record, manuscript compliance, corrections) and only those: no registrar buttons or pages, even if their course role would give them. They receive the emails addressed to the library. Someone missing from the list must first be enrolled in the course.";
$string['settingsagentsnone'] = 'No agent selected';
$string['settingschecklibraryagents'] = 'Library agents';
$string['scopesettings'] = 'Workflow settings';
$string['scopesettings_desc'] = 'Service roles, registrar signing agents, institution, university, document signatories and standard '
    . 'distribution contract of this activity. None of them is a site setting.';
$string['scopesettingsshared_desc'] = 'Service roles, registrar signing agents, institution, university, document signatories and '
    . 'standard distribution contract shared by all the followed thesis activities. None of them is a site setting.';
$string['settingsroles'] = 'Service roles';
$string['deverole'] = 'Registrar role';
$string['deverole_help'] = 'Role whose holders act as the registrar in thesis activities.';
$string['libraryrole'] = 'Library role';
$string['libraryrole_help'] = 'Role whose holders act as the library. By default, the internal "Library (theses)" role created when '
    . 'the module is installed.'
    . ' ' . 'Just as the registrar is recognised by the "Teacher" role, the library is recognised by the role chosen '
    . 'here. The role can be assigned in the course, its category or at site level (it then gives access to all thesis activities '
    . 'without enrolment). The module capabilities are also taken into account.';
$string['libraryrolename'] = 'Library (theses)';
$string['libraryroledescription'] = 'Internal role of the thesis management module: recording subjects in the library catalogue and '
    . 'certifying manuscripts. Assign it at site, category or course level.';

// Documents (workflow settings).
$string['settingsdefenses'] = 'Defences';
$string['settingsdefenseduration'] = 'Duration of a defence (minutes)';
$string['settingsdefenseduration_help'] = 'Used to detect jury conflicts: a member sitting on two overlapping defences is flagged in '
    . 'the schedule and on the file. 60 minutes by default.';
$string['settingsdefenserooms'] = 'Defence rooms';
$string['settingsdefenserooms_help'] = 'One room per line. They are suggested when entering the location (student deposit, DEVE validation and editing); the first one is the default. Another location remains possible.';
$string['settingsdefensedurationinvalid'] = 'Enter a duration of at least one minute.';
$string['settingsdocuments'] = 'Documents and signatories';
$string['doctplimages'] = 'Template images';
$string['doctplimages_help'] = 'Images (PNG or JPEG) usable in the document templates as {{img.name}}, name being the file name in '
    . 'lower case, without extension or special characters. The module provides none: the default templates use logo, ministere, '
    . 'universite, bibliotheque and abes, and an image not uploaded is simply left out.';
$string['doctplimageslots'] = 'Images used by the default templates (PNG with a transparent background recommended, about 300 dpi):';
$string['doctplimageslot'] = '{$a->file}: {$a->role} — {$a->state}';
$string['doctplimageslotlogo'] = 'logo of the institution, at the top of every document and of the schedule';
$string['doctplimageslotministere'] = 'Marianne brand block of the supervising ministry, at the top of the official documents';
$string['doctplimageslotuniversite'] = 'logo of the university awarding the degree (agreement form, record sheet)';
$string['doctplimageslotbibliotheque'] = 'logo of the university library (distribution agreement)';
$string['doctplimageslotabes'] = 'ABES logo (thesis form)';
$string['doctplimagepresent'] = 'uploaded';
$string['doctplimagemissing'] = 'to upload';
$string['settingsdevename'] = 'Head of studies: name';
$string['settingsdevename_help'] = 'Name of the head of studies and student life (agreement form).';
$string['settingsdevetitle'] = 'Head of studies: title';
$string['settingsdevetitle_help'] = 'Title printed on the agreement form (without article).';
$string['settingscontracttemplate'] = 'Electronic distribution contract template (PDF)';
$string['settingscontracttemplate_help'] = 'The official contract (ENS-INI-P308-E04), as is. The module adds before it a page with the '
    . 'author\'s data and choices, and after it a signature page.';
$string['settingsinstitution'] = 'Institution';
$string['settingsinstitution_help'] = 'Short name used in the documents.'
    . ' ' . 'Header and signatories of the institution documents produced by the module. Signatories without '
    . 'a Moodle account (general management, CCEM, university presidency) receive a signing link by email if their address is set; '
    . 'otherwise the registrar collects their signature in person.';
$string['settingsuniversity'] = 'University';
$string['settingsuniversity_help'] = 'University awarding the degree, with its French article (« l\'Université … »): « Pour le président de '
    . '… et par délégation », university libraries of the distribution agreement.';
$string['settingshetopurl'] = 'HeTOP link';
$string['settingshetopurl_help'] = 'Reference terminology for subject keywords.';
$string['settingsdgtitle'] = 'General management (title)';
$string['settingsdgtitle_help'] = 'Full title printed above their signature, in French, for example « La Directrice générale de … ».';
$string['settingsdgname'] = 'General management (name)';
$string['settingsvisaplace'] = 'Place of approval';
$string['settingsvisaplace_help'] = '"Seen, at …" of the conclusions, "Done at" of the distribution contract.';
$string['settingsccemname'] = 'CCEM (name)';
$string['settingsccememail'] = 'CCEM (email)';
$string['settingsccememail_help'] = 'Address receiving signing links; empty: signature collected in person by the registrar.';
$string['settingsucbltitle'] = 'University presidency (title)';
$string['settingsucbltitle_help'] = 'Full title printed above their signature (distribution agreement), in French, for example « Le Président de l\'Université … ».';
$string['settingsucblname'] = 'University presidency (name)';
$string['settingsucblemail'] = 'University presidency (email)';
$string['settingsucblemail_help'] = 'Address receiving signing links; empty: signature collected in person by the registrar.';

// Subject form.
$string['studyyear'] = 'Year of study';
$string['confidential'] = 'Confidential thesis, do not disclose the title';
$string['confidential_help'] = 'The subject of a confidential thesis (title, English title, abstract, keywords) is only known to the student, the registrar, the library, the thesis director and the jury members. The thesis can be found and appears in the schedule like any other, but for everyone else (other teachers, staff who see all files without being the registrar or the library), its title, keywords and abstract are replaced by "Confidential thesis" (pages, lists, schedule, search, export, emails), it is never found by its subject, and the documents bearing the title are not available to them.';
$string['confidentialtitle'] = 'Confidential thesis';
$string['confidentialbadge'] = 'Confidential thesis: subject known only to the student, the registrar, the library, the director and the jury';
$string['confidentialitylabel'] = 'Confidentiality';
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
$string['externalsigners'] = 'Signatures to collect';
$string['signer'] = 'Signatory';
$string['noemail'] = 'no address';
$string['documentstosign'] = 'Documents to sign';
$string['resendsignature'] = 'Send the link';
$string['remindagents'] = 'Remind';
$string['agentsreminded'] = 'Reminder sent to the registrar signatories.';
$string['signinperson'] = 'Sign in person';
$string['signaturerequestsent'] = 'Signing link sent.';
$string['signaturesrecorded'] = 'Signature recorded. Thank you.';
$string['signpagetitle'] = 'Signing thesis documents';
$string['signinvalidtoken'] = 'This link is no longer valid: the documents have already been signed, a new invitation was sent, '
    . 'or the link has expired (60 days). Contact the registrar to receive a new one.';
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
$string['a6validation'] = 'A6 validation';
$string['a6notvalidated'] = 'not validated';
$string['a6bydeve'] = 'Entered by the registrar.';
$string['a6bydevestatus'] = '{$a} (entered by the registrar)';
$string['loga6recorded'] = 'A6 validation recorded';
$string['bulka6'] = 'Record the A6 validation';
$string['bulka6params'] = 'A6 validation (for that action; empty date: not validated):';
$string['a6import'] = 'Import A6 validations';
$string['a6dryrunresult'] = 'Dry run: {$a->ok} student(s) to record, {$a->errors} row(s) with errors.';
$string['a6result'] = 'Import complete: {$a->ok} validation(s) recorded, {$a->errors} row(s) with errors.';
$string['a6import_desc'] = "An Excel (.xlsx), OpenDocument (.ods) or CSV file, one row per student, with a header row: column \"etudiant\" (username, email address or student number), column \"date\" (A6 validation date, dd/mm/yyyy, or \"non\" if not validated) and, optionally, column \"ecole\" (Lyon, Alfort, Nantes, Toulouse, Rouen). The A6 validation belongs to the student, not to their thesis file: any student enrolled in the course may be listed, with or without a thesis file, and it applies to all their files. The dry run checks the file without saving anything.";
$string['a6file'] = 'Validations file';
$string['a6file_help'] = 'Excel (.xlsx), OpenDocument (.ods) or CSV (semicolon or comma separated). Recognised headers: etudiant, date, ecole.';
$string['a6importformat'] = 'Unrecognised file format: Excel (.xlsx), OpenDocument (.ods) or CSV.';
$string['a6importunreadable'] = 'The file cannot be read.';
$string['a6notenrolled'] = "The student is not enrolled in the course of this activity.";
$string['a6baddate'] = 'Unreadable date: "{$a}" (dd/mm/yyyy, or "non").';
$string['a6badschool'] = 'Unknown school: "{$a}".';
$string['a6wouldrecord'] = 'A6 validated on {$a} (dry run)';
$string['a6recordedon'] = 'A6 validated on {$a}';
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
$string['conclusionstext_help'] = 'The conclusions as they will appear in the manuscript. They must fit on one page with the '
    . 'signatures (about 2,200 characters at most; separate paragraphs with a blank line). They will be signed by the director, the '
    . 'jury president, the general management and the CCEM president.';
$string['conclusionstoolong'] = 'The conclusions have {$a->length} characters for {$a->max} at most: they must fit on one page with the signatures.';
$string['conclusionsonepage'] = 'The conclusions do not fit on one page with the signatures: shorten them or use fewer paragraphs.';
$string['contracthelp'] = 'Author choices for the electronic distribution contract (articles 2 and 4). A confidential thesis (box '
    . 'ticked with the subject) stays confidential until the date given.';
$string['contracttemplatemissing'] = 'The standard distribution contract has not been uploaded in the workflow settings: the contract only contains the module\'s pages and will not be sealed until it is provided.';
$string['contracttemplateunreadable'] = 'The standard distribution contract uploaded in the workflow settings could not be read (unsupported PDF format): the contract only contains the module\'s pages and will not be sealed. Upload the contract saved in an older PDF format (PDF 1.4).';
$string['bconfidentialuntil'] = 'Confidential until';
$string['bconfidentialuntil_help'] = 'Only for theses declared confidential with the subject.';
$string['bembargo'] = 'I request a {$a}-year distribution embargo from the defence date';
$string['embargo_help'] = 'During the embargo, the thesis is registered and listed, but its full text is neither distributed online nor available for consultation until the end, 5 years after the defence date. The request must be justified (patent, publication in progress, sensitive data, partnership...). It is stated in the distribution contract, which the thesis director endorses.';
$string['bembargoreason'] = 'Reason for the embargo request';
$string['bembargoreasonrequired'] = 'Give the reason for the embargo request.';
$string['embargo'] = 'Distribution embargo';
$string['embargorequested'] = 'Requested: {$a->years} years after the defence, until {$a->until}';
$string['embargorequestednodate'] = 'Requested: {$a} years after the defence (defence date to be set)';
$string['embargoreason'] = 'Embargo reason';
$string['embargoexport'] = 'Embargo until';
$string['diffusion'] = 'Distribution';
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
$string['gridsectionpdffond'] = 'Content conformity';
$string['gridsectionpdfforme'] = 'Form conformity';
$string['gridconditional'] = '(depending on the thesis)';
$string['griditems'] = 'Conformity grid';
$string['griditems_desc'] = 'Items of the conformity grid filled by the thesis director. Changing an item updates the PDF grids '
    . '(defence documents already generated will be at their next generation); a removed item disappears from the grids; an added '
    . 'item applies to the grids filled afterwards. "Depending on the thesis": the item may stay unticked. To remove an item, empty '
    . 'its label.';
$string['griditemsection'] = 'Section';
$string['griditemlabel'] = 'Item';
$string['griditemconditional'] = 'Depending on the thesis (optional)';
$string['griditemadd'] = 'Add {no} item(s)';
$string['griditemssaved'] = 'The conformity grid has been saved.';
$string['griditemsreset'] = 'Restore the original grid';
$string['gridstudenthelp'] = 'Tick each point your manuscript complies with. Your supervisor will check this list, complete '
    . 'it if needed and sign it when validating your defence.';
$string['gridstudentconfirm'] = 'I certify that my manuscript complies with the ticked points of the compliance checklist';
$string['gridstudentconfirmrequired'] = 'Certify the compliance checklist before submitting the file.';
$string['gridprefilledbystudent'] = 'Checklist prefilled and certified by the student when submitting: check each point before '
    . 'signing.';
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
$string['logdocumentsealed'] = 'Document sealed';
$string['dossierchanged'] = 'This file was changed in the meantime (by someone else or by submitting the form twice): the action was not recorded. Reload the file to see its current state.';
$string['dossierbusy'] = 'This file is being processed by another action. Try again in a moment.';
$string['logsignaturesinvalidated'] = 'Signatures invalidated (document changed)';
$string['logsignaturerequested'] = 'Signature requested';
$string['logsignedinperson'] = 'Signature collected in person';
$string['logidentityverified'] = 'Identity of the signatory checked by the registrar';
$string['emailkeysignaturerequest'] = 'Signature request (signatories without an account)';
$string['emailkeyagentsignature'] = 'Documents to be signed by the registrar\'s signing officer';
$string['emailkeysubjectchanged'] = 'Change of subject';
$string['emailkeyadjourned'] = 'Thesis adjourned';
$string['emailkeyjurydesignation'] = 'Appointment of a jury member';
$string['emailjurydesignationsubject'] = 'Appointment to the thesis jury of {$a->student}';
$string['emailjurydesignationbody'] = 'Hello {$a->name},

You are appointed as {$a->role} of the jury of the veterinary thesis of {$a->student}, entitled "{$a->title}".

Planned defence: {$a->date}, {$a->location}.

The manuscript and file documents are available through this personal link: {$a->url}

The official notice will follow separately.';
$string['emailsignaturerequestsubject'] = 'Thesis of {$a->student}: document(s) to sign';
$string['emailsignaturerequestbody'] = 'Hello {$a->name},

The following documents of the thesis of {$a->student}, entitled "{$a->title}", are waiting for your signature: {$a->document}.

To read and sign them: {$a->url}

This link is personal and valid for one signature only.';

// PDF labels (the documents are official French forms: labels stay close to the originals).
$string['pdfsignedby'] = 'Electronically signed by {$a->name} on {$a->date} — fingerprint {$a->hash}';
$string['pdfsignedbyinperson'] = 'Signed in person by {$a->name} on {$a->date}, identity checked by the registrar ({$a->verifier}) — fingerprint {$a->hash}';
$string['pdfsignedbyscan'] = 'Signed by hand by {$a->name} on {$a->date}, scan attached to the file by the registrar ({$a->verifier}) — fingerprint {$a->hash}';
$string['pdfdelegated'] = '{$a} (by delegation)';
$string['signeddelegated'] = 'by delegation';
$string['signedmethodinperson'] = 'in person, identity checked by the registrar';
$string['signedmethodscan'] = 'by hand, scan attached to the file';
$string['signidentity'] = 'Checking the identity of the signatory';
$string['signidentity_desc'] = 'Check the identity of the person present (identity document) before opening the signing page. '
    . 'The check is recorded in the history under your name, kept with the signature and printed under it.';
$string['signidentityverified'] = 'I certify that I have checked the identity of the signatory (identity document)';
$string['signidentityopen'] = 'Open the signing page';
$string['signinfoslot'] = 'Signing as';
$string['reviewsummary'] = 'File under review';
$string['reviewfulldossier'] = 'Complete file (click to expand)';
$string['mysignaturesdossier'] = '{$a->student} — "{$a->title}"{$a->number}';
$string['signinfostudent'] = 'Student';
$string['signinfotitle'] = 'Thesis title';
$string['signinfonumber'] = 'Registrar number';
$string['signinfodefense'] = 'Defence';
$string['signinfodocuments'] = 'Documents to read before signing';
$string['signidentityrequired'] = 'Check the identity of the signatory before recording their signature.';
$string['signidentityverifiedby'] = 'identity checked by {$a}';
$string['scanslots'] = 'Signatories of the scan';
$string['scansignaturesrecorded'] = '{$a} signature(s) recorded as signed by hand.';
$string['scanslots_desc'] = 'Tick the signatures on the scanned document: they are recorded as signed by hand on the date above, '
    . 'and the document PDF and the signature tracking are updated.';
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
$string['doctplplanning'] = 'Defence schedule';
$string['doctplteachinglist'] = 'Teaching staff list';
$string['teachinglist'] = 'Teaching staff list (PDF)';
$string['teachinglist_help'] = 'Active teachers of the jury directory whose institution is the one in the workflow settings, grouped by status, up to date on the day of download: the page is to be included in the thesis manuscript.';
$string['teachinglistdefense'] = 'Up-to-date teaching staff list to include in your manuscript:';
$string['teachinggrouppr'] = 'Professors';
$string['teachinggrouppremerite'] = 'Emeritus professors';
$string['teachinggroupprstag'] = 'Probationary professors';
$string['teachinggroupmcfhdr'] = 'Associate professors (HDR)';
$string['teachinggroupmcf'] = 'Associate professors';
$string['teachinggroupmcfemerite'] = 'Emeritus associate professors';
$string['teachinggroupmcfstag'] = 'Probationary associate professors';
$string['teachinggroupir'] = 'Research engineers';

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
$string['transfergroupgriditems'] = 'Conformity grid (items)';
$string['transferdonegriditems'] = '{$a} conformity grid item(s);';
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

// Settings shared by a "Thesis follow-up" activity.
$string['sharedsettings'] = 'This activity is followed by the "Thesis follow-up" activity {$a->name}, where "{$a->what}" is '
    . 'defined for every followed cohort: edit it there.';
$string['doctemplatesshared_desc'] = 'Document templates shared by every "Thesis management" activity followed by this activity: '
    . 'each cohort produces its documents from them. Tags and layout are described in the guide; the preview uses sample data.';
$string['griditemsshared_desc'] = 'Conformity grid items shared by every "Thesis management" activity followed by this activity. '
    . 'Changing an item updates the PDF grids produced afterwards.';

// Pre-jury questionnaire.
$string['questions'] = 'Pre-jury questionnaire';
$string['questions_desc'] = 'Questions asked to the student before the defence submission: short answer, long answer, list or '
    . 'multiple choice. Required questions condition the submission; answers appear in the record, are shown to the jury and are '
    . 'used to search theses.';
$string['questionsshared_desc'] = 'Questions shared by every "Thesis management" activity followed by this activity, asked to the '
    . 'student before the defence submission. Required questions condition the submission; answers are used to search theses.';
$string['questionsnone'] = 'No question is defined.';
$string['questionadd'] = 'Add a question';
$string['questionedit'] = 'Edit the question';
$string['questiontype'] = 'Question type';
$string['questionlabel'] = 'Question';
$string['questionoptions'] = 'Choices';
$string['questionoptions_help'] = 'One choice per line. A list accepts a single choice, a multiple-choice question several.';
$string['questionoptionsrequired'] = 'Enter at least two choices, one per line.';
$string['questionrequired'] = 'Answer required';
$string['questionsaved'] = 'Question saved.';
$string['questiondeleted'] = 'Question deleted.';
$string['questiondeleteconfirm'] = 'Delete the question "{$a->label}" and the {$a->answers} answer(s) already given?';
$string['qtypetext'] = 'Short answer';
$string['qtypetextarea'] = 'Long answer';
$string['qtypelist'] = 'List (one choice)';
$string['qtypemulti'] = 'Multiple choice';
$string['questionnaire'] = 'Pre-jury questionnaire';
$string['questionnaire_desc'] = 'Answer these questions before submitting the defence. Your answers appear in the record and are '
    . 'shown to the jury; they are also used to find theses.';
$string['questionnairesaved'] = 'Answers saved.';
$string['questionnaireunanswered'] = 'No answer';
$string['questionnairemissing'] = 'Required questions without an answer: {$a}.';
$string['questionnairecomplete'] = 'Questionnaire completed.';
$string['questionnairefill'] = 'Fill in the questionnaire';
$string['questionnairerequired'] = 'Fill in the pre-jury questionnaire before submitting the defence.';
$string['transfergroupquestions'] = 'Pre-jury questionnaire (questions)';
$string['transferdonequestions'] = '{$a} question(s);';

// Director report written in the module.
$string['writereport'] = 'Write the report';
$string['writereport_desc'] = 'Write your report on the manuscript: it is printed in the PDF report, which must fit on one page with '
    . 'your signature. You can save it several times and preview it; you sign it when approving the defence.';
$string['reportsave'] = 'Save the report';
$string['reportsaved'] = 'Report saved.';
$string['reportnoteditable'] = 'The report can only be written before the director approves the defence.';
$string['reportnotwritten'] = 'Report not written.';
$string['reportwrittenfavorable'] = 'Report written, favourable opinion.';
$string['reportwrittennotfavorable'] = 'Report written, without a favourable opinion.';
$string['reporttoolong'] = 'The report has {$a->length} characters for at most {$a->max}: it must fit on one page with the signature.';
$string['reportonepage'] = 'The report does not fit on one page with the signature: shorten it.';
$string['logreportsaved'] = 'Director report saved';

// Minutes: jury decisions.
$string['minutesscan'] = 'Signed minutes (scan)';
$string['minutesscan_help'] = 'The minutes signed by the jury members, scanned (PDF or images), on which the jury ticked its decisions.';
$string['jurydecisions'] = 'Jury decisions';
$string['jurydecisions_help'] = 'Report the decisions ticked by the jury on the minutes. Without corrections, the file is closed; if '
    . 'the jury requests corrections, the student submits the corrected manuscript, which the director, the library and then the '
    . 'registrar approve before closing. An adjourned thesis goes back to the defence phase: the student submits a new defence '
    . 'file, which follows the whole circuit again.';
$string['mentionhonorable'] = 'Honourable';
$string['mentiontreshonorable'] = 'Very honourable';
$string['mentionfelicitations'] = 'Very honourable with the jury\'s congratulations';
$string['prizeproposal'] = 'Proposed for a thesis prize';
$string['correctionsrequested'] = 'Corrections requested by the jury';
$string['recordminutes'] = 'Save the minutes';
$string['correctionsopened'] = 'Minutes saved: the student is asked to submit the corrected manuscript.';

// Corrections circuit.
$string['corrections'] = 'Corrections';
$string['correctionsnote'] = 'Requested corrections';
$string['correctionsnote_help'] = 'Corrections requested by the jury, as written in the minutes: they are sent to the student.';
$string['correctionsdone'] = 'Corrections made';
$string['correctionsdone_help'] = 'Describe the corrections made to the manuscript, point by point.';
$string['correctedmanuscript'] = 'Corrected manuscript';
$string['correctedmanuscriptrequired'] = 'The corrected manuscript is required.';
$string['depositcorrections'] = 'Submit the corrections';
$string['correctionsnotexpected'] = 'No correction is expected for this file.';
$string['correctionssubmittedmsg'] = 'Corrections submitted: your thesis director has been notified.';
$string['statuscorrections'] = 'Corrections requested by the jury';
$string['statuscorrectionssubmitted'] = 'Corrections submitted, awaiting the director';
$string['statuscorrectionsapproved'] = 'Corrections approved by the director, awaiting the library';
$string['statuscorrectionsconform'] = 'Corrections approved by the library, awaiting the registrar';
$string['stepcorrections'] = 'Corrections, if any';
$string['reviewdirectorcorrections'] = 'Approve the corrections (director)';
$string['reviewdirectorcorrections_help'] = 'Check that the corrected manuscript addresses the corrections requested by the jury. You '
    . 'can also return it to the student with your comments.';
$string['reviewlibrarycorrections'] = 'Approve the corrections (library)';
$string['reviewlibrarycorrections_help'] = 'Check the format of the corrected manuscript before its final deposit. A non-compliant '
    . 'manuscript is returned to the student with the reason.';
$string['reviewdevecorrections'] = 'Approve the corrections and close the file (registrar)';
$string['reviewdevecorrections_help'] = 'Approving the corrections closes the file; the student and the director are notified.';
$string['validatedirectorcorrections'] = 'Approve the corrections';
$string['validatelibrarycorrections'] = 'Approve the corrections';
$string['validatedevecorrections'] = 'Approve and close the file';
$string['logminutescorrections'] = 'Minutes uploaded, corrections requested';
$string['logcorrectionssubmitted'] = 'Corrected manuscript submitted';
$string['logcorrectionsresubmitted'] = 'Corrected manuscript submitted again';
$string['logvalidateddirectorcorrections'] = 'Corrections approved by the director';
$string['logvalidatedlibrarycorrections'] = 'Corrections approved by the library';
$string['logvalidateddevecorrections'] = 'Corrections approved by the registrar, file closed';
$string['privacy:metadata:these_dossier:correctionsdone'] = 'Corrections made, described by the student.';

// Adjournment.
$string['minutesadjourned'] = 'Adjournment minutes';
$string['adjournednotice'] = 'Defence adjourned on {$a}: the file is back in the defence phase, awaiting a new submission from the '
    . 'student.';
$string['adjournednoticestudent'] = 'Your defence was adjourned on {$a}. Submit a new defence file (revised manuscript, date, '
    . 'jury): it will be approved again by your director, the registrar and the library.';
$string['logadjourned'] = 'Minutes uploaded, defence adjourned: back to the defence phase';
$string['logsignedscan'] = 'Signed scan uploaded (signed outside the module)';
$string['scanlogremark'] = 'Document: {$a->doctype}. Signatories: {$a->signer}. SHA-256 fingerprint: {$a->files}.';
$string['signedscans'] = 'Signed scans (outside the module)';
$string['addsignedscan'] = 'Add a signed scan';
$string['recordscan'] = 'Save the scan';
$string['scandoctype'] = 'Signed document';
$string['scandoctypesubject'] = 'Subject form (E01)';
$string['scandoctypeagreement'] = 'Approval form (E02)';
$string['scandoctypesignaletique'] = 'Thesis details form (E03)';
$string['scandoctypecontract'] = 'Distribution contract (E04)';
$string['scandoctypeconclusions'] = 'Conclusions';
$string['scansigneddate'] = 'Signature date';
$string['scanfiles'] = 'Signed scans (PDF or images)';
$string['scanfiles_help'] = 'The document signed by hand by the signer, scanned. It is attached to the file for record keeping: it does not replace the signature in the module and cannot be changed afterwards.';
$string['scanremark'] = 'Comment';
$string['scanrequired'] = 'Upload at least one scan.';
$string['scanunavailable'] = 'Signed scans can be uploaded once the file has been validated by the DEVE, and as long as it is not cancelled.';
$string['scanrecorded'] = '{$a} file(s) attached to the file, fingerprint(s) recorded in the history.';
$string['emailadjournedsubject'] = 'Thesis of {$a->student}: defence adjourned';
$string['emailadjournedbody'] = 'Hello,

The thesis defence of {$a->student} has been adjourned by the jury. The file goes back to the defence phase: a new defence file (revised manuscript, date, jury) must be submitted, then approved again by the director, the registrar and the library.

Subject: {$a->title}

Open the file: {$a->url}';

// DEVE agent signatory and saved signature.
$string['agentdeve'] = 'Registrar officer';
$string['settingsdeveagent'] = 'Registrar signatories';
$string['settingsdeveagent_help'] = 'Officers allowed to sign for the registrar, chosen among the registrar members enrolled '
    . 'in the courses of the followed activities (registrar capability or role): type a name to find them. The registrar team '
    . 'fills in and checks the file, then validates without signing; each officer then receives an email and finds in "My '
    . 'signatures" the subject visa, the administrative approval and the general management documents (information sheet, '
    . 'distribution contract, conclusions), which they sign by delegation: the general management never signs itself. The first '
    . 'officer to sign applies the signature, with their saved signature. Without an officer, the registrar member who validates '
    . 'signs the visa and the approval, and the general management documents are signed in person or by scan.';
$string['mysignatures'] = 'My signatures';
$string['mysignatures_desc'] = 'Documents awaiting your signature, all cohorts together. Check each document as a PDF, tick those '
    . 'to sign, then sign once: your saved signature is used by default.';
$string['mysignaturescount'] = 'My signatures ({$a})';
$string['signmyintro'] = 'Your signature is applied to each ticked document.';
$string['signnodocument'] = 'Tick at least one document.';
$string['signpreview'] = 'view the PDF';
$string['signaturesrecordedcount'] = '{$a} document(s) signed.';
$string['signaturesaved'] = 'Saved signature';
$string['signatureusesaved'] = 'Use my saved signature';
$string['signaturesave'] = 'Save this signature for my next signatures';
$string['signaturesaveddelete'] = 'Delete my saved signature';
$string['signaturesaveddeleted'] = 'Saved signature deleted.';
$string['signaturesavednone'] = 'No saved signature: tick "Save this signature" next time you sign so you no longer need to draw it.';
$string['emailagentsignaturesubject'] = 'Documents to sign: thesis of {$a->student}';
$string['emailagentsignaturebody'] = 'Hello {$a->name},

The registrar has checked the thesis file of {$a->student} ({$a->activity}). The following documents await your signature: {$a->document}.

Subject: {$a->title}

Sign from "My signatures": {$a->url}';

// Subject change.
$string['subjectchange'] = 'Change thesis subject';
$string['subjectchange_desc'] = 'Explain why you are changing subject. The former subject is archived in the file; your former '
    . 'director, the registrar and the library are notified. The new subject is approved again by the director (you may choose '
    . 'another one), then registered by the registrar and the library.';
$string['subjectchangereason'] = 'Reason for the change';
$string['subjectchangesubmit'] = 'Submit the new subject';
$string['subjectchangecurrent'] = 'Current subject: {$a}';
$string['subjectchangenotallowed'] = 'The subject can only be changed while the file is active (subject registered, defence not '
    . 'submitted).';
$string['subjectchangedmsg'] = 'New subject submitted: your thesis director, the registrar and the library have been notified.';
$string['subjectchangelog'] = 'Former subject: {$a->old}. Reason: {$a->reason}';
$string['subjecthistory'] = 'Previous subjects';
$string['subjectarchive'] = 'Archived subject form';
$string['logsubjectchanged'] = 'Subject changed';
$string['emailsubjectchangedsubject'] = 'Thesis of {$a->student}: subject changed';
$string['emailsubjectchangedbody'] = 'Hello,

{$a->student} ({$a->activity}) has changed thesis subject.

Former subject: {$a->oldtitle}
New subject: {$a->title}
Reason: {$a->reason}

The former subject is archived in the file. The new subject will be approved by the thesis director, then registered by the registrar and the library.

Open the file: {$a->url}';
$string['privacy:metadata:these_subjecthistory'] = 'Previous subjects, archived at each subject change.';
$string['privacy:metadata:these_subjecthistory:title'] = 'Title of the previous subject.';
$string['privacy:metadata:these_subjecthistory:summary'] = 'Summary of the previous subject.';
$string['privacy:metadata:these_subjecthistory:reason'] = 'Reason for the change.';
$string['privacy:metadata:these_subjecthistory:changedby'] = 'Author of the change.';
$string['privacy:metadata:these_subjecthistory:timecreated'] = 'Time of the change.';
