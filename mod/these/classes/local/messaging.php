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

use core\message\message;
use core_user;
use stdClass;

/**
 * Remise des courriels de l'activité : par l'API de messagerie de Moodle (fournisseurs de
 * db/messages.php, préférences de l'utilisateur) pour les destinataires qui ont un compte, par
 * courriel direct pour les destinataires sans compte (voir notifications::make_external_user()) et
 * pour les envois avec pièce jointe, que la messagerie n'accepte qu'en fichier stocké.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messaging {
    /** @var string Fournisseur d'un courriel absent de providers(). */
    const DEFAULT_PROVIDER = 'statuschanged';

    /**
     * Fournisseur de messages (db/messages.php) de chaque courriel (voir
     * notifications::get_email_definitions()).
     *
     * @return array emailkey => nom du fournisseur
     */
    public static function providers() {
        return [
            'actionrequired' => 'dossierawaiting',
            'reminder' => 'dossierawaiting',
            'returned' => 'statuschanged',
            'subjectactive' => 'statuschanged',
            'closed' => 'statuschanged',
            'adjourned' => 'statuschanged',
            'subjectchanged' => 'statuschanged',
            'daterequested' => 'statuschanged',
            'dateproposed' => 'statuschanged',
            'signaturerequest' => 'signaturerequest',
            'agentsignature' => 'signaturerequest',
            'agentrefused' => 'signaturerequest',
            'convocation' => 'juryconvocation',
            'university' => 'juryconvocation',
            'jurydesignation' => 'juryconvocation',
            'digest' => 'digest',
        ];
    }

    /**
     * Fournisseur de messages d'un courriel.
     *
     * @param string $emailkey
     * @return string
     */
    public static function provider_for($emailkey) {
        return self::providers()[$emailkey] ?? self::DEFAULT_PROVIDER;
    }

    /**
     * Indique si un destinataire reçoit par la messagerie de Moodle : un compte réel, sans pièce jointe.
     *
     * @param stdClass $recipient Compte Moodle, ou destinataire sans compte.
     * @param string $attachment Chemin de la pièce jointe, '' sans.
     * @return bool
     */
    public static function uses_messaging(stdClass $recipient, $attachment = '') {
        return (int) $recipient->id > 0 && (string) $attachment === '';
    }

    /**
     * Remet un courriel à un destinataire : message de la messagerie (sujet, corps en texte brut et
     * son rendu HTML, lien vers le dossier) pour un compte, courriel direct sinon.
     *
     * @param stdClass $these
     * @param string $emailkey
     * @param stdClass $recipient
     * @param string $subject
     * @param string $body
     * @param string $attachment Chemin d'une pièce jointe (dans le répertoire temporaire de Moodle).
     * @param string $attachname Nom de la pièce jointe.
     * @param string $contexturl Page du dossier, ou page utile au destinataire ('' sans).
     * @return bool
     */
    public static function deliver(
        stdClass $these,
        $emailkey,
        stdClass $recipient,
        $subject,
        $body,
        $attachment = '',
        $attachname = '',
        $contexturl = ''
    ) {
        $noreply = core_user::get_noreply_user();
        if (!self::uses_messaging($recipient, $attachment)) {
            return (bool) email_to_user($recipient, $noreply, $subject, $body, '', $attachment, $attachname);
        }
        $message = new message();
        $message->component = 'mod_these';
        $message->name = self::provider_for($emailkey);
        $message->userfrom = $noreply;
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($body, false, false, true);
        $message->smallmessage = $subject;
        $message->notification = 1;
        $message->courseid = (int) $these->course;
        if ((string) $contexturl !== '') {
            $message->contexturl = (string) $contexturl;
            $message->contexturlname = get_string('dossier', 'mod_these');
        }
        return message_send($message) !== false;
    }
}
