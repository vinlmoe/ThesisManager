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
 * Courriels personnalisables, notifications et relances.
 *
 * Fichier chargé par locallib.php ; ne pas l'inclure directement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Courriels.

/**
 * Courriels envoyés par l'activité, personnalisables par la DEVE (notifications.php).
 *
 * "vars" liste les variables disponibles sous la forme {{xxx}}, comme dans mod_stage : le texte
 * personnalisé n'est pas une chaîne de langue et ne passe pas par son mécanisme de substitution.
 *
 * @return array emailkey => ['subjectstring' => string, 'bodystring' => string, 'vars' => string[]]
 */
function these_get_email_definitions() {
    return [
        'actionrequired' => [
            'subjectstring' => 'emailactionrequiredsubject',
            'bodystring' => 'emailactionrequiredbody',
            'vars' => ['student', 'activity', 'title', 'status', 'url'],
        ],
        'returned' => [
            'subjectstring' => 'emailreturnedsubject',
            'bodystring' => 'emailreturnedbody',
            'vars' => ['student', 'activity', 'title', 'comment', 'url'],
        ],
        'subjectactive' => [
            'subjectstring' => 'emailsubjectactivesubject',
            'bodystring' => 'emailsubjectactivebody',
            'vars' => ['student', 'activity', 'title', 'url'],
        ],
        'convocation' => [
            'subjectstring' => 'emailconvocationsubject',
            'bodystring' => 'emailconvocationbody',
            'vars' => ['name', 'student', 'title', 'date', 'location'],
        ],
        'university' => [
            'subjectstring' => 'emailuniversitysubject',
            'bodystring' => 'emailuniversitybody',
            'vars' => ['student', 'title', 'devenumber', 'date', 'location'],
        ],
        'closed' => [
            'subjectstring' => 'emailclosedsubject',
            'bodystring' => 'emailclosedbody',
            'vars' => ['student', 'activity', 'title', 'result', 'url'],
        ],
        'agentsignature' => [
            'subjectstring' => 'emailagentsignaturesubject',
            'bodystring' => 'emailagentsignaturebody',
            'vars' => ['name', 'student', 'activity', 'title', 'document', 'url'],
        ],
        'subjectchanged' => [
            'subjectstring' => 'emailsubjectchangedsubject',
            'bodystring' => 'emailsubjectchangedbody',
            'vars' => ['student', 'activity', 'oldtitle', 'title', 'reason', 'url'],
        ],
        'adjourned' => [
            'subjectstring' => 'emailadjournedsubject',
            'bodystring' => 'emailadjournedbody',
            'vars' => ['student', 'activity', 'title', 'url'],
        ],
        'jurydesignation' => [
            'subjectstring' => 'emailjurydesignationsubject',
            'bodystring' => 'emailjurydesignationbody',
            'vars' => ['name', 'role', 'student', 'title', 'date', 'location', 'url'],
        ],
        'signaturerequest' => [
            'subjectstring' => 'emailsignaturerequestsubject',
            'bodystring' => 'emailsignaturerequestbody',
            'vars' => ['name', 'student', 'title', 'document', 'url'],
        ],
        'reminder' => [
            'subjectstring' => 'emailremindersubject',
            'bodystring' => 'emailreminderbody',
            'vars' => ['student', 'activity', 'title', 'status', 'days', 'url'],
        ],
    ];
}

/**
 * Personnalisation éventuelle d'un courriel pour une instance.
 *
 * @param int $theseid
 * @param string $emailkey
 * @return stdClass|null {subject, body}
 */
function these_get_custom_email_template($theseid, $emailkey) {
    global $DB;

    return $DB->get_record('these_email_template', ['theseid' => $theseid, 'emailkey' => $emailkey]) ?: null;
}

/**
 * Enregistre (ou efface, si les deux champs sont vides) la personnalisation d'un courriel.
 *
 * @param int $theseid
 * @param string $emailkey
 * @param string $subject
 * @param string $body
 * @return void
 */
function these_save_email_template($theseid, $emailkey, $subject, $body) {
    global $DB;

    $existing = $DB->get_record('these_email_template', ['theseid' => $theseid, 'emailkey' => $emailkey]);
    if (trim($subject) === '' && trim($body) === '') {
        if ($existing) {
            $DB->delete_records('these_email_template', ['id' => $existing->id]);
        }
        return;
    }

    $record = (object) [
        'theseid' => $theseid,
        'emailkey' => $emailkey,
        'subject' => $subject,
        'body' => $body,
        'timemodified' => time(),
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('these_email_template', $record);
    } else {
        $DB->insert_record('these_email_template', $record);
    }
}

/**
 * Remplace dans un texte personnalisé les jetons {{cle}} par les valeurs fournies.
 *
 * @param string $text
 * @param array $vars cle => valeur
 * @return string
 */
function these_render_email_placeholders($text, array $vars) {
    $search = [];
    $replace = [];
    foreach ($vars as $key => $value) {
        $search[] = '{{' . $key . '}}';
        $replace[] = (string) $value;
    }
    return str_replace($search, $replace, $text);
}

