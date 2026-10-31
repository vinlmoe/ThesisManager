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
use stdClass;
use stored_file;

/**
 * Signatures de l'université (présidence de l'université, CCEM : voir
 * prerequisites::UNIVERSITY_SLOTS). Selon les paramètres, chacun de ces signataires signe :
 * - par ses délégataires internes, depuis « Mes signatures » (voir signatureagent) ;
 * - sinon, si l'université signe tout sur papier, sur les pièces scellées qui lui sont envoyées ;
 * - sinon, en ligne, par le lien de signature reçu par courriel.
 *
 * Pièces sur papier : dès qu'un document n'attend plus que les signatures de l'université, la DEVE le scelle tel quel
 * (zone « universityseal », empreinte SHA-256 à l'historique) et télécharge les pièces scellées pour
 * les envoyer à l'université. Au retour, elle dépose les scans signés (scan.php) : les signatures de
 * l'université sont alors inscrites (méthode « scan ») et le document est scellé définitivement (voir
 * these_seal_document()). Le procès-verbal attend ces scans (voir prerequisites).
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class university {
    /** Zone des pièces scellées pour l'université. */
    const FILEAREA = 'universityseal';

    /**
     * Indique si l'université signe tout sur papier (paramètre « universitypaper ») : aucun lien de
     * signature ne lui est envoyé, les pièces scellées lui sont envoyées et les scans déposés au retour.
     * Sinon, un lien de signature est envoyé par courriel.
     *
     * @param stdClass $these
     * @return bool
     */
    public static function paper_only(stdClass $these) {
        return (bool) scopesettings::for_these($these)['universitypaper'];
    }

    /**
     * Délégataires internes d'un signataire de l'université (paramètres « ccemagent », « ucblagent ») :
     * ils signent à sa place, par délégation, depuis « Mes signatures ».
     *
     * @param stdClass $these
     * @param string $slot
     * @return stdClass[] Comptes, indexés par identifiant.
     */
    public static function agents(stdClass $these, $slot) {
        if (!in_array($slot, prerequisites::UNIVERSITY_SLOTS, true)) {
            return [];
        }
        return scopesettings::listed_users(scopesettings::for_these($these)[$slot . 'agent']);
    }

    /**
     * Emplacements de l'université signés par des délégataires internes.
     *
     * @param stdClass $these
     * @return string[]
     */
    public static function delegated_slots(stdClass $these) {
        return array_values(array_filter(prerequisites::UNIVERSITY_SLOTS, function ($slot) use ($these) {
            return (bool) self::agents($these, $slot);
        }));
    }

    /**
     * Emplacements de l'université signés sur papier : tous ceux sans délégataire, si l'université
     * signe tout sur papier ; aucun sinon.
     *
     * @param stdClass $these
     * @return string[]
     */
    public static function paper_slots(stdClass $these) {
        if (!self::paper_only($these)) {
            return [];
        }
        return array_values(array_diff(prerequisites::UNIVERSITY_SLOTS, self::delegated_slots($these)));
    }

    /**
     * Indique si un signataire reçoit un lien de signature par courriel : pas l'université quand elle
     * signe sur papier ou par ses délégataires internes.
     *
     * @param stdClass $these
     * @param string $slot
     * @return bool
     */
    public static function sends_link(stdClass $these, $slot) {
        return !in_array($slot, prerequisites::UNIVERSITY_SLOTS, true)
            || (!self::paper_only($these) && !self::agents($these, $slot));
    }

    /**
     * Documents qui n'attendent plus que les signatures de l'université sur papier (voir
     * paper_slots()).
     *
     * @param stdClass $dossier
     * @param stdClass|null $these Activité du dossier, déjà lue.
     * @param array|null $signatures Signatures du dossier déjà lues (voir signatures::get_signatures()).
     * @return array doctype => emplacements de l'université encore attendus
     */
    public static function pending(stdClass $dossier, ?stdClass $these = null, ?array $signatures = null) {
        global $DB;

        $these = $these ?? $DB->get_record('these', ['id' => $dossier->theseid]);
        $paper = $these ? self::paper_slots($these) : [];
        if (!$paper) {
            return [];
        }
        $signatures = $signatures ?? signatures::get_signatures($dossier->id);
        $pending = [];
        foreach (array_keys(signatures::document_definitions()) as $doctype) {
            if (!signatures::document_available($dossier, $doctype, $signatures)) {
                continue;
            }
            $university = [];
            foreach (signatures::document_slots($dossier, $doctype) as $slot) {
                if (!empty($signatures[$doctype . ':' . $slot]->timesigned)) {
                    continue;
                }
                if (!in_array($slot, $paper, true)) {
                    // Une autre signature manque encore : pas encore à envoyer.
                    $university = [];
                    break;
                }
                $university[] = $slot;
            }
            if ($university) {
                $pending[$doctype] = $university;
            }
        }
        return $pending;
    }

    /**
     * Pièce scellée pour l'université.
     *
     * @param context $context
     * @param int $dossierid
     * @param string $doctype
     * @return stored_file|null
     */
    public static function get_sealed(context $context, $dossierid, $doctype) {
        $file = get_file_storage()->get_file($context->id, 'mod_these', self::FILEAREA, $dossierid, '/', $doctype . '.pdf');
        return $file ?: null;
    }

    /**
     * Scelle un document qui n'attend plus que l'université : le PDF, avec toutes les signatures déjà
     * apposées, est conservé tel quel et son empreinte inscrite à l'historique.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @param int $userid Compte de la DEVE.
     * @return stored_file|null Pièce scellée, null si le document attend une autre signature.
     */
    public static function seal(stdClass $these, context $context, stdClass $dossier, $doctype, $userid) {
        if ($sealed = self::get_sealed($context, $dossier->id, $doctype)) {
            return $sealed;
        }
        signatures::refresh_signatures($context, $dossier);
        if (!isset(self::pending($dossier)[$doctype])) {
            return null;
        }
        $pdf = documentrenderer::render_document_pdf($these, $context, $dossier, $doctype);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => self::FILEAREA,
            'itemid' => $dossier->id,
            'filepath' => '/',
            'filename' => $doctype . '.pdf',
        ], $pdf);
        dossiers::log(
            $dossier->id,
            $userid,
            'universitysealed',
            $dossier->status,
            $dossier->status,
            signatures::document_label($doctype) . ' — SHA-256 ' . hash('sha256', $pdf)
        );
        return $file;
    }

    /**
     * Retire la pièce scellée d'un document dont une signature disparaît : elle sera scellée de
     * nouveau.
     *
     * @param context $context
     * @param int $dossierid
     * @param string $doctype
     * @return void
     */
    public static function unseal(context $context, $dossierid, $doctype) {
        if ($file = self::get_sealed($context, $dossierid, $doctype)) {
            $file->delete();
        }
    }

    /**
     * Scelle les documents qui n'attendent plus que l'université et les réunit dans une archive à
     * envoyer, inscrite à l'historique.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param int $userid
     * @return array [chemin de l'archive, nom de l'archive], ou [null, ''] sans pièce à envoyer.
     */
    public static function package(stdClass $these, context $context, stdClass $dossier, $userid) {
        $files = [];
        $labels = [];
        foreach (array_keys(self::pending($dossier)) as $doctype) {
            $file = self::seal($these, $context, $dossier, $doctype, $userid);
            if ($file) {
                // Pièce scellée : la date du scellement s'ajoute à son nom.
                $files[documents::document_filename($doctype, '', (int) $file->get_timecreated())] = $file;
                $labels[] = signatures::document_label($doctype);
            }
        }
        if (!$files) {
            return [null, ''];
        }
        $zipname = clean_filename('universite-' . ($dossier->devenumber ?: $dossier->id) . '.zip');
        $zippath = make_request_directory() . '/' . $zipname;
        get_file_packer('application/zip')->archive_to_pathname($files, $zippath);
        dossiers::log($dossier->id, $userid, 'universitydownloaded', $dossier->status, $dossier->status, implode(', ', $labels));
        return [$zippath, $zipname];
    }

    /**
     * Rubrique « Pièces pour l'université » de la DEVE (documents de soutenance, procès-verbal) :
     * documents qui n'attendent plus que l'université, date de leur scellement, bouton de
     * téléchargement des pièces scellées et, une fois envoyées, rappel du dépôt des scans au retour.
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @return string HTML, vide si aucun document n'attend l'université.
     */
    public static function render_section(stdClass $cm, context $context, stdClass $dossier) {
        global $OUTPUT, $PAGE;

        $pending = self::pending($dossier);
        if (!$pending) {
            return '';
        }
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $items = [];
        $sealed = 0;
        foreach ($pending as $doctype => $slots) {
            $waiting = implode(', ', array_map('these_slot_label', $slots));
            $item = signatures::document_label($doctype) . ' — ' . get_string('universitywaitingfor', 'mod_these', $waiting);
            if ($file = self::get_sealed($context, $dossier->id, $doctype)) {
                $item .= ' — ' . get_string('universitysealedon', 'mod_these', userdate($file->get_timecreated(), $dateformat));
                $sealed++;
            }
            $items[] = $item;
        }
        $renderer = $PAGE->get_renderer('mod_these');
        $params = ['id' => $cm->id, 'dossierid' => $dossier->id];
        $html = $OUTPUT->heading(get_string('universitypieces', 'mod_these'), 3, 'mt-4');
        $html .= $renderer->render(\mod_these\output\message::help(get_string('universitypieces_help', 'mod_these')));
        $html .= $renderer->render(new \mod_these\output\bullet_list($items));
        $html .= $OUTPUT->single_button(
            new \moodle_url('/mod/these/documents.php', $params + ['action' => 'university', 'sesskey' => sesskey()]),
            get_string($sealed ? 'universitydownloadagain' : 'universitydownload', 'mod_these')
        );
        if ($sealed) {
            $return = get_string('universityreturn', 'mod_these');
            $html .= $renderer->render(new \mod_these\output\message($return, 'alert alert-info mt-2'));
            $html .= $renderer->render(new \mod_these\output\link(
                new \moodle_url('/mod/these/scan.php', $params),
                get_string('universityaddscan', 'mod_these'),
                'btn btn-primary mb-3'
            ));
        }
        return $html;
    }
}
