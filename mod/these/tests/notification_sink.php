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

namespace mod_these;

/**
 * Collecteur des envois de l'activité pendant un test : les messages de la messagerie (comptes
 * Moodle) et les courriels directs (destinataires sans compte, pièces jointes), présentés tous
 * sous la forme des courriels interceptés par redirectEmails() ({to, from, subject, body, header},
 * corps encodé quoted-printable), pour que les tests écrits avant l'API de messagerie restent valables.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notification_sink {
    /** @var \phpunit_phpmailer_sink */
    private $emails;
    /** @var \phpunit_message_sink */
    private $messages;

    /**
     * Constructeur.
     *
     * @param \phpunit_phpmailer_sink $emails
     * @param \phpunit_message_sink $messages
     */
    public function __construct(\phpunit_phpmailer_sink $emails, \phpunit_message_sink $messages) {
        $this->emails = $emails;
        $this->messages = $messages;
    }

    /**
     * Messages de la messagerie, sous la forme de courriels, puis courriels directs.
     *
     * @return \stdClass[]
     */
    public function get_messages(): array {
        global $DB;

        $all = [];
        foreach ($this->messages->get_messages() as $message) {
            $to = $DB->get_record('user', ['id' => $message->useridto], 'id, email');
            $all[] = (object) [
                'to' => $to ? $to->email : '',
                'from' => \core_user::get_noreply_user()->email,
                'subject' => $message->subject,
                'body' => quoted_printable_encode((string) $message->fullmessage),
                'header' => '',
                'notification' => $message,
            ];
        }
        foreach ($this->emails->get_messages() as $email) {
            $all[] = $email;
        }
        return $all;
    }

    /**
     * Messages de la messagerie seuls (enregistrements de la table notifications).
     *
     * @return \stdClass[]
     */
    public function get_notifications(): array {
        return $this->messages->get_messages();
    }

    /**
     * Courriels directs seuls.
     *
     * @return \stdClass[]
     */
    public function get_emails(): array {
        return $this->emails->get_messages();
    }

    /**
     * Nombre d'envois.
     *
     * @return int
     */
    public function count(): int {
        return $this->messages->count() + $this->emails->count();
    }

    /**
     * Oublie les envois déjà interceptés.
     *
     * @return void
     */
    public function clear(): void {
        $this->messages->clear();
        $this->emails->clear();
    }

    /**
     * Arrête l'interception.
     *
     * @return void
     */
    public function close(): void {
        $this->messages->close();
        $this->emails->close();
    }
}
