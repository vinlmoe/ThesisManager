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

use moodle_url;
use stdClass;

/**
 * Désignation des membres du jury et accès aux documents du dossier : notification avec lien
 * personnel à jeton, accès depuis un compte Moodle, durée d'ouverture de l'accès.
 *
 * Les fonctions these_*() correspondantes de locallib/jury.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class juryaccess {
    /**
     * Prévient les membres du jury de leur désignation et leur donne accès aux documents du dossier
     * (manuscrit, rapport, grille, conclusions...) par un lien personnel, sans compte Moodle. Seuls les
     * membres pas encore prévenus le sont, sauf $force.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param bool $force Prévenir de nouveau tous les membres.
     * @return stdClass {sent, failed, missing (noms des membres sans adresse)}
     */
    public static function notify_jury(stdClass $these, stdClass $cm, stdClass $dossier, $force = false) {
        global $DB;

        $result = (object) ['sent' => 0, 'failed' => 0, 'missing' => []];
        $student = $DB->get_record('user', ['id' => $dossier->userid]);
        $date = (int) ($dossier->defensedate ?: $dossier->proposeddate);
        $roles = workflow::jury_role_options();
        foreach (jury::get_jury($dossier->id) as $member) {
            if (!$force && !empty($member->notifiedtime)) {
                continue;
            }
            if (trim((string) $member->email) === '') {
                $result->missing[] = $member->fullname;
                continue;
            }
            // Seule l'empreinte du jeton est conservée : chaque envoi donne un nouveau lien, qui
            // remplace le précédent.
            $token = rights::new_token();
            $DB->set_field('these_jury', 'accesstoken', rights::token_hash($token), ['id' => $member->id]);
            $recipient = notifications::make_external_user($member->email, $member->fullname);
            $sent = notifications::send_email($these, 'jurydesignation', [$recipient], [
                'name' => trim($member->jurytitle . ' ' . $member->fullname),
                'role' => $roles[$member->juryrole] ?? $member->juryrole,
                'student' => $student ? fullname($student) : '',
                'title' => format_string($dossier->title),
                'date' => $date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-',
                'location' => (string) $dossier->defenselocation,
                'url' => (new moodle_url('/mod/these/jury.php', ['token' => $token]))->out(false),
            ], '', '', $dossier);
            if ($sent) {
                $DB->set_field('these_jury', 'notifiedtime', time(), ['id' => $member->id]);
                $result->sent++;
            } else {
                $result->failed++;
            }
        }
        return $result;
    }

    /**
     * Membre du jury correspondant à un lien d'accès aux documents.
     *
     * @param string $token
     * @return stdClass|null Membre, avec dossier, these et cm ; null si le lien est inconnu ou n'est plus
     *                       valide (voir these_jury_access_open()).
     */
    public static function get_jury_access($token) {
        global $DB;

        $token = clean_param($token, PARAM_ALPHANUM);
        if (strlen($token) < 32) {
            return null;
        }
        $member = $DB->get_record('these_jury', ['accesstoken' => rights::token_hash($token)]);
        return $member ? self::jury_access_member($member) : null;
    }

    /**
     * Membre du jury consultant les documents depuis son compte Moodle (activité « Suivi des thèses »),
     * reconnu au compte qui lui est rattaché (voir rights::user_in_jury()), ni suspendu ni supprimé.
     *
     * @param int $memberid
     * @param stdClass $user
     * @return stdClass|null Voir these_get_jury_access().
     */
    public static function get_jury_member_access($memberid, stdClass $user) {
        global $DB;

        $member = $DB->get_record('these_jury', ['id' => $memberid]);
        if (!$member || empty($user->id) || empty($member->notifiedtime) || (int) $member->userid !== (int) $user->id) {
            return null;
        }
        if (!$DB->record_exists('user', ['id' => $user->id, 'deleted' => 0, 'suspended' => 0])) {
            return null;
        }
        return self::jury_access_member($member);
    }

    /**
     * Complète un membre du jury de son dossier, de son activité et de son module de cours, si son
     * accès aux documents est ouvert.
     *
     * @param stdClass $member
     * @return stdClass|null
     */
    public static function jury_access_member(stdClass $member) {
        global $DB;

        $dossier = $DB->get_record('these_dossier', ['id' => $member->dossierid]);
        if (!$dossier || !self::jury_access_open($dossier, $member)) {
            return null;
        }
        $member->dossier = $dossier;
        $member->these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $member->cm = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, MUST_EXIST);
        return $member;
    }

    /**
     * Indique si l'accès d'un membre du jury aux documents est ouvert : jusqu'à rights::TOKEN_LIFETIME
     * après la soutenance (ou après le dernier envoi du lien, s'il est plus tardif), et tant que le
     * dossier n'est ni clos ni abandonné.
     *
     * @param stdClass $dossier
     * @param stdClass $member
     * @return bool
     */
    public static function jury_access_open(stdClass $dossier, stdClass $member) {
        if (in_array((int) $dossier->status, [THESE_STATUS_CANCELLED, THESE_STATUS_CLOSED], true)) {
            return false;
        }
        $from = max((int) $member->notifiedtime, (int) ($dossier->defensedate ?: $dossier->proposeddate));
        return $from > 0 && time() <= $from + rights::TOKEN_LIFETIME;
    }
}
