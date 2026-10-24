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
 * Changement de sujet de thèse.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Changement de sujet.

/**
 * Indique si l'étudiant peut changer de sujet : dossier actif (sujet enregistré, soutenance pas
 * encore déposée, y compris après un ajournement).
 *
 * @param stdClass $dossier
 * @return bool
 */
function these_subject_changeable(stdClass $dossier) {
    return (int) $dossier->status === THESE_STATUS_ACTIVE;
}

/**
 * Sujets précédents d'un dossier, du plus récent au plus ancien.
 *
 * @param int $dossierid
 * @return stdClass[]
 */
function these_get_subject_history($dossierid) {
    global $DB;
    return $DB->get_records('these_subjecthistory', ['dossierid' => $dossierid], 'timecreated DESC, id DESC');
}

/**
 * Change le sujet d'un dossier actif. L'ancien sujet est archivé (historique et PDF du dépôt de
 * sujet signé, zone subjectarchive) ; l'ancien directeur, la DEVE et la bibliothèque sont
 * prévenus ; le nouveau sujet, signé par l'étudiant, repasse toute la phase 1 : directeur
 * (éventuellement nouveau), DEVE, bibliothèque.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param stdClass $data Nouveau sujet (champs du formulaire de sujet).
 * @param int $userid
 * @param string $reason Motif du changement.
 * @param stdClass|null $signature Signature de l'étudiant (voir these_signature_from_form()).
 * @return stdClass Le dossier à jour.
 */
function these_change_subject(
    stdClass $these,
    stdClass $cm,
    stdClass $dossier,
    stdClass $data,
    $userid,
    $reason,
    ?stdClass $signature = null
) {
    global $DB;

    if (!these_subject_changeable($dossier)) {
        throw new coding_exception('Changement de sujet impossible au statut ' . $dossier->status);
    }
    $context = context_module::instance($cm->id);
    $now = time();
    $olddirector = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid, 'deleted' => 0]) : null;

    // Trace de l'ancien sujet.
    $history = (object) [
        'dossierid' => $dossier->id,
        'title' => (string) $dossier->title,
        'titleen' => (string) $dossier->titleen,
        'summary' => (string) $dossier->summary,
        'keywords' => (string) $dossier->keywords,
        'studyyear' => (string) ($dossier->studyyear ?? ''),
        'confidential' => (int) ($dossier->confidential ?? 0),
        'directorid' => (int) $dossier->directorid,
        'directorname' => !empty($dossier->externaldirector) ? (string) $dossier->extdirectorname
            : ($olddirector ? fullname($olddirector) : ''),
        'codirectorname' => (string) $dossier->codirectorname,
        'libraryref' => (string) $dossier->libraryref,
        'reason' => trim((string) $reason),
        'changedby' => (int) $userid,
        'timecreated' => $now,
    ];
    $history->id = $DB->insert_record('these_subjecthistory', $history);
    try {
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => 'subjectarchive',
            'itemid' => $dossier->id,
            'filepath' => '/',
            'filename' => 'sujet-' . userdate($now, '%Y-%m-%d') . '-' . $history->id . '.pdf',
        ], these_signed_document_pdf($these, $context, $dossier, 'subject'));
    } catch (\Throwable $e) {
        debugging('Archivage du dépôt de sujet impossible : ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // Le dépôt de sujet sera signé de nouveau par tous.
    foreach (these_get_signatures($dossier->id) as $existing) {
        if ($existing->doctype === 'subject') {
            these_delete_signature($context, $existing);
        }
    }

    $oldtitle = (string) $dossier->title;
    $fields = [];
    foreach (
        ['title', 'titleen', 'summary', 'keywords', 'codirectorname', 'studyyear', 'directortitle',
            'extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field
    ) {
        $fields[$field] = (string) these_submitted_value($data, $dossier, $field);
    }
    foreach (['directorid', 'confidential', 'externaldirector'] as $field) {
        $fields[$field] = (int) these_submitted_value($data, $dossier, $field);
    }
    if (property_exists($data, 'commitguide')) {
        $fields['commitments'] = (!empty($data->commitguide) ? THESE_COMMIT_GUIDE : 0)
            | (!empty($data->commitduplicate) ? THESE_COMMIT_DUPLICATE : 0)
            | (!empty($data->commitethics) ? THESE_COMMIT_ETHICS : 0);
    }
    if (empty($fields['externaldirector'])) {
        foreach (['extdirectorname', 'extdirectorgrade', 'extdirectorinstitution', 'extdirectoremail'] as $field) {
            $fields[$field] = '';
        }
    }
    $dossier = these_set_status(
        $dossier,
        THESE_STATUS_SUBJECT_SUBMITTED,
        $userid,
        'subjectchanged',
        get_string('subjectchangelog', 'mod_these', (object) ['old' => $oldtitle, 'reason' => trim((string) $reason)]),
        array_merge($fields, these_cleared_return_fields())
    );
    if ($signature !== null) {
        these_sign_document($context, $dossier, 'subject', 'student', $userid, $signature->name, $signature->png);
    }
    if (!empty($dossier->externaldirector)) {
        these_request_external_signatures($these, $cm, $dossier, 'subject');
    }

    // Avertissement de l'ancien directeur, de la DEVE et de la bibliothèque.
    $recipients = [];
    if ($olddirector) {
        $recipients[core_text::strtolower($olddirector->email)] = $olddirector;
    }
    foreach (['deve', 'library'] as $service) {
        foreach (these_get_service_recipients($these, $context, $service) as $recipient) {
            $recipients[core_text::strtolower((string) $recipient->email)] = $recipient;
        }
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['oldtitle'] = format_string($oldtitle);
    $vars['reason'] = trim((string) $reason);
    these_send_email($these, 'subjectchanged', array_values($recipients), $vars);
    // Le (nouveau) directeur est attendu pour valider le nouveau sujet.
    these_notify_actor($these, $cm, $dossier);
    return $dossier;
}

/**
 * Sujets précédents d'un dossier, pour l'affichage : date, titre, directeur, motif et PDF archivé.
 *
 * @param context $context
 * @param stdClass $dossier
 * @return string HTML ('' sans changement de sujet)
 */
function these_render_subject_history(context $context, stdClass $dossier) {
    $history = these_get_subject_history($dossier->id);
    if (!$history) {
        return '';
    }
    $files = [];
    foreach (these_get_files($context, 'subjectarchive', $dossier->id) as $file) {
        if (preg_match('/-(\d+)\.pdf$/', $file->get_filename(), $match)) {
            $files[(int) $match[1]] = $file;
        }
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable these-subjecthistory';
    $table->head = [get_string('date'), get_string('subjecttitle', 'mod_these'), get_string('director', 'mod_these'),
        get_string('subjectchangereason', 'mod_these'), get_string('subjectarchive', 'mod_these')];
    foreach ($history as $entry) {
        $file = $files[(int) $entry->id] ?? null;
        $table->data[] = [
            userdate($entry->timecreated, get_string('strftimedate', 'langconfig')),
            format_string($entry->title),
            s((string) $entry->directorname),
            format_text((string) $entry->reason, FORMAT_PLAIN),
            $file ? html_writer::link(moodle_url::make_pluginfile_url(
                $context->id,
                'mod_these',
                'subjectarchive',
                $dossier->id,
                '/',
                $file->get_filename(),
                true
            ), $file->get_filename()) : '-',
        ];
    }
    return html_writer::tag('h4', get_string('subjecthistory', 'mod_these'), ['class' => 'mt-4']) . html_writer::table($table);
}
