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

use coding_exception;
use context;
use context_module;
use core_text;
use stdClass;

/**
 * Modèles des documents et production des PDF.
 *
 * Les fonctions these_*() correspondantes de locallib.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class doctemplates {
    /**
     * Documents produits à partir d'un modèle modifiable (doctemplates/, page d'administration
     * « Modèles des documents »), dans l'ordre de présentation.
     *
     * @return array type de modèle => document signé dont il reprend les signatures (null : aucun).
     */
    public static function doctemplate_types() {
        return [
            'subject' => 'subject',
            'agreement' => 'agreement',
            'signaletique' => 'signaletique',
            'bordereau' => 'bordereau',
            'grid' => 'grid',
            'conclusions' => 'conclusions',
            'contractdata' => 'contract',
            'contractsign' => 'contract',
            'conformity' => 'conformity',
            'convocation' => null,
            'jurysheet' => null,
            'minutes' => null,
            'planning' => null,
            'teachinglist' => null,
        ];
    }

    /**
     * Libellé d'un modèle de document.
     *
     * @param string $type
     * @return string
     */
    public static function doctemplate_label($type) {
        return get_string('doctpl' . $type, 'mod_these');
    }

    /**
     * Modèle d'origine d'un document, fourni avec le module (doctemplates/<type>.html).
     *
     * @param string $type
     * @return string
     */
    public static function doctemplate_default($type) {
        global $CFG;

        if (!array_key_exists($type, these_doctemplate_types())) {
            throw new coding_exception('Modèle de document inconnu : ' . $type);
        }
        return (string) file_get_contents($CFG->dirroot . '/mod/these/doctemplates/' . $type . '.html');
    }

    /**
     * Portée propre à une activité « Gestion des thèses » : ses propres modèles, images, grille et
     * questionnaire.
     *
     * @param stdClass $these
     * @return stdClass {theseid, synthesisid, shared, component, contextid, cmid, name}
     */
    public static function local_scope(stdClass $these) {
        $cm = !empty($these->id) ? get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING) : null;
        return (object) [
            'theseid' => (int) ($these->id ?? 0),
            'synthesisid' => 0,
            'shared' => false,
            'component' => 'mod_these',
            'contextid' => $cm ? context_module::instance($cm->id)->id : 0,
            'cmid' => $cm ? (int) $cm->id : 0,
            'name' => (string) ($these->name ?? ''),
        ];
    }

    /**
     * Portée d'une activité « Suivi des thèses » : ses modèles, images, grille et questionnaire valent
     * pour toutes les activités « Gestion des thèses » qu'elle suit.
     *
     * @param int $synthesisid
     * @return stdClass|null Voir these_local_scope().
     */
    public static function synthesis_scope($synthesisid) {
        global $DB;

        $synthesis = $DB->get_record('thesesynthesis', ['id' => $synthesisid]);
        $cm = $synthesis ? get_coursemodule_from_instance('thesesynthesis', $synthesis->id, 0, false, IGNORE_MISSING) : null;
        if (!$cm) {
            return null;
        }
        return (object) [
            'theseid' => 0,
            'synthesisid' => (int) $synthesis->id,
            'shared' => true,
            'component' => 'mod_thesesynthesis',
            'contextid' => context_module::instance($cm->id)->id,
            'cmid' => (int) $cm->id,
            'name' => (string) $synthesis->name,
        ];
    }

    /**
     * Portée des paramètres en vigueur pour une activité : l'activité « Suivi des thèses » qui la suit
     * (la plus ancienne si plusieurs la suivent), sinon l'activité elle-même. Les modèles des documents,
     * leurs images, la grille de conformité et le questionnaire préalable au jury sont ainsi définis une
     * fois pour toutes les promotions suivies.
     *
     * @param stdClass $these
     * @return stdClass Voir these_local_scope().
     */
    public static function settings_scope(stdClass $these) {
        global $DB;

        if (!empty($these->id) && get_config('mod_thesesynthesis', 'version')) {
            $cm = get_coursemodule_from_instance('these', $these->id, 0, false, IGNORE_MISSING);
            if ($cm) {
                $synthesisids = $DB->get_fieldset_sql(
                    "SELECT l.synthesisid
                       FROM {thesesynthesis_link} l
                       JOIN {thesesynthesis} t ON t.id = l.synthesisid
                      WHERE l.thesecmid = :cmid
                   ORDER BY l.synthesisid",
                    ['cmid' => $cm->id]
                );
                foreach ($synthesisids as $synthesisid) {
                    if ($scope = these_synthesis_scope($synthesisid)) {
                        return $scope;
                    }
                }
            }
        }
        return these_local_scope($these);
    }

    /**
     * Conditions de base de données désignant une portée.
     *
     * @param stdClass $scope
     * @return array
     */
    public static function scope_conditions(stdClass $scope) {
        return ['theseid' => (int) $scope->theseid, 'synthesisid' => (int) $scope->synthesisid];
    }

    /**
     * Modèle en vigueur d'un document dans une portée : celui modifié par la DEVE, sinon celui
     * d'origine.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param string $type
     * @return string
     */
    public static function get_doctemplate(stdClass $scope, $type) {
        global $DB;

        $custom = $DB->get_field('these_doctemplate', 'content', these_scope_conditions($scope) + ['doctype' => $type]);
        return is_string($custom) && trim($custom) !== '' ? $custom : these_doctemplate_default($type);
    }

    /**
     * Le modèle d'un document a-t-il été modifié dans la portée ?
     *
     * @param stdClass $scope
     * @param string $type
     * @return bool
     */
    public static function doctemplate_customised(stdClass $scope, $type) {
        global $DB;

        return $DB->record_exists('these_doctemplate', these_scope_conditions($scope) + ['doctype' => $type]);
    }

    /**
     * Enregistre le modèle d'un document d'une portée ; null, ou le modèle d'origine, rétablit celui-ci.
     *
     * @param stdClass $scope
     * @param string $type
     * @param string|null $content
     * @return void
     */
    public static function set_doctemplate(stdClass $scope, $type, $content) {
        global $DB;

        $normalise = function ($text) {
            return trim(str_replace("\r\n", "\n", (string) $text));
        };
        $conditions = these_scope_conditions($scope) + ['doctype' => $type];
        $existing = $DB->get_record('these_doctemplate', $conditions);
        if ($content === null || $normalise($content) === $normalise(these_doctemplate_default($type))) {
            if ($existing) {
                $DB->delete_records('these_doctemplate', ['id' => $existing->id]);
            }
            return;
        }
        $record = (object) ($conditions + ['content' => str_replace("\r\n", "\n", $content), 'timemodified' => time()]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('these_doctemplate', $record);
        } else {
            $DB->insert_record('these_doctemplate', $record);
        }
    }

    /**
     * Images appelées par les modèles d'origine, à déposer par la DEVE sous ces noms de fichier
     * (logo.png...). Leur rôle est décrit par les chaînes doctplimageslot{nom}.
     *
     * @return string[]
     */
    public static function doctemplate_image_slots() {
        return ['logo', 'ministere', 'universite', 'bibliotheque', 'abes'];
    }

    /**
     * Nom sous lequel une image est appelée dans les modèles ({{img.nom}}).
     *
     * @param string $filename
     * @return string
     */
    public static function doctemplate_image_name($filename) {
        return preg_replace('/[^a-z0-9]/', '', core_text::strtolower(pathinfo($filename, PATHINFO_FILENAME)));
    }

    /**
     * Images utilisables dans les modèles : celles déposées par la DEVE dans la portée. Le module n'en
     * fournit aucune ; les modèles d'origine appellent logo, ministere, universite, bibliotheque et
     * abes (voir doctemplate_image_slots()), et une image absente est omise du document.
     *
     * @param stdClass|null $scope null : aucune image.
     * @return array nom => source pour TCPDF ('@' suivi du contenu en base64)
     */
    public static function doctemplate_images(?stdClass $scope) {
        $images = [];
        if ($scope && $scope->contextid) {
            $fs = get_file_storage();
            foreach ($fs->get_area_files($scope->contextid, $scope->component, 'docimages', 0, 'filename', false) as $file) {
                if (in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                    $images[these_doctemplate_image_name($file->get_filename())] = '@' . base64_encode($file->get_content());
                }
            }
        }
        return $images;
    }

    /**
     * Données d'un dossier nécessaires à ses documents, lues en base.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string|null $doctype Document signé dont reprendre les signatures (null : aucun).
     * @return array Voir these_build_document_context().
     */
    public static function document_sources(stdClass $these, context $context, stdClass $dossier, $doctype) {
        global $DB;

        $signatures = [];
        if ($doctype !== null) {
            foreach (these_get_signatures($dossier->id) as $signature) {
                if ($signature->doctype === $doctype && !empty($signature->timesigned)) {
                    $verifier = !empty($signature->verifiedby) ? $DB->get_record('user', ['id' => $signature->verifiedby]) : null;
                    $validator = !empty($signature->validatedby)
                        ? $DB->get_record('user', ['id' => $signature->validatedby]) : null;
                    $signatures[$signature->slot] = [
                        'name' => $signature->signername,
                        'time' => (int) $signature->timesigned,
                        'hash' => substr((string) $signature->contenthash, 0, 12),
                        'image' => these_get_signature_image($context->id, $signature->id),
                        'method' => (string) ($signature->method ?? 'online'),
                        'delegated' => !empty($signature->delegated),
                        'verifier' => $verifier ? fullname($verifier) : '',
                        'validator' => $validator ? fullname($validator) : '',
                        'validatedtime' => (int) ($signature->validatedtime ?? 0),
                    ];
                }
            }
        }
        return [
            'these' => $these,
            'dossier' => $dossier,
            'student' => $DB->get_record('user', ['id' => $dossier->userid]) ?: null,
            'director' => $dossier->directorid ? ($DB->get_record('user', ['id' => $dossier->directorid]) ?: null) : null,
            'jury' => these_get_jury($dossier->id),
            'signatures' => $signatures,
            'recipient' => null,
            'scope' => these_settings_scope($these),
        ];
    }

    /**
     * Données d'un document, prêtes à être insérées dans son modèle.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string|null $doctype Document signé dont reprendre les signatures (null : aucun).
     * @param stdClass|null $recipient Destinataire d'une convocation : membre du jury, ou null pour
     *                                 l'étudiant.
     * @return array
     */
    public static function document_context(
        stdClass $these,
        context $context,
        stdClass $dossier,
        $doctype,
        ?stdClass $recipient = null
    ) {
        $sources = these_document_sources($these, $context, $dossier, $doctype);
        $sources['recipient'] = $recipient;
        return these_build_document_context($sources);
    }

    /**
     * Case à cocher des modèles (glyphes de FreeSerif : FreeSans ne les contient pas).
     *
     * @param bool $checked
     * @return string HTML
     */
    public static function doctemplate_box($checked) {
        return '<span style="font-family:freeserif;">' . ($checked ? '&#9746;' : '&#9744;') . '</span>';
    }

    /**
     * Texte multiligne pour un modèle : échappé, retours à la ligne conservés.
     *
     * @param string|null $text
     * @return string HTML
     */
    public static function doctemplate_text($text) {
        return nl2br(htmlspecialchars(trim((string) $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    }

    /**
     * Compose les données des modèles de documents. Toutes les valeurs sont du texte brut (échappé
     * par {{...}}), sauf celles dont le nom finit par « html » et les tableaux box, mark, sig, qui
     * contiennent du HTML à insérer par {{{...}}}. La liste complète, avec un exemple de valeur, est
     * présentée dans la page de modification d'un modèle.
     *
     * @param array $src {these, dossier, student, director, jury, signatures, recipient, scope}, voir
     *                   these_document_sources().
     * @return array
     */
    public static function build_document_context(array $src) {
        $these = $src['these'];
        $d = $src['dossier'];
        $b = these_get_bordereau($d);
        $agreementdata = these_get_agreement($d);
        $griddata = these_get_grid($d);
        $config = scopesettings::get_all($src['scope'] ?? these_settings_scope($these));

        $day = function ($time) {
            return (int) $time ? userdate((int) $time, get_string('strftimedatefullshort', 'langconfig')) : '';
        };
        $up = function ($text) {
            return core_text::strtoupper(trim((string) $text));
        };
        $schools = these_school_options();
        $school = function ($key) use ($schools) {
            return (string) $key !== '' ? ($schools[$key] ?? '') : '';
        };

        // Étudiant : noms de l'état civil déclaré au bordereau, à défaut ceux du compte.
        $user = $src['student'];
        $lastname = $up(($b['birthname'] ?? '') ?: ($user->lastname ?? ''));
        $firstname = trim((string) (($b['firstnames'] ?? '') ?: ($user->firstname ?? '')));
        $address = implode(', ', array_filter([
            trim((string) ($b['street'] ?? '')),
            trim(($b['postcode'] ?? '') . ' ' . ($b['city'] ?? '')),
            trim((string) ($b['country'] ?? '')),
        ], 'strlen'));
        $student = [
            'lastname' => $lastname,
            'firstname' => $firstname,
            'fullname' => trim($lastname . ' ' . $firstname),
            'email' => (string) ($user->email ?? ''),
            'usagename' => $up($b['usagename'] ?? ''),
            'publicationname' => $up($b['publicationname'] ?? ''),
            'birthdate' => $day($b['birthdate'] ?? 0),
            'birthdatecompact' => !empty($b['birthdate']) ? userdate((int) $b['birthdate'], '%d%m%Y') : '',
            'birthplace' => (string) ($b['birthplace'] ?? ''),
            'birthdept' => (string) ($b['birthdept'] ?? ''),
            'nationality' => (string) ($b['nationality'] ?? ''),
            'street' => (string) ($b['street'] ?? ''),
            'postcode' => (string) ($b['postcode'] ?? ''),
            'city' => (string) ($b['city'] ?? ''),
            'country' => (string) ($b['country'] ?? ''),
            'address' => $address,
            'phone' => (string) ($b['phone'] ?? ''),
            'personalemail' => (string) ($b['personalemail'] ?? ''),
            'emailconsent' => !empty($b['emailconsent']),
        ];

        // Directeur (membre de l'établissement) et directeur extérieur éventuel.
        $duser = $src['director'];
        $directorlast = $up($duser->lastname ?? '');
        $directorfirst = (string) ($duser->firstname ?? '');
        // Titre du directeur : celui de l'annuaire du jury s'il y figure, sinon celui saisi au dépôt du sujet.
        $directorjuror = $duser ? \mod_these\local\jurors::find_by_email($duser->email) : null;
        $directortitle = $directorjuror ? $directorjuror->title : (string) $d->directortitle;
        $director = [
            'title' => $directortitle,
            'lastname' => $directorlast,
            'firstname' => $directorfirst,
            'fullname' => trim($directortitle . ' ' . $directorlast . ' ' . $directorfirst),
            'email' => (string) ($duser->email ?? ''),
            'function' => (string) ($agreementdata['directorfunction'] ?? ''),
        ];
        $external = !empty($d->externaldirector);
        $extdirector = [
            'present' => $external,
            'name' => $external ? trim((string) $d->extdirectorname) : '',
            'grade' => $external ? trim((string) $d->extdirectorgrade) : '',
            'institution' => $external ? trim((string) $d->extdirectorinstitution) : '',
            'email' => $external ? trim((string) $d->extdirectoremail) : '',
        ];
        // Directeur de thèse au sens des formulaires : l'extérieur s'il y en a un.
        $thesisdirector = [
            'name' => $external ? $extdirector['name'] : $director['fullname'],
            'function' => $external ? $extdirector['grade'] : $director['function'],
            'external' => $external,
        ];

        // Thèse.
        $keywords = these_split_keywords((string) $d->keywords);
        $abeskeywords = array_slice(array_values((array) ($b['keywords'] ?? [])), 0, THESE_BORDEREAU_KEYWORDS);
        $thesis = [
            'title' => (string) $d->title,
            'titleupper' => $up($d->title),
            'titleen' => (string) $d->titleen,
            'summary' => (string) $d->summary,
            'summaryhtml' => these_doctemplate_text($d->summary),
            'keywords' => implode(' ; ', $keywords),
            'confidential' => !empty($d->confidential),
            // Titre à imprimer sur un document destiné à des personnes qui ne connaissent pas le sujet
            // d'une thèse confidentielle (modèles personnalisés).
            'publictitle' => !empty($d->confidential) ? get_string('confidentialtitle', 'mod_these') : (string) $d->title,
            'studyyear' => (string) $d->studyyear,
            'devenumber' => (string) $d->devenumber,
            'libraryref' => (string) $d->libraryref,
            'ethicsnumber' => trim((string) $d->ethicsnumber),
        ];
        $summary = function ($field) use ($b) {
            return core_text::substr((string) ($b[$field] ?? ''), 0, THESE_BORDEREAU_SUMMARY_MAX);
        };
        $abes = [
            'nbvolumes' => (string) (int) ($b['nbvolumes'] ?? 1 ?: 1),
            'nbpages' => !empty($b['nbpages']) ? (string) (int) $b['nbpages'] : '',
            'nbrefs' => !empty($b['nbrefs']) ? (string) (int) $b['nbrefs'] : '',
            // Résumés coupés à 1700 caractères, comme l'annonce le bordereau.
            'summaryfrhtml' => these_doctemplate_text($summary('summaryfr')),
            'summaryenhtml' => these_doctemplate_text($summary('summaryen')),
        ];
        for ($i = 1; $i <= THESE_BORDEREAU_KEYWORDS; $i++) {
            $abes['kw' . $i] = (string) ($abeskeywords[$i - 1] ?? '');
        }

        // Soutenance.
        $date = (int) ($d->defensedate ?: $d->proposeddate);
        $defense = [
            'date' => $day($date),
            'longdate' => $date ? userdate($date, get_string('strftimedaydate', 'langconfig')) : '',
            'time' => $date ? userdate($date, '%H:%M') : '',
            'year' => $date ? userdate($date, '%Y') : '',
            'month' => $date ? userdate($date, '%m') : '',
            'day' => $date ? userdate($date, '%d') : '',
            'location' => (string) $d->defenselocation,
            'visio' => !empty($b['visio']),
            'result' => these_result_label($d->defenseresult),
            'mention' => these_mention_label($d->mention),
            'prizeproposal' => !empty($d->prizeproposal),
            'corrections' => !empty($d->correctionsrequested),
            'correctionsnote' => (string) ($d->correctionsnote ?? ''),
        ];

        // Cursus (fiche signalétique) et agrément administratif de la DEVE. La validation des études
        // (DEFV, A6), liée à l'étudiant, est saisie par la DEVE (voir \mod_these\local\a6).
        $a6 = a6::for_documents((int) ($d->userid ?? 0), $agreementdata, $b);
        $a6notyet = $a6['notyet'] || !$a6['date'];
        $cursus = [
            'defvdate' => $day($a6['defvdate']),
            'defvschool' => $school($a6['defvschool']),
            'a6notyet' => $a6notyet,
            'a6date' => $a6notyet ? '' : $day($a6['date']),
            'a6school' => $a6notyet ? '' : $school($a6['school']),
        ];
        $agreement = [
            'defv' => !empty($a6['defvdate']),
            'defvdate' => $day($a6['defvdate']),
            'mobility' => !empty($agreementdata['mobility']),
            'a6notyet' => $a6notyet,
            'a6' => !$a6notyet,
            'a6date' => $a6notyet ? '' : $day($a6['date']),
        ];

        // Jury : liste complète, et places fixes des formulaires (président, membre de
        // l'établissement, deux autres membres, directeur, deux invités).
        $roles = these_jury_role_options();
        $empty = ['role' => '', 'rolelabel' => '', 'title' => '', 'fullname' => '', 'name' => '', 'institution' => '',
            'email' => ''];
        $jury = [];
        $president = $empty;
        $jurydirector = $empty;
        $members = [];
        $guests = [];
        foreach ($src['jury'] as $member) {
            $item = [
                'role' => $member->juryrole,
                'rolelabel' => $roles[$member->juryrole] ?? $member->juryrole,
                'title' => (string) $member->jurytitle,
                'fullname' => (string) $member->fullname,
                'name' => trim($member->jurytitle . ' ' . $member->fullname),
                'institution' => (string) $member->institution,
                'email' => (string) $member->email,
            ];
            $jury[] = $item;
            if ($member->juryrole === 'president') {
                $president = $item;
            } else if ($member->juryrole === 'director') {
                $jurydirector = $item;
            } else if ($member->juryrole === 'guest') {
                $guests[] = $item;
            } else {
                $members[] = $item;
            }
        }
        $institution = core_text::strtolower(trim((string) ($config['institution'] ?? '')));
        $local = $empty;
        foreach ($members as $index => $member) {
            if ($institution !== '' && strpos(core_text::strtolower($member['institution']), $institution) !== false) {
                $local = $member;
                unset($members[$index]);
                break;
            }
        }
        $members = array_values($members);
        if ($jurydirector['name'] === '') {
            $jurydirector = ['name' => $thesisdirector['name'], 'fullname' => $thesisdirector['name'],
                'institution' => $external ? $extdirector['institution'] : (string) ($config['institution'] ?? '')] + $empty;
        }
        // Membres votants : ni le directeur de thèse ni les invités.
        $voting = array_values(array_filter($jury, function ($item) {
            return !in_array($item['role'], ['director', 'guest'], true);
        }));

        // Grille et conclusions du directeur (son rapport est un fichier déposé, voir report.php).
        $grid = [];
        $mark = [];
        $gridsections = [];
        $checkmark = '<span style="font-family:freeserif;">&#10003;</span>';
        foreach (these_get_grid_items($src['scope'] ?? null) as $section => $items) {
            $rows = [];
            foreach ($items as $key => $item) {
                $checked = !empty($griddata[$key]);
                $grid[$key] = $checked;
                $mark[$key] = $checked ? $checkmark : '';
                $rows[] = ['key' => $key, 'label' => $item->label, 'checked' => $checked, 'mark' => $checked ? $checkmark : ''];
            }
            $gridsections[] = [
                'key' => $section,
                'first' => !$gridsections,
                'label' => get_string('gridsection' . $section, 'mod_these'),
                'pdflabel' => get_string('gridsectionpdf' . $section, 'mod_these'),
                'items' => $rows,
            ];
        }
        $conclusions = these_conclusions_context((string) $d->conclusions);

        // Contrat de diffusion.
        $embargo = !empty($b['embargo']);
        $contract = [
            'confidential' => !empty($d->confidential),
            'confidentialuntil' => !empty($d->confidential) ? $day($b['confidentialuntil'] ?? 0) : '',
            'embargo' => $embargo,
            'embargountil' => $embargo ? $day(these_embargo_until($d)) : '',
            'embargomonths' => $embargo ? signatures::embargo_months($b) : 0,
            'embargoreason' => $embargo ? trim((string) ($b['embargoreason'] ?? '')) : '',
            'internet' => ($b['diffusion'] ?? '') === 'internet',
            'restricted' => ($b['diffusion'] ?? '') === 'restricted',
            'templatemissing' => false,
        ];

        // Signatures : image et mention par emplacement, plus l'alias « thesisdirector ».
        $sig = [];
        $sigsrc = [];
        $sigcaption = [];
        $sigdate = [];
        $signer = [];
        $signed = [];
        $slots = ['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl', 'president'];
        foreach ($slots as $slot) {
            $signature = $src['signatures'][$slot] ?? null;
            $signed[$slot] = (bool) $signature;
            $sigdate[$slot] = $signature ? $day($signature['time']) : '';
            $signer[$slot] = $signature ? (string) $signature['name'] : '';
            $sigcaption[$slot] = $signature ? these_signature_caption($signature) : '';
            $sigsrc[$slot] = $signature && !empty($signature['image']) ? '@' . base64_encode($signature['image']) : '';
            $sig[$slot] = $signature ? ($sigsrc[$slot] !== '' ? '<img src="' . $sigsrc[$slot] . '" height="12mm" /><br />' : '')
                . '<span style="font-size:6pt;color:#555555;letter-spacing:0;">'
                . htmlspecialchars($sigcaption[$slot], ENT_QUOTES, 'UTF-8')
                . '</span>' : '';
        }
        $alias = $external ? 'extdirector' : 'director';
        $sig['thesisdirector'] = $sig[$alias];
        $sigsrc['thesisdirector'] = $sigsrc[$alias];
        $sigcaption['thesisdirector'] = $sigcaption[$alias];
        $sigdate['thesisdirector'] = $sigdate[$alias];
        $signer['thesisdirector'] = $signer[$alias];
        $signed['thesisdirector'] = $signed[$alias];

        // Destinataire d'une convocation.
        $recipient = $src['recipient'];
        $recipientdata = $recipient ? [
            'jury' => true,
            'name' => trim($recipient->jurytitle . ' ' . $recipient->fullname),
            'institution' => (string) $recipient->institution,
            'rolelabel' => $roles[$recipient->juryrole] ?? $recipient->juryrole,
        ] : ['jury' => false, 'name' => $student['fullname'], 'institution' => '', 'rolelabel' => ''];

        $years = [];
        foreach (['A2', 'A3', 'A4', 'A5', 'A6'] as $year) {
            $years[$year] = $thesis['studyyear'] === $year;
        }
        $commitments = (int) $d->commitments;
        $box = [
            'confidential' => $thesis['confidential'],
            'notconfidential' => !$thesis['confidential'],
            'visio' => $defense['visio'],
            'defv' => $agreement['defv'],
            'mobility' => $agreement['mobility'],
            'a6' => $agreement['a6'],
            'a6notyet' => $agreement['a6notyet'],
            'cursusa6notyet' => $cursus['a6notyet'],
            'embargo' => $embargo,
            'noembargo' => !$embargo,
            'internet' => $contract['internet'],
            'restricted' => $contract['restricted'],
            'favorable' => !empty($d->reportfavorable),
            'emailconsent' => $student['emailconsent'],
            'commitguide' => (bool) ($commitments & THESE_COMMIT_GUIDE),
            'commitduplicate' => (bool) ($commitments & THESE_COMMIT_DUPLICATE),
            'commitethics' => (bool) ($commitments & THESE_COMMIT_ETHICS),
            'admitted' => (int) $d->defenseresult === THESE_RESULT_ADMITTED,
            'adjourned' => (int) $d->defenseresult === THESE_RESULT_ADJOURNED,
            'honorable' => (string) $d->mention === 'honorable',
            'treshonorable' => (string) $d->mention === 'treshonorable',
            'felicitations' => (string) $d->mention === 'felicitations',
            'prize' => !empty($d->prizeproposal),
            'corrections' => !empty($d->correctionsrequested),
        ] + $years;

        return [
            'today' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
            'settings' => [
                'institution' => (string) ($config['institution'] ?? ''),
                'institutionupper' => $up($config['institution'] ?? ''),
                'university' => (string) ($config['university'] ?? ''),
                'visaplace' => (string) ($config['visaplace'] ?? ''),
                'hetopurl' => (string) ($config['hetopurl'] ?? ''),
                'devename' => (string) ($config['devename'] ?? ''),
                'devetitle' => (string) ($config['devetitle'] ?? ''),
                'dgtitle' => (string) ($config['dgtitle'] ?? ''),
                'dgname' => (string) ($config['dgname'] ?? ''),
                'ccemname' => (string) ($config['ccemname'] ?? ''),
                'ucbltitle' => (string) ($config['ucbltitle'] ?? ''),
                'ucblname' => (string) ($config['ucblname'] ?? ''),
            ],
            'establishment' => [
                'name' => (string) $these->establishmentname,
                'addresshtml' => these_doctemplate_text($these->establishmentaddress),
                'city' => (string) $these->establishmentcity,
                'signatoryname' => (string) $these->signatoryname,
                'signatorytitle' => (string) $these->signatorytitle,
                'deveemail' => (string) ($these->deveemail ?? ''),
            ],
            'student' => $student,
            'director' => $director,
            'extdirector' => $extdirector,
            'thesisdirector' => $thesisdirector,
            'thesis' => $thesis,
            'abes' => $abes,
            'defense' => $defense,
            'cursus' => $cursus,
            'agreement' => $agreement,
            'year' => $years,
            'jury' => $jury,
            'juryvoting' => $voting,
            'jurypresident' => $president,
            'jurydirector' => $jurydirector,
            'jurylocal' => $local,
            'jurymember1' => $members[0] ?? $empty,
            'jurymember2' => $members[1] ?? $empty,
            'juryothers' => $members,
            'juryguest1' => $guests[0] ?? $empty,
            'juryguest2' => $guests[1] ?? $empty,
            'juryguests' => $guests,
            'grid' => $grid,
            'mark' => $mark,
            'gridsections' => $gridsections,
            'conclusions' => $conclusions,
            'contract' => $contract,
            'recipient' => $recipientdata,
            'box' => array_map('these_doctemplate_box', $box),
            'checked' => these_doctemplate_box(true),
            'unchecked' => these_doctemplate_box(false),
            'check' => '<span style="font-family:freeserif;">&#10003;</span>',
            'sig' => $sig,
            'sigsrc' => $sigsrc,
            'sigcaption' => $sigcaption,
            'sigdate' => $sigdate,
            'signer' => $signer,
            'signed' => $signed,
            'img' => these_doctemplate_images($src['scope'] ?? null),
        ];
    }

    /**
     * Mention imprimée sous une signature : manière de signer (en ligne, sur place avec identité
     * vérifiée par la DEVE, à la main avec scan joint au dossier), délégation, date et empreinte.
     *
     * Une signature d'agent DEVE (direction générale par délégation comprise) précise le compte de
     * l'équipe DEVE qui a validé les documents.
     *
     * @param array $signature {name, time, hash, method?, delegated?, verifier?, validator?, validatedtime?}
     * @return string
     */
    public static function signature_caption(array $signature) {
        $method = in_array($signature['method'] ?? '', ['inperson', 'scan'], true) ? $signature['method'] : 'online';
        $name = $signature['name'];
        if (!empty($signature['delegated'])) {
            $name = get_string('pdfdelegated', 'mod_these', $name);
        }
        $format = $method === 'scan' ? get_string('strftimedate', 'langconfig') : get_string('strftimedatetimeshort', 'langconfig');
        $caption = get_string($method === 'online' ? 'pdfsignedby' : 'pdfsignedby' . $method, 'mod_these', (object) [
            'name' => $name,
            'date' => userdate($signature['time'], $format),
            'hash' => $signature['hash'],
            'verifier' => ($signature['verifier'] ?? '') !== '' ? $signature['verifier'] : '-',
        ]);
        if (($signature['validator'] ?? '') !== '') {
            $caption .= ' — ' . (!empty($signature['validatedtime'])
                ? get_string('pdfvalidatedbyon', 'mod_these', (object) [
                    'name' => $signature['validator'],
                    'date' => userdate($signature['validatedtime'], get_string('strftimedatetimeshort', 'langconfig')),
                ])
                : get_string('pdfvalidatedby', 'mod_these', $signature['validator']));
        }
        return $caption;
    }

    /**
     * Conclusions prêtes pour le modèle : paragraphes (séparés par une ligne vide), le dernier à part.
     *
     * @param string $text
     * @return array {html, bodyhtml, lasthtml}
     */
    public static function conclusions_context($text) {
        $paragraphs = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", trim($text))), 'strlen'));
        $paragraphs = array_map('these_doctemplate_text', $paragraphs);
        $last = $paragraphs ? array_pop($paragraphs) : '';
        return [
            'html' => implode('<br /><br />', array_merge($paragraphs, $last !== '' ? [$last] : [])),
            'bodyhtml' => implode('<br /><br />', $paragraphs),
            'lasthtml' => $last,
        ];
    }

    /**
     * Vérifie que des conclusions tiennent sur une page, signatures comprises : longueur maximale,
     * puis mise en page réelle avec le modèle de l'activité.
     *
     * @param stdClass $these
     * @param context|null $context
     * @param string $text
     * @return string|null Message d'erreur, ou null si elles tiennent.
     */
    public static function conclusions_error(stdClass $these, ?context $context, $text) {
        $length = core_text::strlen(trim((string) $text));
        if ($length > THESE_CONCLUSIONS_MAX) {
            return get_string('conclusionstoolong', 'mod_these', (object) ['max' => THESE_CONCLUSIONS_MAX, 'length' => $length]);
        }
        $data = these_sample_document_context(these_settings_scope($these), $these);
        $data['conclusions'] = these_conclusions_context((string) $text);
        try {
            $pages = \mod_these\pdf\html_document::page_count(these_render_doctemplate($these, 'conclusions', $data));
        } catch (\Throwable $e) {
            return null;
        }
        return $pages > 1 ? get_string('conclusionsonepage', 'mod_these') : null;
    }

    /**
     * Produit le PDF d'un modèle de document, avec le modèle en vigueur pour l'activité (voir
     * these_settings_scope()).
     *
     * @param stdClass $these Activité.
     * @param string $type Voir these_doctemplate_types().
     * @param array $data Voir these_build_document_context().
     * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
     * @return string Contenu PDF.
     */
    public static function render_doctemplate(stdClass $these, $type, array $data, $template = null) {
        return these_render_scope_doctemplate(these_settings_scope($these), $type, $data, $template);
    }

    /**
     * Produit le PDF d'un modèle de document d'une portée.
     *
     * @param stdClass|null $scope Voir these_settings_scope() ; null : modèle d'origine.
     * @param string $type
     * @param array $data
     * @param string|null $template Modèle à utiliser à la place de celui en vigueur (aperçu).
     * @return string Contenu PDF.
     */
    public static function render_scope_doctemplate(?stdClass $scope, $type, array $data, $template = null) {
        return \mod_these\pdf\html_document::render(
            $template ?? ($scope ? these_get_doctemplate($scope, $type) : these_doctemplate_default($type)),
            $data,
            these_doctemplate_label($type)
        );
    }

    /**
     * Produit le PDF d'un document signé.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @return string Contenu PDF.
     */
    public static function signed_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
        if (!array_key_exists($doctype, these_document_definitions())) {
            throw new coding_exception('Document inconnu : ' . $doctype);
        }
        // Document entièrement signé : le PDF scellé fait foi (scellé au besoin pour un document signé
        // avant l'apparition des scellés).
        $sealed = these_seal_document($context, $dossier, $doctype);
        if ($sealed) {
            return $sealed->get_content();
        }
        return these_render_document_pdf($these, $context, $dossier, $doctype);
    }

    /**
     * Produit le PDF d'un document signé à partir de son modèle en vigueur, sans tenir compte du
     * scellé.
     *
     * @param stdClass $these
     * @param context $context
     * @param stdClass $dossier
     * @param string $doctype
     * @return string Contenu PDF.
     */
    public static function render_document_pdf(stdClass $these, context $context, stdClass $dossier, $doctype) {
        $data = these_document_context($these, $context, $dossier, $doctype);
        if ($doctype !== 'contract') {
            return these_render_doctemplate($these, $doctype, $data);
        }

        // Contrat de diffusion, comme les conventions de mod_stage : page de données et de choix,
        // contrat type officiel déposé dans les paramètres, page de signatures.
        $template = these_get_contract_template($these);
        if ($template !== null) {
            try {
                return these_merge_pdfs([
                    these_render_doctemplate($these, 'contractdata', $data),
                    $template,
                    these_render_doctemplate($these, 'contractsign', $data),
                ]);
            } catch (\Throwable $e) {
                // Contrat type illisible par l'analyseur libre de FPDI (certaines compressions récentes) :
                // les pages du module restent produites, avec un avertissement à la place du contrat, et
                // le document n'est pas scellé (voir these_contract_template_problem()).
                debugging('Contrat type de diffusion illisible : ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $data['contract']['templatemissing'] = true;
        return these_merge_pdfs([
            these_render_doctemplate($these, 'contractdata', $data),
            these_render_doctemplate($these, 'contractsign', $data),
        ]);
    }

    /**
     * Problème du contrat type de diffusion déposé dans les paramètres de l'activité, qui empêche de
     * produire le contrat complet : sans lui, le contrat n'est pas scellé et la DEVE en est avertie.
     *
     * @param stdClass $these
     * @return string|null 'contracttemplatemissing', 'contracttemplateunreadable' ou null.
     */
    public static function contract_template_problem(stdClass $these) {
        $template = these_get_contract_template($these);
        if ($template === null) {
            return 'contracttemplatemissing';
        }
        try {
            these_merge_pdfs([$template]);
        } catch (\Throwable $e) {
            return 'contracttemplateunreadable';
        }
        return null;
    }

    /**
     * Données d'exemple d'un modèle : celles d'un dossier, celles d'un mois de soutenances pour le
     * planning mensuel, ou une liste d'enseignants pour la liste du corps enseignant.
     *
     * @param stdClass|null $scope
     * @param string $type Voir these_doctemplate_types().
     * @param stdClass|null $activity Activité dont reprendre l'établissement.
     * @return array
     */
    public static function doctemplate_sample_context(?stdClass $scope, $type, ?stdClass $activity = null) {
        if ($type === 'planning') {
            return these_sample_planning_context($scope, $activity);
        }
        if ($type === 'teachinglist') {
            return jurors::sample_teaching_list_context($scope, $activity);
        }
        return these_sample_document_context($scope, $activity);
    }

    /**
     * Vérifie qu'un modèle produit un document avec les données d'exemple.
     *
     * @param stdClass $scope Voir these_settings_scope().
     * @param string $type
     * @param string $content
     * @param stdClass|null $activity Activité dont reprendre l'établissement dans l'exemple.
     * @return string|null Message d'erreur, ou null si le modèle est utilisable.
     */
    public static function doctemplate_error(stdClass $scope, $type, $content, ?stdClass $activity = null) {
        try {
            these_render_scope_doctemplate($scope, $type, these_doctemplate_sample_context($scope, $type, $activity), $content);
        } catch (\Throwable $e) {
            return get_string('doctplerror', 'mod_these', $e->getMessage());
        }
        return null;
    }

    /**
     * Données fictives pour l'aperçu d'un modèle : un dossier complet, signé à tous les emplacements.
     *
     * @param stdClass|null $scope Portée des paramètres (images, grille) ; null : ceux d'origine.
     * @param stdClass|null $activity Activité dont reprendre l'établissement (sinon des valeurs fictives).
     * @return array Voir these_build_document_context().
     */
    public static function sample_document_context(?stdClass $scope = null, ?stdClass $activity = null) {
        $now = time();
        $these = $activity ?? (object) [
            'establishmentname' => 'École vétérinaire', 'establishmentaddress' => "1 rue de l'École\n00000 Ville",
            'establishmentcity' => 'Ville', 'signatoryname' => 'Prénom NOM',
            'signatorytitle' => 'Directrice des Études et de la Vie Étudiante', 'deveemail' => 'deve@example.org',
        ];
        $bordereau = [
            'birthname' => 'Martin', 'firstnames' => 'Camille', 'usagename' => '', 'publicationname' => '',
            'birthdate' => make_timestamp(1999, 4, 12), 'birthplace' => 'Lyon', 'birthdept' => '69', 'nationality' => 'Française',
            'street' => '12 rue des Écoles', 'postcode' => '69007', 'city' => 'Lyon', 'country' => 'France',
            'phone' => '06 12 34 56 78', 'personalemail' => 'camille.martin@example.org', 'emailconsent' => 1,
            'defvdate' => make_timestamp(2023, 7, 3), 'defvschool' => 'lyon', 'a6date' => make_timestamp(2025, 7, 1),
            'a6school' => 'lyon', 'a6notyet' => 0, 'nbvolumes' => 1, 'nbpages' => 112, 'nbrefs' => 86,
            'summaryfr' => str_repeat("Ce travail étudie la prise en charge de la douleur chronique chez le chien âgé. ", 12),
            'summaryen' => str_repeat("This work studies the management of chronic pain in elderly dogs. ", 12),
            'keywords' => ['Chien', 'Douleur chronique', 'Gériatrie', 'Analgésie', 'Arthrose'],
            'confidentialuntil' => 0, 'embargo' => 0, 'embargountil' => 0, 'diffusion' => 'internet', 'visio' => 0,
        ];
        $dossier = (object) [
            'id' => 0, 'userid' => 0, 'directorid' => 0,
            'title' => 'Prise en charge de la douleur chronique chez le chien âgé : enquête auprès des vétérinaires praticiens',
            'titleen' => 'Management of chronic pain in elderly dogs: a survey of veterinary practitioners',
            'summary' => 'Résumé du sujet.', 'keywords' => 'Chien ; Douleur chronique ; Gériatrie ; Analgésie',
            'studyyear' => 'A5', 'confidential' => 0, 'directortitle' => 'Pr',
            'externaldirector' => 0, 'extdirectorname' => '', 'extdirectorgrade' => '', 'extdirectorinstitution' => '',
            'extdirectoremail' => '', 'commitments' => THESE_COMMIT_GUIDE | THESE_COMMIT_DUPLICATE, 'ethicsnumber' => '2526',
            'bordereau' => json_encode($bordereau),
            'conclusions' => "Premier paragraphe des conclusions.\n\n"
                . "Dernier paragraphe des conclusions, qui reste avec les signatures.",
            'reportfavorable' => 1,
            'conformitygrid' => json_encode(array_fill_keys(
                array_merge(...array_map('array_keys', array_values(these_get_grid_items($scope)))),
                1
            )),
            'agreement' => json_encode(['defvdate' => make_timestamp(2023, 7, 3), 'mobility' => 1,
                'a6date' => make_timestamp(2025, 7, 1), 'a6notyet' => 0, 'directorfunction' => 'Professeur']),
            'devenumber' => '2026-042', 'libraryref' => 'THV-2026-042', 'proposeddate' => $now + 30 * DAYSECS,
            'defensedate' => $now + 30 * DAYSECS, 'defenselocation' => 'Amphithéâtre A', 'defenseresult' => 0,
            'mention' => '',
        ];
        $jury = [];
        foreach (
            [
            ['president', 'Pr', 'Jean DUPONT', 'Université'],
            ['member', 'Dr', 'Claire BERNARD', 'École vétérinaire'],
            ['member', 'Dr', 'Paul LEROY', 'École vétérinaire'],
            ['director', 'Pr', 'Anne DURAND', 'École vétérinaire'],
            ['guest', 'Dr', 'Luc PETIT', 'Clinique vétérinaire des Monts'],
            ] as $i => [$role, $title, $name, $institution]
        ) {
            $jury[] = (object) ['juryrole' => $role, 'jurytitle' => $title, 'fullname' => $name, 'institution' => $institution,
                'email' => 'jury' . $i . '@example.org'];
        }
        $image = these_sample_signature_image();
        $signatures = [];
        foreach (['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl', 'president'] as $slot) {
            $signatures[$slot] = ['name' => these_slot_label($slot), 'time' => $now, 'hash' => '3f2a9c51d0e4',
                'image' => $image];
        }
        return these_build_document_context([
            'these' => $these,
            'dossier' => $dossier,
            'student' => (object) ['firstname' => 'Camille', 'lastname' => 'Martin', 'email' => 'camille.martin@example.org'],
            'director' => (object) ['firstname' => 'Anne', 'lastname' => 'Durand', 'email' => 'anne.durand@example.org'],
            'jury' => $jury,
            'signatures' => $signatures,
            'recipient' => null,
            'scope' => $scope,
        ]);
    }

    /**
     * Tracé de signature fictif (PNG) pour les aperçus.
     *
     * @return string
     */
    public static function sample_signature_image() {
        $image = imagecreatetruecolor(300, 100);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        $ink = imagecolorallocate($image, 20, 40, 120);
        imagesetthickness($image, 3);
        $previous = null;
        for ($x = 20; $x <= 280; $x += 4) {
            $point = [$x, (int) (50 + 28 * sin($x / 18) * cos($x / 47))];
            if ($previous) {
                imageline($image, $previous[0], $previous[1], $point[0], $point[1], $ink);
            }
            $previous = $point;
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /**
     * Variables d'un contexte de modèle, à plat, avec un exemple de valeur : aide de la page de
     * modification d'un modèle.
     *
     * @param array $data
     * @param string $prefix
     * @return array nom => exemple (texte)
     */
    public static function doctemplate_variables(array $data, $prefix = '') {
        $variables = [];
        foreach ($data as $key => $value) {
            $name = $prefix . $key;
            if ($key === 'img') {
                foreach (array_keys($value) as $image) {
                    $variables[$name . '.' . $image] = get_string('doctplvarimage', 'mod_these');
                }
            } else if (is_array($value) && isset($value[0]) && is_array($value[0])) {
                $variables[$name] = get_string('doctplvarlist', 'mod_these', implode(', ', array_keys($value[0])));
            } else if (is_array($value) && !isset($value[0])) {
                $variables += these_doctemplate_variables($value, $name . '.');
            } else if (is_bool($value)) {
                $variables[$name] = get_string('doctplvarbool', 'mod_these', $value ? 'true' : 'false');
            } else if (is_scalar($value)) {
                $value = (string) $value;
                $variables[$name] = strpos($value, '<') !== false ? get_string('doctplvarhtml', 'mod_these')
                    : core_text::substr($value, 0, 60) . (core_text::strlen($value) > 60 ? '…' : '');
            }
        }
        return $variables;
    }

    /**
     * Contrat type de diffusion électronique (PDF officiel), déposé dans les paramètres de l'activité
     * « Suivi des thèses » qui suit l'activité, sinon de l'activité elle-même.
     *
     * @param stdClass $these
     * @return string|null Contenu du PDF.
     */
    public static function get_contract_template(stdClass $these) {
        return scopesettings::contract_template(these_settings_scope($these));
    }

    /**
     * Assemble plusieurs PDF en un seul, page à page et au format de chaque page (FPDI, fournie dans
     * thirdparty/, comme dans mod_stage).
     *
     * @param string[] $sources Contenus PDF, dans l'ordre.
     * @return string Contenu PDF assemblé.
     */
    public static function merge_pdfs(array $sources) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->dirroot . '/mod/these/thirdparty/vendor/autoload.php');

        $merger = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $merger->setPrintHeader(false);
        $merger->setPrintFooter(false);
        foreach ($sources as $source) {
            $pagecount = $merger->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($source));
            for ($pageno = 1; $pageno <= $pagecount; $pageno++) {
                $tplidx = $merger->importPage($pageno);
                $size = $merger->getTemplateSize($tplidx);
                $merger->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merger->useTemplate($tplidx);
            }
        }
        return $merger->Output('', 'S');
    }
}
