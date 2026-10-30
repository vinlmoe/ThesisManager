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

use mod_these\event\document_signed;
use mod_these\output;
use coding_exception;
use context;
use stdClass;

/**
 * Documents officiels et signatures : documents produits par le module et leurs emplacements de
 * signature, contenu couvert par les signatures, apposition, suppression, révocation et
 * invalidation des signatures.
 *
 * Voir aussi seals (scellés), signaturetokens (demandes aux signataires sans compte),
 * signaturefields (bloc de signature des formulaires), bordereau et grid.
 *
 * Les fonctions these_*() correspondantes de locallib/signatures.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signatures {
    /** Engagement de l'étudiant : avoir pris connaissance du guide des thèses. */
    public const COMMIT_GUIDE = 1;

    /** Engagement de l'étudiant : avoir vérifié que le sujet n'a pas déjà été traité. */
    public const COMMIT_DUPLICATE = 2;

    /** Engagement de l'étudiant : soumettre le protocole au comité d'éthique (si nécessaire). */
    public const COMMIT_ETHICS = 4;

    /**
     * Documents de l'établissement produits par le module, et qui les signe.
     *
     * - subject : formulaire de dépôt de sujet (étudiant, directeur, directeur extérieur éventuel,
     *   visa de la DEVE) ;
     * - bordereau : bordereau de thèse d'exercice vétérinaire (ABES), signé par l'étudiant ;
     * - grid : grille de conformité, renseignée par le directeur (son rapport est un fichier déposé) ;
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
        $slots = self::document_definitions()[$doctype]['slots'];
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
     * Emplacements que la DEVE peut faire signer sur place, après avoir vérifié l'identité du
     * signataire : l'étudiant, le directeur et les signataires sans compte. Les emplacements de la
     * DEVE, de ses agents et de la bibliothèque sont signés par eux-mêmes.
     *
     * @return string[]
     */
    public static function inperson_slots() {
        return array_merge(['student', 'director'], self::external_slots());
    }

    /**
     * Signataire attendu sur place à un emplacement : compte et nom de l'étudiant ou du directeur,
     * nom et adresse des signataires sans compte (voir external_signers()).
     *
     * @param stdClass $dossier
     * @param string $slot
     * @return array ['userid' => int, 'name' => string, 'email' => string]
     */
    public static function inperson_signer(stdClass $dossier, $slot) {
        global $DB;

        $userid = (int) (['student' => $dossier->userid, 'director' => $dossier->directorid][$slot] ?? 0);
        if ($userid && ($user = $DB->get_record('user', ['id' => $userid]))) {
            return ['userid' => $userid, 'name' => fullname($user), 'email' => (string) $user->email];
        }
        $signer = self::external_signers($dossier)[$slot] ?? ['name' => '', 'email' => ''];
        return ['userid' => 0] + $signer;
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
                return $common + ['titleen' => (string) $dossier->titleen, 'bordereau' => bordereau::get_bordereau($dossier)];
            case 'grid':
                return $common + ['grid' => grid::get_grid($dossier), 'ethicsnumber' => (string) ($dossier->ethicsnumber ?? '')];
            case 'agreement':
                $scientific = [
                    'directorid' => (int) $dossier->directorid,
                    'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                    'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
                ];
                if (in_array($slot, ['director', 'extdirector'], true)) {
                    $function = (string) (self::get_agreement($dossier)['directorfunction'] ?? '');
                    return $common + $scientific + ['function' => $function];
                }
                return $common + [
                    'agreement' => self::get_agreement($dossier),
                    'directorid' => (int) $dossier->directorid,
                    'externaldirector' => (int) ($dossier->externaldirector ?? 0),
                    'extdirectorname' => (string) ($dossier->extdirectorname ?? ''),
                ];
            case 'signaletique':
                $student = bordereau::get_bordereau($dossier);
                $keys = ['birthname', 'firstnames', 'personalemail', 'emailconsent', 'birthdate', 'birthplace', 'birthdept',
                    'street', 'postcode', 'city', 'country', 'phone', 'defvdate', 'defvschool', 'a6date', 'a6school', 'a6notyet'];
                return $common + [
                    'student' => array_intersect_key($student, array_flip($keys)),
                    'confidential' => (int) ($dossier->confidential ?? 0),
                    'jury' => array_map(function ($member) {
                        return [$member->juryrole, $member->fullname, $member->jurytitle, $member->institution];
                    }, jury::get_jury($dossier->id)),
                ];
            case 'contract':
                $student = bordereau::get_bordereau($dossier);
                $keys = ['birthname', 'firstnames', 'birthdate', 'street', 'postcode', 'city', 'country', 'personalemail',
                    'confidentialuntil', 'embargo', 'embargomonths', 'embargountil', 'embargoreason', 'diffusion'];
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
        return hash('sha256', json_encode(self::document_content($dossier, $doctype, $slot), JSON_UNESCAPED_UNICODE));
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
                return !empty(bordereau::get_bordereau($dossier));
            case 'grid':
                return !empty(grid::get_grid($dossier));
            case 'agreement':
                return !empty(self::get_agreement($dossier)) || self::has_signature($dossier->id, 'agreement');
            case 'signaletique':
            case 'contract':
                return !empty(bordereau::get_bordereau($dossier));
            case 'conformity':
                return rendering::rank_reached($dossier, THESE_STATUS_CONFORM);
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
        if (!rights::can_view_dossier($rights)) {
            return false;
        }
        // Thèse confidentielle : aucun document, tous portant le titre, pour qui ne connaît pas le sujet.
        if (isset($rights->knowssubject) && !$rights->knowssubject) {
            return false;
        }
        if (self::document_definitions()[$doctype]['staffonly']) {
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
     * Signatures d'un dossier, sur les documents que le module produit encore : celles d'un document
     * retiré (rapport du directeur, désormais déposé en fichier) restent en base sans être reprises.
     *
     * @param int $dossierid
     * @return array « doctype:slot » => enregistrement these_signature
     */
    public static function get_signatures($dossierid) {
        global $DB;

        $definitions = self::document_definitions();
        $signatures = [];
        foreach ($DB->get_records('these_signature', ['dossierid' => $dossierid], 'id ASC') as $signature) {
            if (isset($definitions[$signature->doctype])) {
                $signatures[$signature->doctype . ':' . $signature->slot] = $signature;
            }
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
     * Options :
     * - method : 'online' (lien ou compte du signataire, par défaut), 'inperson' (sur place auprès de
     *   la DEVE, identité vérifiée), 'scan' (signature à la main, scan joint au dossier, sans image) ;
     * - delegated : signature par délégation (direction générale) ;
     * - verifiedby : compte DEVE qui a vérifié l'identité du signataire ou enregistré le scan ;
     * - time : date de la signature (scan : date portée sur le document), maintenant par défaut.
     *
     * @param context $context Contexte du module.
     * @param stdClass $dossier
     * @param string $doctype
     * @param string $slot
     * @param int $userid Signataire (0 sans compte).
     * @param string $name Nom du signataire tel qu'il apparaît sur le document.
     * @param string $png Image de la signature ('' pour un scan).
     * @param string $email
     * @param array $options Voir ci-dessus.
     * @return stdClass La signature.
     */
    public static function sign_document(
        context $context,
        stdClass $dossier,
        $doctype,
        $slot,
        $userid,
        $name,
        $png,
        $email = '',
        array $options = []
    ) {
        global $DB;

        $now = (int) ($options['time'] ?? 0) ?: time();
        $method = in_array($options['method'] ?? '', ['inperson', 'scan'], true) ? $options['method'] : 'online';
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
            'contenthash' => self::document_hash($dossier, $doctype, $slot),
            'templatehash' => seals::document_template_hash($dossier, $doctype),
            'ipaddress' => getremoteaddr(),
            'method' => $method,
            'delegated' => empty($options['delegated']) ? 0 : 1,
            'verifiedby' => (int) ($options['verifiedby'] ?? 0),
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
        if ((string) $png !== '') {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'mod_these',
                'filearea' => 'signature',
                'itemid' => $record->id,
                'filepath' => '/',
                'filename' => 'signature.png',
                'userid' => $userid ?: null,
            ], $png);
        }

        // Historique : au nom du compte DEVE qui a vérifié l'identité ou enregistré le scan, s'il y en a un.
        $remark = self::document_label($doctype) . ' — ' . self::slot_label($slot) . ' — ' . trim($name);
        if ($record->delegated) {
            $remark .= ' (' . get_string('signeddelegated', 'mod_these') . ')';
        }
        $validated = $signature ? signatureagent::validator_label($signature) : '';
        if ($validated !== '') {
            $remark .= ' — ' . $validated;
        }
        if ($method !== 'online') {
            $remark .= ' — ' . get_string('signedmethod' . $method, 'mod_these');
        }
        dossiers::log(
            $dossier->id,
            $record->verifiedby ?: $userid,
            'signed',
            $dossier->status,
            $dossier->status,
            $remark
        );
        seals::seal_document($context, $dossier, $doctype);
        document_signed::create_from_dossier($dossier, $context, $record->verifiedby ?: $userid, [
            'doctype' => $doctype, 'slot' => $slot, 'method' => $method, 'signatureid' => (int) $record->id,
        ])->trigger();
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
        seals::unseal_document($context, $signature->dossierid, $signature->doctype);
    }

    /**
     * Révoque les signatures d'un dossier, apposées ou encore demandées (lien envoyé, demande à un
     * agent) : elles seront redemandées quand le dossier repassera les étapes du circuit. Sert au
     * renvoi d'un dossier et au changement de sujet. La révocation est inscrite à l'historique.
     *
     * @param context $context
     * @param stdClass $dossier
     * @param int $userid Auteur du renvoi ou du changement.
     * @param string[]|null $doctypes Documents concernés ; null pour tous.
     * @param string[] $keepslots Emplacements conservés (signataires des étapes qui ne sont pas refaites).
     * @return string[] Signatures révoquées (« doctype:slot »).
     */
    public static function revoke_signatures(
        context $context,
        stdClass $dossier,
        $userid,
        ?array $doctypes = null,
        array $keepslots = []
    ) {
        $revoked = [];
        $labels = [];
        foreach (self::get_signatures($dossier->id) as $key => $signature) {
            if (
                ($doctypes !== null && !in_array($signature->doctype, $doctypes, true))
                || in_array($signature->slot, $keepslots, true)
            ) {
                continue;
            }
            self::delete_signature($context, $signature);
            $revoked[] = $key;
            if (!empty($signature->timesigned)) {
                $labels[] = self::document_label($signature->doctype) . ' — ' . self::slot_label($signature->slot);
            }
        }
        if ($labels) {
            dossiers::log($dossier->id, $userid, 'signaturesrevoked', $dossier->status, $dossier->status, implode(', ', $labels));
        }
        return $revoked;
    }

    /**
     * Documents d'une phase du circuit : le dépôt de sujet en phase 1, les documents de la
     * soutenance en phase 2.
     *
     * @param int $phase Voir these_phase().
     * @return string[]
     */
    public static function phase_documents($phase) {
        $doctypes = array_keys(self::document_definitions());
        return (int) $phase === 1 ? ['subject'] : array_values(array_diff($doctypes, ['subject']));
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
        foreach (self::get_signatures($dossier->id) as $key => $signature) {
            if (empty($signature->timesigned)) {
                continue;
            }
            if (
                $signature->contenthash !== self::document_hash($dossier, $signature->doctype, $signature->slot)
                || seals::signature_template_changed($context, $dossier, $signature)
            ) {
                self::delete_signature($context, $signature);
                $invalidated[] = $key;
            }
        }
        if ($invalidated) {
            dossiers::log($dossier->id, 0, 'signaturesinvalidated', $dossier->status, $dossier->status, implode(', ', array_map(
                function ($key) {
                    [$doctype, $slot] = explode(':', $key);
                    return self::document_label($doctype) . ' — ' . self::slot_label($slot);
                },
                $invalidated
            )));
        }
        return $invalidated;
    }

    /**
     * Signataires sans compte attendus pour un dossier, avec leur nom et leur adresse : le
     * directeur extérieur (formulaire de sujet), le président du jury, le directeur général, le
     * président du CCEM (conclusions) et la présidence de l'université, ces trois derniers étant
     * définis dans les paramètres de l'activité (voir \mod_these\local\scopesettings).
     *
     * @param stdClass $dossier
     * @return array slot => ['name' => string, 'email' => string]
     */
    public static function external_signers(stdClass $dossier) {
        $settings = scopesettings::for_dossier($dossier);
        $signers = [
            'extdirector' => [
                'name' => trim(($dossier->extdirectorname ?? '')),
                'email' => trim((string) ($dossier->extdirectoremail ?? '')),
            ],
            'president' => ['name' => '', 'email' => ''],
            // La direction générale ne signe pas elle-même : pas d'adresse, ses documents vont aux agents DEVE.
            'dg' => ['name' => $settings['dgname'], 'email' => ''],
            'ccem' => ['name' => $settings['ccemname'], 'email' => $settings['ccememail']],
            'ucbl' => ['name' => $settings['ucblname'], 'email' => $settings['ucblemail']],
        ];
        foreach (jury::get_jury($dossier->id) as $member) {
            if ($member->juryrole === 'president') {
                $signers['president'] = ['name' => trim($member->jurytitle . ' ' . $member->fullname), 'email' => $member->email];
            }
        }
        return $signers;
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

    /**
     * Voir signaturetokens::requested_documents_for_slot(). Conservé pour les appels existants.
     *
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes
     * @return string[]
     */
    public static function requested_documents_for_slot(stdClass $dossier, $slot, ?array $doctypes = null) {
        return signaturetokens::requested_documents_for_slot($dossier, $slot, $doctypes);
    }

    /**
     * Voir signaturetokens::request_signature(). Conservé pour les appels existants.
     *
     * @param stdClass $these
     * @param stdClass $cm
     * @param stdClass $dossier
     * @param string $slot
     * @param string[]|null $doctypes
     * @return string|null
     */
    public static function request_signature(stdClass $these, stdClass $cm, stdClass $dossier, $slot, ?array $doctypes = null) {
        return signaturetokens::request_signature($these, $cm, $dossier, $slot, $doctypes);
    }
}
