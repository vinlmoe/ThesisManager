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

use html_writer;
use moodleform;
use MoodleQuickForm;
use stdClass;

/**
 * Saisie du jury dans les formulaires : lignes répétables, rôles figés des premières lignes, jury
 * en lecture pour la DEVE, valeurs du formulaire et extraction des membres soumis.
 *
 * Les fonctions these_*() correspondantes de locallib/jury.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class juryform {
    /** Rôles figés des premières lignes de saisie du jury, dans l'ordre. */
    const FIXED_ROLES = ['president', 'director'];

    /**
     * Ajoute à un formulaire les lignes répétables de saisie du jury : chaque membre est choisi dans
     * l'annuaire des enseignants (titre, établissement, adresse et statut repris de l'annuaire) ou, pour
     * un membre extérieur ou un invité, saisi. Les deux premières lignes ont leur fonction figée :
     * président, puis directeur de thèse ; les suivantes sont des membres du jury ou des invités.
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

        $jurors = [0 => get_string('juryexternal', 'mod_these')] + jurors::options(false, $keep);
        $repeat = [];
        $repeat[] = $mform->createElement('static', 'jurymemberlabel', '', html_writer::tag('hr', ''));
        $repeat[] = $mform->createElement('select', 'juryrole', get_string('juryrole', 'mod_these'), workflow::jury_role_options());
        $repeat[] = $mform->createElement('select', 'jurorid', get_string('juryjuror', 'mod_these'), $jurors);
        $repeat[] = $mform->createElement('text', 'juryname', get_string('juryname', 'mod_these'), ['size' => 48]);
        $repeat[] = $mform->createElement('text', 'jurytitle', get_string('jurytitle', 'mod_these'), ['size' => 48]);
        $repeat[] = $mform->createElement(
            'select',
            'jurystatus',
            get_string('jurystatus', 'mod_these'),
            ['' => get_string('choosedots')] + jurors::external_statuses()
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
            max(4, $count + 1 + count(self::FIXED_ROLES)),
            $options,
            'juryrepeats',
            'juryaddfields',
            2,
            get_string('juryaddmembers', 'mod_these'),
            true
        );
        self::fix_roles($mform);
    }

    /**
     * Fige la fonction des premières lignes du jury (voir FIXED_ROLES) : libellé affiché et valeur
     * imposée ; les lignes suivantes ne proposent que membre du jury ou invité.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    protected static function fix_roles(MoodleQuickForm $mform) {
        $roles = workflow::jury_role_options();
        for ($i = 0; $mform->elementExists("juryrole[$i]"); $i++) {
            $role = self::FIXED_ROLES[$i] ?? null;
            if ($role === null) {
                $select = $mform->getElement("juryrole[$i]");
                $select->removeOptions();
                $select->loadArray(['member' => $roles['member'], 'guest' => $roles['guest']]);
                continue;
            }
            $mform->removeElement("juryrole[$i]");
            $label = $mform->createElement(
                'static',
                "juryrolelabel[$i]",
                get_string('juryrole', 'mod_these'),
                html_writer::tag('strong', $roles[$role])
            );
            $mform->insertElementBefore($label, "jurorid[$i]");
            // QuickForm garde une référence à la variable : la libérer avant la ligne suivante.
            unset($label);
            $mform->insertElementBefore($mform->createElement('hidden', "juryrole[$i]", $role), "jurorid[$i]");
            $mform->setType("juryrole[$i]", PARAM_ALPHA);
            $mform->setConstant("juryrole[$i]", $role);
        }
    }

    /**
     * Membres d'un jury rangés selon les lignes du formulaire : le président sur la première, le
     * directeur de thèse sur la deuxième (lignes laissées vides s'ils manquent), les autres ensuite.
     *
     * @param array $members
     * @return array Indice de ligne => membre.
     */
    public static function form_rows(array $members) {
        $rows = [];
        $others = [];
        foreach (array_values($members) as $member) {
            $fixed = array_search($member->juryrole, self::FIXED_ROLES, true);
            if ($fixed !== false && !isset($rows[$fixed])) {
                $rows[$fixed] = $member;
            } else {
                $others[] = $member;
            }
        }
        foreach ($others as $n => $member) {
            $rows[count(self::FIXED_ROLES) + $n] = $member;
        }
        ksort($rows);
        return $rows;
    }

    /**
     * Jury proposé présenté en lecture à la DEVE : tableau des membres, et seulement les cases
     * d'éligibilité à vérifier (membres extérieurs). « Modifier la composition du jury » rouvre la saisie
     * complète. Sans jury enregistré, la saisie reste ouverte.
     *
     * @param MoodleQuickForm $mform Formulaire où these_add_jury_fields() a déjà ajouté la saisie.
     * @param array $members Jury enregistré (voir these_get_jury()), rangé comme dans le formulaire (voir form_rows()).
     * @return void
     */
    public static function jury_readonly(MoodleQuickForm $mform, array $members) {
        if (!$members) {
            return;
        }
        $rows = self::form_rows($members);
        $first = 'jurymemberlabel[0]';
        $summary = $mform->createElement('static', 'jurysummary', '', rendering::render_jury($members, true, false));
        $mform->insertElementBefore($summary, $first);
        $toggle = $mform->createElement('advcheckbox', 'juryedit', '', get_string('juryedit', 'mod_these'));
        $mform->insertElementBefore($toggle, $first);
        $mform->hideIf('jurysummary', 'juryedit', 'checked');

        $fields = ['jurymemberlabel', 'juryrolelabel', 'juryrole', 'jurorid', 'juryname', 'jurytitle', 'jurystatus',
            'juryinstitution', 'juryemail'];
        for ($i = 0; $mform->elementExists("juryrole[$i]"); $i++) {
            foreach ($fields as $field) {
                if ($mform->elementExists("{$field}[$i]")) {
                    $mform->hideIf("{$field}[$i]", 'juryedit', 'notchecked');
                }
            }
            $member = $rows[$i] ?? null;
            if ($member) {
                // Case d'éligibilité nommée : elle reste seule visible en lecture.
                $mform->getElement("juryeligible[$i]")->setText(
                    get_string('juryeligiblecheckof', 'mod_these', s($member->fullname))
                );
            }
            if (!$member || jury::jury_eligibility($member) === 'ok') {
                $mform->hideIf("juryeligible[$i]", 'juryedit', 'notchecked');
            }
        }
        if ($mform->elementExists('juryaddfields')) {
            $mform->hideIf('juryaddfields', 'juryedit', 'notchecked');
        }
    }

    /**
     * Valeurs de formulaire correspondant à un jury enregistré (voir these_add_jury_fields()), rangé
     * selon les lignes du formulaire (voir form_rows()).
     *
     * @param array $members
     * @return array
     */
    public static function jury_form_data(array $members) {
        $data = ['juryrole' => [], 'jurorid' => [], 'juryname' => [], 'jurytitle' => [], 'jurystatus' => [],
            'juryinstitution' => [], 'juryemail' => [], 'juryeligible' => []];
        foreach (self::form_rows($members) as $i => $member) {
            $data['juryrole'][$i] = $member->juryrole;
            $data['jurorid'][$i] = (int) ($member->jurorid ?? 0);
            $data['juryname'][$i] = $member->fullname;
            $data['jurytitle'][$i] = $member->jurytitle;
            $data['jurystatus'][$i] = (string) ($member->jurystatus ?? '');
            $data['juryinstitution'][$i] = $member->institution;
            $data['juryemail'][$i] = $member->email;
            // Pour la DEVE : éligibilité déjà validée, ou évidente (enseignant de l'annuaire au statut admis pour son rôle).
            $data['juryeligible'][$i] = (int) (!empty($member->eligible) || jury::jury_eligibility($member) === 'ok');
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
            $role = array_key_exists($role, workflow::jury_role_options()) ? $role : 'member';
            // Président et directeur seulement sur leurs lignes : les suivantes sont membres ou invités.
            if ($i >= count(self::FIXED_ROLES) && in_array($role, self::FIXED_ROLES, true)) {
                $role = 'member';
            }
            $juror = jurors::get((int) ($data['jurorid'][$i] ?? 0));
            if ($juror) {
                $member = (object) [
                    'juryrole' => $role,
                    'fullname' => jurors::fullname($juror),
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
                    'jurystatus' => array_key_exists($status, jurors::external_statuses()) ? $status : '',
                ];
            }
            if ($witheligibility) {
                $member->eligible = !empty($data['juryeligible'][$i]);
            }
            $members[] = $member;
        }
        return $members;
    }
}