/**
 * Sujet et corps d'un courriel : le texte personnalisé par la DEVE s'il existe, sinon le texte
 * par défaut (chaîne de langue, qui reçoit les mêmes variables sous la forme {$a->xxx}).
 *
 * @param int $theseid
 * @param string $emailkey
 * @param array $vars
 * @return stdClass {subject, body}
 */
function these_resolve_email_text($theseid, $emailkey, array $vars) {
    $definition = these_get_email_definitions()[$emailkey];
    $custom = these_get_custom_email_template($theseid, $emailkey);

    if ($custom && trim((string) $custom->subject) !== '') {
        $subject = these_render_email_placeholders($custom->subject, $vars);
    } else {
        $subject = get_string($definition['subjectstring'], 'mod_these', (object) $vars);
    }
    if ($custom && trim((string) $custom->body) !== '') {
        $body = these_render_email_placeholders($custom->body, $vars);
    } else {
        $body = get_string($definition['bodystring'], 'mod_these', (object) $vars);
    }
    return (object) ['subject' => $subject, 'body' => $body];
}

/**
 * Destinataire factice pour une adresse sans compte Moodle (boîte de service, membre du jury),
 * sur le modèle du maître de stage de mod_stage.
 *
 * @param string $email
 * @param string $name
 * @return stdClass
 */
function these_make_external_user($email, $name = '') {
    $names = explode(' ', trim($name), 2);
    return (object) [
        'id' => -1,
        'email' => $email,
        'firstname' => $names[0] !== '' ? $names[0] : $email,
        'lastname' => $names[1] ?? '',
        'firstnamephonetic' => '',
        'lastnamephonetic' => '',
        'middlename' => '',
        'alternatename' => '',
        'maildisplay' => true,
        'mailformat' => 1,
        'auth' => 'manual',
        'confirmed' => 1,
        'suspended' => 0,
        'deleted' => 0,
        'emailstop' => 0,
    ];
}

/**
 * Destinataires des notifications adressées à un service (DEVE ou bibliothèque) : sa boîte de
 * service si elle est renseignée dans les paramètres de l'activité, sinon chaque utilisateur
 * inscrit qui détient la capacité correspondante.
 *
 * @param stdClass $these
 * @param context $context
 * @param string $actor 'deve' ou 'library'.
 * @return array Utilisateurs (réels ou factices).
 */
function these_get_service_recipients(stdClass $these, context $context, $actor) {
    global $DB;

    $email = $actor === 'deve' ? trim((string) $these->deveemail) : trim((string) $these->libraryemail);
    if ($email !== '') {
        return [these_make_external_user($email, these_actor_label($actor))];
    }
    $capability = $actor === 'deve' ? 'mod/these:managedeve' : 'mod/these:managelibrary';
    $users = get_enrolled_users($context, $capability, 0, 'u.*', null, 0, 0, true);
    // Détenteurs du rôle du service, attribué dans le cours, sa catégorie ou au niveau du site.
    $roleid = these_get_service_roleid($actor);
    if ($roleid) {
        $userids = [];
        foreach ($context->get_parent_context_ids(true) as $contextid) {
            $userids = array_merge($userids, $DB->get_fieldset_select(
                'role_assignments',
                'userid',
                'roleid = ? AND contextid = ?',
                [$roleid, $contextid]
            ));
        }
        $userids = array_diff(array_unique($userids), array_keys($users));
        if ($userids) {
            $users += $DB->get_records_list('user', 'id', $userids);
        }
    }
    return array_values(array_filter($users, function ($user) {
        return empty($user->deleted) && empty($user->suspended);
    }));
}

/**
 * Destinataires attendus à l'étape courante d'un dossier.
 *
 * @param stdClass $these
 * @param context $context
 * @param stdClass $dossier
 * @return array
 */
function these_get_actor_recipients(stdClass $these, context $context, stdClass $dossier) {
    global $DB;

    $actor = these_step_actor($dossier->status);
    if ($actor === 'student') {
        $user = $DB->get_record('user', ['id' => $dossier->userid, 'deleted' => 0]);
        return $user ? [$user] : [];
    }
    if ($actor === 'director') {
        $user = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid, 'deleted' => 0]) : null;
        return $user ? [$user] : [];
    }
    if ($actor === 'deve' || $actor === 'library') {
        return these_get_service_recipients($these, $context, $actor);
    }
    return [];
}

/**
 * Variables communes à tous les courriels portant sur un dossier.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return array
 */
function these_email_base_vars(stdClass $these, stdClass $cm, stdClass $dossier) {
    global $DB;

    $student = $DB->get_record('user', ['id' => $dossier->userid]);
    $url = new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]);
    return [
        'student' => $student ? fullname($student) : '',
        'activity' => format_string($these->name),
        'title' => format_string($dossier->title),
        'url' => $url->out(false),
    ];
}

/**
 * Envoie un courriel à une liste de destinataires.
 *
 * @param stdClass $these
 * @param string $emailkey
 * @param array $recipients
 * @param array $vars
 * @param string $attachment Chemin d'une pièce jointe (dans le répertoire temporaire de Moodle).
 * @param string $attachname Nom de la pièce jointe.
 * @return int Nombre de courriels partis.
 */
