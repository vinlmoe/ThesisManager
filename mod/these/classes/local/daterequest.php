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

use context_module;
use moodle_url;
use stdClass;

/**
 * Demande d'une nouvelle date de soutenance par la DEVE, quand l'horaire proposé par l'étudiant
 * n'est pas disponible (aucune salle libre, par exemple). Seule la date est remise en cause : le
 * dossier n'est pas renvoyé, il garde son statut, ses documents et leurs signatures, mais la DEVE ne
 * peut pas le valider tant que l'étudiant n'a pas proposé une autre date (newdate.php).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class daterequest {
    /**
     * Statuts où la DEVE peut demander une autre date : dossier de soutenance déposé, avant qu'elle
     * ne l'ait validé.
     *
     * @return int[]
     */
    public static function statuses() {
        return [THESE_STATUS_DEFENSE_SUBMITTED, THESE_STATUS_DEFENSE_APPROVED];
    }

    /**
     * Indique si une nouvelle date est attendue de l'étudiant.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function pending(stdClass $dossier) {
        return !empty($dossier->daterequested) && in_array((int) $dossier->status, self::statuses(), true);
    }

    /**
     * Indique si la DEVE peut demander une autre date pour ce dossier.
     *
     * @param stdClass $dossier
     * @return bool
     */
    public static function allowed(stdClass $dossier) {
        return in_array((int) $dossier->status, self::statuses(), true) && !self::pending($dossier);
    }

    /**
     * Demande à l'étudiant une autre date, motif à l'appui, et l'en prévient.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid Compte de la DEVE.
     * @param string $reason Motif (horaire indisponible...), communiqué à l'étudiant.
     * @return stdClass Le dossier à jour.
     */
    public static function request(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $reason) {
        global $DB;

        $reason = trim((string) $reason);
        $dossier = these_dossier_operation($dossier, function () use ($dossier, $userid, $reason) {
            return these_set_status($dossier, $dossier->status, $userid, 'daterequested', $reason, [
                'daterequested' => time(),
                'daterequestcomment' => $reason,
            ]);
        });
        $student = $DB->get_record('user', ['id' => $dossier->userid, 'deleted' => 0]);
        if ($student) {
            $vars = these_email_base_vars($these, $cm, $dossier);
            $vars['comment'] = $reason;
            $format = get_string('strftimedaydatetime', 'langconfig');
            $vars['date'] = $dossier->proposeddate ? userdate($dossier->proposeddate, $format) : '';
            $vars['url'] = self::url($cm)->out(false);
            these_send_email($these, 'daterequested', [$student], $vars, '', '', $dossier);
        }
        return $dossier;
    }

    /**
     * Enregistre la nouvelle date proposée par l'étudiant et en prévient la DEVE : le dossier peut de
     * nouveau être validé.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param int $userid Étudiant.
     * @param int $date Nouvelle date proposée.
     * @return stdClass Le dossier à jour.
     */
    public static function propose(stdClass $these, stdClass $cm, stdClass $dossier, $userid, $date) {
        $date = (int) $date;
        $dossier = these_dossier_operation($dossier, function () use ($dossier, $userid, $date) {
            // La DEVE n'a pas encore arrêté la date : la date retenue suit la nouvelle proposition.
            return these_set_status(
                $dossier,
                $dossier->status,
                $userid,
                'dateproposed',
                userdate($date, get_string('strftimedaydatetime', 'langconfig')),
                ['proposeddate' => $date, 'defensedate' => $date, 'daterequested' => 0, 'daterequestcomment' => '']
            );
        });
        $context = context_module::instance($cm->id);
        $vars = these_email_base_vars($these, $cm, $dossier);
        $vars['date'] = userdate($date, get_string('strftimedaydatetime', 'langconfig'));
        these_send_email($these, 'dateproposed', these_get_service_recipients($these, $context, 'deve'), $vars, '', '', $dossier);
        return $dossier;
    }

    /**
     * Page où l'étudiant propose une nouvelle date.
     *
     * @param stdClass $cm
     * @return moodle_url
     */
    public static function url(stdClass $cm) {
        return new moodle_url('/mod/these/newdate.php', ['id' => $cm->id]);
    }
}
