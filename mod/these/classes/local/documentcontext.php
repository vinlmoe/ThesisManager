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
 * Données des modèles de documents : lecture des sources d'un dossier, composition du contexte
 * bloc par bloc (établissement, étudiant, directeur, thèse, soutenance, jury, grille, contrat,
 * signatures...), données d'exemple des aperçus et liste des variables.
 *
 * Les fonctions these_*() correspondantes de locallib/doctemplates.php sont des façades de ces méthodes.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documentcontext {
    /** Coche des modèles (glyphe de FreeSerif). */
    public const CHECKMARK = '<span style="font-family:freeserif;">&#10003;</span>';

    /** Emplacements de signature repris dans les modèles (voir signatures::document_definitions()). */
    public const SIGNATURE_SLOTS = ['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl',
        'president'];

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
            foreach (signatures::get_signatures($dossier->id) as $signature) {
                if ($signature->doctype === $doctype && !empty($signature->timesigned)) {
                    $verifier = !empty($signature->verifiedby) ? $DB->get_record('user', ['id' => $signature->verifiedby]) : null;
                    $validator = !empty($signature->validatedby)
                        ? $DB->get_record('user', ['id' => $signature->validatedby]) : null;
                    $signatures[$signature->slot] = [
                        'name' => $signature->signername,
                        'time' => (int) $signature->timesigned,
                        'hash' => substr((string) $signature->contenthash, 0, 12),
                        'image' => signatures::get_signature_image($context->id, $signature->id),
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
            'jury' => jury::get_jury($dossier->id),
            'signatures' => $signatures,
            'recipient' => null,
            'scope' => doctemplates::settings_scope($these),
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
        $sources = self::document_sources($these, $context, $dossier, $doctype);
        $sources['recipient'] = $recipient;
        return self::build_document_context($sources);
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
        $b = bordereau::get_bordereau($d);
        $agreementdata = signatures::get_agreement($d);
        $config = scopesettings::get_all($src['scope'] ?? doctemplates::settings_scope($these));

        $student = self::student_context($b, $src['student']);
        [$director, $extdirector, $thesisdirector] = self::director_context($d, $src['director'], $agreementdata);
        $external = !empty($d->externaldirector);
        $thesis = self::thesis_context($d);
        $defense = self::defense_context($d, $b);
        [$cursus, $agreement] = self::cursus_context($d, $agreementdata, $b);
        $jury = self::jury_context($src['jury'], $config, $thesisdirector, $extdirector);
        $contract = self::contract_context($d, $b);
        $sig = self::signatures_context($src['signatures'], $external);
        $years = [];
        foreach (['A2', 'A3', 'A4', 'A5', 'A6'] as $year) {
            $years[$year] = $thesis['studyyear'] === $year;
        }
        $box = self::box_context($d, $thesis, $defense, $cursus, $agreement, $contract, $student) + $years;

        return [
            'today' => userdate(time(), get_string('strftimedatefullshort', 'langconfig')),
            'settings' => self::settings_context($config),
            'establishment' => [
                'name' => (string) $these->establishmentname,
                'addresshtml' => self::doctemplate_text($these->establishmentaddress),
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
            'abes' => self::abes_context($b),
            'defense' => $defense,
            'cursus' => $cursus,
            'agreement' => $agreement,
            'year' => $years,
        ] + $jury + self::grid_context($src['scope'] ?? null, grid::get_grid($d)) + [
            'conclusions' => self::conclusions_context((string) $d->conclusions),
            'contract' => $contract,
            'recipient' => self::recipient_context($src['recipient'], $jury['jury'], $student),
            'box' => array_map([self::class, 'doctemplate_box'], $box),
            'checked' => self::doctemplate_box(true),
            'unchecked' => self::doctemplate_box(false),
            'check' => self::CHECKMARK,
        ] + $sig + [
            'img' => doctemplates::doctemplate_images($src['scope'] ?? null),
        ];
    }

    /**
     * Date courte d'un horodatage, '' s'il est nul.
     *
     * @param int|string|null $time
     * @return string
     */
    protected static function day($time) {
        return (int) $time ? userdate((int) $time, get_string('strftimedatefullshort', 'langconfig')) : '';
    }

    /**
     * Texte en capitales, sans espaces superflus.
     *
     * @param string|null $text
     * @return string
     */
    protected static function upper($text) {
        return core_text::strtoupper(trim((string) $text));
    }

    /**
     * Libellé d'une école nationale vétérinaire, '' si la clé est vide ou inconnue.
     *
     * @param string|null $key
     * @return string
     */
    protected static function school_label($key) {
        return (string) $key !== '' ? (bordereau::school_options()[$key] ?? '') : '';
    }

    /**
     * Étudiant : noms de l'état civil déclaré au bordereau, à défaut ceux du compte, et coordonnées.
     *
     * @param array $b Bordereau.
     * @param stdClass|null $user Compte de l'étudiant.
     * @return array
     */
    protected static function student_context(array $b, ?stdClass $user) {
        $lastname = self::upper(($b['birthname'] ?? '') ?: ($user->lastname ?? ''));
        $firstname = trim((string) (($b['firstnames'] ?? '') ?: ($user->firstname ?? '')));
        $address = implode(', ', array_filter([
            trim((string) ($b['street'] ?? '')),
            trim(($b['postcode'] ?? '') . ' ' . ($b['city'] ?? '')),
            trim((string) ($b['country'] ?? '')),
        ], 'strlen'));
        return [
            'lastname' => $lastname,
            'firstname' => $firstname,
            'fullname' => trim($lastname . ' ' . $firstname),
            'email' => (string) ($user->email ?? ''),
            'usagename' => self::upper($b['usagename'] ?? ''),
            'publicationname' => self::upper($b['publicationname'] ?? ''),
            'birthdate' => self::day($b['birthdate'] ?? 0),
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
    }

    /**
     * Directeur (membre de l'établissement), directeur extérieur éventuel, et directeur de thèse au
     * sens des formulaires (l'extérieur s'il y en a un).
     *
     * @param stdClass $d Dossier.
     * @param stdClass|null $duser Compte du directeur.
     * @param array $agreementdata Agrément administratif (voir signatures::get_agreement()).
     * @return array [director, extdirector, thesisdirector]
     */
    protected static function director_context(stdClass $d, ?stdClass $duser, array $agreementdata) {
        $directorlast = self::upper($duser->lastname ?? '');
        $directorfirst = (string) ($duser->firstname ?? '');
        // Titre du directeur : celui de l'annuaire du jury s'il y figure, sinon celui saisi au dépôt du sujet.
        $directorjuror = $duser ? jurors::find_by_email($duser->email) : null;
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
        $thesisdirector = [
            'name' => $external ? $extdirector['name'] : $director['fullname'],
            'function' => $external ? $extdirector['grade'] : $director['function'],
            'external' => $external,
        ];
        return [$director, $extdirector, $thesisdirector];
    }

    /**
     * Thèse : titre, résumé, mots-clés, références.
     *
     * @param stdClass $d Dossier.
     * @return array
     */
    protected static function thesis_context(stdClass $d) {
        $keywords = dossiers::split_keywords((string) $d->keywords);
        return [
            'title' => (string) $d->title,
            'titleupper' => self::upper($d->title),
            'titleen' => (string) $d->titleen,
            'summary' => (string) $d->summary,
            'summaryhtml' => self::doctemplate_text($d->summary),
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
    }

    /**
     * Bordereau ABES : volumes, pages, références, résumés coupés à la longueur annoncée, mots-clés.
     *
     * @param array $b Bordereau.
     * @return array
     */
    protected static function abes_context(array $b) {
        $abeskeywords = array_slice(array_values((array) ($b['keywords'] ?? [])), 0, bordereau::BORDEREAU_KEYWORDS);
        $summary = function ($field) use ($b) {
            return core_text::substr((string) ($b[$field] ?? ''), 0, bordereau::BORDEREAU_SUMMARY_MAX);
        };
        $abes = [
            'nbvolumes' => (string) (int) ($b['nbvolumes'] ?? 1 ?: 1),
            'nbpages' => !empty($b['nbpages']) ? (string) (int) $b['nbpages'] : '',
            'nbrefs' => !empty($b['nbrefs']) ? (string) (int) $b['nbrefs'] : '',
            // Résumés coupés à 1700 caractères, comme l'annonce le bordereau.
            'summaryfrhtml' => self::doctemplate_text($summary('summaryfr')),
            'summaryenhtml' => self::doctemplate_text($summary('summaryen')),
        ];
        for ($i = 1; $i <= bordereau::BORDEREAU_KEYWORDS; $i++) {
            $abes['kw' . $i] = (string) ($abeskeywords[$i - 1] ?? '');
        }
        return $abes;
    }

    /**
     * Soutenance : date (arrêtée, à défaut proposée), lieu, résultat.
     *
     * @param stdClass $d Dossier.
     * @param array $b Bordereau.
     * @return array
     */
    protected static function defense_context(stdClass $d, array $b) {
        $date = (int) ($d->defensedate ?: $d->proposeddate);
        return [
            'date' => self::day($date),
            'longdate' => $date ? userdate($date, get_string('strftimedaydate', 'langconfig')) : '',
            'time' => $date ? userdate($date, '%H:%M') : '',
            'year' => $date ? userdate($date, '%Y') : '',
            'month' => $date ? userdate($date, '%m') : '',
            'day' => $date ? userdate($date, '%d') : '',
            'location' => (string) $d->defenselocation,
            'visio' => !empty($b['visio']),
            'result' => workflow::result_label($d->defenseresult),
            'mention' => workflow::mention_label($d->mention),
            'prizeproposal' => !empty($d->prizeproposal),
            'corrections' => !empty($d->correctionsrequested),
            'correctionsnote' => (string) ($d->correctionsnote ?? ''),
        ];
    }

    /**
     * Cursus (fiche signalétique) et agrément administratif de la DEVE. La validation des études
     * (DEFV, A6), liée à l'étudiant, est saisie par la DEVE (voir \mod_these\local\a6).
     *
     * @param stdClass $d Dossier.
     * @param array $agreementdata Agrément administratif.
     * @param array $b Bordereau.
     * @return array [cursus, agreement]
     */
    protected static function cursus_context(stdClass $d, array $agreementdata, array $b) {
        $a6 = a6::for_documents((int) ($d->userid ?? 0), $agreementdata, $b);
        $a6notyet = $a6['notyet'] || !$a6['date'];
        $cursus = [
            'defvdate' => self::day($a6['defvdate']),
            'defvschool' => self::school_label($a6['defvschool']),
            'a6notyet' => $a6notyet,
            'a6date' => $a6notyet ? '' : self::day($a6['date']),
            'a6school' => $a6notyet ? '' : self::school_label($a6['school']),
        ];
        $agreement = [
            'defv' => !empty($a6['defvdate']),
            'defvdate' => self::day($a6['defvdate']),
            'mobility' => !empty($agreementdata['mobility']),
            'a6notyet' => $a6notyet,
            'a6' => !$a6notyet,
            'a6date' => $a6notyet ? '' : self::day($a6['date']),
        ];
        return [$cursus, $agreement];
    }

    /**
     * Jury : liste complète, membres votants, et places fixes des formulaires (président, membre de
     * l'établissement, deux autres membres, directeur, deux invités).
     *
     * @param stdClass[] $jurymembers Voir jury::get_jury().
     * @param array $config Paramètres de la portée (institution).
     * @param array $thesisdirector Voir director_context().
     * @param array $extdirector Voir director_context().
     * @return array Clés jury, juryvoting, jurypresident, jurydirector, jurylocal, jurymember1,
     *               jurymember2, juryothers, juryguest1, juryguest2, juryguests.
     */
    protected static function jury_context(array $jurymembers, array $config, array $thesisdirector, array $extdirector) {
        $roles = workflow::jury_role_options();
        $empty = ['role' => '', 'rolelabel' => '', 'title' => '', 'fullname' => '', 'name' => '', 'institution' => '',
            'email' => ''];
        $jury = [];
        $president = $empty;
        $jurydirector = $empty;
        $members = [];
        $guests = [];
        foreach ($jurymembers as $member) {
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
                'institution' => $thesisdirector['external'] ? $extdirector['institution']
                    : (string) ($config['institution'] ?? '')] + $empty;
        }
        // Membres votants : ni le directeur de thèse ni les invités.
        $voting = array_values(array_filter($jury, function ($item) {
            return !in_array($item['role'], ['director', 'guest'], true);
        }));
        return [
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
        ];
    }

    /**
     * Grille de conformité du directeur (son rapport est un fichier déposé, voir report.php) : état
     * de chaque élément, coche, et rubriques pour les modèles.
     *
     * @param stdClass|null $scope Portée des éléments de la grille.
     * @param array $griddata Voir grid::get_grid().
     * @return array Clés grid, mark, gridsections.
     */
    protected static function grid_context(?stdClass $scope, array $griddata) {
        $grid = [];
        $mark = [];
        $gridsections = [];
        foreach (grid::get_grid_items($scope) as $section => $items) {
            $rows = [];
            foreach ($items as $key => $item) {
                $checked = !empty($griddata[$key]);
                $grid[$key] = $checked;
                $mark[$key] = $checked ? self::CHECKMARK : '';
                $rows[] = ['key' => $key, 'label' => $item->label, 'checked' => $checked, 'mark' => $mark[$key]];
            }
            $gridsections[] = [
                'key' => $section,
                'first' => !$gridsections,
                'label' => get_string('gridsection' . $section, 'mod_these'),
                'pdflabel' => get_string('gridsectionpdf' . $section, 'mod_these'),
                'items' => $rows,
            ];
        }
        return ['grid' => $grid, 'mark' => $mark, 'gridsections' => $gridsections];
    }

    /**
     * Contrat de diffusion : confidentialité, embargo, mode de diffusion.
     *
     * @param stdClass $d Dossier.
     * @param array $b Bordereau.
     * @return array
     */
    protected static function contract_context(stdClass $d, array $b) {
        $embargo = !empty($b['embargo']);
        return [
            'confidential' => !empty($d->confidential),
            'confidentialuntil' => !empty($d->confidential) ? self::day($b['confidentialuntil'] ?? 0) : '',
            'embargo' => $embargo,
            'embargountil' => $embargo ? self::day(bordereau::embargo_until($d)) : '',
            'embargomonths' => $embargo ? bordereau::embargo_months($b) : 0,
            'embargoreason' => $embargo ? trim((string) ($b['embargoreason'] ?? '')) : '',
            'internet' => ($b['diffusion'] ?? '') === 'internet',
            'restricted' => ($b['diffusion'] ?? '') === 'restricted',
            'templatemissing' => false,
        ];
    }

    /**
     * Signatures : image et mention par emplacement, plus l'alias « thesisdirector ».
     *
     * @param array $signatures slot => {name, time, hash, image...}, voir document_sources().
     * @param bool $external Directeur extérieur déclaré (alias thesisdirector).
     * @return array Clés sig, sigsrc, sigcaption, sigdate, signer, signed.
     */
    protected static function signatures_context(array $signatures, $external) {
        $sig = [];
        $sigsrc = [];
        $sigcaption = [];
        $sigdate = [];
        $signer = [];
        $signed = [];
        foreach (self::SIGNATURE_SLOTS as $slot) {
            $signature = $signatures[$slot] ?? null;
            $signed[$slot] = (bool) $signature;
            $sigdate[$slot] = $signature ? self::day($signature['time']) : '';
            $signer[$slot] = $signature ? (string) $signature['name'] : '';
            $sigcaption[$slot] = $signature ? self::signature_caption($signature) : '';
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
        return [
            'sig' => $sig,
            'sigsrc' => $sigsrc,
            'sigcaption' => $sigcaption,
            'sigdate' => $sigdate,
            'signer' => $signer,
            'signed' => $signed,
        ];
    }

    /**
     * Destinataire d'une convocation : membre du jury, ou l'étudiant.
     *
     * @param stdClass|null $recipient Membre du jury, null pour l'étudiant.
     * @param array $jury Voir jury_context() (clé jury).
     * @param array $student Voir student_context().
     * @return array
     */
    protected static function recipient_context(?stdClass $recipient, array $jury, array $student) {
        if (!$recipient) {
            return ['jury' => false, 'name' => $student['fullname'], 'institution' => '', 'rolelabel' => ''];
        }
        $roles = workflow::jury_role_options();
        return [
            'jury' => true,
            'name' => trim($recipient->jurytitle . ' ' . $recipient->fullname),
            'institution' => (string) $recipient->institution,
            'rolelabel' => $roles[$recipient->juryrole] ?? $recipient->juryrole,
        ];
    }

    /**
     * Cases à cocher des formulaires (voir doctemplate_box()).
     *
     * @param stdClass $d Dossier.
     * @param array $thesis Voir thesis_context().
     * @param array $defense Voir defense_context().
     * @param array $cursus Voir cursus_context().
     * @param array $agreement Voir cursus_context().
     * @param array $contract Voir contract_context().
     * @param array $student Voir student_context().
     * @return array clé => bool
     */
    protected static function box_context(
        stdClass $d,
        array $thesis,
        array $defense,
        array $cursus,
        array $agreement,
        array $contract,
        array $student
    ) {
        $commitments = (int) $d->commitments;
        return [
            'confidential' => $thesis['confidential'],
            'notconfidential' => !$thesis['confidential'],
            'visio' => $defense['visio'],
            'defv' => $agreement['defv'],
            'mobility' => $agreement['mobility'],
            'a6' => $agreement['a6'],
            'a6notyet' => $agreement['a6notyet'],
            'cursusa6notyet' => $cursus['a6notyet'],
            'embargo' => $contract['embargo'],
            'noembargo' => !$contract['embargo'],
            'internet' => $contract['internet'],
            'restricted' => $contract['restricted'],
            'favorable' => !empty($d->reportfavorable),
            'emailconsent' => $student['emailconsent'],
            'commitguide' => (bool) ($commitments & signatures::COMMIT_GUIDE),
            'commitduplicate' => (bool) ($commitments & signatures::COMMIT_DUPLICATE),
            'commitethics' => (bool) ($commitments & signatures::COMMIT_ETHICS),
            'admitted' => (int) $d->defenseresult === THESE_RESULT_ADMITTED,
            'adjourned' => (int) $d->defenseresult === THESE_RESULT_ADJOURNED,
            'honorable' => (string) $d->mention === 'honorable',
            'treshonorable' => (string) $d->mention === 'treshonorable',
            'felicitations' => (string) $d->mention === 'felicitations',
            'prize' => !empty($d->prizeproposal),
            'corrections' => !empty($d->correctionsrequested),
        ];
    }

    /**
     * Paramètres partagés repris dans les documents (établissement, université, signataires).
     *
     * @param array $config Voir scopesettings::get_all().
     * @return array
     */
    protected static function settings_context(array $config) {
        return [
            'institution' => (string) ($config['institution'] ?? ''),
            'institutionupper' => self::upper($config['institution'] ?? ''),
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
        $paragraphs = array_map([self::class, 'doctemplate_text'], $paragraphs);
        $last = $paragraphs ? array_pop($paragraphs) : '';
        return [
            'html' => implode('<br /><br />', array_merge($paragraphs, $last !== '' ? [$last] : [])),
            'bodyhtml' => implode('<br /><br />', $paragraphs),
            'lasthtml' => $last,
        ];
    }
}
