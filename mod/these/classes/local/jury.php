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
use core_text;
use stdClass;

/**
 * Directeurs de thèse et composition du jury de soutenance : membres enregistrés d'un dossier,
 * identité d'un membre d'une saisie à l'autre, éligibilité aux rôles, règles de composition.
 *
 * Voir aussi jurors (annuaire des enseignants), juryform (saisie du jury dans les formulaires),
 * juryaccess (désignation et accès des membres aux documents) et defenseconflicts (chevauchements
 * de soutenances).
 *
 * Les fonctions these_*() correspondantes de locallib/jury.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jury {
    /**
     * Utilisateurs inscrits pouvant être choisis comme directeur de thèse.
     *
     * @param context $context
     * @return array Utilisateurs indexés par id, triés par nom.
     */
    public static function get_potential_directors(context $context) {
        $users = get_enrolled_users($context, 'mod/these:direct', 0, 'u.*', 'u.lastname ASC, u.firstname ASC', 0, 0, true);
        // Les agents de la bibliothèque ne dirigent pas de thèse.
        return array_diff_key($users, scopesettings::library_agents($context));
    }

    /**
     * Options du sélecteur de directeur, en conservant le directeur actuel même s'il n'est plus
     * inscrit (pour ne pas le perdre silencieusement à l'enregistrement).
     *
     * @param context $context
     * @param int $currentid
     * @return array id => nom
     */
    public static function director_options(context $context, $currentid = 0) {
        global $DB;

        $options = [0 => get_string('choosedots')];
        foreach (self::get_potential_directors($context) as $user) {
            $options[$user->id] = fullname($user);
        }
        if ($currentid && !isset($options[$currentid])) {
            $user = $DB->get_record('user', ['id' => $currentid]);
            if ($user) {
                $options[$currentid] = fullname($user);
            }
        }
        return $options;
    }

    /**
     * Membres du jury d'un dossier, dans leur ordre.
     *
     * @param int $dossierid
     * @return array
     */
    public static function get_jury($dossierid) {
        global $DB;

        return array_values($DB->get_records('these_jury', ['dossierid' => $dossierid], 'sortorder ASC, id ASC'));
    }

    /**
     * Jurys de plusieurs dossiers, lus en une requête, chacun dans son ordre.
     *
     * @param int[] $dossierids
     * @return array dossierid => membres (liste vide au besoin), un tableau par dossier demandé.
     */
    public static function get_juries(array $dossierids) {
        global $DB;

        $juries = array_fill_keys(array_map('intval', $dossierids), []);
        if (!$juries) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($juries), SQL_PARAMS_NAMED);
        $members = $DB->get_records_select('these_jury', "dossierid $insql", $params, 'dossierid ASC, sortorder ASC, id ASC');
        foreach ($members as $member) {
            $juries[(int) $member->dossierid][] = $member;
        }
        return $juries;
    }

    /**
     * Clé d'identité d'un membre du jury, pour reconnaître le même membre d'une saisie à l'autre.
     *
     * @param stdClass $member
     * @return string
     */
    public static function jury_member_key(stdClass $member) {
        if (!empty($member->jurorid)) {
            return 'j' . (int) $member->jurorid;
        }
        return 'e' . core_text::strtolower(trim((string) $member->email)) . '|'
            . core_text::strtolower(trim((string) $member->fullname));
    }

    /**
     * Deux membres de jury sont-ils la même personne ? Même enseignant de l'annuaire, même adresse
     * ou, à défaut d'adresse, même nom.
     *
     * @param stdClass $a
     * @param stdClass $b
     * @return bool
     */
    public static function same_member(stdClass $a, stdClass $b) {
        if (!empty($a->jurorid) && (int) $a->jurorid === (int) ($b->jurorid ?? 0)) {
            return true;
        }
        $email = function ($member) {
            return core_text::strtolower(trim((string) ($member->email ?? '')));
        };
        if ($email($a) !== '' && $email($b) !== '') {
            return $email($a) === $email($b);
        }
        $name = function ($member) {
            return core_text::strtolower(preg_replace('/\s+/', ' ', trim((string) $member->fullname)));
        };
        return $name($a) !== '' && $name($a) === $name($b);
    }

    /**
     * Remplace le jury d'un dossier. Un membre déjà présent (même enseignant de l'annuaire, ou mêmes
     * nom et adresse) garde son accès aux documents et la trace de sa notification ; son éligibilité
     * validée est conservée s'il garde le même rôle et le même statut, sauf si $member->eligible est
     * fourni (saisie de la DEVE). Le compte Moodle rattaché au membre est fixé au choix dans l'annuaire
     * et à la validation d'éligibilité (voir member_userid()).
     *
     * @param int $dossierid
     * @param array $members Liste d'objets {juryrole, fullname, jurytitle, institution, email, jurorid?,
     *                       jurystatus?, eligible?}.
     * @param int $userid Auteur de la validation d'éligibilité, s'il y en a une.
     * @return void
     */
    public static function save_jury($dossierid, array $members, $userid = 0) {
        global $DB;

        $previous = [];
        foreach ($DB->get_records('these_jury', ['dossierid' => $dossierid]) as $record) {
            $previous[self::jury_member_key($record)] = $record;
        }
        $DB->delete_records('these_jury', ['dossierid' => $dossierid]);
        $sortorder = 0;
        foreach ($members as $member) {
            $old = $previous[self::jury_member_key($member)] ?? null;
            $record = (object) [
                'dossierid' => $dossierid,
                'juryrole' => $member->juryrole,
                'fullname' => $member->fullname,
                'jurytitle' => $member->jurytitle,
                'institution' => $member->institution,
                'email' => $member->email,
                'sortorder' => $sortorder++,
                'timecreated' => $old->timecreated ?? time(),
                'jurorid' => (int) ($member->jurorid ?? 0),
                'jurystatus' => (string) ($member->jurystatus ?? ''),
                'eligible' => 0,
                'eligibleby' => 0,
                'eligibletime' => 0,
                'accesstoken' => $old->accesstoken ?? null,
                'notifiedtime' => (int) ($old->notifiedtime ?? 0),
            ];
            if (isset($member->eligible)) {
                $record->eligible = $member->eligible ? 1 : 0;
                $record->eligibleby = $member->eligible ? ($old && $old->eligible ? $old->eligibleby : (int) $userid) : 0;
                $record->eligibletime = $member->eligible ? ($old && $old->eligible ? $old->eligibletime : time()) : 0;
            } else if ($old && $old->eligible && $old->juryrole === $record->juryrole && $old->jurystatus === $record->jurystatus) {
                $record->eligible = 1;
                $record->eligibleby = $old->eligibleby;
                $record->eligibletime = $old->eligibletime;
            }
            $record->userid = self::member_userid($record, $old, isset($member->eligible) && $member->eligible);
            $DB->insert_record('these_jury', $record);
        }
    }

    /**
     * Compte Moodle rattaché à un membre du jury enregistré, qui seul le fait reconnaître comme
     * membre du jury (sujet d'une thèse confidentielle, jurys du suivi). Le rattachement est décidé
     * quand le membre est choisi dans l'annuaire et quand la DEVE valide son éligibilité ; il est
     * conservé tant que l'adresse du membre ne change pas, et recalculé sinon.
     *
     * @param stdClass $record Membre tel qu'il va être enregistré.
     * @param stdClass|null $old Même membre dans le jury précédent.
     * @param bool $validated La DEVE valide (ou confirme) l'éligibilité du membre.
     * @return int Identifiant du compte, 0 sans compte rattaché.
     */
    protected static function member_userid(stdClass $record, ?stdClass $old, $validated) {
        $email = function ($member) {
            return core_text::strtolower(trim((string) ($member->email ?? '')));
        };
        $userid = 0;
        if ($old && $email($old) === $email($record)) {
            $userid = (int) ($old->userid ?? 0);
        } else if ($old && !empty($old->userid)) {
            // Adresse changée : le rattachement est recalculé.
            return self::resolve_userid($record);
        }
        if (!empty($record->jurorid) || ($validated && !$userid)) {
            $userid = self::resolve_userid($record) ?: $userid;
        }
        return $userid;
    }

    /**
     * Compte Moodle d'un membre du jury : celui de sa fiche dans l'annuaire, reconnu à l'adresse de
     * la fiche, sinon l'unique compte confirmé, non suspendu et non supprimé portant l'adresse du
     * membre. Aucun si l'adresse est inconnue ou partagée par plusieurs comptes.
     *
     * @param stdClass $member {email, jurorid?}
     * @return int Identifiant du compte, 0 si aucun.
     */
    public static function resolve_userid(stdClass $member) {
        $juror = !empty($member->jurorid) ? jurors::get((int) $member->jurorid) : null;
        if ($juror && ($userid = self::active_account((string) $juror->email))) {
            return $userid;
        }
        return self::active_account((string) ($member->email ?? ''));
    }

    /**
     * Unique compte confirmé, non suspendu et non supprimé portant une adresse.
     *
     * @param string $email
     * @return int Identifiant du compte, 0 si aucun ou si plusieurs comptes portent cette adresse.
     */
    protected static function active_account($email) {
        global $DB;

        $email = core_text::strtolower(trim($email));
        if ($email === '') {
            return 0;
        }
        $users = $DB->get_records_select(
            'user',
            'LOWER(email) = :email AND confirmed = 1 AND suspended = 0 AND deleted = 0',
            ['email' => $email],
            '',
            'id',
            0,
            2
        );
        return count($users) === 1 ? (int) key($users) : 0;
    }

    /**
     * Éligibilité d'un membre du jury à son rôle : un invité ne vote pas ; seuls les maîtres de
     * conférences HDR, les professeurs et les émérites peuvent présider ; un enseignant de l'annuaire
     * est éligible d'office aux autres rôles ; un membre extérieur l'est sous réserve de validation par
     * la DEVE.
     *
     * @param stdClass $member
     * @return string 'ok', 'tocheck' (à valider par la DEVE) ou 'ko'
     */
    public static function jury_eligibility(stdClass $member) {
        if (!self::is_voting($member)) {
            return 'ok';
        }
        $status = (string) ($member->jurystatus ?? '');
        if ($member->juryrole === 'president' && !jurors::can_preside($status)) {
            return 'ko';
        }
        return !empty($member->jurorid) ? 'ok' : 'tocheck';
    }

    /**
     * Membre votant du jury : ni le directeur de thèse, qui siège sans voter, ni un invité.
     *
     * @param stdClass $member
     * @return bool
     */
    public static function is_voting(stdClass $member) {
        return !in_array($member->juryrole, ['director', 'guest'], true);
    }

    /**
     * Vérifie la composition d'un jury : un et un seul président, éligible à la présidence, un et un
     * seul directeur de thèse, au moins
     * $minjury membres votants (le directeur de thèse et les invités ne votent pas), un statut déclaré pour chaque membre
     * extérieur votant, pas deux fois le même enseignant, et des adresses de courriel valides lorsqu'elles
     * sont données.
     *
     * @param array $members Voir these_extract_submitted_jury().
     * @param int $minjury
     * @return string|null Message d'erreur, ou null si le jury est valide.
     */
    public static function validate_jury(array $members, $minjury) {
        $presidents = 0;
        $directors = 0;
        $voting = 0;
        $seen = [];
        foreach ($members as $member) {
            if ($member->juryrole === 'president') {
                $presidents++;
            }
            if ($member->juryrole === 'director') {
                $directors++;
            }
            if (self::is_voting($member)) {
                $voting++;
                if (empty($member->jurorid) && (string) ($member->jurystatus ?? '') === '') {
                    return get_string('jurystatusrequired', 'mod_these', s($member->fullname));
                }
            }
            if (!empty($member->jurorid)) {
                if (isset($seen[$member->jurorid])) {
                    return get_string('juryduplicate', 'mod_these', s($member->fullname));
                }
                $seen[$member->jurorid] = true;
            }
            if ($member->juryrole === 'president' && self::jury_eligibility($member) === 'ko') {
                return get_string('jurypresidentnoteligible', 'mod_these', s($member->fullname));
            }
            if ($member->email !== '' && !validate_email($member->email)) {
                return get_string('juryinvalidemail', 'mod_these', s($member->fullname));
            }
        }
        if ($presidents !== 1) {
            return get_string('juryonepresident', 'mod_these');
        }
        if ($directors !== 1) {
            return get_string('juryonedirector', 'mod_these');
        }
        if ($voting < (int) $minjury) {
            return get_string('juryminmembers', 'mod_these', (int) $minjury);
        }
        return null;
    }

    /**
     * Validation de l'éligibilité par la DEVE : chaque membre votant doit être coché, et aucun ne peut
     * être inéligible à son rôle.
     *
     * @param array $members Voir these_extract_submitted_jury() (avec éligibilité).
     * @return string|null Message d'erreur, ou null.
     */
    public static function validate_jury_eligibility(array $members) {
        foreach ($members as $member) {
            if (!self::is_voting($member)) {
                continue;
            }
            if (self::jury_eligibility($member) === 'ko') {
                return get_string('jurypresidentnoteligible', 'mod_these', s($member->fullname));
            }
            if (empty($member->eligible)) {
                return get_string('juryeligiblerequired', 'mod_these', s($member->fullname));
            }
        }
        return null;
    }

    /**
     * Zones de fichiers et documents signés auxquels le jury a accès.
     *
     * @return array {areas => string[], documents => string[]}
     */
    public static function jury_documents() {
        return [
            'areas' => ['manuscript', 'defensedocs', 'directorreport'],
            'documents' => ['grid', 'conclusions', 'signaletique'],
        ];
    }

    /**
     * Voir defenseconflicts::room_conflicts(). Conservé pour les appels existants.
     *
     * @param stdClass $dossier
     * @param string|null $location
     * @return stdClass[]
     */
    public static function room_conflicts(stdClass $dossier, $location = null) {
        return defenseconflicts::room_conflicts($dossier, $location);
    }

    /**
     * Voir defenseconflicts::jury_conflicts(). Conservé pour les appels existants.
     *
     * @param stdClass $dossier
     * @param array|null $members
     * @return stdClass[]
     */
    public static function jury_conflicts(stdClass $dossier, ?array $members = null) {
        return defenseconflicts::jury_conflicts($dossier, $members);
    }
}
