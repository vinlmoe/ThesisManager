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
use mod_these\output;
use stdClass;

/**
 * Liens de retour des pages des deux modules.
 *
 * Chaque page affiche un lien de retour vers la page d'où l'utilisateur vient. Les pages d'origine
 * (tableau de bord ou planning de l'activité « Gestion des thèses », accueil, recherche ou planning
 * d'une activité « Suivi des thèses » qui suit cette activité) se mémorisent dans la session (voir
 * remember()) ; venu du suivi des thèses, on y revient donc depuis toutes les pages de l'activité :
 * - une page d'un dossier (validation, documents, signatures...) revient au dossier quand il a été
 *   ouvert d'abord, sinon (action ouverte directement depuis « À traiter », suivi des signatures...)
 *   à la page d'origine ;
 * - un dossier revient à la page d'origine ;
 * - les autres pages de l'activité (création, import, paramètres...) reviennent à la page d'origine ;
 * - sans origine valable pour l'activité (lien d'un courriel), à l'accueil de l'activité : le tableau
 *   de bord du personnel, le dossier de l'étudiant ;
 * - les pages d'accueil reviennent au cours ;
 * - les pages communes à toutes les activités (Mes signatures, accès d'un membre du jury connecté)
 *   reviennent à la dernière page d'origine, à défaut au tableau de bord Moodle.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backlinks {
    /** Clé de la session où est mémorisée la page d'origine. */
    const SESSIONKEY = 'mod_these_origin';
    /** Clé de la session où est mémorisé le dossier consulté depuis la page d'origine. */
    const DOSSIERKEY = 'mod_these_origin_dossier';

    /**
     * Mémorise la page d'origine courante : une page d'accueil depuis laquelle l'utilisateur ouvre des
     * dossiers (tableau de bord, planning, suivi des thèses, recherche).
     *
     * @param moodle_url $url Adresse de la page, filtres compris.
     * @param string $label Nom de la page (texte brut).
     * @param int[] $theseids Activités « Gestion des thèses » (id d'instance) dont la page présente les dossiers.
     * @return void
     */
    public static function remember(moodle_url $url, $label, array $theseids) {
        global $SESSION;

        $SESSION->{self::SESSIONKEY} = [
            'url' => $url->out_as_local_url(false),
            'label' => (string) $label,
            'theseids' => array_values(array_map('intval', $theseids)),
        ];
        // De retour sur une page d'origine : aucun dossier n'est plus ouvert depuis elle.
        unset($SESSION->{self::DOSSIERKEY});
    }

    /**
     * Mémorise le dossier affiché : ses pages (validation, documents...) y reviennent ensuite.
     *
     * @param int $dossierid
     * @return void
     */
    public static function dossier_viewed($dossierid) {
        global $SESSION;

        $SESSION->{self::DOSSIERKEY} = (int) $dossierid;
    }

    /**
     * Page d'origine mémorisée.
     *
     * @param int|null $theseid Activité dont la page d'origine doit présenter les dossiers (null : toute origine).
     * @return stdClass|null {url, label}, null sans origine (ou sans origine valable pour l'activité).
     */
    public static function origin($theseid = null) {
        global $SESSION;

        $origin = $SESSION->{self::SESSIONKEY} ?? null;
        if (!is_array($origin) || empty($origin['url'])) {
            return null;
        }
        if ($theseid !== null && !in_array((int) $theseid, $origin['theseids'] ?? [], true)) {
            return null;
        }
        try {
            return self::target(new moodle_url($origin['url']), (string) $origin['label']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cible d'un lien de retour.
     *
     * @param moodle_url $url
     * @param string $label Nom de la page (texte brut).
     * @return stdClass {url, label}
     */
    public static function target(moodle_url $url, $label) {
        return (object) ['url' => $url, 'label' => (string) $label];
    }

    /**
     * Accueil de l'activité « Gestion des thèses » pour l'utilisateur : le tableau de bord pour le
     * personnel (directeurs, DEVE, bibliothèque), son dossier pour l'étudiant.
     *
     * @param stdClass $cm
     * @param context $context
     * @return stdClass {url, label}
     */
    public static function activity_home(stdClass $cm, context $context) {
        global $USER;

        $rights = rights::get_rights($context, null, $USER->id);
        if ($rights->viewall || has_capability('mod/these:direct', $context)) {
            return self::target(
                new moodle_url('/mod/these/dashboard.php', ['id' => $cm->id]),
                get_string('dashboard', 'mod_these')
            );
        }
        return self::student_dossier($cm);
    }

    /**
     * Dossier de l'étudiant connecté, présenté sur la page d'accueil de l'activité.
     *
     * @param stdClass $cm
     * @return stdClass {url, label}
     */
    public static function student_dossier(stdClass $cm) {
        return self::target(new moodle_url('/mod/these/view.php', ['id' => $cm->id]), get_string('mydossier', 'mod_these'));
    }

    /**
     * Retour d'un dossier : la page d'où il a été ouvert si elle présente les dossiers de l'activité,
     * sinon l'accueil de l'activité.
     *
     * @param stdClass $cm
     * @param context $context
     * @return stdClass {url, label}
     */
    public static function from_dossier(stdClass $cm, context $context) {
        return self::from_activity($cm, $context);
    }

    /**
     * Retour d'une page de l'activité (création, import, export, paramètres, annuaire...) : la page
     * d'origine si elle présente les dossiers de l'activité (suivi des thèses compris), sinon
     * l'accueil de l'activité.
     *
     * @param stdClass $cm
     * @param context $context
     * @return stdClass {url, label}
     */
    public static function from_activity(stdClass $cm, context $context) {
        return self::origin((int) $cm->instance) ?? self::activity_home($cm, $context);
    }

    /**
     * Retour d'une page d'un dossier : le dossier s'il a été ouvert depuis la page d'origine, sinon
     * cette page (action ouverte directement depuis « À traiter » ou le suivi des signatures), à
     * défaut le dossier. Pour l'étudiant, son dossier sur la page d'accueil.
     *
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param stdClass|null $rights Voir these_get_rights() ; null : page du personnel.
     * @return stdClass {url, label}
     */
    public static function to_dossier(stdClass $cm, stdClass $dossier, ?stdClass $rights = null) {
        global $SESSION;

        if ($rights && $rights->student) {
            return self::student_dossier($cm);
        }
        $origin = self::origin((int) $cm->instance);
        if ($origin && (int) ($SESSION->{self::DOSSIERKEY} ?? 0) !== (int) $dossier->id) {
            return $origin;
        }
        return self::target(
            new moodle_url('/mod/these/dossier.php', ['id' => $cm->id, 'dossierid' => $dossier->id]),
            get_string('dossier', 'mod_these')
        );
    }

    /**
     * Retour d'une page d'accueil : le cours.
     *
     * @param int $courseid
     * @return stdClass {url, label}
     */
    public static function course($courseid) {
        return self::target(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('course'));
    }

    /**
     * Retour d'une page commune à toutes les activités : la dernière page d'origine, à défaut le
     * tableau de bord Moodle.
     *
     * @return stdClass {url, label}
     */
    public static function from_global() {
        return self::origin() ?? self::target(new moodle_url('/my/'), get_string('myhome'));
    }

    /**
     * Lien de retour.
     *
     * @param stdClass $target Voir target().
     * @return string HTML
     */
    public static function render(stdClass $target) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\link(
            $target->url,
            get_string('backto', 'mod_these', $target->label),
            'these-back',
            '',
            'mb-2'
        ));
    }
}
