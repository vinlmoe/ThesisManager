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
use html_writer;
use moodle_url;
use moodleform;
use MoodleQuickForm;
use stdClass;

/**
 * Directeurs de thèse et jury de soutenance.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
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
        foreach (these_get_potential_directors($context) as $user) {
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
     * Durée d'une soutenance d'un dossier, en secondes : paramètre du circuit de son activité.
     *
     * @param stdClass $dossier
     * @return int
     */
    public static function defense_duration(stdClass $dossier) {
        return max(1, (int) scopesettings::for_dossier($dossier)['defenseduration']) * MINSECS;
    }

    /**
     * Autres soutenances qui chevauchent celle d'un dossier (date arrêtée, ou à défaut date proposée),
     * toutes promotions confondues, chacune durant la durée fixée dans les paramètres du circuit de son
     * activité. Les dossiers abandonnés sont ignorés.
     *
     * @param stdClass $dossier
     * @return stdClass[] Dossiers, par date.
     */
    public static function overlapping_dossiers(stdClass $dossier) {
        global $DB;

        $time = (int) ($dossier->defensedate ?: $dossier->proposeddate);
        if (!$time) {
            return [];
        }
        // Candidats : soutenances commençant au plus une journée avant et avant la fin de celle-ci ; le
        // chevauchement est ensuite vérifié avec la durée propre à chacune.
        $end = $time + self::defense_duration($dossier);
        $params = ['id' => (int) $dossier->id, 'cancelled' => THESE_STATUS_CANCELLED, 'from1' => $time - DAYSECS,
            'to1' => $end - 1, 'from2' => $time - DAYSECS, 'to2' => $end - 1];
        $others = $DB->get_records_select(
            'these_dossier',
            'id <> :id AND status <> :cancelled
             AND ((defensedate > 0 AND defensedate BETWEEN :from1 AND :to1)
                  OR (defensedate = 0 AND proposeddate BETWEEN :from2 AND :to2))',
            $params,
            'defensedate ASC, proposeddate ASC, id ASC'
        );
        return array_values(array_filter($others, function ($other) use ($time) {
            return (int) ($other->defensedate ?: $other->proposeddate) + self::defense_duration($other) > $time;
        }));
    }

    /**
     * Lieu ramené à une forme comparable : casse, espaces.
     *
     * @param string|null $location
     * @return string
     */
    public static function normalise_location($location) {
        return \core_text::strtolower(trim(preg_replace('/\s+/u', ' ', (string) $location)));
    }

    /**
     * Autres soutenances prévues dans la même salle sur un créneau qui chevauche celle d'un dossier
     * (voir overlapping_dossiers()). Les soutenances sans lieu sont ignorées.
     *
     * @param stdClass $dossier
     * @param string|null $location Lieu à vérifier (par défaut, celui du dossier).
     * @return stdClass[] {location, time, student (nom), activity (nom de l'activité)}
     */
    public static function room_conflicts(stdClass $dossier, $location = null) {
        global $DB;

        $room = self::normalise_location($location ?? $dossier->defenselocation);
        if ($room === '') {
            return [];
        }
        $conflicts = [];
        foreach (self::overlapping_dossiers($dossier) as $other) {
            if (self::normalise_location($other->defenselocation) !== $room) {
                continue;
            }
            $student = $DB->get_record('user', ['id' => $other->userid]);
            $conflicts[] = (object) [
                'location' => trim((string) $other->defenselocation),
                'time' => (int) ($other->defensedate ?: $other->proposeddate),
                'student' => $student ? fullname($student) : '-',
                'activity' => (string) $DB->get_field('these', 'name', ['id' => $other->theseid]),
            ];
        }
        return $conflicts;
    }

    /**
     * Avertissement des conflits de salle d'un dossier (voir room_conflicts()), pour le personnel.
     *
     * @param stdClass $dossier
     * @return string HTML, vide sans conflit.
     */
    public static function render_room_conflicts(stdClass $dossier) {
        global $OUTPUT, $PAGE;

        $lines = array_map(function ($conflict) {
            return get_string('roomconflict', 'mod_these', (object) [
                'location' => $conflict->location,
                'date' => userdate($conflict->time, get_string('strftimedaydatetime', 'langconfig')),
                'student' => $conflict->student,
                'activity' => format_string($conflict->activity),
            ]);
        }, self::room_conflicts($dossier));
        if (!$lines) {
            return '';
        }
        $list = $PAGE->get_renderer('mod_these')->render(new \mod_these\output\bullet_list($lines));
        return $OUTPUT->notification(get_string('roomconflicts', 'mod_these') . $list, 'warning');
    }

    /**
     * Membres du jury d'un dossier déjà engagés dans un autre jury sur le même créneau, toutes
     * promotions confondues : les deux soutenances (date arrêtée, ou à défaut date proposée) se
     * chevauchent, chacune durant la durée fixée dans les paramètres du circuit de son activité. Les
     * dossiers abandonnés sont ignorés.
     *
     * @param stdClass $dossier
     * @param array|null $members Membres à vérifier (par défaut, le jury enregistré du dossier).
     * @return stdClass[] {member (nom), time, student (nom), activity (nom de l'activité)}
     */
    public static function jury_conflicts(stdClass $dossier, ?array $members = null) {
        global $DB;

        $members = $members ?? these_get_jury($dossier->id);
        if (!$members) {
            return [];
        }
        $conflicts = [];
        foreach (self::overlapping_dossiers($dossier) as $other) {
            $otherjury = these_get_jury($other->id);
            foreach ($members as $member) {
                foreach ($otherjury as $othermember) {
                    if (!self::same_member($member, $othermember)) {
                        continue;
                    }
                    $student = $DB->get_record('user', ['id' => $other->userid]);
                    $conflicts[] = (object) [
                        'member' => trim(($member->jurytitle ?? '') . ' ' . $member->fullname),
                        'time' => (int) ($other->defensedate ?: $other->proposeddate),
                        'student' => $student ? fullname($student) : '-',
                        'activity' => (string) $DB->get_field('these', 'name', ['id' => $other->theseid]),
                    ];
                }
            }
        }
        return $conflicts;
    }

    /**
     * Avertissement des conflits de jury d'un dossier (voir jury_conflicts()), pour la DEVE.
     *
     * @param stdClass $dossier
     * @return string HTML, vide sans conflit.
     */
    public static function render_jury_conflicts(stdClass $dossier) {
        global $OUTPUT, $PAGE;

        $lines = array_map(function ($conflict) {
            return get_string('juryconflict', 'mod_these', (object) [
                'member' => $conflict->member,
                'date' => userdate($conflict->time, get_string('strftimedaydatetime', 'langconfig')),
                'student' => $conflict->student,
                'activity' => format_string($conflict->activity),
            ]);
        }, self::jury_conflicts($dossier));
        if (!$lines) {
            return '';
        }
        $list = $PAGE->get_renderer('mod_these')->render(new \mod_these\output\bullet_list($lines));
        return $OUTPUT->notification(get_string('juryconflicts', 'mod_these') . $list, 'warning');
    }

    /**
     * Remplace le jury d'un dossier. Un membre déjà présent (même enseignant de l'annuaire, ou mêmes
     * nom et adresse) garde son accès aux documents et la trace de sa notification ; son éligibilité
     * validée est conservée s'il garde le même rôle et le même statut, sauf si $member->eligible est
     * fourni (saisie de la DEVE).
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
            $previous[these_jury_member_key($record)] = $record;
        }
        $DB->delete_records('these_jury', ['dossierid' => $dossierid]);
        $sortorder = 0;
        foreach ($members as $member) {
            $old = $previous[these_jury_member_key($member)] ?? null;
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
            $DB->insert_record('these_jury', $record);
        }
    }

    /**
     * Ajoute à un formulaire les lignes répétables de saisie du jury : chaque membre est choisi dans
     * l'annuaire des enseignants (titre, établissement, adresse et statut repris de l'annuaire) ou, pour
     * un membre extérieur ou un invité, saisi.
     *
     * @param moodleform $form
     * @param MoodleQuickForm $mform
     * @param int $count Nombre de lignes déjà renseignées.
     * @param bool $eligibility Ajouter la validation d'éligibilité par la DEVE.
     * @param int[] $keep Enseignants de l'annuaire à proposer même s'ils ne sont plus actifs.
     * @param string $warning Avertissement affiché en tête de la rubrique (HTML, voir render_jury_conflicts()).
     * @return void
     */
    public static function add_jury_fields(
        moodleform $form,
        MoodleQuickForm $mform,
        $count,
        $eligibility = false,
        array $keep = [],
        $warning = ''
    ) {
        $mform->addElement('header', 'juryheader', get_string('jury', 'mod_these'));
        $mform->setExpanded('juryheader');
        if ($warning !== '') {
            $mform->addElement('static', 'juryconflicts', '', $warning);
        }
        // Les erreurs de composition du jury s'affichent ici, en tête de la rubrique. La DEVE, qui
        // connaît les règles, n'a qu'un rappel d'une ligne.
        $mform->addElement('static', 'juryhelp', '', get_string($eligibility ? 'juryhelpdeve' : 'juryhelp', 'mod_these'));

        $jurors = [0 => get_string('juryexternal', 'mod_these')] + \mod_these\local\jurors::options(false, $keep);
        $repeat = [];
        $repeat[] = $mform->createElement('static', 'jurymemberlabel', '', html_writer::tag('hr', ''));
        $repeat[] = $mform->createElement('select', 'juryrole', get_string('juryrole', 'mod_these'), these_jury_role_options());
        $repeat[] = $mform->createElement('select', 'jurorid', get_string('juryjuror', 'mod_these'), $jurors);
        $repeat[] = $mform->createElement('text', 'juryname', get_string('juryname', 'mod_these'), ['size' => 48]);
        $repeat[] = $mform->createElement('text', 'jurytitle', get_string('jurytitle', 'mod_these'), ['size' => 48]);
        $repeat[] = $mform->createElement(
            'select',
            'jurystatus',
            get_string('jurystatus', 'mod_these'),
            ['' => get_string('choosedots')] + \mod_these\local\jurors::external_statuses()
        );
        $repeat[] = $mform->createElement('text', 'juryinstitution', get_string('juryinstitution', 'mod_these'), ['size' => 48]);
        $repeat[] = $mform->createElement('text', 'juryemail', get_string('juryemail', 'mod_these'), ['size' => 48]);
        if ($eligibility) {
            $repeat[] = $mform->createElement('advcheckbox', 'juryeligible', '', get_string('juryeligiblecheck', 'mod_these'));
        }

        $external = ['jurorid', 'neq', 0];
        $options = [
            'jurorid' => ['type' => PARAM_INT, 'helpbutton' => ['juryjuror', 'mod_these']],
            'juryname' => ['type' => PARAM_TEXT, 'hideif' => $external],
            'jurytitle' => ['type' => PARAM_TEXT, 'hideif' => $external],
            'jurystatus' => ['type' => PARAM_ALPHA, 'hideif' => $external],
            'juryinstitution' => ['type' => PARAM_TEXT, 'hideif' => $external],
            'juryemail' => ['type' => PARAM_RAW_TRIMMED, 'hideif' => $external],
        ];
        $form->repeat_elements(
            $repeat,
            max(4, $count + 1),
            $options,
            'juryrepeats',
            'juryaddfields',
            2,
            get_string('juryaddmembers', 'mod_these'),
            true
        );
    }

    /**
     * Jury proposé présenté en lecture à la DEVE : tableau des membres, et seulement les cases
     * d'éligibilité à vérifier (membres extérieurs). « Modifier la composition du jury » rouvre la saisie
     * complète. Sans jury enregistré, la saisie reste ouverte.
     *
     * @param MoodleQuickForm $mform Formulaire où these_add_jury_fields() a déjà ajouté la saisie.
     * @param array $members Jury enregistré (voir these_get_jury()), dans l'ordre des lignes du formulaire.
     * @return void
     */
    public static function jury_readonly(MoodleQuickForm $mform, array $members) {
        if (!$members) {
            return;
        }
        $first = 'jurymemberlabel[0]';
        $summary = $mform->createElement('static', 'jurysummary', '', these_render_jury($members, true, false));
        $mform->insertElementBefore($summary, $first);
        $toggle = $mform->createElement('advcheckbox', 'juryedit', '', get_string('juryedit', 'mod_these'));
        $mform->insertElementBefore($toggle, $first);
        $mform->hideIf('jurysummary', 'juryedit', 'checked');

        $fields = ['jurymemberlabel', 'juryrole', 'jurorid', 'juryname', 'jurytitle', 'jurystatus', 'juryinstitution',
            'juryemail'];
        for ($i = 0; $mform->elementExists("juryrole[$i]"); $i++) {
            foreach ($fields as $field) {
                $mform->hideIf("{$field}[$i]", 'juryedit', 'notchecked');
            }
            $member = $members[$i] ?? null;
            if ($member) {
                // Case d'éligibilité nommée : elle reste seule visible en lecture.
                $mform->getElement("juryeligible[$i]")->setText(
                    get_string('juryeligiblecheckof', 'mod_these', s($member->fullname))
                );
            }
            if (!$member || these_jury_eligibility($member) === 'ok') {
                $mform->hideIf("juryeligible[$i]", 'juryedit', 'notchecked');
            }
        }
        if ($mform->elementExists('juryaddfields')) {
            $mform->hideIf('juryaddfields', 'juryedit', 'notchecked');
        }
    }

    /**
     * Valeurs de formulaire correspondant à un jury enregistré (voir these_add_jury_fields()).
     *
     * @param array $members
     * @return array
     */
    public static function jury_form_data(array $members) {
        $data = ['juryrole' => [], 'jurorid' => [], 'juryname' => [], 'jurytitle' => [], 'jurystatus' => [],
            'juryinstitution' => [], 'juryemail' => [], 'juryeligible' => []];
        foreach (array_values($members) as $i => $member) {
            $data['juryrole'][$i] = $member->juryrole;
            $data['jurorid'][$i] = (int) ($member->jurorid ?? 0);
            $data['juryname'][$i] = $member->fullname;
            $data['jurytitle'][$i] = $member->jurytitle;
            $data['jurystatus'][$i] = (string) ($member->jurystatus ?? '');
            $data['juryinstitution'][$i] = $member->institution;
            $data['juryemail'][$i] = $member->email;
            // Pour la DEVE : éligibilité déjà validée, ou évidente (enseignant de l'annuaire au statut admis pour son rôle).
            $data['juryeligible'][$i] = (int) (!empty($member->eligible) || these_jury_eligibility($member) === 'ok');
        }
        return $data;
    }

    /**
     * Extrait des données soumises les membres du jury renseignés : enseignant choisi dans l'annuaire
     * (ses titre, nom, établissement, adresse et statut en sont repris), ou ligne au nom non vide.
     *
     * @param stdClass|array $data
     * @param bool $witheligibility Reprendre la validation d'éligibilité de la DEVE.
     * @return array Liste d'objets {juryrole, fullname, jurytitle, institution, email, jurorid, jurystatus, eligible?}.
     */
    public static function extract_submitted_jury($data, $witheligibility = false) {
        $data = (array) $data;
        $members = [];
        $rows = array_unique(array_merge(
            array_keys((array) ($data['juryname'] ?? [])),
            array_keys((array) ($data['jurorid'] ?? []))
        ));
        sort($rows);
        foreach ($rows as $i) {
            $role = $data['juryrole'][$i] ?? 'member';
            $role = array_key_exists($role, these_jury_role_options()) ? $role : 'member';
            $juror = \mod_these\local\jurors::get((int) ($data['jurorid'][$i] ?? 0));
            if ($juror) {
                $member = (object) [
                    'juryrole' => $role,
                    'fullname' => \mod_these\local\jurors::fullname($juror),
                    'jurytitle' => $juror->title,
                    'institution' => (string) $juror->institution,
                    'email' => (string) $juror->email,
                    'jurorid' => (int) $juror->id,
                    'jurystatus' => $juror->status,
                ];
            } else {
                $name = trim((string) ($data['juryname'][$i] ?? ''));
                if ($name === '') {
                    continue;
                }
                $status = (string) ($data['jurystatus'][$i] ?? '');
                $member = (object) [
                    'juryrole' => $role,
                    'fullname' => $name,
                    'jurytitle' => trim((string) ($data['jurytitle'][$i] ?? '')),
                    'institution' => trim((string) ($data['juryinstitution'][$i] ?? '')),
                    'email' => trim((string) ($data['juryemail'][$i] ?? '')),
                    'jurorid' => 0,
                    'jurystatus' => array_key_exists($status, \mod_these\local\jurors::external_statuses()) ? $status : '',
                ];
            }
            if ($witheligibility) {
                $member->eligible = !empty($data['juryeligible'][$i]);
            }
            $members[] = $member;
        }
        return $members;
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
        if ($member->juryrole === 'president' && !\mod_these\local\jurors::can_preside($status)) {
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
     * Vérifie la composition d'un jury : un et un seul président, éligible à la présidence, au moins
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
        $voting = 0;
        $seen = [];
        foreach ($members as $member) {
            if ($member->juryrole === 'president') {
                $presidents++;
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
            if ($member->juryrole === 'president' && these_jury_eligibility($member) === 'ko') {
                return get_string('jurypresidentnoteligible', 'mod_these', s($member->fullname));
            }
            if ($member->email !== '' && !validate_email($member->email)) {
                return get_string('juryinvalidemail', 'mod_these', s($member->fullname));
            }
        }
        if ($presidents !== 1) {
            return get_string('juryonepresident', 'mod_these');
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
            if (these_jury_eligibility($member) === 'ko') {
                return get_string('jurypresidentnoteligible', 'mod_these', s($member->fullname));
            }
            if (empty($member->eligible)) {
                return get_string('juryeligiblerequired', 'mod_these', s($member->fullname));
            }
        }
        return null;
    }

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
        $roles = these_jury_role_options();
        foreach (these_get_jury($dossier->id) as $member) {
            if (!$force && !empty($member->notifiedtime)) {
                continue;
            }
            if (trim((string) $member->email) === '') {
                $result->missing[] = $member->fullname;
                continue;
            }
            // Seule l'empreinte du jeton est conservée : chaque envoi donne un nouveau lien, qui
            // remplace le précédent.
            $token = these_new_token();
            $DB->set_field('these_jury', 'accesstoken', these_token_hash($token), ['id' => $member->id]);
            $sent = these_send_email($these, 'jurydesignation', [these_make_external_user($member->email, $member->fullname)], [
                'name' => trim($member->jurytitle . ' ' . $member->fullname),
                'role' => $roles[$member->juryrole] ?? $member->juryrole,
                'student' => $student ? fullname($student) : '',
                'title' => format_string($dossier->title),
                'date' => $date ? userdate($date, get_string('strftimedaydatetime', 'langconfig')) : '-',
                'location' => (string) $dossier->defenselocation,
                'url' => (new moodle_url('/mod/these/jury.php', ['token' => $token]))->out(false),
            ]);
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
        $member = $DB->get_record('these_jury', ['accesstoken' => these_token_hash($token)]);
        return $member ? these_jury_access_member($member) : null;
    }

    /**
     * Membre du jury consultant les documents depuis son compte Moodle (activité « Suivi des thèses »),
     * reconnu à son adresse ou à celle de sa fiche dans l'annuaire du jury.
     *
     * @param int $memberid
     * @param stdClass $user
     * @return stdClass|null Voir these_get_jury_access().
     */
    public static function get_jury_member_access($memberid, stdClass $user) {
        global $DB;

        $email = core_text::strtolower(trim((string) $user->email));
        $member = $DB->get_record('these_jury', ['id' => $memberid]);
        if (!$member || $email === '' || empty($member->notifiedtime)) {
            return null;
        }
        $emails = [core_text::strtolower(trim((string) $member->email))];
        if (!empty($member->jurorid)) {
            $emails[] = core_text::strtolower(trim((string) $DB->get_field('these_juror', 'email', ['id' => $member->jurorid])));
        }
        return in_array($email, $emails, true) ? these_jury_access_member($member) : null;
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
        if (!$dossier || !these_jury_access_open($dossier, $member)) {
            return null;
        }
        $member->dossier = $dossier;
        $member->these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $member->cm = get_coursemodule_from_instance('these', $dossier->theseid, 0, false, MUST_EXIST);
        return $member;
    }

    /**
     * Indique si l'accès d'un membre du jury aux documents est ouvert : jusqu'à THESE_TOKEN_LIFETIME
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
        return $from > 0 && time() <= $from + THESE_TOKEN_LIFETIME;
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
}
