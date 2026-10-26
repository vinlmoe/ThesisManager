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

/**
 * Annuaire des enseignants pouvant diriger une thèse ou siéger au jury, commun à toutes les
 * activités et à toutes les promotions (table these_juror). Chacun a un titre (Dr ou Pr) et un
 * statut ; tous peuvent diriger une thèse ou être membre du jury, seuls certains statuts permettent
 * de présider (voir can_preside()).
 *
 * L'annuaire est tenu par la DEVE, depuis une activité « Gestion des thèses » ou « Suivi des
 * thèses » ; les formulaires de jury y puisent pour être préremplis.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jurors {
    /** @var string[] Statuts permettant de présider un jury. */
    const PRESIDENT_STATUSES = ['mcfhdr', 'pr', 'premerite', 'mcfemerite'];

    /**
     * Titres.
     *
     * @return array
     */
    public static function titles() {
        return ['Dr' => 'Dr', 'Pr' => 'Pr'];
    }

    /**
     * Statuts, dans l'ordre de présentation.
     *
     * @return array clé => libellé
     */
    public static function statuses() {
        $statuses = [];
        foreach (['ir', 'mcfstag', 'mcf', 'mcfhdr', 'mcfemerite', 'prstag', 'pr', 'premerite'] as $key) {
            $statuses[$key] = get_string('jurystatus' . $key, 'mod_these');
        }
        return $statuses;
    }

    /**
     * Statuts proposés pour un membre extérieur : ceux de l'annuaire et « autre ».
     *
     * @return array
     */
    public static function external_statuses() {
        return self::statuses() + ['other' => get_string('jurystatusother', 'mod_these')];
    }

    /**
     * Libellé d'un statut.
     *
     * @param string|null $status
     * @return string
     */
    public static function status_label($status) {
        return self::external_statuses()[(string) $status] ?? '';
    }

    /**
     * Un statut permet-il de présider un jury ?
     *
     * @param string|null $status
     * @return bool
     */
    public static function can_preside($status) {
        return in_array((string) $status, self::PRESIDENT_STATUSES, true);
    }

    /**
     * Enseignants de l'annuaire.
     *
     * @param bool $activeonly
     * @return array id => enregistrement
     */
    public static function get_all($activeonly = false) {
        global $DB;
        return $DB->get_records('these_juror', $activeonly ? ['active' => 1] : null, 'lastname ASC, firstname ASC');
    }

    /**
     * Un enseignant de l'annuaire.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get($id) {
        global $DB;
        return $id ? ($DB->get_record('these_juror', ['id' => $id]) ?: null) : null;
    }

    /**
     * Enseignant actif de l'annuaire ayant cette adresse.
     *
     * @param string|null $email
     * @return \stdClass|null
     */
    public static function find_by_email($email) {
        global $DB;
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }
        return $DB->get_record_select(
            'these_juror',
            'LOWER(email) = LOWER(:email) AND active = 1',
            ['email' => $email],
            '*',
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * Enseignant de l'annuaire, actif ou retiré, ayant cette adresse ou, à défaut, ces nom et prénom.
     *
     * @param string|null $email
     * @param string $lastname
     * @param string $firstname
     * @return \stdClass|null
     */
    public static function find_existing($email, $lastname, $firstname) {
        global $DB;
        $email = trim((string) $email);
        $existing = $email !== '' ? $DB->get_record('these_juror', ['email' => $email], 'id', IGNORE_MULTIPLE) : null;
        if (!$existing) {
            $existing = $DB->get_record_select(
                'these_juror',
                'LOWER(lastname) = LOWER(:lastname) AND LOWER(firstname) = LOWER(:firstname)',
                ['lastname' => trim($lastname), 'firstname' => trim($firstname)],
                'id',
                IGNORE_MULTIPLE
            );
        }
        return $existing ?: null;
    }

    /**
     * Enseignants inscrits au cours pouvant diriger une thèse (enseignants non éditeurs), absents de
     * l'annuaire. La DEVE (capacité ou rôle de service, en pratique l'enseignant éditeur) et la
     * bibliothèque en sont exclues.
     *
     * @param \context $context Contexte de l'activité (Gestion ou Suivi des thèses) ou du cours.
     * @return array id => utilisateur, triés par nom.
     */
    public static function course_teachers_missing(\context $context) {
        $coursecontext = $context->get_course_context();
        $users = get_enrolled_users($coursecontext, 'mod/these:direct', 0, 'u.*', 'u.lastname ASC, u.firstname ASC', 0, 0, true);
        $missing = [];
        foreach ($users as $user) {
            if (
                has_capability('mod/these:managedeve', $coursecontext, $user)
                    || has_capability('mod/these:managelibrary', $coursecontext, $user)
                    || these_user_has_service_role($context, $user->id, 'deve')
                    || these_user_has_service_role($context, $user->id, 'library')
                    || scopesettings::is_library_agent($context, $user->id)
                    || self::find_existing($user->email, $user->lastname, $user->firstname)
            ) {
                continue;
            }
            $missing[$user->id] = $user;
        }
        return $missing;
    }

    /**
     * Préremplit l'annuaire avec les enseignants du cours qui n'y sont pas (voir
     * course_teachers_missing()) : titre Dr, statut maître de conférences et établissement par
     * défaut, à vérifier par la DEVE. Les enseignants déjà présents ne sont pas modifiés.
     *
     * @param \context $context
     * @return int Nombre d'enseignants ajoutés.
     */
    public static function prefill_from_course(\context $context) {
        $institution = (string) scopesettings::for_context($context)['institution'];
        $count = 0;
        foreach (self::course_teachers_missing($context) as $user) {
            self::save((object) [
                'title' => 'Dr',
                'lastname' => $user->lastname,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'status' => 'mcf',
                'institution' => $institution,
                'active' => 1,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * « Pr NOM Prénom ».
     *
     * @param \stdClass $juror
     * @return string
     */
    public static function name(\stdClass $juror) {
        return trim($juror->title . ' ' . \core_text::strtoupper($juror->lastname) . ' ' . $juror->firstname);
    }

    /**
     * Nom complet sans titre, comme dans le jury : « Prénom NOM ».
     *
     * @param \stdClass $juror
     * @return string
     */
    public static function fullname(\stdClass $juror) {
        return trim($juror->firstname . ' ' . \core_text::strtoupper($juror->lastname));
    }

    /**
     * Options d'une liste déroulante de l'annuaire (enseignants actifs, et ceux déjà choisis).
     *
     * @param bool $presidentsonly Seulement ceux qui peuvent présider.
     * @param int[] $keep Identifiants à garder même s'ils ne sont plus actifs.
     * @return array id => « Pr NOM Prénom — statut (établissement) »
     */
    public static function options($presidentsonly = false, array $keep = []) {
        $options = [];
        foreach (self::get_all() as $juror) {
            if (
                (!$juror->active && !in_array((int) $juror->id, $keep, true))
                    || ($presidentsonly && !self::can_preside($juror->status))
            ) {
                continue;
            }
            $options[$juror->id] = self::name($juror) . ' — ' . self::status_label($juror->status)
                . ($juror->institution !== '' && $juror->institution !== null ? ' (' . $juror->institution . ')' : '');
        }
        return $options;
    }

    /**
     * Enregistre un enseignant.
     *
     * @param \stdClass $data {id?, title, lastname, firstname, email, status, institution, active}
     * @return int id
     */
    public static function save(\stdClass $data) {
        global $DB;

        $record = (object) [
            'title' => array_key_exists($data->title, self::titles()) ? $data->title : 'Dr',
            'lastname' => trim($data->lastname),
            'firstname' => trim($data->firstname),
            'email' => trim((string) ($data->email ?? '')),
            'status' => array_key_exists($data->status, self::statuses()) ? $data->status : 'mcf',
            'institution' => trim((string) ($data->institution ?? '')),
            'active' => empty($data->active) ? 0 : 1,
            'timemodified' => time(),
        ];
        if (!empty($data->id) && $DB->record_exists('these_juror', ['id' => $data->id])) {
            $record->id = $data->id;
            $DB->update_record('these_juror', $record);
            return (int) $record->id;
        }
        $record->timecreated = time();
        return (int) $DB->insert_record('these_juror', $record);
    }

    /**
     * Supprime un enseignant de l'annuaire. Les jurys qui l'ont retenu gardent son nom, son titre
     * et son statut ; ils ne sont plus rattachés à l'annuaire.
     *
     * @param int $id
     * @return void
     */
    public static function delete($id) {
        global $DB;
        $DB->set_field('these_jury', 'jurorid', 0, ['jurorid' => $id]);
        $DB->delete_records('these_juror', ['id' => $id]);
    }

    /**
     * Retrouve un statut à partir d'une clé ou d'un libellé, français ou anglais (import CSV).
     *
     * @param string $value
     * @return string|null
     */
    public static function parse_status($value) {
        $value = \core_text::strtolower(trim($value));
        $strings = get_string_manager();
        foreach (array_keys(self::statuses()) as $key) {
            // Clé, ou libellé en français ou en anglais, quelle que soit la langue de l'utilisateur.
            $labels = [$key];
            foreach (['fr', 'en'] as $lang) {
                $labels[] = \core_text::strtolower($strings->get_string('jurystatus' . $key, 'mod_these', null, $lang));
            }
            if (in_array($value, $labels, true)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Importe des enseignants depuis un CSV (colonnes : title, lastname, firstname, email, status,
     * institution ; séparateur virgule ou point-virgule). Un enseignant déjà présent (même adresse,
     * ou à défaut mêmes nom et prénom) est mis à jour.
     *
     * @param string $content
     * @return \stdClass {created, updated, errors (string[])}
     */
    public static function import_csv($content) {
        global $DB;

        $result = (object) ['created' => 0, 'updated' => 0, 'errors' => []];
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (!$lines || trim($lines[0]) === '') {
            return $result;
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(function ($value) {
            return \core_text::strtolower(trim($value));
        }, str_getcsv(array_shift($lines), $delimiter));
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = array_combine($header, array_pad(str_getcsv($line, $delimiter), count($header), ''));
            $lineno = $index + 2;
            $status = self::parse_status((string) ($row['status'] ?? ''));
            $title = ucfirst(\core_text::strtolower(trim((string) ($row['title'] ?? ''))));
            if (
                trim((string) ($row['lastname'] ?? '')) === '' || trim((string) ($row['firstname'] ?? '')) === ''
                    || $status === null || !array_key_exists($title, self::titles())
            ) {
                $result->errors[] = get_string('jurorimportbadline', 'mod_these', $lineno);
                continue;
            }
            $email = trim((string) ($row['email'] ?? ''));
            if ($email !== '' && !validate_email($email)) {
                $result->errors[] = get_string('jurorimportbadline', 'mod_these', $lineno);
                continue;
            }
            $existing = self::find_existing($email, $row['lastname'], $row['firstname']);
            self::save((object) [
                'id' => $existing->id ?? 0,
                'title' => $title,
                'lastname' => $row['lastname'],
                'firstname' => $row['firstname'],
                'email' => $email,
                'status' => $status,
                'institution' => (string) ($row['institution'] ?? ''),
                'active' => 1,
            ]);
            $existing ? $result->updated++ : $result->created++;
        }
        return $result;
    }
}
