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
    /** @var int Colonnes de chaque section de la liste du corps enseignant. */
    const TEACHING_LIST_COLUMNS = 3;

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
     * Titres connus par l'annuaire d'utilisateurs Moodle (directeurs de thèse) : ceux dont l'adresse
     * est celle d'un enseignant actif de l'annuaire.
     *
     * @param int[] $userids
     * @return array userid => titre (Dr ou Pr) ; les utilisateurs absents de l'annuaire n'y sont pas.
     */
    public static function titles_for_users(array $userids) {
        global $DB;
        $userids = array_filter(array_map('intval', $userids));
        if (!$userids) {
            return [];
        }
        $titles = [];
        foreach ($DB->get_records_list('user', 'id', $userids, '', 'id, email') as $user) {
            $juror = self::find_by_email($user->email);
            if ($juror) {
                $titles[(int) $user->id] = (string) $juror->title;
            }
        }
        return $titles;
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
        $existing = $email !== '' ? $DB->get_record('these_juror', ['email' => $email], 'id, discipline', IGNORE_MULTIPLE) : null;
        if (!$existing) {
            $existing = $DB->get_record_select(
                'these_juror',
                'LOWER(lastname) = LOWER(:lastname) AND LOWER(firstname) = LOWER(:firstname)',
                ['lastname' => trim($lastname), 'firstname' => trim($firstname)],
                'id, discipline',
                IGNORE_MULTIPLE
            );
        }
        return $existing ?: null;
    }

    /**
     * Enseignants inscrits au cours pouvant diriger une thèse (enseignants non éditeurs), absents de
     * l'annuaire. La DEVE (capacité, en pratique l'enseignant éditeur) et la bibliothèque en sont
     * exclues.
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
     * @param \stdClass $data {id?, title, lastname, firstname, email, status, institution, discipline?, active}
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
            'discipline' => trim((string) ($data->discipline ?? '')),
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
     * institution, et facultativement discipline ; séparateur virgule ou point-virgule). Un enseignant déjà présent (même adresse,
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
                // Matière : colonne facultative ; sans elle, celle déjà connue est gardée.
                'discipline' => array_key_exists('discipline', $row) ? (string) $row['discipline']
                    : (string) ($existing->discipline ?? ''),
                'active' => 1,
            ]);
            $existing ? $result->updated++ : $result->created++;
        }
        return $result;
    }

    /**
     * Annuaire en lecture seule (étudiants compris) : enseignants actifs, internes comme extérieurs,
     * filtrés par un texte cherché dans le nom, la matière et l'établissement (casse et accents
     * ignorés), et au besoin limités à ceux qui peuvent présider un jury.
     *
     * @param string $search
     * @param bool $presidentsonly
     * @return \stdClass[] Enseignants, triés par nom.
     */
    public static function directory($search = '', $presidentsonly = false) {
        $normalise = function ($value) {
            return trim(preg_replace('/\s+/', ' ', \core_text::strtolower(\core_text::specialtoascii((string) $value))));
        };
        $words = array_filter(explode(' ', $normalise($search)), 'strlen');
        return array_values(array_filter(self::get_all(true), function ($juror) use ($normalise, $words, $presidentsonly) {
            if ($presidentsonly && !self::can_preside($juror->status)) {
                return false;
            }
            $haystack = $normalise(implode(' ', [$juror->title, $juror->lastname, $juror->firstname,
                $juror->discipline ?? '', $juror->institution ?? '', self::status_label($juror->status)]));
            foreach ($words as $word) {
                if (strpos($haystack, $word) === false) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Statuts dans l'ordre de la liste du corps enseignant (professeurs d'abord).
     *
     * @return string[]
     */
    public static function teaching_list_order() {
        return ['pr', 'premerite', 'prstag', 'mcfhdr', 'mcf', 'mcfemerite', 'mcfstag', 'ir'];
    }

    /**
     * Enseignants actifs de l'annuaire internes à l'établissement : ceux dont l'établissement contient
     * le nom de l'établissement de la portée (casse et espaces ignorés). Sans nom d'établissement,
     * tous les enseignants actifs.
     *
     * @param string $institution
     * @return \stdClass[]
     */
    public static function internal_members($institution) {
        $institution = jury::normalise_location($institution);
        return array_values(array_filter(self::get_all(true), function ($juror) use ($institution) {
            return $institution === '' || strpos(jury::normalise_location($juror->institution), $institution) !== false;
        }));
    }

    /**
     * Données du modèle « Liste du corps enseignant » : les enseignants actifs internes à
     * l'établissement (voir internal_members()), groupés par statut, à inclure dans les thèses. Chaque
     * groupe est aussi présenté en trois colonnes (rows : lignes de trois cellules, chacune avec son
     * enseignant ou vide ; la première colonne remplie d'abord, pour garder l'ordre alphabétique en
     * lisant de haut en bas).
     *
     * @param \stdClass|null $scope Portée dont reprendre l'établissement et les images.
     * @param \stdClass|null $activity Activité dont reprendre l'établissement, à défaut.
     * @param \stdClass[]|null $members Enseignants à lister (par défaut, ceux de l'annuaire).
     * @return array today, establishment {name, city}, count, hasmembers, groups {label, members, rows {cells {member}}}, img ;
     *               un enseignant : {name, title, fullname, discipline}
     */
    public static function teaching_list_context(?\stdClass $scope, ?\stdClass $activity = null, ?array $members = null) {
        $settings = $scope ? scopesettings::get_all($scope) : scopesettings::defaults();
        $name = trim((string) $settings['institution']) ?: trim((string) ($activity->establishmentname ?? ''));
        $members = $members ?? self::internal_members($name);
        $groups = [];
        foreach (self::teaching_list_order() as $status) {
            $rows = array_values(array_filter($members, function ($juror) use ($status) {
                return (string) $juror->status === $status;
            }));
            if (!$rows) {
                continue;
            }
            $people = array_map(function ($juror) {
                return [
                    'title' => (string) $juror->title,
                    'fullname' => self::fullname($juror),
                    'name' => trim($juror->title . ' ' . self::fullname($juror)),
                    'discipline' => trim((string) ($juror->discipline ?? '')),
                ];
            }, $rows);
            // Colonnes aussi égales que possible (4 enseignants : 2, 1, 1), remplies l'une après l'autre.
            $sizes = [];
            $starts = [];
            $start = 0;
            for ($column = 0; $column < self::TEACHING_LIST_COLUMNS; $column++) {
                $sizes[$column] = intdiv(count($people), self::TEACHING_LIST_COLUMNS)
                    + ($column < count($people) % self::TEACHING_LIST_COLUMNS ? 1 : 0);
                $starts[$column] = $start;
                $start += $sizes[$column];
            }
            $columns = [];
            for ($i = 0; $i < $sizes[0]; $i++) {
                $cells = [];
                for ($column = 0; $column < self::TEACHING_LIST_COLUMNS; $column++) {
                    $cells[] = ['member' => $i < $sizes[$column] ? $people[$starts[$column] + $i] : null];
                }
                $columns[] = ['cells' => $cells];
            }
            $groups[] = [
                'label' => get_string('teachinggroup' . $status, 'mod_these'),
                'members' => $people,
                'rows' => $columns,
            ];
        }
        return [
            'today' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
            'establishment' => ['name' => $name, 'city' => (string) ($activity->establishmentcity ?? '')],
            'count' => array_sum(array_map(function ($group) {
                return count($group['members']);
            }, $groups)),
            'hasmembers' => !empty($groups),
            'groups' => $groups,
            'img' => these_doctemplate_images($scope),
        ];
    }

    /**
     * Données d'exemple de la liste du corps enseignant, pour l'aperçu et la vérification du modèle.
     *
     * @param \stdClass|null $scope
     * @param \stdClass|null $activity
     * @return array Voir teaching_list_context().
     */
    public static function sample_teaching_list_context(?\stdClass $scope = null, ?\stdClass $activity = null) {
        $sample = [
            ['Pr', 'DUPONT', 'Jean', 'pr', 'Anatomie'], ['Pr', 'MARTIN', 'Claire', 'pr', 'Pharmacologie'],
            ['Pr', 'LEROY', 'Marc', 'pr', 'Chirurgie'], ['Dr', 'DURAND', 'Anne', 'mcfhdr', 'Parasitologie'],
            ['Dr', 'PETIT', 'Luc', 'mcf', 'Médecine interne'], ['Dr', 'BERNARD', 'Marie', 'mcf', 'Biochimie'],
            ['Dr', 'ROUX', 'Paul', 'ir', ''],
        ];
        $members = array_map(function ($row) {
            return (object) ['title' => $row[0], 'lastname' => $row[1], 'firstname' => $row[2], 'status' => $row[3],
                'discipline' => $row[4]];
        }, $sample);
        return self::teaching_list_context($scope, $activity, $members);
    }
}
