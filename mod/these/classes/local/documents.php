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
 * Production et transmission des documents de soutenance.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documents {
    /**
     * Données communes aux documents générés pour un dossier.
     *
     * @param stdClass $these
     * @param stdClass $dossier
     * @return array
     */
    public static function document_data(stdClass $these, stdClass $dossier) {
        global $DB;

        $student = $DB->get_record('user', ['id' => $dossier->userid]);
        $director = $dossier->directorid ? $DB->get_record('user', ['id' => $dossier->directorid]) : null;
        $date = (int) ($dossier->defensedate ?: $dossier->proposeddate);

        return [
            'establishmentname' => (string) $these->establishmentname,
            'establishmentaddress' => (string) $these->establishmentaddress,
            'city' => (string) $these->establishmentcity,
            'signatoryname' => (string) $these->signatoryname,
            'signatorytitle' => (string) $these->signatorytitle,
            'student' => $student ? fullname($student) : '',
            'director' => $director ? fullname($director) : '',
            'codirector' => (string) $dossier->codirectorname,
            'title' => (string) $dossier->title,
            'titleen' => (string) $dossier->titleen,
            'summary' => (string) $dossier->summary,
            'keywords' => (string) $dossier->keywords,
            'devenumber' => (string) $dossier->devenumber,
            'libraryref' => (string) $dossier->libraryref,
            'date' => $date ? userdate($date, get_string('strftimedaydate', 'langconfig')) : '-',
            'time' => $date ? userdate($date, get_string('strftimetime', 'langconfig')) : '-',
            'location' => (string) $dossier->defenselocation,
            'today' => userdate(time(), get_string('strftimedate', 'langconfig')),
            'jury' => these_get_jury($dossier->id),
            'result' => these_result_label($dossier->defenseresult),
            'mention' => these_mention_label($dossier->mention),
        ];
    }

    /**
     * Nom de fichier sûr pour un document généré.
     *
     * @param string $prefix
     * @param string $name
     * @return string
     */
    public static function document_filename($prefix, $name = '') {
        $slug = $name !== '' ? '-' . trim(preg_replace('/[^a-z0-9]+/', '-', core_text::strtolower(
            core_text::specialtoascii($name)
        )), '-') : '';
        return clean_filename($prefix . $slug . '.pdf');
    }

    /**
     * Génère les documents de soutenance (phase 2, étape 5) et les range dans la zone « generated »,
     * à la place des précédents : une convocation pour l'étudiant, une par membre du jury, la feuille
     * de jury (émargement) et le procès-verbal à compléter et signer le jour de la soutenance.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param int $userid
     * @return stored_file[]
     */
    public static function generate_documents(stdClass $these, context $context, stdClass $dossier, $userid) {
        global $DB;

        $data = these_document_context($these, $context, $dossier, null);
        $documents = [];
        $documents[these_document_filename('convocation-etudiant')] = these_render_doctemplate($these, 'convocation', $data);
        foreach (these_get_jury($dossier->id) as $index => $member) {
            $documents[these_document_filename('convocation-jury-' . ($index + 1), $member->fullname)] =
                these_render_doctemplate($these, 'convocation', these_document_context($these, $context, $dossier, null, $member));
        }
        $documents[these_document_filename('feuille-de-jury')] = these_render_doctemplate($these, 'jurysheet', $data);
        $documents[these_document_filename('proces-verbal')] = these_render_doctemplate($these, 'minutes', $data);
        // Documents de l'établissement, dans leur état de signature à la génération. Les documents
        // réservés au personnel (rapport du directeur, grille) n'entrent pas dans cette zone, que
        // l'étudiant consulte : ils ne sont joints qu'à l'archive transmise à l'université.
        foreach (these_document_definitions() as $doctype => $definition) {
            if (!$definition['staffonly'] && these_document_available($dossier, $doctype)) {
                $documents[these_document_filename($doctype)] = these_signed_document_pdf($these, $context, $dossier, $doctype);
            }
        }

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_these', 'generated', $dossier->id);
        $files = [];
        foreach ($documents as $filename => $content) {
            $files[] = $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_these',
                'filearea' => 'generated',
                'itemid' => $dossier->id,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $userid,
            ], $content);
        }

        $now = time();
        $DB->update_record('these_dossier', (object) ['id' => $dossier->id, 'documentstime' => $now, 'timemodified' => $now]);
        $dossier->documentstime = $now;
        these_log($dossier->id, $userid, 'documentsgenerated', $dossier->status, $dossier->status);
        return $files;
    }

    /**
     * Fichiers de l'archive transmise à l'université : les documents générés, puis les documents de
     * l'établissement réservés au personnel (rapport du directeur, grille de conformité), rendus à
     * la demande dans leur état de signature courant.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @return array nom de fichier => stored_file ou chemin d'un fichier temporaire
     */
    public static function archive_files(stdClass $these, context $context, stdClass $dossier) {
        $files = [];
        foreach (these_get_files($context, 'generated', $dossier->id) as $file) {
            $files[$file->get_filename()] = $file;
        }
        $directory = null;
        foreach (these_document_definitions() as $doctype => $definition) {
            if (!$definition['staffonly'] || !these_document_available($dossier, $doctype)) {
                continue;
            }
            $directory = $directory ?? make_request_directory();
            $filename = these_document_filename($doctype);
            $path = $directory . '/' . $filename;
            file_put_contents($path, these_signed_document_pdf($these, $context, $dossier, $doctype));
            $files[$filename] = $path;
        }
        return $files;
    }

    /**
     * Transmet les documents générés : à chaque membre du jury qui a une adresse, sa convocation ; à
     * l'étudiant, la sienne ; à l'adresse universitaire de l'activité, l'ensemble des documents en
     * archive, documents réservés au personnel compris. La première transmission fait passer le
     * dossier au statut « soutenance programmée ».
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param int $userid
     * @return stdClass {sent, failed, missing (noms des membres du jury sans adresse)}
     */
    public static function transmit_documents(stdClass $these, stdClass $cm, context $context, stdClass $dossier, $userid) {
        return these_dossier_operation($dossier, function () use ($these, $cm, $context, $dossier, $userid) {
            global $DB;

            $files = [];
            foreach (these_get_files($context, 'generated', $dossier->id) as $file) {
                $files[$file->get_filename()] = $file;
            }
            $data = these_document_data($these, $dossier);
            $result = (object) ['sent' => 0, 'failed' => 0, 'missing' => []];
            $basevars = [
                'student' => $data['student'],
                'title' => format_string($dossier->title),
                'date' => $data['date'] . ' ' . $data['time'],
                'location' => $data['location'],
            ];

            $send = function ($recipient, $name, $filename) use ($these, $dossier, $files, $basevars, $result) {
                $attachment = '';
                if (isset($files[$filename])) {
                    $attachment = $files[$filename]->copy_content_to_temp('mod_these');
                }
                $sent = these_send_email(
                    $these,
                    'convocation',
                    [$recipient],
                    array_merge($basevars, ['name' => $name]),
                    $attachment ?: '',
                    $attachment ? $filename : '',
                    $dossier
                );
                $sent ? $result->sent++ : $result->failed++;
            };

            $student = $DB->get_record('user', ['id' => $dossier->userid, 'deleted' => 0]);
            if ($student) {
                $send($student, fullname($student), these_document_filename('convocation-etudiant'));
            }
            foreach ($data['jury'] as $index => $member) {
                if ($member->email === '') {
                    $result->missing[] = $member->fullname;
                    continue;
                }
                $send(
                    these_make_external_user($member->email, $member->fullname),
                    $member->fullname,
                    these_document_filename('convocation-jury-' . ($index + 1), $member->fullname)
                );
            }

            $universityemail = trim((string) $these->universityemail);
            $archive = $universityemail !== '' ? self::archive_files($these, $context, $dossier) : [];
            if ($archive) {
                $zipname = clean_filename('soutenance-' . ($dossier->devenumber ?: $dossier->id) . '.zip');
                $zippath = make_request_directory() . '/' . $zipname;
                $packer = get_file_packer('application/zip');
                $packer->archive_to_pathname($archive, $zippath);
                $sent = these_send_email(
                    $these,
                    'university',
                    [these_make_external_user($universityemail)],
                    array_merge($basevars, ['devenumber' => (string) $dossier->devenumber]),
                    $zippath,
                    $zipname,
                    $dossier
                );
                $sent ? $result->sent++ : $result->failed++;
            }

            $fields = ['senttime' => time()];
            $remark = get_string('transmitsummary', 'mod_these', $result);
            if ((int) $dossier->status === THESE_STATUS_CONFORM) {
                these_set_status($dossier, THESE_STATUS_SCHEDULED, $userid, 'documentssent', $remark, $fields);
            } else {
                these_set_status($dossier, $dossier->status, $userid, 'documentsresent', $remark, $fields);
            }
            return $result;
        });
    }
}
