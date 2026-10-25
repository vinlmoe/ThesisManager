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

use mod_these\output;
use coding_exception;
use context;
use html_writer;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Documents officiels, signatures et scellés.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signatures {
    /**
     * Documents de l'établissement produits par le module, et qui les signe.
     *
     * - subject : formulaire de dépôt de sujet (étudiant, directeur, directeur extérieur éventuel,
     *   visa de la DEVE) ;
     * - bordereau : bordereau de thèse d'exercice vétérinaire (ABES), signé par l'étudiant ;
     * - report : rapport du directeur ; grid : grille de conformité, renseignée par le directeur ;
     * - agreement : fiche d'agréments administratif (DEVE) et scientifique (directeur, et directeur
     *   extérieur éventuel), visée par la direction générale (représentée par la DEVE) et le CCEM ;
     * - signaletique : fiche signalétique de l'étudiant, signée par le président du jury, la
     *   direction générale et le CCEM ;
     * - contract : contrat de diffusion électronique, signé par l'auteur, visé par le directeur, et
     *   signé par la direction générale et la présidence de l'université ;
     * - conformity : attestation de conformité du manuscrit, délivrée par la bibliothèque ;
     * - conclusions : conclusions de la thèse, signées par le directeur, le président du jury, la
     *   direction générale et le CCEM.
     *
     * « staffonly » : document non communiqué à l'étudiant (voir these_can_view_document()).
     *
     * @return array doctype => ['slots' => string[], 'staffonly' => bool]
     */
    public static function document_definitions() {
        return [
            'subject' => ['slots' => ['student', 'director', 'extdirector', 'deve'], 'staffonly' => false],
            'bordereau' => ['slots' => ['student'], 'staffonly' => false],
            'report' => ['slots' => ['director'], 'staffonly' => true],
            'grid' => ['slots' => ['director'], 'staffonly' => true],
            'agreement' => ['slots' => ['deve', 'director', 'extdirector', 'dgrep', 'ccem'], 'staffonly' => false],
            'signaletique' => ['slots' => ['president', 'dg', 'ccem'], 'staffonly' => false],
            'contract' => ['slots' => ['student', 'director', 'dg', 'ucbl'], 'staffonly' => false],
            'conformity' => ['slots' => ['library'], 'staffonly' => false],
            'conclusions' => ['slots' => ['director', 'president', 'dg', 'ccem'], 'staffonly' => false],
        ];
    }

    /**
     * Emplacements de signature effectifs d'un document pour un dossier : le directeur extérieur
     * n'est attendu que si le dossier en déclare un.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return string[]
     */
    public static function document_slots(stdClass $dossier, $doctype) {
        $slots = these_document_definitions()[$doctype]['slots'];
        if (empty($dossier->externaldirector)) {
            $slots = array_values(array_diff($slots, ['extdirector']));
        }
        return $slots;
    }

    /**
     * Emplacements signés par un signataire sans compte, invité par un lien à jeton.
     *
     * @return string[]
     */
    public static function external_slots() {
        return ['extdirector', 'president', 'dg', 'ccem', 'ucbl'];
    }

    /**
     * Libellé d'un document.
     *
     * @param string $doctype
     * @return string
     */
    public static function document_label($doctype) {
        return get_string('doc' . $doctype, 'mod_these');
    }

    /**
     * Libellé d'un emplacement de signature.
     *
     * @param string $slot
     * @return string
     */
    public static function slot_label($slot) {
        return get_string('slot' . $slot, 'mod_these');
    }

    /**
     * Contenu d'un document couvert par les signatures : toute modification de ces données après
     * signature invalide les signatures du document (voir these_refresh_signatures()). Les données
     * purement administratives (date de soutenance, numéro DEVE pour le formulaire de sujet) n'en
     * font pas partie, pour qu'un report de date n'oblige pas à tout faire signer de nouveau.
     *
     * Un document rempli en plusieurs temps n'engage chaque signataire que sur sa partie : sur la
     * fiche d'agréments, le directeur signe l'agrément scientifique avant que la DEVE ne renseigne
     * l'agrément administratif, qui ne doit donc pas invalider sa signature.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @param string $slot Emplacement de signature concerné, '' pour le document entier.
     * @return array
     */
    public static function document_content(stdClass $dossier, $doctype, $slot = '') {
        $common = ['userid' => (int) $dossier->userid, 'title' => (string) $dossier->title];
        switch ($doctype) {
            case 'subject':
                return $common + [
                    'confidential' => (int) ($dossier->confidential ?? 0),
                    'studyyear' => (string) ($dossier->studyyear ?? ''),
                    'keywords' => (string) $dossier->keywords,
                    'directorid' => (int) $dossier->directorid,
                    'directortitle' => (string) ($dossier->directortitle ?? ''),
                    'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                    'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
                    'extdirectorgrade' => (string) ($dossier->extdirectorgrade ?? ''),
                    'extdirectorinstitution' => (string) ($dossier->extdirectorinstitution ?? ''),
                    'extdirectoremail' => (string) ($dossier->extdirectoremail ?? ''),
                    'commitments' => (int) ($dossier->commitments ?? 0),
                ];
            case 'bordereau':
                return $common + ['titleen' => (string) $dossier->titleen, 'bordereau' => these_get_bordereau($dossier)];
            case 'report':
                return $common + ['reporttext' => (string) ($dossier->reporttext ?? ''),
                    'reportfavorable' => (int) ($dossier->reportfavorable ?? 0)];
            case 'grid':
                return $common + ['grid' => these_get_grid($dossier), 'ethicsnumber' => (string) ($dossier->ethicsnumber ?? '')];
            case 'agreement':
                $scientific = [
                    'directorid' => (int) $dossier->directorid,
                    'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                    'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
                ];
                if (in_array($slot, ['director', 'extdirector'], true)) {
                    $function = (string) (these_get_agreement($dossier)['directorfunction'] ?? '');
                    return $common + $scientific + ['function' => $function];
                }
                return $common + [
                    'agreement' => these_get_agreement($dossier),
                    'directorid' => (int) $dossier->directorid,
                    'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                    'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
                ];
            case 'signaletique':
                $student = these_get_bordereau($dossier);
                $keys = ['birthname', 'firstnames', 'personalemail', 'emailconsent', 'birthdate', 'birthplace', 'birthdept',
                    'street', 'postcode', 'city', 'country', 'phone', 'defvdate', 'defvschool', 'a6date', 'a6school', 'a6notyet'];
                return $common + [
                    'student' => array_intersect_key($student, array_flip($keys)),
                    'confidential' => (int) ($dossier->confidential ?? 0),
                    'jury' => array_map(function ($member) {
                        return [$member->juryrole, $member->fullname, $member->jurytitle, $member->institution];
                    }, these_get_jury($dossier->id)),
                ];
            case 'contract':
                $student = these_get_bordereau($dossier);
                $keys = ['birthname', 'firstnames', 'birthdate', 'street', 'postcode', 'city', 'country', 'personalemail',
                    'confidentialuntil', 'embargo', 'embargountil', 'embargoreason', 'diffusion'];
                return $common + [
                    'student' => array_intersect_key($student, array_flip($keys)),
                    'confidential' => (int) ($dossier->confidential ?? 0),
                ];
            case 'conformity':
                return $common + ['devenumber' => (string) $dossier->devenumber];
            case 'conclusions':
                return $common + ['conclusions' => (string) ($dossier->conclusions ?? '')];
        }
        throw new coding_exception('Document inconnu : ' . $doctype);
    }

    /**
     * Empreinte SHA-256 du contenu d'un document couvert par un emplacement de signature.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @param string $slot Voir these_document_content().
     * @return string
     */
    public static function document_hash(stdClass $dossier, $doctype, $slot = '') {
        return hash('sha256', json_encode(these_document_content($dossier, $doctype, $slot), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Indique si un document peut être produit pour un dossier (ses données ont été saisies).
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return bool
     */
    public static function document_available(stdClass $dossier, $doctype) {
        switch ($doctype) {
            case 'subject':
                return trim((string) $dossier->title) !== '';
            case 'bordereau':
                return !empty(these_get_bordereau($dossier));
            case 'report':
                return trim((string) ($dossier->reporttext ?? '')) !== '';
            case 'grid':
                return !empty(these_get_grid($dossier));
            case 'agreement':
                return !empty(these_get_agreement($dossier)) || these_has_signature($dossier->id, 'agreement');
            case 'signaletique':
            case 'contract':
                return !empty(these_get_bordereau($dossier));
            case 'conformity':
                return these_rank_reached($dossier, THESE_STATUS_CONFORM);
            case 'conclusions':
                return trim((string) ($dossier->conclusions ?? '')) !== '';
        }
        return false;
    }

    /**
     * Indique si l'utilisateur peut consulter un document d'un dossier.
     *
     * @param string $doctype
     * @param stdClass $rights Voir these_get_rights().
     * @return bool
     */
    public static function can_view_document($doctype, stdClass $rights) {
        if (!these_can_view_dossier($rights)) {
            return false;
        }
        // Thèse confidentielle : aucun document, tous portant le titre, pour qui ne connaît pas le sujet.
        if (isset($rights->knowssubject) && !$rights->knowssubject) {
            return false;
        }
        if (these_document_definitions()[$doctype]['staffonly']) {
            return $rights->director || $rights->viewall;
        }
        return true;
    }

    /**
     * Indique si au moins une signature a été apposée sur un document d'un dossier.
     *
     * @param int $dossierid
     * @param string $doctype
     * @return bool
     */
    public static function has_signature($dossierid, $doctype) {
        global $DB;

        return $DB->record_exists_select(
            'these_signature',
            'dossierid = ? AND doctype = ? AND timesigned > 0',
            [$dossierid, $doctype]
        );
    }

    /**
     * Agrément administratif renseigné par la DEVE (diplôme d'études fondamentales, mobilité
     * internationale, année d'approfondissement).
     *
     * @param stdClass $dossier
     * @return array {defvdate, mobility, a6date, a6notyet} ; tableau vide s'il n'a pas été renseigné.
     */
    public static function get_agreement(stdClass $dossier) {
        $data = json_decode((string) ($dossier->agreement ?? ''), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Écoles nationales vétérinaires, pour le cursus déclaré dans la fiche signalétique.
     *
     * @return array
     */
    public static function school_options() {
        return [
            '' => get_string('choosedots'),
            'alfort' => 'EnvA (Maisons-Alfort)',
            'lyon' => 'VetAgro Sup (Lyon)',
            'nantes' => 'Oniris VetAgroBio (Nantes)',
            'toulouse' => 'ENVT (Toulouse)',
            'rouen' => 'UniLaSalle (Rouen)',
            'other' => get_string('schoolother', 'mod_these'),
        ];
    }

    /**
     * Bordereau de thèse d'un dossier.
     *
     * @param stdClass $dossier
     * @return array Voir these_bordereau_fields() ; tableau vide s'il n'a pas été renseigné.
     */
    public static function get_bordereau(stdClass $dossier) {
        $data = json_decode((string) ($dossier->bordereau ?? ''), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Données saisies par l'étudiant au dépôt de sa soutenance, communes au bordereau ABES, à la
     * fiche signalétique et au contrat de diffusion (stockées ensemble dans these_dossier.bordereau).
     *
     * @return string[]
     */
    public static function bordereau_fields() {
        return [
            // État civil et coordonnées.
            'birthname', 'firstnames', 'usagename', 'publicationname', 'birthdate', 'birthplace', 'birthdept',
            'nationality', 'street', 'postcode', 'city', 'country', 'phone', 'personalemail', 'emailconsent',
            // Cursus.
            'defvdate', 'defvschool', 'a6date', 'a6school', 'a6notyet',
            // Bordereau ABES.
            'nbvolumes', 'nbpages', 'nbrefs', 'summaryfr', 'summaryen', 'keywords',
            // Contrat de diffusion.
            'confidentialuntil', 'embargo', 'embargountil', 'embargoreason', 'diffusion',
            // Soutenance.
            'visio',
        ];
    }

    /**
     * Fin de l'embargo de diffusion d'une thèse : THESE_EMBARGO_YEARS ans après la soutenance (date
     * arrêtée, à défaut date proposée). Un embargo enregistré avant cette règle garde sa date.
     *
     * @param stdClass $dossier
     * @return int 0 sans embargo, ou tant que la soutenance n'a pas de date.
     */
    public static function embargo_until(stdClass $dossier) {
        $bordereau = these_get_bordereau($dossier);
        if (empty($bordereau['embargo'])) {
            return 0;
        }
        if (!empty($bordereau['embargountil'])) {
            return (int) $bordereau['embargountil'];
        }
        $defense = (int) ($dossier->defensedate ?: $dossier->proposeddate);
        if (!$defense) {
            return 0;
        }
        $date = usergetdate($defense);
        return make_timestamp($date['year'] + THESE_EMBARGO_YEARS, $date['mon'], $date['mday']);
    }

    /**
     * Éléments de la grille de conformité du directeur, par rubrique. Les éléments « conditionnels »
     * ne s'appliquent qu'à certaines thèses : ils peuvent rester non cochés.
     *
     * @return array rubrique => [clé => conditionnel (bool)]
     */
    public static function grid_items() {
        return [
            'fond' => [
                'question' => false, 'answer' => false, 'biblio' => false, 'critique' => false, 'ownwork' => false,
                'tool' => true,
            ],
            'forme' => [
                'french' => false, 'language' => false, 'typing' => false, 'thanks' => false, 'toc' => false,
                'staff' => false, 'lists' => false, 'covers' => false, 'citations' => false, 'bibliography' => false,
                'figures' => false, 'introduction' => false, 'conclusion' => false, 'abstract' => false,
                'structure' => true,
            ],
        ];
    }

    /**
     * Rubriques de la grille de conformité, dans l'ordre.
     *
     * @return string[]
     */
    public static function grid_sections() {
        return ['fond', 'forme'];
    }

    /**
     * Éléments de la grille de conformité d'une portée de paramètres (voir these_settings_scope()),
     * modifiables par la DEVE. Les éléments d'origine (these_grid_items()) sont enregistrés à la
     * première consultation.
     *
     * @param stdClass|null $scope null : éléments d'origine, sans enregistrement (aperçus).
     * @return array rubrique => [clé => {itemkey, section, label, conditional, sortorder}]
     */
    public static function get_grid_items(?stdClass $scope) {
        global $DB;

        $records = $scope ? $DB->get_records('these_griditem', these_scope_conditions($scope), 'sortorder ASC, id ASC') : [];
        if (!$records) {
            $records = [];
            $sortorder = 0;
            foreach (these_grid_items() as $section => $items) {
                foreach ($items as $key => $conditional) {
                    $records[] = (object) array_merge($scope ? these_scope_conditions($scope) : [], [
                        'itemkey' => $key,
                        'section' => $section,
                        'label' => get_string('grid' . $key, 'mod_these'),
                        'conditional' => (int) $conditional,
                        'sortorder' => $sortorder++,
                        'timemodified' => time(),
                    ]);
                }
            }
            if ($scope) {
                foreach ($records as $record) {
                    $record->id = $DB->insert_record('these_griditem', $record);
                }
            }
        }
        $sections = array_fill_keys(these_grid_sections(), []);
        foreach ($records as $record) {
            $section = array_key_exists($record->section, $sections) ? $record->section : 'forme';
            $sections[$section][$record->itemkey] = $record;
        }
        return $sections;
    }

    /**
     * Remplace les éléments de la grille de conformité d'une portée. Un élément gardé conserve sa clé,
     * et donc les réponses déjà données ; un élément supprimé disparaît des grilles et de leurs PDF.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param array $items Liste d'objets {itemkey ('' pour un nouvel élément), section, label, conditional}, dans l'ordre ;
     *                     une clé inconnue de la portée (transfert depuis une autre) est reprise telle quelle.
     * @return void
     */
    public static function save_grid_items(stdClass $scope, array $items) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $existing = $DB->get_records_menu('these_griditem', these_scope_conditions($scope), '', 'itemkey, id');
        $kept = [];
        $sortorder = 0;
        foreach ($items as $item) {
            $label = trim((string) $item->label);
            if ($label === '') {
                continue;
            }
            $key = (string) ($item->itemkey ?? '');
            if (!preg_match('/^[a-zA-Z0-9]{1,40}$/', $key) || isset($kept[$key])) {
                $key = 'item' . random_string(10);
            }
            $record = (object) array_merge(these_scope_conditions($scope), [
                'itemkey' => $key,
                'section' => in_array($item->section, these_grid_sections(), true) ? $item->section : 'forme',
                'label' => $label,
                'conditional' => empty($item->conditional) ? 0 : 1,
                'sortorder' => $sortorder++,
                'timemodified' => time(),
            ]);
            if (isset($existing[$key])) {
                $record->id = $existing[$key];
                $DB->update_record('these_griditem', $record);
            } else {
                $DB->insert_record('these_griditem', $record);
            }
            $kept[$key] = true;
        }
        foreach ($existing as $key => $id) {
            if (!isset($kept[$key])) {
                $DB->delete_records('these_griditem', ['id' => $id]);
            }
        }
        $transaction->allow_commit();
    }

    /**
     * Grille de conformité d'un dossier.
     *
     * @param stdClass $dossier
     * @return array clé => 0|1 ; tableau vide si elle n'a pas été renseignée.
     */
    public static function get_grid(stdClass $dossier) {
        $data = json_decode((string) ($dossier->conformitygrid ?? ''), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Signatures d'un dossier.
     *
     * @param int $dossierid
     * @return array « doctype:slot » => enregistrement these_signature
     */
    public static function get_signatures($dossierid) {
        global $DB;

        $signatures = [];
        foreach ($DB->get_records('these_signature', ['dossierid' => $dossierid], 'id ASC') as $signature) {
            $signatures[$signature->doctype . ':' . $signature->slot] = $signature;
        }
        return $signatures;
    }

    /**
     * Décode l'image de signature tracée dans le formulaire (URL de données PNG).
     *
     * @param string $dataurl
     * @return string|null Contenu PNG, ou null si la donnée n'est pas une image PNG plausible.
     */
    public static function decode_signature_image($dataurl) {
        $prefix = 'data:image/png;base64,';
        if (!is_string($dataurl) || strpos($dataurl, $prefix) !== 0 || strlen($dataurl) > 600000) {
            return null;
        }
        $png = base64_decode(substr($dataurl, strlen($prefix)), true);
        if ($png === false || strlen($png) < 100) {
            return null;
        }
        $info = @getimagesizefromstring($png);
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 2000 || $info[1] > 1000) {
            return null;
        }
        return $png;
    }

    /**
     * Contenu de l'image d'une signature.
     *
     * @param int $contextid
     * @param int $signatureid
     * @return string|null
     */
    public static function get_signature_image($contextid, $signatureid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'mod_these', 'signature', $signatureid, 'id', false);
        $file = reset($files);
        return $file ? $file->get_content() : null;
    }

    /**
     * Appose une signature sur un document : l'emplacement est créé ou complété, l'image enregistrée
     * et l'empreinte du contenu signé conservée. Le jeton d'une invitation éventuelle est effacé.
     *
     * @param context $context Contexte du module.
     * @param stdClass $dossier
     * @param string $doctype
     * @param string $slot
     * @param int $userid Signataire (0 sans compte).
     * @param string $name Nom du signataire tel qu'il apparaît sur le document.
     * @param string $png Image de la signature.
     * @param string $email
     * @return stdClass La signature.
     */
    public static function sign_document(context $context, stdClass $dossier, $doctype, $slot, $userid, $name, $png, $email = '') {
        global $DB;

        $now = time();
        $signature = $DB->get_record('these_signature', ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]);
        $record = (object) [
            'dossierid' => $dossier->id,
            'doctype' => $doctype,
            'slot' => $slot,
            'userid' => (int) $userid,
            'signername' => trim($name),
            'signeremail' => $email !== '' ? $email : ($signature->signeremail ?? ''),
            'token' => null,
            'timesigned' => $now,
            'contenthash' => these_document_hash($dossier, $doctype, $slot),
            'templatehash' => these_document_template_hash($dossier, $doctype),
            'ipaddress' => getremoteaddr(),
        ];
        if ($signature) {
            $record->id = $signature->id;
            $DB->update_record('these_signature', $record);
        } else {
            $record->requesttime = 0;
            $record->timecreated = $now;
            $record->id = $DB->insert_record('these_signature', $record);
        }

        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_these', 'signature', $record->id);
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => 'signature',
            'itemid' => $record->id,
            'filepath' => '/',
            'filename' => 'signature.png',
            'userid' => $userid ?: null,
        ], $png);

        these_log(
            $dossier->id,
            $userid,
            'signed',
            $dossier->status,
            $dossier->status,
            these_document_label($doctype) . ' — ' . these_slot_label($slot) . ' — ' . trim($name)
        );
        these_seal_document($context, $dossier, $doctype);
        return $DB->get_record('these_signature', ['id' => $record->id]);
    }

    /**
     * Supprime une signature et son image.
     *
     * @param context $context
     * @param stdClass $signature
     * @return void
     */
    public static function delete_signature(context $context, stdClass $signature) {
        global $DB;

        get_file_storage()->delete_area_files($context->id, 'mod_these', 'signature', $signature->id);
        $DB->delete_records('these_signature', ['id' => $signature->id]);
        these_unseal_document($context, $signature->dossierid, $signature->doctype);
    }

    /**
     * Invalide les signatures dont le document a changé depuis qu'elles ont été apposées : un
     * document signé ne peut pas être modifié en silence. Tant que le document n'est pas scellé
     * (voir these_seal_document()), un changement de son modèle compte aussi. Les signatures concernées sont supprimées
     * et la suppression est inscrite à l'historique.
     *
     * @param context $context
     * @param stdClass $dossier
     * @return string[] Emplacements invalidés (« doctype:slot »).
     */
    public static function refresh_signatures(context $context, stdClass $dossier) {
        $invalidated = [];
        foreach (these_get_signatures($dossier->id) as $key => $signature) {
            if (empty($signature->timesigned)) {
                continue;
            }
            if (
                $signature->contenthash !== these_document_hash($dossier, $signature->doctype, $signature->slot)
                || these_signature_template_changed($context, $dossier, $signature)
            ) {
                these_delete_signature($context, $signature);
                $invalidated[] = $key;
            }
        }
        if ($invalidated) {
            these_log($dossier->id, 0, 'signaturesinvalidated', $dossier->status, $dossier->status, implode(', ', array_map(
                function ($key) {
                    [$doctype, $slot] = explode(':', $key);
                    return these_document_label($doctype) . ' — ' . these_slot_label($slot);
                },
                $invalidated
            )));
        }
        return $invalidated;
    }

    /**
     * Empreinte SHA-256 de la mise en forme d'un document : modèles en vigueur, images qu'ils
     * appellent et, pour le contrat de diffusion, contrat type déposé dans les réglages du site.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return string
     */
    public static function document_template_hash(stdClass $dossier, $doctype) {
        global $DB;

        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $scope = these_settings_scope($these);
        $types = array_keys(array_filter(these_doctemplate_types(), function ($signed) use ($doctype) {
            return $signed === $doctype;
        }));
        $parts = [];
        $images = these_doctemplate_images($scope);
        foreach ($types as $type) {
            $template = these_get_doctemplate($scope, $type);
            $parts[] = $template;
            foreach ($images as $name => $source) {
                if (strpos($template, 'img.' . $name) !== false) {
                    $parts[] = $name . ':' . sha1($source);
                }
            }
        }
        if ($doctype === 'contract') {
            $parts[] = sha1((string) these_get_contract_template());
        }
        return hash('sha256', implode("\n", $parts));
    }

    /**
     * Indique si la mise en forme d'un document a changé depuis une signature. Un document scellé
     * ne dépend plus de son modèle ; une signature antérieure à l'enregistrement de cette empreinte
     * n'est pas remise en cause.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass $signature
     * @return bool
     */
    public static function signature_template_changed(context $context, stdClass $dossier, stdClass $signature) {
        if (empty($signature->templatehash) || these_get_sealed_document($context, $dossier->id, $signature->doctype)) {
            return false;
        }
        return $signature->templatehash !== these_document_template_hash($dossier, $signature->doctype);
    }

    /**
     * Indique si tous les emplacements de signature d'un document sont signés.
     *
     * @param stdClass $dossier
     * @param string $doctype
     * @return bool
     */
    public static function document_fully_signed(stdClass $dossier, $doctype) {
        $signatures = these_get_signatures($dossier->id);
        foreach (these_document_slots($dossier, $doctype) as $slot) {
            if (empty($signatures[$doctype . ':' . $slot]->timesigned)) {
                return false;
            }
        }
        return true;
    }

    /**
     * PDF scellé d'un document.
     *
     * @param context $context
     * @param int $dossierid
     * @param string $doctype
     * @return stored_file|null
     */
    public static function get_sealed_document(context $context, $dossierid, $doctype) {
        $file = get_file_storage()->get_file($context->id, 'mod_these', 'sealed', $dossierid, '/', $doctype . '.pdf');
        return $file ?: null;
    }

    /**
     * Scelle un document dont toutes les signatures sont apposées : le PDF est produit une dernière
     * fois et conservé tel quel. Il ne dépend plus ensuite ni des modèles ni du contrat type, et son
     * empreinte SHA-256, calculée sur ses octets, est inscrite à l'historique du dossier.
     *
     * Une signature dont la mise en forme a changé depuis qu'elle a été apposée est d'abord invalidée
     * (voir these_refresh_signatures()) : le document n'est alors pas scellé.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @return stored_file|null Document scellé, null s'il attend encore une signature.
     */
    public static function seal_document(context $context, stdClass $dossier, $doctype) {
        global $DB;

        if ($sealed = these_get_sealed_document($context, $dossier->id, $doctype)) {
            return $sealed;
        }
        if (
            !these_document_fully_signed($dossier, $doctype)
                || ($doctype === 'contract' && these_contract_template_problem() !== null)
        ) {
            return null;
        }
        these_refresh_signatures($context, $dossier);
        if (!these_document_fully_signed($dossier, $doctype)) {
            return null;
        }
        $these = $DB->get_record('these', ['id' => $dossier->theseid], '*', MUST_EXIST);
        $pdf = these_render_document_pdf($these, $context, $dossier, $doctype);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_these',
            'filearea' => 'sealed',
            'itemid' => $dossier->id,
            'filepath' => '/',
            'filename' => $doctype . '.pdf',
        ], $pdf);
        these_log(
            $dossier->id,
            0,
            'documentsealed',
            $dossier->status,
            $dossier->status,
            these_document_label($doctype) . ' — SHA-256 ' . hash('sha256', $pdf)
        );
        return $file;
    }

    /**
     * Retire le scellé d'un document dont une signature disparaît : il sera scellé de nouveau une
     * fois toutes ses signatures apposées.
     *
     * @param context $context
     * @param int $dossierid
     * @param string $doctype
     * @return void
     */
    public static function unseal_document(context $context, $dossierid, $doctype) {
        if ($file = these_get_sealed_document($context, $dossierid, $doctype)) {
            $file->delete();
        }
    }

    /**
     * Signataires sans compte attendus pour un dossier, avec leur nom et leur adresse : le
     * directeur extérieur (formulaire de sujet), le président du jury, le directeur général et le
     * président du CCEM (conclusions), ces deux derniers étant définis dans les réglages du site.
     *
     * @param stdClass $dossier
     * @return array slot => ['name' => string, 'email' => string]
     */
    public static function external_signers(stdClass $dossier) {
        $signers = [
            'extdirector' => [
                'name' => trim(($dossier->extdirectorname ?? '')),
                'email' => trim((string) ($dossier->extdirectoremail ?? '')),
            ],
            'president' => ['name' => '', 'email' => ''],
            'dg' => ['name' => (string) get_config('mod_these', 'dgname'), 'email' => (string) get_config('mod_these', 'dgemail')],
            'ccem' => ['name' => (string) get_config('mod_these', 'ccemname'),
                'email' => (string) get_config('mod_these', 'ccememail')],
            'ucbl' => ['name' => (string) get_config('mod_these', 'ucblname'),
                'email' => (string) get_config('mod_these', 'ucblemail')],
        ];
        foreach (these_get_jury($dossier->id) as $member) {
            if ($member->juryrole === 'president') {
                $signers['president'] = ['name' => trim($member->jurytitle . ' ' . $member->fullname), 'email' => $member->email];
            }
        }
        return $signers;
    }

    /**
     * Documents en attente de la signature d'un signataire sans compte, dans un dossier.
     *
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes Documents à considérer (tous par défaut).
     * @return string[]
     */
    public static function pending_documents_for_slot(stdClass $dossier, $slot, ?array $doctypes = null) {
        $signatures = these_get_signatures($dossier->id);
        $pending = [];
        foreach ($doctypes ?? array_keys(these_document_definitions()) as $doctype) {
            if (!in_array($slot, these_document_slots($dossier, $doctype), true)) {
                continue;
            }
            $existing = $signatures[$doctype . ':' . $slot] ?? null;
            if (!$existing || empty($existing->timesigned)) {
                $pending[] = $doctype;
            }
        }
        return $pending;
    }

    /**
     * Invite un signataire sans compte à signer, en une seule fois, les documents qui l'attendent :
     * chaque emplacement reçoit le même nouveau jeton, et le lien de signature est envoyé au
     * signataire s'il a une adresse. Sans adresse, les signatures restent en attente et peuvent être
     * recueillies sur place par la DEVE (signatures.php).
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes Documents concernés (tous ceux en attente par défaut).
     * @return string|null Jeton, ou null si aucun document n'attend ce signataire.
     */
    public static function request_signature(stdClass $these, stdClass $cm, stdClass $dossier, $slot, ?array $doctypes = null) {
        global $DB;

        $doctypes = these_pending_documents_for_slot($dossier, $slot, $doctypes);
        if (!$doctypes) {
            return null;
        }
        $signer = these_external_signers($dossier)[$slot];
        $token = these_new_token();
        $now = time();
        foreach ($doctypes as $doctype) {
            $signature = $DB->get_record('these_signature', ['dossierid' => $dossier->id, 'doctype' => $doctype, 'slot' => $slot]);
            $record = (object) [
                'dossierid' => $dossier->id,
                'doctype' => $doctype,
                'slot' => $slot,
                // Compte Moodle du signataire s'il en a un : « Mes signatures » et la reprise de sa
                // signature enregistrée lui sont ouverts, sans dépendre de son adresse de profil.
                'userid' => \mod_these\local\rights::account_for_email($signer['email']),
                'signername' => $signer['name'],
                'signeremail' => $signer['email'],
                'token' => these_token_hash($token),
                'requesttime' => $now,
                'timesigned' => 0,
                'contenthash' => null,
                'templatehash' => null,
            ];
            if ($signature) {
                $record->id = $signature->id;
                $DB->update_record('these_signature', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('these_signature', $record);
            }
        }

        $labels = implode(', ', array_map('these_document_label', $doctypes));
        if ($signer['email'] !== '' && validate_email($signer['email'])) {
            $vars = these_email_base_vars($these, $cm, $dossier);
            $vars['name'] = $signer['name'];
            $vars['document'] = $labels;
            $vars['url'] = (new moodle_url('/mod/these/sign.php', ['token' => $token]))->out(false);
            these_send_email(
                $these,
                'signaturerequest',
                [these_make_external_user($signer['email'], $signer['name'])],
                $vars,
                '',
                '',
                $dossier
            );
        }
        these_log(
            $dossier->id,
            0,
            'signaturerequested',
            $dossier->status,
            $dossier->status,
            these_slot_label($slot) . ($signer['name'] !== '' ? ' (' . $signer['name'] . ')' : '') . ' — ' . $labels
        );
        return $token;
    }

    /**
     * Invite tous les signataires sans compte attendus sur des documents et qui n'ont pas encore
     * signé (un seul courriel par signataire, pour tous ses documents).
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param string|string[] $doctypes
     * @return int Nombre de signataires invités.
     */
    public static function request_external_signatures(stdClass $these, stdClass $cm, stdClass $dossier, $doctypes) {
        $count = 0;
        foreach (these_external_slots() as $slot) {
            if (these_request_signature($these, $cm, $dossier, $slot, (array) $doctypes) !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Signatures en attente correspondant à un jeton d'invitation : tous les documents qu'un même
     * signataire doit signer dans un dossier. Le lien vaut THESE_TOKEN_LIFETIME à partir de
     * l'invitation ; la DEVE peut en envoyer un nouveau depuis le suivi des signatures.
     *
     * @param string $token
     * @return stdClass[] Signatures en attente, vide si le jeton est inconnu, déjà utilisé ou expiré.
     */
    public static function get_signatures_by_token($token) {
        global $DB;

        $token = clean_param($token, PARAM_ALPHANUM);
        if (strlen($token) < 32) {
            return [];
        }
        return array_values($DB->get_records_select(
            'these_signature',
            'token = :token AND timesigned = 0 AND requesttime > :since',
            ['token' => these_token_hash($token), 'since' => time() - THESE_TOKEN_LIFETIME],
            'id ASC'
        ));
    }

    /**
     * Ajoute à un formulaire le bloc de signature : zone de tracé, nom du signataire et
     * certification. La zone de tracé alimente un champ caché « signaturedata » (module AMD
     * mod_these/signaturepad).
     *
     * @param MoodleQuickForm $mform
     * @param string $defaultname Nom proposé.
     * @param string $intro Texte d'introduction (ce qui est signé).
     * @param bool $allowsaved Proposer de reprendre ou d'enregistrer la signature de l'utilisateur.
     * @return void
     */
    public static function add_signature_fields(MoodleQuickForm $mform, $defaultname, $intro = '', $allowsaved = true) {
        global $PAGE, $USER;

        $mform->addElement('header', 'signatureheader', get_string('signature', 'mod_these'));
        $mform->setExpanded('signatureheader');
        if ($intro !== '') {
            $mform->addElement('static', 'signatureintro', '', $intro);
        }
        // Signature enregistrée de l'utilisateur connecté : reprise d'office, sans nouveau tracé.
        $cansave = $allowsaved && isloggedin() && !isguestuser();
        $saved = $cansave ? these_get_saved_signature($USER->id) : null;
        if ($saved !== null) {
            $mform->addElement('advcheckbox', 'usesavedsignature', '', get_string('signatureusesaved', 'mod_these')
                . html_writer::empty_tag('br') . html_writer::empty_tag('img', [
                    'src' => 'data:image/png;base64,' . base64_encode($saved), 'alt' => get_string('signaturesaved', 'mod_these'),
                    'class' => 'these-saved-signature border mt-1', 'style' => 'max-height:80px;background:#fff;',
                ]));
            $mform->setDefault('usesavedsignature', 1);
        }
        $pad = html_writer::div(
            html_writer::tag('canvas', '', ['width' => 520, 'height' => 170, 'class' => 'these-signature-canvas',
                'aria-label' => get_string('signaturedraw', 'mod_these')])
                . html_writer::div(
                    html_writer::tag('button', get_string('signatureclear', 'mod_these'), [
                        'type' => 'button', 'class' => 'btn btn-sm btn-secondary', 'data-action' => 'these-signature-clear',
                    ]),
                    'mt-1'
                ),
            'these-signature-pad'
        );
        $mform->addElement('static', 'signaturepad', get_string('signaturedraw', 'mod_these'), $pad);
        $mform->addElement('hidden', 'signaturedata', '');
        $mform->setType('signaturedata', PARAM_RAW);
        if ($cansave) {
            $mform->addElement('advcheckbox', 'savesignature', '', get_string('signaturesave', 'mod_these'));
            $mform->setDefault('savesignature', 1);
        }
        if ($saved !== null) {
            $mform->hideIf('signaturepad', 'usesavedsignature', 'checked');
            $mform->hideIf('savesignature', 'usesavedsignature', 'checked');
        }
        $mform->addElement('text', 'signername', get_string('signername', 'mod_these'), ['size' => 48]);
        $mform->setType('signername', PARAM_TEXT);
        $mform->setDefault('signername', $defaultname);
        $mform->addElement('advcheckbox', 'signatureconsent', '', get_string('signatureconsent', 'mod_these'));
        $PAGE->requires->js_call_amd('mod_these/signaturepad', 'init');
    }

    /**
     * Contrôle du bloc de signature (voir these_add_signature_fields()).
     *
     * @param array $data
     * @return array Erreurs, indexées par champ.
     */
    public static function signature_errors(array $data) {
        global $USER;

        $errors = [];
        $saved = !empty($data['usesavedsignature']) && these_get_saved_signature($USER->id) !== null;
        if (!$saved && these_decode_signature_image($data['signaturedata'] ?? '') === null) {
            $errors['signername'] = get_string('signaturemissing', 'mod_these');
        } else if (trim((string) ($data['signername'] ?? '')) === '') {
            $errors['signername'] = get_string('required');
        }
        if (empty($data['signatureconsent'])) {
            $errors['signatureconsent'] = get_string('signatureconsentrequired', 'mod_these');
        }
        return $errors;
    }

    /**
     * Appose la signature saisie dans un formulaire sur un ou plusieurs documents.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param string[] $doctypes
     * @param string $slot
     * @param stdClass $data Données du formulaire (signaturedata, signername).
     * @param int $userid
     * @return void
     */
    public static function sign_from_form(context $context, stdClass $dossier, array $doctypes, $slot, stdClass $data, $userid) {
        $signature = these_signature_from_form($data);
        if ($signature === null) {
            throw new coding_exception('Signature absente');
        }
        foreach ($doctypes as $doctype) {
            these_sign_document($context, $dossier, $doctype, $slot, $userid, $signature->name, $signature->png);
        }
    }

    /**
     * Tableau des documents signés d'un dossier : état de chaque signature et lien de
     * téléchargement du PDF.
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $dossier
     * @param stdClass $rights
     * @return string HTML
     */
    public static function render_signed_documents(stdClass $cm, context $context, stdClass $dossier, stdClass $rights) {
        global $PAGE;

        return $PAGE->get_renderer('mod_these')->render(new output\signed_documents($cm, $context, $dossier, $rights));
    }
}
