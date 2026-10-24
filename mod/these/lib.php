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

/**
 * Library of interface functions and constants for mod_these.
 *
 * Le circuit d'un dossier de thèse est porté par un statut unique (these_dossier.status), dont les
 * valeurs sont espacées de dix en dix pour laisser la place à une étape intermédiaire future sans
 * renuméroter les dossiers existants. Les valeurs suivent l'ordre du circuit : un dossier dont le
 * statut est plus grand est plus avancé, à l'exception des deux statuts « renvoyé » (qui se
 * trouvent dans leur phase) et du statut « abandonné » (négatif).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Phase 1 : dépôt et validation du sujet.

/** Sujet en préparation : l'étudiant a enregistré un brouillon, rien n'est encore soumis. */
define('THESE_STATUS_DRAFT', 0);
/** Sujet soumis par l'étudiant, en attente du directeur de thèse (phase 1, étape 2). */
define('THESE_STATUS_SUBJECT_SUBMITTED', 10);
/** Sujet renvoyé à l'étudiant avec commentaires, à corriger puis à soumettre de nouveau. */
define('THESE_STATUS_SUBJECT_RETURNED', 15);
/** Sujet validé par le directeur, en attente d'enregistrement par la DEVE (phase 1, étape 3). */
define('THESE_STATUS_SUBJECT_APPROVED', 20);
/** Sujet enregistré par la DEVE (numéro attribué), en attente de la bibliothèque (phase 1, étape 4). */
define('THESE_STATUS_SUBJECT_REGISTERED', 30);
/**
 * Sujet enregistré par la bibliothèque : dossier ouvert et actif, « dormant » jusqu'au dépôt des
 * documents de soutenance, potentiellement pendant plusieurs mois ou années.
 */
define('THESE_STATUS_ACTIVE', 40);

// Phase 2 : soutenance.

/** Manuscrit et proposition de soutenance déposés, en attente du directeur (phase 2, étape 2). */
define('THESE_STATUS_DEFENSE_SUBMITTED', 50);
/** Dossier de soutenance renvoyé à l'étudiant avec commentaires. */
define('THESE_STATUS_DEFENSE_RETURNED', 55);
/** Soutenance validée par le directeur, rapport joint, en attente de la DEVE (phase 2, étape 3). */
define('THESE_STATUS_DEFENSE_APPROVED', 60);
/** Dossier complet validé par la DEVE, en attente de la bibliothèque (phase 2, étape 4). */
define('THESE_STATUS_DEFENSE_VALIDATED', 70);
/** Manuscrit déclaré conforme : documents de soutenance à générer et transmettre (phase 2, étape 5). */
define('THESE_STATUS_CONFORM', 80);
/** Documents transmis au jury et à l'université : soutenance programmée, PV attendu (étape 6). */
define('THESE_STATUS_SCHEDULED', 90);
/** Procès-verbal déposé : dossier clôturé. */
define('THESE_STATUS_CLOSED', 100);

/** Dossier abandonné (clos par la DEVE avant son terme) : la DEVE peut le rouvrir. */
define('THESE_STATUS_CANCELLED', -1);

/** Résultat de la soutenance : pas encore connu. */
define('THESE_RESULT_NONE', 0);
/** Résultat de la soutenance : thèse admise. */
define('THESE_RESULT_ADMITTED', 1);
/** Résultat de la soutenance : thèse ajournée. */
define('THESE_RESULT_ADJOURNED', 2);

/** Nombre de lignes par page pour les listes paginées. */
define('THESE_LIST_PERPAGE', 40);

/**
 * Zones de fichiers rattachées à un dossier (l'itemid étant toujours l'identifiant du dossier,
 * these_dossier.id), et qui peut les consulter en plus de l'étudiant, de son directeur, de la DEVE
 * et de la bibliothèque. Voir these_can_view_filearea().
 */
define('THESE_FILEAREAS', ['subjectdocs', 'manuscript', 'defensedocs', 'directorreport', 'generated', 'pv']);

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function these_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : 'administration';
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_these into the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_these_mod_form|null $mform
 * @return int New instance id.
 */
function these_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    return $DB->insert_record('these', $moduleinstance);
}

/**
 * Updates an instance of mod_these in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_these_mod_form|null $mform
 * @return bool True on success.
 */
function these_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('these', $moduleinstance);
}

