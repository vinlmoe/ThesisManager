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
 * Étape de sauvegarde de la structure d'une instance de mod_these.
 *
 * @package   mod_these
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Décrit l'arbre XML (these.xml) d'une instance : le paramétrage (établissement, adresses de
 * service, modèles de courriels) est toujours sauvegardé ; les dossiers, leur jury, leur
 * historique et leurs fichiers ne le sont que si les données utilisateur sont demandées.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_these_activity_structure_step extends backup_activity_structure_step {
    /**
     * Construit la structure sauvegardée.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $these = new backup_nested_element('these', ['id'], [
            'name', 'intro', 'introformat',
            'establishmentname', 'establishmentaddress', 'establishmentcity', 'signatoryname', 'signatorytitle',
            'defaultlocation', 'minjury', 'deveemail', 'libraryemail', 'universityemail', 'reminderdays',
            'timecreated', 'timemodified',
        ]);

        $emailtemplates = new backup_nested_element('emailtemplates');
        $emailtemplate = new backup_nested_element('emailtemplate', ['id'], ['emailkey', 'subject', 'body', 'timemodified']);

        $doctemplates = new backup_nested_element('doctemplates');
        $doctemplate = new backup_nested_element('doctemplate', ['id'], ['doctype', 'content', 'timemodified']);

        $griditems = new backup_nested_element('griditems');
        $griditem = new backup_nested_element('griditem', ['id'], [
            'itemkey', 'section', 'label', 'conditional', 'sortorder', 'timemodified',
        ]);

        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], [
            'qtype', 'label', 'options', 'required', 'sortorder', 'timemodified',
        ]);

        $dossiers = new backup_nested_element('dossiers');
        $dossier = new backup_nested_element('dossier', ['id'], [
            'userid', 'status', 'statustime', 'title', 'titleen', 'summary', 'keywords',
            'directorid', 'codirectorname', 'devenumber', 'libraryref',
            'proposeddate', 'defensedate', 'defenselocation', 'defenseresult', 'mention',
            'prizeproposal', 'correctionsrequested', 'correctionsnote', 'correctionsdone',
            'studyyear', 'confidential', 'directortitle', 'externaldirector', 'extdirectorname', 'extdirectorgrade',
            'extdirectorinstitution', 'extdirectoremail', 'commitments', 'ethicsnumber', 'bordereau', 'conclusions',
            'reporttext', 'reportfavorable', 'conformitygrid', 'agreement',
            'returnstatus', 'returncomment', 'returnedby', 'returntime', 'cancelledfromstatus',
            'documentstime', 'senttime', 'remindertime', 'timecreated', 'timemodified',
        ]);

        $jurymembers = new backup_nested_element('jurymembers');
        $jurymember = new backup_nested_element('jurymember', ['id'], [
            'juryrole', 'fullname', 'jurytitle', 'institution', 'email', 'sortorder', 'timecreated',
            'jurorid', 'jurystatus', 'eligible', 'eligibleby', 'eligibletime', 'notifiedtime',
        ]);

        $subjecthistory = new backup_nested_element('subjecthistory');
        $subject = new backup_nested_element('previoussubject', ['id'], [
            'title', 'titleen', 'summary', 'keywords', 'studyyear', 'confidential', 'directorid', 'directorname',
            'codirectorname', 'libraryref', 'reason', 'changedby', 'timecreated',
        ]);

        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], ['questionid', 'value', 'timemodified']);

        // Le jeton d'une invitation en attente n'est pas sauvegardé : c'est un secret d'accès sans
        // compte, que la DEVE renverra depuis la copie restaurée si besoin.
        $signatures = new backup_nested_element('signatures');
        $signature = new backup_nested_element('signature', ['id'], [
            'doctype', 'slot', 'userid', 'signername', 'signeremail', 'requesttime', 'timesigned', 'contenthash',
            'ipaddress', 'timecreated',
        ]);

        $logs = new backup_nested_element('logs');
        $log = new backup_nested_element('log', ['id'], [
            'userid', 'logaction', 'fromstatus', 'tostatus', 'remark', 'timecreated',
        ]);

        $these->add_child($emailtemplates);
        $emailtemplates->add_child($emailtemplate);
        $these->add_child($doctemplates);
        $doctemplates->add_child($doctemplate);
        $these->add_child($griditems);
        $griditems->add_child($griditem);
        $these->add_child($questions);
        $questions->add_child($question);
        $these->add_child($dossiers);
        $dossiers->add_child($dossier);
        $dossier->add_child($jurymembers);
        $jurymembers->add_child($jurymember);
        $dossier->add_child($subjecthistory);
        $subjecthistory->add_child($subject);
        $dossier->add_child($answers);
        $answers->add_child($answer);
        $dossier->add_child($signatures);
        $signatures->add_child($signature);
        $dossier->add_child($logs);
        $logs->add_child($log);

        $these->set_source_table('these', ['id' => backup::VAR_ACTIVITYID]);
        $emailtemplate->set_source_table('these_email_template', ['theseid' => backup::VAR_PARENTID], 'id ASC');
        $doctemplate->set_source_table('these_doctemplate', ['theseid' => backup::VAR_PARENTID], 'id ASC');
        $griditem->set_source_table('these_griditem', ['theseid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
        $question->set_source_table('these_question', ['theseid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');

        if ($userinfo) {
            $dossier->set_source_table('these_dossier', ['theseid' => backup::VAR_PARENTID], 'id ASC');
            $jurymember->set_source_table('these_jury', ['dossierid' => backup::VAR_PARENTID], 'sortorder ASC, id ASC');
            $answer->set_source_table('these_answer', ['dossierid' => backup::VAR_PARENTID], 'id ASC');
            $subject->set_source_table('these_subjecthistory', ['dossierid' => backup::VAR_PARENTID], 'id ASC');
            $log->set_source_table('these_log', ['dossierid' => backup::VAR_PARENTID], 'id ASC');
            $signature->set_source_table('these_signature', ['dossierid' => backup::VAR_PARENTID], 'id ASC');
        }

        $dossier->annotate_ids('user', 'userid');
        $dossier->annotate_ids('user', 'directorid');
        $dossier->annotate_ids('user', 'returnedby');
        $log->annotate_ids('user', 'userid');
        $subject->annotate_ids('user', 'directorid');
        $subject->annotate_ids('user', 'changedby');
        $jurymember->annotate_ids('user', 'eligibleby');
        $signature->annotate_ids('user', 'userid');
        $signature->annotate_files('mod_these', 'signature', 'id');

        $these->annotate_files('mod_these', 'intro', null);
        $these->annotate_files('mod_these', 'docimages', null);
        foreach (THESE_FILEAREAS as $filearea) {
            $dossier->annotate_files('mod_these', $filearea, 'id');
        }
        // Documents scellés (voir these_seal_document()).
        $dossier->annotate_files('mod_these', 'sealed', 'id');

        return $this->prepare_activity_structure($these);
    }
}
