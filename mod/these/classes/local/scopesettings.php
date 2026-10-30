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
use moodle_url;
use stdClass;

/**
 * Paramètres du circuit : agents DEVE signataires et agents de la bibliothèque, durée et salles des
 * soutenances, établissement, université, signataires et contrat type de diffusion. Aucun n'est un
 * réglage du site : comme les modèles des documents, ils appartiennent à une portée (voir
 * these_settings_scope()), l'activité « Suivi des thèses » qui suit l'activité, sinon l'activité
 * « Gestion des thèses » elle-même (table these_setting, zone de fichiers « contracttemplate » de la
 * portée).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scopesettings {
    /** @var string[] Paramètres contenant une adresse de courriel. */
    const EMAILS = ['ccememail', 'ucblemail'];

    /** @var array Options du dépôt du modèle de rapport du directeur (document Word). */
    const REPORT_TEMPLATE_OPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['.docx']];

    /**
     * Cache des paramètres lus pendant la requête, par portée et par contexte (vidé entre deux tests).
     *
     * @return \cache
     */
    protected static function cache() {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'mod_these', 'scopesettings');
    }

    /**
     * Paramètres texte et leur valeur par défaut, dans l'ordre du formulaire.
     *
     * @return array nom => valeur par défaut
     */
    public static function text_defaults() {
        return [
            'institution' => '',
            'university' => '',
            'hetopurl' => 'https://www.hetop.eu/hetop/',
            'devename' => '',
            'devetitle' => 'Directrice des Etudes et de la Vie Etudiante',
            'dgtitle' => '',
            'dgname' => '',
            'visaplace' => '',
            'ccemname' => '',
            'ccememail' => '',
            'ucbltitle' => '',
            'ucblname' => '',
            'ucblemail' => '',
        ];
    }

    /**
     * Tous les paramètres et leur valeur par défaut.
     *
     * @return array nom => valeur par défaut
     */
    public static function defaults() {
        return [
            'deveagent' => '',
            'libraryagent' => '',
            'universitypaper' => 1,
            'ccemagent' => '',
            'ucblagent' => '',
            'defenseduration' => 60,
            'mindelayweeks' => 4,
            'defenserooms' => '',
            'weeklydigest' => 1,
        ] + self::text_defaults();
    }

    /**
     * Date de soutenance la plus proche que l'étudiant peut proposer : le délai minimal des paramètres
     * du circuit (en semaines) après le dépôt.
     *
     * @param \context $context Contexte de l'activité.
     * @param int|null $now Instant du dépôt, maintenant par défaut.
     * @return int
     */
    public static function earliest_defense(\context $context, $now = null) {
        $weeks = max(0, (int) self::for_context($context)['mindelayweeks']);
        return (int) ($now ?? time()) + $weeks * WEEKSECS;
    }

    /**
     * Comptes désignés dans un paramètre (agents DEVE signataires, agents de la bibliothèque) :
     * identifiants ou adresses de courriel séparés par des virgules, des points-virgules ou des retours
     * à la ligne (le formulaire enregistre les identifiants des comptes choisis, un par ligne). Les
     * comptes supprimés ou suspendus sont ignorés.
     *
     * @param string $setting
     * @return stdClass[] Comptes, indexés par identifiant, dans l'ordre du paramètre.
     */
    public static function listed_users($setting) {
        global $DB;

        $setting = trim((string) $setting);
        if ($setting === '') {
            return [];
        }
        $key = 'users_' . sha1($setting);
        $users = self::cache()->get($key);
        if ($users === false) {
            $users = [];
            foreach (preg_split('/[\s,;]+/', $setting, -1, PREG_SPLIT_NO_EMPTY) as $value) {
                // Identifiant d'abord (il peut être une adresse), puis adresse de courriel.
                foreach (strpos($value, '@') !== false ? ['username', 'email'] : ['username'] as $field) {
                    $found = $DB->get_records_select(
                        'user',
                        "LOWER($field) = LOWER(?) AND deleted = 0 AND suspended = 0",
                        [$value],
                        'id ASC',
                        '*',
                        0,
                        1
                    );
                    if ($found) {
                        $user = reset($found);
                        $users[$user->id] = $user;
                        break;
                    }
                }
            }
            self::cache()->set($key, $users);
        }
        return $users;
    }

    /**
     * Contextes des activités dont les inscrits peuvent être désignés agents dans une portée :
     * l'activité « Gestion des thèses » elle-même, ou l'activité « Suivi des thèses » et toutes les
     * activités qu'elle suit.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @return context[]
     */
    public static function scope_contexts(stdClass $scope) {
        $cmids = $scope->cmid ? [(int) $scope->cmid] : [];
        if ($scope->shared) {
            // Activités suivies, connues du module qui tient la portée (rappel <module>_these_scope_cmids()).
            $cmids = array_merge($cmids, array_map('intval', (array) component_callback(
                $scope->component,
                'these_scope_cmids',
                [$scope],
                []
            )));
        }
        $contexts = [];
        foreach (array_unique($cmids) as $cmid) {
            if ($context = \context_module::instance($cmid, IGNORE_MISSING)) {
                $contexts[$context->id] = $context;
            }
        }
        return $contexts;
    }

    /**
     * Comptes proposés pour un paramètre d'agents d'une portée, dans les activités de la portée (voir
     * scope_contexts()) :
     * - agents DEVE : les membres de la DEVE, inscrits avec le droit de la DEVE (rôle « Enseignant ») ;
     * - agents de la bibliothèque, délégataires internes du président du CCEM et de la présidence de
     *   l'université : les inscrits qui ne déposent pas de thèse (personnels).
     * Les comptes déjà désignés restent proposés, même s'ils ne sont plus inscrits.
     *
     * @param stdClass $scope
     * @param string $actor 'deve', 'library', 'ccem' ou 'ucbl' (paramètre « <actor>agent »).
     * @return stdClass[] Comptes actifs, indexés par identifiant, triés par nom.
     */
    public static function candidate_users(stdClass $scope, $actor) {
        $settings = self::get_all($scope);
        $users = [];
        foreach (self::scope_contexts($scope) as $context) {
            if ($actor === 'deve') {
                $users += get_enrolled_users($context, 'mod/these:managedeve', 0, 'u.*', null, 0, 0, true);
            } else {
                $students = get_enrolled_users($context, 'mod/these:submit', 0, 'u.id', null, 0, 0, true);
                $users += array_diff_key(get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true), $students);
            }
        }
        $users += self::listed_users($settings[$actor . 'agent']);
        $users = array_filter($users, function ($user) {
            return empty($user->deleted) && empty($user->suspended) && !isguestuser($user);
        });
        $names = array_map('fullname', $users);
        \core_collator::asort($names);
        return array_replace($names, $users);
    }

    /**
     * Agents de la bibliothèque désignés dans les paramètres en vigueur dans un contexte (activité
     * « Gestion des thèses » ou « Suivi des thèses ») : ils ont les droits de la bibliothèque sans rôle
     * à attribuer, et seulement ceux-là (ni DEVE ni paramétrage).
     *
     * @param context $context
     * @return stdClass[] Comptes, indexés par identifiant.
     */
    public static function library_agents(context $context) {
        return self::listed_users(self::for_context($context)['libraryagent'] ?? '');
    }

    /**
     * Indique si un utilisateur est agent de la bibliothèque dans un contexte (voir library_agents()).
     *
     * @param context $context
     * @param int $userid
     * @return bool
     */
    public static function is_library_agent(context $context, $userid) {
        return $userid && isset(self::library_agents($context)[(int) $userid]);
    }

    /**
     * Salles de soutenance proposées dans une portée, une par ligne du paramètre « defenserooms ».
     * Sans salle, le lieu par défaut de l'ancien réglage de l'activité, s'il existe.
     *
     * @param array $settings Paramètres de la portée (voir get_all()).
     * @param string $fallback Lieu par défaut de l'activité « Gestion des thèses ».
     * @return string[] Salles, la première étant proposée par défaut.
     */
    public static function rooms(array $settings, $fallback = '') {
        $rooms = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/\R/', (string) ($settings['defenserooms'] ?? ''))),
            'strlen'
        )));
        if (!$rooms && trim((string) $fallback) !== '') {
            $rooms = [trim((string) $fallback)];
        }
        return $rooms;
    }

    /**
     * Champ « Lieu de soutenance » d'un formulaire : texte libre, avec la liste des salles de la portée
     * proposée à la saisie.
     *
     * @param \MoodleQuickForm $mform
     * @param string[] $rooms Voir rooms().
     * @param string $warning Avertissement affiché sous le champ (HTML, voir defenseconflicts::render_room_conflicts()).
     * @return void
     */
    public static function add_location_field(\MoodleQuickForm $mform, array $rooms, $warning = '') {
        $attributes = ['size' => 64];
        if ($rooms) {
            $attributes['list'] = 'these-defense-rooms';
        }
        $mform->addElement('text', 'defenselocation', get_string('defenselocation', 'mod_these'), $attributes);
        $mform->setType('defenselocation', PARAM_TEXT);
        if ($rooms) {
            $options = implode('', array_map(function ($room) {
                return \html_writer::tag('option', '', ['value' => $room]);
            }, $rooms));
            $mform->addElement(
                'static',
                'defenseroomshelp',
                '',
                \html_writer::tag('datalist', $options, ['id' => 'these-defense-rooms'])
                    . get_string('defenseroomshelp', 'mod_these', implode(' ; ', array_map('s', $rooms)))
            );
        }
        if ($warning !== '') {
            $mform->addElement('static', 'roomconflicts', '', $warning);
        }
    }

    /**
     * Conditions désignant les paramètres d'une portée.
     *
     * @param stdClass $scope
     * @return array
     */
    protected static function conditions(stdClass $scope) {
        return ['theseid' => (int) $scope->theseid, 'synthesisid' => (int) $scope->synthesisid];
    }

    /**
     * Paramètres d'une portée, valeurs par défaut comprises.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @return array nom => valeur
     */
    public static function get_all(stdClass $scope) {
        global $DB;

        $key = 'scope_' . (int) $scope->theseid . '_' . (int) $scope->synthesisid;
        $values = self::cache()->get($key);
        if ($values === false) {
            $values = self::defaults();
            foreach ($DB->get_records('these_setting', self::conditions($scope)) as $record) {
                if (array_key_exists($record->name, $values)) {
                    $values[$record->name] = is_int($values[$record->name]) ? (int) $record->value : (string) $record->value;
                }
            }
            self::cache()->set($key, $values);
        }
        return $values;
    }

    /**
     * Un paramètre d'une portée.
     *
     * @param stdClass $scope
     * @param string $name
     * @return mixed
     */
    public static function get(stdClass $scope, $name) {
        return self::get_all($scope)[$name];
    }

    /**
     * Paramètres en vigueur pour une activité « Gestion des thèses ».
     *
     * @param stdClass $these
     * @return array
     */
    public static function for_these(stdClass $these) {
        return self::get_all(doctemplates::settings_scope($these));
    }

    /**
     * Paramètres en vigueur pour un dossier.
     *
     * @param stdClass $dossier
     * @return array
     */
    public static function for_dossier(stdClass $dossier) {
        global $DB;
        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        return self::for_these($these);
    }

    /**
     * Paramètres en vigueur dans un contexte : celui d'une activité « Gestion des thèses » ou
     * « Suivi des thèses ». Ailleurs (cours, catégorie, site), les valeurs par défaut.
     *
     * @param context $context
     * @return array
     */
    public static function for_context(context $context) {
        global $DB;

        $key = 'context_' . $context->id;
        $values = self::cache()->get($key);
        if ($values === false) {
            $values = null;
            if ($context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, IGNORE_MISSING);
                if ($cm && $cm->modname === 'these' && ($these = $DB->get_record('these', ['id' => $cm->instance]))) {
                    $values = self::for_these($these);
                } else if ($cm && $cm->modname !== 'these' && ($scope = doctemplates::scope_for_cm($cm))) {
                    $values = self::get_all($scope);
                }
            }
            $values = $values ?? self::defaults();
            self::cache()->set($key, $values);
        }
        return $values;
    }

    /**
     * Pages où se tiennent les paramètres partagés d'une portée : l'activité « Gestion des thèses »
     * elle-même, ou celles du module qui tient la portée (rappel <module>_these_scope_urls()).
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @return moodle_url[] {home (accueil de l'activité), settings (paramètres du circuit)}
     */
    public static function scope_urls(stdClass $scope) {
        if (!$scope->shared) {
            return [
                'home' => new moodle_url('/mod/these/view.php', ['id' => $scope->cmid]),
                'settings' => new moodle_url('/mod/these/parameters.php', ['id' => $scope->cmid]),
            ];
        }
        return component_callback($scope->component, 'these_scope_urls', [$scope], []);
    }

    /**
     * Enregistre des paramètres d'une portée ; les autres restent inchangés.
     *
     * @param stdClass $scope
     * @param array $values nom => valeur
     * @return void
     */
    public static function set(stdClass $scope, array $values) {
        global $DB;

        $defaults = self::defaults();
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, $defaults)) {
                continue;
            }
            $value = is_int($defaults[$name]) ? (string) (int) $value : trim((string) $value);
            if ($name === 'defenseduration' && (int) $value < 1) {
                $value = (string) $defaults[$name];
            }
            if ($name === 'mindelayweeks' && (int) $value < 0) {
                $value = '0';
            }
            $conditions = self::conditions($scope) + ['name' => $name];
            if ($id = $DB->get_field('these_setting', 'id', $conditions)) {
                $DB->set_field('these_setting', 'value', $value, ['id' => $id]);
            } else {
                $DB->insert_record('these_setting', (object) ($conditions + ['value' => $value]));
            }
        }
        self::reset_cache();
    }

    /**
     * Supprime les paramètres d'une portée (suppression de l'activité).
     *
     * @param stdClass $scope
     * @return void
     */
    public static function delete(stdClass $scope) {
        global $DB;
        $DB->delete_records('these_setting', self::conditions($scope));
        self::reset_cache();
    }

    /**
     * Oublie les paramètres lus (après un enregistrement, et entre deux tests).
     *
     * @return void
     */
    public static function reset_cache() {
        self::cache()->purge();
    }

    /**
     * Réglages d'une portée à compléter avant de faire tourner le circuit : textes et signataires
     * des documents, contrat type, images des modèles, salles de soutenance, questionnaire. Les
     * agents DEVE signataires, ceux de la bibliothèque et le questionnaire sont facultatifs.
     *
     * @param stdClass $scope
     * @return array[] label, complete (bool), optional (bool), url (moodle_url de la page où le régler, ou null : cette page)
     */
    public static function checklist(stdClass $scope) {
        $values = self::get_all($scope);
        $page = function ($script) use ($scope) {
            if (!$scope->cmid) {
                return null;
            }
            return new \moodle_url('/mod/' . substr($scope->component, 4) . '/' . $script, ['id' => $scope->cmid]);
        };
        $filled = function (array $names) use ($values) {
            foreach ($names as $name) {
                if (trim((string) $values[$name]) === '') {
                    return false;
                }
            }
            return true;
        };
        $images = doctemplates::doctemplate_images($scope);
        $missingimages = array_diff(doctemplates::doctemplate_image_slots(), array_keys($images));
        $items = [
            ['settingscheckinstitution', $filled(['institution', 'university']), false, null],
            ['settingscheckdg', $filled(['dgtitle', 'dgname']), false, null],
            ['settingscheckccem', $filled(['ccemname']), false, null],
            ['settingscheckucbl', $filled(['ucbltitle', 'ucblname']), false, null],
            ['settingscheckdeve', $filled(['devename']), false, null],
            ['settingscheckcontract', self::contract_template($scope) !== null, false, null],
            ['settingscheckrooms', (bool) self::rooms($values), false, null],
            ['settingscheckagents', $filled(['deveagent']), false, null],
            ['settingschecklibraryagents', $filled(['libraryagent']), true, null],
            ['settingscheckquestions', (bool) questionnaire::get_questions($scope), true, $page('questions.php')],
        ];
        $list = [];
        foreach ($items as [$key, $complete, $optional, $url]) {
            $list[] = ['label' => get_string($key, 'mod_these'), 'complete' => $complete, 'optional' => $optional, 'url' => $url];
        }
        $list[] = [
            'label' => $missingimages
                ? get_string('settingscheckimagesmissing', 'mod_these', implode(', ', array_map(function ($slot) {
                    return $slot . '.png';
                }, $missingimages)))
                : get_string('settingscheckimages', 'mod_these'),
            'complete' => !$missingimages,
            'optional' => false,
            'url' => $page('doctemplates.php'),
        ];
        return $list;
    }

    /**
     * Modèle du rapport du directeur (document Word) déposé dans la portée : le directeur le
     * télécharge, le remplit et dépose son rapport (voir report.php).
     *
     * @param stdClass $scope
     * @return \stored_file|null
     */
    public static function report_template(stdClass $scope) {
        if (empty($scope->contextid)) {
            return null;
        }
        $files = get_file_storage()->get_area_files($scope->contextid, $scope->component, 'reporttemplate', 0, 'id', false);
        return reset($files) ?: null;
    }

    /**
     * Contrat type de diffusion électronique (PDF officiel) déposé dans la portée.
     *
     * @param stdClass $scope
     * @return string|null Contenu du PDF.
     */
    public static function contract_template(stdClass $scope) {
        if (empty($scope->contextid)) {
            return null;
        }
        $files = get_file_storage()->get_area_files($scope->contextid, $scope->component, 'contracttemplate', 0, 'id', false);
        $file = reset($files);
        return $file ? $file->get_content() : null;
    }
}
