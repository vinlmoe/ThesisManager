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

namespace mod_these\local;

use context;
use context_module;
use core_text;
use core_user;
use moodle_url;
use stdClass;

/**
 * Courriels personnalisables, notifications et relances.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {
    /**
     * Courriels envoyés par l'activité, personnalisables par la DEVE (notifications.php).
     *
     * "vars" liste les variables disponibles sous la forme {{xxx}}, comme dans mod_stage : le texte
     * personnalisé n'est pas une chaîne de langue et ne passe pas par son mécanisme de substitution.
     *
     * @return array emailkey => ['subjectstring' => string, 'bodystring' => string, 'vars' => string[]]
     */
    public static function get_email_definitions() {
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
            'agentrefused' => [
                'subjectstring' => 'emailagentrefusedsubject',
                'bodystring' => 'emailagentrefusedbody',
                'vars' => ['name', 'student', 'activity', 'title', 'document', 'comment', 'target', 'url'],
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
            'digest' => [
                'subjectstring' => 'emaildigestsubject',
                'bodystring' => 'emaildigestbody',
                'vars' => ['activity', 'todo', 'signatures', 'defenses', 'conflicts', 'url'],
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
    public static function get_custom_email_template($theseid, $emailkey) {
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
    public static function save_email_template($theseid, $emailkey, $subject, $body) {
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
    public static function render_email_placeholders($text, array $vars) {
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
    public static function resolve_email_text($theseid, $emailkey, array $vars) {
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
    public static function make_external_user($email, $name = '') {
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
    public static function get_service_recipients(stdClass $these, context $context, $actor) {
        $email = $actor === 'deve' ? trim((string) $these->deveemail) : trim((string) $these->libraryemail);
        if ($email !== '') {
            return [these_make_external_user($email, these_actor_label($actor))];
        }
        $capability = $actor === 'deve' ? 'mod/these:managedeve' : 'mod/these:managelibrary';
        $users = get_enrolled_users($context, $capability, 0, 'u.*', null, 0, 0, true);
        // Agents de la bibliothèque : destinataires de la bibliothèque, jamais de la DEVE.
        $agents = scopesettings::library_agents($context);
        $users = $actor === 'library' ? $users + $agents : array_diff_key($users, $agents);
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
    public static function get_actor_recipients(stdClass $these, context $context, stdClass $dossier) {
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
    public static function email_base_vars(stdClass $these, stdClass $cm, stdClass $dossier) {
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
     * Pour une thèse confidentielle, les destinataires qui ne doivent pas connaître le sujet (voir
     * these_user_knows_subject()) reçoivent « Thèse confidentielle » à la place du titre.
     *
     * @param stdClass $these
     * @param string $emailkey
     * @param array $recipients
     * @param array $vars
     * @param string $attachment Chemin d'une pièce jointe (dans le répertoire temporaire de Moodle).
     * @param string $attachname Nom de la pièce jointe.
     * @param stdClass|null $dossier Dossier dont le courriel cite le titre.
     * @return int Nombre de courriels partis.
     */
    public static function send_email(
        stdClass $these,
        $emailkey,
        array $recipients,
        array $vars,
        $attachment = '',
        $attachname = '',
        ?stdClass $dossier = null
    ) {
        $noreply = core_user::get_noreply_user();
        $text = these_resolve_email_text($these->id, $emailkey, $vars);
        $masked = null;
        $sent = 0;
        foreach ($recipients as $recipient) {
            $message = $text;
            if ($dossier !== null && !these_recipient_knows_subject($these, $dossier, $recipient)) {
                if ($masked === null) {
                    $hidden = get_string('confidentialtitle', 'mod_these');
                    $masked = these_resolve_email_text($these->id, $emailkey, array_merge($vars, array_intersect_key(
                        ['title' => $hidden, 'oldtitle' => $hidden],
                        $vars
                    )));
                }
                $message = $masked;
            }
            if (email_to_user($recipient, $noreply, $message->subject, $message->body, '', $attachment, $attachname)) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Indique si le destinataire d'un courriel peut connaître le sujet d'une thèse : un compte qui le
     * connaît (voir these_user_knows_subject()), ou la boîte de service de la DEVE ou de la bibliothèque.
     *
     * @param stdClass $these
     * @param stdClass $dossier
     * @param stdClass $recipient Compte Moodle, ou destinataire sans compte (voir these_make_external_user()).
     * @return bool
     */
    public static function recipient_knows_subject(stdClass $these, stdClass $dossier, stdClass $recipient) {
        if (empty($dossier->confidential)) {
            return true;
        }
        if ((int) $recipient->id > 0) {
            $cm = get_coursemodule_from_instance('these', $these->id, 0, false, MUST_EXIST);
            return these_user_knows_subject($dossier, context_module::instance($cm->id), $recipient->id);
        }
        $email = core_text::strtolower(trim((string) $recipient->email));
        foreach (['deveemail', 'libraryemail'] as $field) {
            $service = core_text::strtolower(trim((string) ($these->$field ?? '')));
            if ($service !== '' && $email === $service) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prévient l'acteur attendu à l'étape courante qu'un dossier l'attend.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @return int Nombre de courriels partis.
     */
    public static function notify_actor(stdClass $these, stdClass $cm, stdClass $dossier) {
        $context = context_module::instance($cm->id);
        $recipients = these_get_actor_recipients($these, $context, $dossier);
        if (empty($recipients)) {
            return 0;
        }
        $vars = these_email_base_vars($these, $cm, $dossier);
        $vars['status'] = these_status_label($dossier->status);
        return these_send_email($these, 'actionrequired', $recipients, $vars, '', '', $dossier);
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
    public static function notify_returned(stdClass $these, stdClass $cm, stdClass $dossier, $target, $comment) {
        global $DB;

        $userid = $target === 'student' ? $dossier->userid : $dossier->directorid;
        $user = $userid ? $DB->get_record('user', ['id' => $userid, 'deleted' => 0]) : null;
        if (!$user) {
            return 0;
        }
        $vars = these_email_base_vars($these, $cm, $dossier);
        $vars['comment'] = $comment;
        return these_send_email($these, 'returned', [$user], $vars, '', '', $dossier);
    }

    /**
     * Prévient l'étudiant et son directeur que le sujet est enregistré et le dossier ouvert.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @return int
     */
    public static function notify_subject_active(stdClass $these, stdClass $cm, stdClass $dossier) {
        $vars = these_email_base_vars($these, $cm, $dossier);
        return these_send_email($these, 'subjectactive', these_get_student_and_director($dossier), $vars, '', '', $dossier);
    }

    /**
     * Prévient l'étudiant et son directeur de la clôture du dossier.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @return int
     */
    public static function notify_closed(stdClass $these, stdClass $cm, stdClass $dossier) {
        $vars = these_email_base_vars($these, $cm, $dossier);
        $vars['result'] = these_decision_label($dossier);
        return these_send_email($these, 'closed', these_get_student_and_director($dossier), $vars, '', '', $dossier);
    }

    /**
     * L'étudiant et son directeur, s'ils existent toujours.
     *
     * @param stdClass $dossier
     * @return array
     */
    public static function get_student_and_director(stdClass $dossier) {
        global $DB;

        $users = [];
        foreach ([$dossier->userid, $dossier->directorid] as $userid) {
            if ($userid && ($user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]))) {
                $users[] = $user;
            }
        }
        return $users;
    }

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
    public static function get_dossiers_needing_reminder($now = null) {
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
    public static function send_reminder(stdClass $these, stdClass $cm, stdClass $dossier) {
        global $DB;

        $context = context_module::instance($cm->id);
        $recipients = these_get_actor_recipients($these, $context, $dossier);
        if (empty($recipients)) {
            return false;
        }
        $vars = these_email_base_vars($these, $cm, $dossier);
        $vars['status'] = these_status_label($dossier->status);
        $vars['days'] = (int) floor((time() - $dossier->statustime) / DAYSECS);
        if (!these_send_email($these, 'reminder', $recipients, $vars, '', '', $dossier)) {
            return false;
        }
        $DB->set_field('these_dossier', 'remindertime', time(), ['id' => $dossier->id]);
        $actor = these_actor_label(these_step_actor($dossier->status));
        these_log($dossier->id, 0, 'reminded', $dossier->status, $dossier->status, $actor);
        return true;
    }

    /**
     * Relance les signataires dont une signature attend depuis plus longtemps que le délai de relance
     * de l'activité : signataires sans compte (nouveau lien, s'ils ont une adresse) et agents DEVE
     * désignés, pour leurs signatures et celles de la direction générale qu'ils signent par
     * délégation. La relance remet le délai à zéro ; elle est répétée tant que la signature manque.
     *
     * @param int|null $now
     * @return int Signataires relancés.
     */
    public static function send_signature_reminders($now = null) {
        global $DB;

        $now = $now ?? time();
        [$statussql, $params] = $DB->get_in_or_equal(
            [THESE_STATUS_CLOSED, THESE_STATUS_CANCELLED],
            SQL_PARAMS_NAMED,
            'st',
            false
        );
        $records = $DB->get_records_sql(
            "SELECT s.id, s.dossierid, s.slot, s.requesttime, t.reminderdays
               FROM {these_signature} s
               JOIN {these_dossier} d ON d.id = s.dossierid
               JOIN {these} t ON t.id = d.theseid
              WHERE s.timesigned = 0 AND s.requesttime > 0 AND t.reminderdays > 0 AND d.status $statussql",
            $params
        );
        $due = [];
        foreach ($records as $record) {
            if ((int) $record->requesttime <= $now - (int) $record->reminderdays * DAYSECS) {
                $due[(int) $record->dossierid][$record->slot] = true;
            }
        }
        $sent = 0;
        foreach ($due as $dossierid => $slots) {
            $dossier = $DB->get_record('these_dossier', ['id' => $dossierid]);
            $these = $dossier ? $DB->get_record('these', ['id' => $dossier->theseid]) : null;
            $cm = $these ? get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING) : null;
            if (!$cm) {
                continue;
            }
            $available = array_values(array_filter(array_keys(these_document_definitions()), function ($doctype) use ($dossier) {
                return these_document_available($dossier, $doctype);
            }));
            $signers = these_external_signers($dossier);
            foreach (array_keys($slots) as $slot) {
                $email = (string) ($signers[$slot]['email'] ?? '');
                if (
                    isset($signers[$slot]) && $email !== '' && validate_email($email)
                        && these_request_signature($these, $cm, $dossier, $slot, $available) !== null
                ) {
                    $sent++;
                }
            }
            if (
                array_intersect(array_keys($slots), signatureagent::slots_for($these))
                    && these_request_agent_signatures($these, $cm, $dossier, $available) > 0
            ) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Contenu du récapitulatif hebdomadaire de la DEVE pour une activité : dossiers qui l'attendent,
     * signatures en attente depuis plus d'une semaine, soutenances des sept prochains jours et
     * conflits de jury ou de salle des quinze prochains jours.
     *
     * @param stdClass $these
     * @param int|null $now
     * @return array todo, signatures, defenses, conflicts (lignes de texte)
     */
    public static function digest_content(stdClass $these, $now = null) {
        global $DB;

        $now = $now ?? time();
        $name = function ($userid) use ($DB) {
            $user = $DB->get_record('user', ['id' => $userid]);
            return $user ? fullname($user) : '-';
        };
        $content = ['todo' => [], 'signatures' => [], 'defenses' => [], 'conflicts' => []];
        foreach (these_get_dossiers($these->id, ['queue' => ['deve']]) as $dossier) {
            $content['todo'][] = $name($dossier->userid) . ' : ' . these_status_label($dossier->status);
        }
        $signatures = $DB->get_records_sql(
            "SELECT s.id, s.dossierid, s.doctype, s.slot, d.userid
               FROM {these_signature} s
               JOIN {these_dossier} d ON d.id = s.dossierid
              WHERE d.theseid = :theseid AND s.timesigned = 0 AND s.requesttime > 0 AND s.requesttime <= :before
                AND d.status <> :cancelled AND d.status <> :closed
           ORDER BY s.dossierid, s.id",
            ['theseid' => $these->id, 'before' => $now - WEEKSECS, 'cancelled' => THESE_STATUS_CANCELLED,
                'closed' => THESE_STATUS_CLOSED]
        );
        foreach ($signatures as $signature) {
            $content['signatures'][] = $name($signature->userid) . ' : ' . these_document_label($signature->doctype)
                . ' (' . these_slot_label($signature->slot) . ')';
        }
        $dateformat = get_string('strftimedaydatetime', 'langconfig');
        $upcoming = $DB->get_records_select(
            'these_dossier',
            'theseid = ? AND status >= ? AND defensedate BETWEEN ? AND ?',
            [$these->id, THESE_STATUS_DEFENSE_VALIDATED, $now, $now + 2 * WEEKSECS],
            'defensedate ASC'
        );
        foreach ($upcoming as $dossier) {
            if ((int) $dossier->defensedate < $now + WEEKSECS) {
                $content['defenses'][] = userdate($dossier->defensedate, $dateformat) . ' : ' . $name($dossier->userid)
                    . (trim((string) $dossier->defenselocation) !== '' ? ' (' . $dossier->defenselocation . ')' : '');
            }
            $jury = count(these_jury_conflicts($dossier));
            $rooms = count(these_room_conflicts($dossier));
            if ($jury || $rooms) {
                $content['conflicts'][] = $name($dossier->userid) . ', ' . userdate($dossier->defensedate, $dateformat) . ' : '
                    . get_string('digestconflicts', 'mod_these', (object) ['jury' => $jury, 'rooms' => $rooms]);
            }
        }
        return $content;
    }

    /**
     * Envoie à la DEVE de chaque activité son récapitulatif hebdomadaire (voir digest_content()), si
     * le paramètre « weeklydigest » de la portée l'active et qu'il y a quelque chose à signaler.
     *
     * @param int|null $now
     * @return int Activités pour lesquelles un récapitulatif est parti.
     */
    public static function send_weekly_digests($now = null) {
        global $DB;

        $sent = 0;
        foreach ($DB->get_records('these') as $these) {
            $cm = get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING);
            if (!$cm || empty(scopesettings::for_these($these)['weeklydigest'])) {
                continue;
            }
            $content = self::digest_content($these, $now);
            if (!array_filter($content)) {
                continue;
            }
            $lines = function (array $items) {
                return $items ? '- ' . implode("\n- ", $items) : get_string('digestnone', 'mod_these');
            };
            $vars = [
                'activity' => format_string($these->name),
                'todo' => $lines($content['todo']),
                'signatures' => $lines($content['signatures']),
                'defenses' => $lines($content['defenses']),
                'conflicts' => $lines($content['conflicts']),
                'url' => (new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]))->out(false),
            ];
            $context = context_module::instance($cm->id);
            if (these_send_email($these, 'digest', these_get_service_recipients($these, $context, 'deve'), $vars)) {
                $sent++;
            }
        }
        return $sent;
    }
}