function these_send_email(stdClass $these, $emailkey, array $recipients, array $vars, $attachment = '', $attachname = '') {
    $text = these_resolve_email_text($these->id, $emailkey, $vars);
    $noreply = core_user::get_noreply_user();
    $sent = 0;
    foreach ($recipients as $recipient) {
        if (email_to_user($recipient, $noreply, $text->subject, $text->body, '', $attachment, $attachname)) {
            $sent++;
        }
    }
    return $sent;
}

/**
 * Prévient l'acteur attendu à l'étape courante qu'un dossier l'attend.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int Nombre de courriels partis.
 */
function these_notify_actor(stdClass $these, stdClass $cm, stdClass $dossier) {
    $context = context_module::instance($cm->id);
    $recipients = these_get_actor_recipients($these, $context, $dossier);
    if (empty($recipients)) {
        return 0;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['status'] = these_status_label($dossier->status);
    return these_send_email($these, 'actionrequired', $recipients, $vars);
}

/**
 * Prévient le destinataire d'un renvoi (étudiant ou directeur), motif à l'appui.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @param string $target 'student' ou 'director'.
 * @param string $comment
 * @return int
 */
function these_notify_returned(stdClass $these, stdClass $cm, stdClass $dossier, $target, $comment) {
    global $DB;

    $userid = $target === 'student' ? $dossier->userid : $dossier->directorid;
    $user = $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]) : null;
    if (!$user) {
        return 0;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['comment'] = $comment;
    return these_send_email($these, 'returned', [$user], $vars);
}

/**
 * Prévient l'étudiant et son directeur que le sujet est enregistré et le dossier ouvert.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int
 */
function these_notify_subject_active(stdClass $these, stdClass $cm, stdClass $dossier) {
    $vars = these_email_base_vars($these, $cm, $dossier);
    return these_send_email($these, 'subjectactive', these_get_student_and_director($dossier), $vars);
}

/**
 * Prévient l'étudiant et son directeur de la clôture du dossier.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return int
 */
function these_notify_closed(stdClass $these, stdClass $cm, stdClass $dossier) {
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['result'] = these_decision_label($dossier);
    return these_send_email($these, 'closed', these_get_student_and_director($dossier), $vars);
}

/**
 * L'étudiant et son directeur, s'ils existent toujours.
 *
 * @param stdClass $dossier
 * @return array
 */
function these_get_student_and_director(stdClass $dossier) {
    global $DB;

    $users = [];
    foreach ([$dossier->userid, $dossier->directorid] as $userid) {
        if ($userid && ($user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]))) {
            $users[] = $user;
        }
    }
    return $users;
}

// Relances.

/**
 * Dossiers dont l'étape courante attend depuis plus longtemps que le délai de relance de leur
 * activité, et qui n'ont pas été relancés depuis ce même délai.
 *
 * Les brouillons et les dossiers actifs ne sont jamais relancés : c'est à l'étudiant de décider
 * quand il dépose son sujet, puis sa soutenance (la phase « dormante » peut durer des années).
 *
 * @param int|null $now
 * @return array Dossiers indexés par id, avec le délai de leur activité (reminderdays).
 */
function these_get_dossiers_needing_reminder($now = null) {
    global $DB;

    $now = $now ?? time();
    $excluded = [THESE_STATUS_DRAFT, THESE_STATUS_ACTIVE, THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED];
    [$notinsql, $params] = $DB->get_in_or_equal($excluded, SQL_PARAMS_NAMED, 'st', false);
    $params['now1'] = $now;
    $params['now2'] = $now;

    $sql = "SELECT d.*, t.reminderdays
              FROM {these_dossier} d
              JOIN {these} t ON t.id = d.theseid
             WHERE t.reminderdays > 0
               AND d.status $notinsql
               AND d.statustime <= :now1 - t.reminderdays * 86400
               AND d.remindertime <= :now2 - t.reminderdays * 86400";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Relance l'acteur attendu pour un dossier, et note la relance si au moins un courriel est parti.
 *
 * @param stdClass $these
 * @param stdClass $cm
 * @param stdClass $dossier
 * @return bool
 */
function these_send_reminder(stdClass $these, stdClass $cm, stdClass $dossier) {
    global $DB;

    $context = context_module::instance($cm->id);
    $recipients = these_get_actor_recipients($these, $context, $dossier);
    if (empty($recipients)) {
        return false;
    }
    $vars = these_email_base_vars($these, $cm, $dossier);
    $vars['status'] = these_status_label($dossier->status);
    $vars['days'] = (int) floor((time() - $dossier->statustime) / DAYSECS);
    if (!these_send_email($these, 'reminder', $recipients, $vars)) {
        return false;
    }
    $DB->set_field('these_dossier', 'remindertime', time(), ['id' => $dossier->id]);
    $actor = these_actor_label(these_step_actor($dossier->status));
    these_log($dossier->id, 0, 'reminded', $dossier->status, $dossier->status, $actor);
    return true;
}
