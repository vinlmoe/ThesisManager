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
use context_module;
use core_text;
use stdClass;

/**
 * Transfert des paramètres entre activités et par fichier.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settingstransfer {
    /** Format du fichier de paramètres exporté. */
    public const SETTINGS_FORMAT = 'mod_these_settings';

    /**
     * Paramètres de l'activité transférables (formulaire de l'activité), avec leur type.
     *
     * @return array champ => PARAM_*
     */
    public static function settings_activity_fields() {
        return [
            'establishmentname' => PARAM_TEXT,
            'establishmentaddress' => PARAM_TEXT,
            'establishmentcity' => PARAM_TEXT,
            'signatoryname' => PARAM_TEXT,
            'signatorytitle' => PARAM_TEXT,
            'defaultlocation' => PARAM_TEXT,
            'minjury' => PARAM_INT,
            'deveemail' => PARAM_EMAIL,
            'libraryemail' => PARAM_EMAIL,
            'universityemail' => PARAM_EMAIL,
            'reminderdays' => PARAM_INT,
        ];
    }

    /**
     * Groupes de paramètres transférables, dans l'ordre de présentation.
     *
     * @return string[]
     */
    public static function settings_groups() {
        return ['activity', 'emails', 'griditems', 'questions', 'doctemplates', 'docimages'];
    }

    /**
     * Paramètres d'une activité, sous une forme transférable (et enregistrable en JSON).
     *
     * @param stdClass $these
     * @param context $context
     * @param string[] $groups Voir these_settings_groups().
     * @return array
     */
    public static function export_settings(stdClass $these, context $context, array $groups) {
        global $DB;

        $data = ['format' => self::SETTINGS_FORMAT, 'version' => 1, 'name' => (string) $these->name, 'groups' => []];
        if (in_array('activity', $groups, true)) {
            $data['groups']['activity'] = [];
            foreach (array_keys(self::settings_activity_fields()) as $field) {
                $data['groups']['activity'][$field] = $these->$field;
            }
        }
        if (in_array('emails', $groups, true)) {
            $data['groups']['emails'] = [];
            foreach ($DB->get_records('these_email_template', ['theseid' => $these->id], 'emailkey') as $record) {
                $data['groups']['emails'][] = ['emailkey' => $record->emailkey, 'subject' => $record->subject,
                    'body' => $record->body];
            }
        }
        if (in_array('griditems', $groups, true)) {
            $data['groups']['griditems'] = [];
            foreach (grid::get_grid_items(doctemplates::local_scope($these)) as $items) {
                foreach ($items as $item) {
                    $data['groups']['griditems'][] = ['itemkey' => $item->itemkey, 'section' => $item->section,
                        'label' => $item->label, 'conditional' => (int) $item->conditional];
                }
            }
        }
        if (in_array('questions', $groups, true)) {
            $data['groups']['questions'] = [];
            foreach (questionnaire::get_questions(doctemplates::local_scope($these)) as $question) {
                $data['groups']['questions'][] = ['qtype' => $question->qtype, 'label' => $question->label,
                    'options' => $question->options, 'required' => (int) $question->required];
            }
        }
        if (in_array('doctemplates', $groups, true)) {
            $data['groups']['doctemplates'] = [];
            foreach ($DB->get_records('these_doctemplate', ['theseid' => $these->id], 'doctype') as $record) {
                $data['groups']['doctemplates'][] = ['doctype' => $record->doctype, 'content' => $record->content];
            }
        }
        if (in_array('docimages', $groups, true)) {
            $data['groups']['docimages'] = [];
            $fs = get_file_storage();
            foreach ($fs->get_area_files($context->id, 'mod_these', 'docimages', 0, 'filename', false) as $file) {
                $data['groups']['docimages'][] = [
                    'filename' => $file->get_filename(),
                    'content' => base64_encode($file->get_content()),
                ];
            }
        }
        return $data;
    }

    /**
     * Lit un fichier de paramètres exporté.
     *
     * @param string $json
     * @return array|null Données (voir these_export_settings()), null si le fichier n'en est pas un.
     */
    public static function parse_settings_file($json) {
        $data = json_decode((string) $json, true);
        if (!is_array($data) || ($data['format'] ?? '') !== self::SETTINGS_FORMAT || !is_array($data['groups'] ?? null)) {
            return null;
        }
        return $data;
    }

    /**
     * Remplace les paramètres choisis d'une activité par ceux transférés. Pour chaque groupe choisi
     * et présent dans les données, les paramètres de l'activité sont remplacés en totalité : un
     * courriel ou un modèle personnalisé dans l'activité mais pas dans la source revient à son texte
     * d'origine. Les éléments inconnus (courriel, document) et les modèles qui ne produisent pas de
     * document sont ignorés et signalés.
     *
     * @param stdClass $these Activité de destination (mise à jour).
     * @param context $context
     * @param array $data Voir these_export_settings().
     * @param string[] $groups Groupes à transférer.
     * @return stdClass {activity (bool), emails, griditems, questions, doctemplates, docimages (nombres),
     *     skipped (string[])}
     */
    public static function import_settings(stdClass $these, context $context, array $data, array $groups) {
        global $DB;

        $result = (object) ['activity' => false, 'emails' => 0, 'griditems' => 0, 'questions' => 0, 'doctemplates' => 0,
            'docimages' => 0, 'skipped' => []];
        $source = $data['groups'];
        $transaction = $DB->start_delegated_transaction();

        if (in_array('activity', $groups, true) && is_array($source['activity'] ?? null)) {
            $update = (object) ['id' => $these->id, 'timemodified' => time()];
            foreach (self::settings_activity_fields() as $field => $type) {
                if (array_key_exists($field, $source['activity'])) {
                    $update->$field = clean_param($source['activity'][$field] ?? '', $type);
                }
            }
            if (isset($update->minjury)) {
                $update->minjury = max(1, (int) $update->minjury);
            }
            if (isset($update->reminderdays)) {
                $update->reminderdays = max(0, (int) $update->reminderdays);
            }
            $DB->update_record('these', $update);
            foreach ((array) $update as $field => $value) {
                $these->$field = $value;
            }
            $result->activity = true;
        }

        if (in_array('emails', $groups, true) && is_array($source['emails'] ?? null)) {
            $DB->delete_records('these_email_template', ['theseid' => $these->id]);
            $definitions = notifications::get_email_definitions();
            foreach ($source['emails'] as $email) {
                $key = (string) ($email['emailkey'] ?? '');
                if (!isset($definitions[$key])) {
                    $result->skipped[] = get_string('transferskippedemail', 'mod_these', s($key));
                    continue;
                }
                $subject = (string) ($email['subject'] ?? '');
                notifications::save_email_template($these->id, $key, $subject, (string) ($email['body'] ?? ''));
                $result->emails++;
            }
        }

        if (in_array('griditems', $groups, true) && is_array($source['griditems'] ?? null) && $source['griditems']) {
            $items = [];
            foreach ($source['griditems'] as $item) {
                $items[] = (object) [
                    'itemkey' => clean_param((string) ($item['itemkey'] ?? ''), PARAM_ALPHANUM),
                    // Longueur de these_griditem.section (install.xml) ; une section inconnue est ramenée par
                    // these_save_grid_items() à la section par défaut.
                    'section' => core_text::substr(clean_param((string) ($item['section'] ?? ''), PARAM_TEXT), 0, 10),
                    'label' => clean_param((string) ($item['label'] ?? ''), PARAM_TEXT),
                    'conditional' => !empty($item['conditional']),
                ];
            }
            $scope = doctemplates::local_scope($these);
            grid::save_grid_items($scope, $items);
            $result->griditems = count(grid::get_grid_items($scope), COUNT_RECURSIVE) - count(grid::grid_sections());
        }

        // Questionnaire : les réponses déjà données aux questions remplacées sont supprimées avec elles.
        if (in_array('questions', $groups, true) && is_array($source['questions'] ?? null)) {
            $scope = doctemplates::local_scope($these);
            foreach (array_keys(questionnaire::get_questions($scope)) as $questionid) {
                questionnaire::delete_question($scope, $questionid);
            }
            foreach ($source['questions'] as $question) {
                $label = clean_param((string) ($question['label'] ?? ''), PARAM_TEXT);
                if (trim($label) === '') {
                    continue;
                }
                questionnaire::save_question($scope, (object) [
                    'qtype' => (string) ($question['qtype'] ?? 'text'),
                    'label' => $label,
                    'options' => clean_param((string) ($question['options'] ?? ''), PARAM_TEXT),
                    'required' => !empty($question['required']),
                ]);
                $result->questions++;
            }
        }

        if (in_array('doctemplates', $groups, true) && is_array($source['doctemplates'] ?? null)) {
            $DB->delete_records('these_doctemplate', ['theseid' => $these->id]);
            $types = doctemplates::doctemplate_types();
            foreach ($source['doctemplates'] as $template) {
                $type = (string) ($template['doctype'] ?? '');
                $content = (string) ($template['content'] ?? '');
                if (!array_key_exists($type, $types)) {
                    $result->skipped[] = get_string('transferskippeddoctemplate', 'mod_these', s($type));
                    continue;
                }
                $error = documentrenderer::doctemplate_error(doctemplates::local_scope($these), $type, $content, $these);
                if ($error !== null) {
                    $result->skipped[] = doctemplates::doctemplate_label($type) . ' : ' . $error;
                    continue;
                }
                doctemplates::set_doctemplate(doctemplates::local_scope($these), $type, $content);
                $result->doctemplates++;
            }
        }

        if (in_array('docimages', $groups, true) && is_array($source['docimages'] ?? null)) {
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_these', 'docimages', 0);
            foreach ($source['docimages'] as $image) {
                $filename = clean_param((string) ($image['filename'] ?? ''), PARAM_FILE);
                $content = base64_decode((string) ($image['content'] ?? ''), true);
                $info = $content !== false ? @getimagesizefromstring($content) : false;
                if (
                    $filename === '' || !$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
                        || $fs->file_exists($context->id, 'mod_these', 'docimages', 0, '/', $filename)
                ) {
                    $result->skipped[] = get_string('transferskippedimage', 'mod_these', s($filename));
                    continue;
                }
                $fs->create_file_from_string([
                    'contextid' => $context->id,
                    'component' => 'mod_these',
                    'filearea' => 'docimages',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $filename,
                ], $content);
                $result->docimages++;
            }
        }

        $transaction->allow_commit();
        return $result;
    }

    /**
     * Autres activités « Gestion des thèses » dont un utilisateur gère les paramètres (DEVE) :
     * sources possibles d'un transfert.
     *
     * @param stdClass $these Activité courante (exclue).
     * @param int $userid
     * @return array id de l'activité => libellé « cours : activité »
     */
    public static function settings_transfer_sources(stdClass $these, $userid) {
        global $DB;

        $sources = [];
        $sql = "SELECT t.id, t.name, c.fullname AS coursename
                  FROM {these} t
                  JOIN {course} c ON c.id = t.course
                 WHERE t.id <> :id
              ORDER BY c.fullname, t.name";
        foreach ($DB->get_records_sql($sql, ['id' => $these->id]) as $record) {
            $cm = get_coursemodule_from_instance('these', $record->id, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $rights = rights::get_rights(context_module::instance($cm->id), null, $userid);
            if ($rights->deve) {
                $sources[$record->id] = format_string($record->coursename) . ' : ' . format_string($record->name);
            }
        }
        return $sources;
    }
}