/**
 * Removes an instance of mod_these from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function these_delete_instance($id) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/these/locallib.php');

    if (!$DB->get_record('these', ['id' => $id])) {
        return false;
    }

    // Dossiers et tout ce qui leur est rattaché (jury, historique). Les fichiers sont laissés à
    // Moodle, qui supprime le contexte du module et ses zones de fichiers juste après.
    these_delete_dossiers($DB->get_fieldset_select('these_dossier', 'id', 'theseid = ?', [$id]));
    $DB->delete_records('these_email_template', ['theseid' => $id]);
    $DB->delete_records('these_doctemplate', ['theseid' => $id]);
    $DB->delete_records('these_griditem', ['theseid' => $id]);
    $DB->delete_records('these_question', ['theseid' => $id]);
    $DB->delete_records('these', ['id' => $id]);

    return true;
}

/**
 * Ajoute au formulaire de réinitialisation du cours l'option propre à l'activité.
 *
 * Contrairement à la plupart des activités, elle n'est pas cochée par défaut : un dossier de thèse
 * reste ouvert plusieurs années, bien au-delà d'une année universitaire, et le cours qui l'héberge
 * n'a en principe pas vocation à être réinitialisé chaque année.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function these_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'theseheader', get_string('modulenameplural', 'mod_these'));
    $mform->addElement('advcheckbox', 'reset_these_dossiers', get_string('resetdossiers', 'mod_these'));
    $mform->addHelpButton('reset_these_dossiers', 'resetdossiers', 'mod_these');
}

/**
 * Valeurs par défaut des options de réinitialisation : voir these_reset_course_form_definition().
 *
 * @param stdClass $course
 * @return array
 */
function these_reset_course_form_defaults($course) {
    return ['reset_these_dossiers' => 0];
}

/**
 * Réinitialise les dossiers de thèse de toutes les activités du cours. La configuration de
 * l'activité (établissement, adresses de service, modèles de courriels) est conservée.
 *
 * @param stdClass $data Données du formulaire de réinitialisation.
 * @return array Statut par élément, au format attendu par reset_course_userdata().
 */
function these_reset_userdata($data) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/these/locallib.php');

    $status = [];
    if (empty($data->reset_these_dossiers)) {
        return $status;
    }

    $instances = $DB->get_records('these', ['course' => $data->courseid]);
    foreach ($instances as $these) {
        $cm = get_coursemodule_from_instance('these', $these->id, $data->courseid, false, IGNORE_MISSING);
        $context = $cm ? context_module::instance($cm->id) : null;
        these_delete_dossiers($DB->get_fieldset_select('these_dossier', 'id', 'theseid = ?', [$these->id]), $context);
    }
    $status[] = [
        'component' => get_string('modulenameplural', 'mod_these'),
        'item' => get_string('resetdossiers', 'mod_these'),
        'error' => false,
    ];
    return $status;
}

/**
 * Returns a small object with summary information about what a user has done
 * with a given particular instance of this module.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info|stdClass $mod
 * @param stdClass $these
 * @return stdClass|null
 */
function these_user_outline($course, $user, $mod, $these) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/these/locallib.php');

    $dossier = $DB->get_record('these_dossier', ['theseid' => $these->id, 'userid' => $user->id]);
    if (!$dossier) {
        return null;
    }
    $result = new stdClass();
    $result->info = these_status_label($dossier->status);
    $result->time = $dossier->timemodified;
    return $result;
}

/**
 * Sert les fichiers des dossiers de thèse.
 *
 * Chaque zone a ses propres règles d'accès (voir these_can_view_filearea()) : le rapport du
 * directeur, notamment, n'est pas communiqué à l'étudiant.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool False si le fichier n'est pas trouvé ; sinon le fichier est envoyé.
 */
function these_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/mod/these/locallib.php');

    if ($context->contextlevel != CONTEXT_MODULE || !in_array($filearea, THESE_FILEAREAS, true)) {
        return false;
    }

    require_login($course, true, $cm);
    these_require_view($context);

    $dossierid = (int) array_shift($args);
    $dossier = $DB->get_record('these_dossier', ['id' => $dossierid, 'theseid' => $cm->instance]);
    if (!$dossier) {
        return false;
    }
    $rights = these_get_rights($context, $dossier, $USER->id);
    if (!these_can_view_filearea($filearea, $rights)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_these', $filearea, $dossierid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true, $options);
}
