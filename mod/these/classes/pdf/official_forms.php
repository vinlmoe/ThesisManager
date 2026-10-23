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

namespace mod_these\pdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Formulaires officiels de l'établissement remplis sur leur propre gabarit (pdftemplates/) :
 * dépôt de sujet (ENS-INI-P308-E01 V9), fiche d'agréments (E02 V8), fiche signalétique (E03 V8),
 * bordereau de thèse ABES (2022), rapport du directeur, grille de conformité (08-2024) et
 * conclusions (09-2026).
 *
 * Les coordonnées (en mm) ont été relevées sur ces versions des gabarits : une nouvelle version
 * d'un formulaire demande de remplacer le fichier dans pdftemplates/ et de vérifier les
 * emplacements correspondants ci-dessous.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class official_forms {
    /**
     * Gabarit d'un formulaire.
     *
     * @param string $name
     * @param int $repeatlast Voir template_filler::__construct().
     * @return template_filler
     */
    protected static function filler($name, $repeatlast = 0) {
        global $CFG;
        return new template_filler(file_get_contents($CFG->dirroot . '/mod/these/pdftemplates/' . $name . '.pdf'), $repeatlast);
    }

    /**
     * Majuscules.
     *
     * @param string|null $value
     * @return string
     */
    protected static function up($value) {
        return \core_text::strtoupper(trim((string) $value));
    }

    /**
     * Date jj/mm/aaaa, ou chaîne vide.
     *
     * @param int|string|null $time
     * @return string
     */
    protected static function day($time) {
        return (int) $time ? userdate((int) $time, '%d/%m/%Y') : '';
    }

    /**
     * « NOM Prénom » de l'étudiant (noms du bordereau s'ils sont renseignés).
     *
     * @param array $data
     * @return string
     */
    protected static function student(array $data) {
        $last = $data['bordereau']['birthname'] ?? '' ?: $data['studentlastname'];
        $first = $data['bordereau']['firstnames'] ?? '' ?: $data['studentfirstname'];
        return trim(self::up($last) . ' ' . $first);
    }

    /**
     * « Titre NOM Prénom » du directeur (membre de l'établissement).
     *
     * @param array $data
     * @return string
     */
    protected static function director(array $data) {
        return trim($data['directortitle'] . ' ' . self::up($data['directorlastname']) . ' ' . $data['directorfirstname']);
    }

    /**
     * Signature d'un emplacement, ou null.
     *
     * @param array $data
     * @param string $slot
     * @return array|null
     */
    protected static function sig(array $data, $slot) {
        return $data['signatures'][$slot] ?? null;
    }

    /**
     * Date de signature d'un emplacement (jj/mm/aaaa), ou chaîne vide.
     *
     * @param array $data
     * @param string $slot
     * @return string
     */
    protected static function sigday(array $data, $slot) {
        return (string) ($data['signatures'][$slot]['day'] ?? '');
    }

    /**
     * Nom d'une école vétérinaire.
     *
     * @param string $key
     * @return string
     */
    protected static function school($key) {
        $schools = these_school_options();
        return $key !== '' && $key !== null ? ($schools[$key] ?? '') : '';
    }

    /**
     * Dépôt de sujet de thèse (ENS-INI-P308-E01 V9).
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function subject(array $data) {
        $d = $data['dossier'];
        $f = self::filler('subject')->page(1);

        $f->text(172.5, 49.0, 24, self::sigday($data, 'deve'), 9, 'B');
        $f->text(26.5, 58.6, 96, self::up($data['studentlastname']), 10);
        $f->text(141.5, 58.6, 52, $data['studentfirstname'], 10);
        $years = ['A2', 'A3', 'A4', 'A5', 'A6'];
        $index = array_search($data['studyyear'], $years, true);
        if ($index !== false) {
            $f->check(73.3 + 15 * $index, 66.9, 3);
        }
        $f->block(16, 81.5, 176, 17, $d->title, 10.5, 7, 'L');
        if (!empty($d->confidential)) {
            $f->check(118.3, 76.4, 3);
        }
        $f->block(16, 108.4, 176, 5.5, implode(' ; ', these_split_keywords((string) $d->keywords)), 10, 7, 'L');

        if (!empty($d->externaldirector)) {
            // Directeur extérieur : il signe en tête ; le membre de l'établissement qui suit la
            // thèse (le directeur dans le module) signe dans le cadre « Membre de VetAgro Sup ».
            $f->text(44, 135.2, 72, $d->extdirectorname, 10);
            self::sigleft($f, self::sig($data, 'extdirector'));
            $f->text(44, 157.1, 72, $d->extdirectorname, 10);
            $f->text(27, 163.2, 88, $d->extdirectorgrade, 10);
            $f->text(37.5, 169.3, 78, $d->extdirectorinstitution, 10);
            $f->text(36, 175.4, 79, $d->extdirectoremail, 10);
            $f->text(45, 191.0, 72, self::director($data), 10);
            $f->signature(self::sig($data, 'director'), 128, 168, 50, 12, 62);
        } else {
            $f->text(44, 135.2, 72, self::director($data), 10);
            self::sigleft($f, self::sig($data, 'director'));
        }
        $f->signature(self::sig($data, 'deve'), 132, 135, 50, 9.5, 62);

        $f->text(39, 254.0, 40, self::sigday($data, 'student'), 10);
        $f->signature(self::sig($data, 'student'), 140, 248.5, 45, 9.5, 55);
        return $f->output();
    }

    /**
     * Signature du cadre « Le Directeur de thèse » du dépôt de sujet : l'image à gauche du libellé
     * « Signature » du modèle, la mention dans l'espace libre sous « Titre NOM Prénom ».
     *
     * @param template_filler $f
     * @param array|null $signature
     * @return void
     */
    protected static function sigleft(template_filler $f, $signature) {
        if ($signature) {
            $f->signature(['caption' => false] + $signature, 62, 137.5, 32, 8, 0);
            $f->signature(['image' => null, 'captiononly' => true] + $signature, 16, 140, 0, 0, 44);
        }
    }

    /**
     * Fiche d'agréments administratif et scientifique (ENS-INI-P308-E02 V8).
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function agreement(array $data) {
        $d = $data['dossier'];
        $agreement = $data['agreement'];
        $institution = (string) ($data['config']['institution'] ?? '');
        $f = self::filler('agreement')->page(1);

        // Agrément administratif.
        $f->white(21.8, 72.9, 35, 4)->text(22, 73.3, 150, self::student($data), 10, 'B');
        $f->white(153.0, 80.5, 9, 4.2);
        if (!empty($agreement['defvdate'])) {
            $f->check(22.4, 80.8, 3.1)->text(153.3, 80.9, 35, self::day($agreement['defvdate']), 10);
        }
        if (!empty($agreement['mobility'])) {
            $f->check(22.4, 88.6, 3.1);
        }
        $f->white(113.5, 95.9, 13.5, 4.2);
        if (empty($agreement['a6notyet']) && !empty($agreement['a6date'])) {
            $f->check(22.4, 96.4, 3.1)->text(113.8, 96.3, 40, self::day($agreement['a6date']), 10);
        }
        if (!empty($agreement['a6notyet'])) {
            $f->check(22.4, 104.5, 3.1);
        }
        $f->signature(self::sig($data, 'deve'), 40, 116.5, 45, 10, 55);

        // Agrément scientifique : directeur (extérieur le cas échéant) et membre de l'établissement.
        $external = !empty($d->externaldirector);
        $directorname = $external ? trim((string) $d->extdirectorname) : self::director($data);
        $function = $external ? trim((string) $d->extdirectorgrade) : trim((string) ($agreement['directorfunction'] ?? ''));
        $f->white(21.5, 151.9, 85, 11.4);
        $f->block(22, 152.2, 83, 11, get_string(
            'pdfagreementdirectortext',
            'mod_these',
            $directorname . ($function !== '' ? ', ' . $function : '')
        ), 9, 6, 'L');
        $f->white(21.5, 181.1, 40, 4.2)->text(22, 181.4, 83, $directorname, 9.5, 'B');
        $f->signature(self::sig($data, $external ? 'extdirector' : 'director'), 22, 186, 40, 10, 82);
        if ($external) {
            $f->white(109, 155.6, 80, 18.5);
            $f->block(109.4, 155.9, 79, 18, get_string('pdfagreementmembertext', 'mod_these', (object) [
                'name' => self::director($data), 'institution' => $institution,
            ]), 9, 6, 'L');
            $f->white(109.2, 181.1, 22, 4.2)->text(109.4, 181.4, 79, self::director($data), 9.5, 'B');
            $f->signature(self::sig($data, 'director'), 109.4, 186, 40, 10, 79);
        } else {
            $f->white(139.5, 155.7, 21, 3.8)->white(109.2, 181.1, 22, 4.2);
        }

        // Étudiant et titre.
        $f->white(33.0, 203.2, 22, 4.2)->text(33.5, 203.5, 48, self::up($data['bordereau']['birthname'] ?? ''
            ?: $data['studentlastname']), 10, 'B');
        $f->white(100.0, 203.2, 26.5, 4.2)->text(100.5, 203.5, 90, $data['bordereau']['firstnames'] ?? ''
            ?: $data['studentfirstname'], 10, 'B');
        $f->white(39.5, 210.6, 13.5, 5.3)->block(40, 211.0, 150, 11, $d->title, 10, 7, 'L');

        // Direction générale (représentée par la DEVE) et CCEM.
        $f->signature(self::sig($data, 'dgrep'), 112, 225, 50, 12, 76);
        $f->signature(self::sig($data, 'ccem'), 112, 249, 50, 12, 76);
        return $f->output();
    }

    /**
     * Fiche signalétique (ENS-INI-P308-E03 V8).
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function signaletique(array $data) {
        $d = $data['dossier'];
        $b = $data['bordereau'];
        $f = self::filler('signaletique')->page(1);

        $f->text(31, 68.2, 96, self::up($b['birthname'] ?? '' ?: $data['studentlastname']), 10);
        $f->text(146, 68.2, 50, $b['firstnames'] ?? '' ?: $data['studentfirstname'], 10);
        $f->text(78, 76.3, 116, $b['personalemail'] ?? '', 10);
        $f->text(36, 86.9, 62, self::day($b['birthdate'] ?? 0), 10);
        $f->text(105, 86.9, 58, $b['birthplace'] ?? '', 10);
        $f->text(181, 86.9, 15, $b['birthdept'] ?? '', 10);
        $address = implode(', ', array_filter([trim(($b['street'] ?? '')), trim(($b['postcode'] ?? '') . ' ' . ($b['city'] ?? '')),
            trim($b['country'] ?? '')], 'strlen'));
        $f->block(52, 93.3, 92, 7.5, $address, 10, 6.5, 'L');
        $f->text(153, 93.3, 43, $b['phone'] ?? '', 10);

        $date = (int) ($d->defensedate ?: $d->proposeddate);
        $f->text(56, 107.4, 18, self::day($date), 9.5);
        $f->text(88, 107.4, 18, $date ? userdate($date, '%H:%M') : '', 9.5);
        $f->text(119, 107.4, 43, $d->defenselocation, 9.5);
        if (!empty($b['visio'])) {
            $f->check(163.1, 107.6, 3);
        }
        $f->white(53.8, 114.4, 8.5, 4.4)->text(54.2, 114.9, 40, self::day($b['defvdate'] ?? 0), 10, 'B');
        $f->white(103.8, 114.4, 19.2, 4.4)->text(104.5, 114.9, 90, self::school($b['defvschool'] ?? ''), 10, 'B');
        $f->white(56.3, 120.9, 8.7, 4.4)->white(103.8, 120.9, 19.2, 4.4);
        if (empty($b['a6notyet'])) {
            $f->text(56.8, 121.4, 40, self::day($b['a6date'] ?? 0), 10, 'B');
            $f->text(104.5, 121.4, 90, self::school($b['a6school'] ?? ''), 10, 'B');
        } else {
            $f->check(20.5, 128.1, 3);
        }

        // Jury : président, membre de l'établissement (ligne pré-remplie), autres membres,
        // directeur, invités.
        $president = null;
        $director = null;
        $members = [];
        $guests = [];
        foreach ($data['jury'] as $member) {
            if ($member->juryrole === 'president') {
                $president = $member;
            } else if ($member->juryrole === 'director') {
                $director = $member;
            } else if ($member->juryrole === 'guest') {
                $guests[] = $member;
            } else {
                $members[] = $member;
            }
        }
        $name = function ($member) {
            return trim($member->jurytitle . ' ' . $member->fullname);
        };
        $institution = \core_text::strtolower((string) ($data['config']['institution'] ?? ''));
        $local = null;
        foreach ($members as $index => $member) {
            if ($institution !== '' && strpos(\core_text::strtolower($member->institution), $institution) !== false) {
                $local = $member;
                unset($members[$index]);
                break;
            }
        }
        if ($president) {
            $f->text(62, 153.3, 58, $name($president), 9.5)->text(122.6, 153.3, 73, $president->institution, 9.5);
        }
        if ($local) {
            $f->text(62, 159.8, 58, $name($local), 9.5);
        }
        foreach (array_slice(array_values($members), 0, 2) as $i => $member) {
            $y = $i === 0 ? 169.5 : 176.0;
            $f->text(62, $y, 58, $name($member), 9.5)->text(122.6, $y, 73, $member->institution, 9.5);
        }
        $directorname = $director ? $name($director) : self::director($data);
        $f->text(62, 184.2, 58, $directorname, 9.5)->text(122.6, 184.2, 73, $director ? $director->institution : '', 9.5);
        foreach (array_slice($guests, 0, 2) as $i => $guest) {
            $y = $i === 0 ? 194.1 : 198.2;
            $f->text(56, $y, 64, $name($guest), 9)->text(142, $y, 54, $guest->email, 9);
        }

        $f->block(20.5, 212, 175, 12, self::up($d->title), 10, 6.5, 'L');
        if (!empty($d->confidential)) {
            $f->check(158.2, 207.5, 3);
        }

        $f->signature(self::sig($data, 'president'), 20, 238, 45, 11, 70);
        $f->signature(self::sig($data, 'dg'), 127.6, 239.5, 45, 9, 68);
        $f->signature(self::sig($data, 'ccem'), 127.6, 257, 45, 9, 68);
        return $f->output();
    }

    /**
     * Bordereau de thèse d'exercice vétérinaire (ABES, 2022), sur trois pages.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function bordereau(array $data) {
        $d = $data['dossier'];
        $b = $data['bordereau'];
        $f = self::filler('bordereau');

        $f->page(1);
        $f->text(102, 87.0, 93, self::up($b['birthname'] ?? ''), 9.5);
        $f->text(102, 91.3, 93, self::up($b['firstnames'] ?? ''), 9.5);
        $f->text(102, 95.5, 93, self::up($b['usagename'] ?? ''), 9.5);
        $f->text(148.5, 99.8, 47, self::up($b['publicationname'] ?? ''), 9.5);
        $f->text(102, 104.1, 93, !empty($b['birthdate']) ? userdate((int) $b['birthdate'], '%d%m%Y') : '', 9.5);
        $f->text(102, 108.3, 93, self::up($b['nationality'] ?? ''), 9.5);
        $f->text(43, 125.7, 152, self::up($b['street'] ?? ''), 9.5);
        $f->text(51.5, 129.9, 46, self::up($b['postcode'] ?? ''), 9.5);
        $f->text(111, 129.9, 84, self::up($b['country'] ?? ''), 9.5);
        $f->text(36, 134.2, 159, self::up($b['city'] ?? ''), 9.5);
        $f->text(71, 138.5, 124, $b['personalemail'] ?? '', 9.5);
        $date = (int) ($d->defensedate ?: $d->proposeddate);
        if ($date) {
            $f->text(101.8, 152.2, 8.2, userdate($date, '%Y'), 8.5);
            $f->text(129.8, 152.2, 5, userdate($date, '%m'), 8.5);
            $f->text(152.6, 152.2, 5, userdate($date, '%d'), 8.5);
        }
        $f->text(35, 174.4, 160, self::up($data['directorlastname']), 9.5);
        $f->text(46, 178.7, 149, self::up($data['directorfirstname']), 9.5);
        if ((int) ($b['nbvolumes'] ?? 1) !== 1) {
            $f->white(58.0, 204.5, 3, 4.2)->text(58.2, 204.8, 4, (string) (int) $b['nbvolumes'], 9.5, 'B');
        }
        $f->text(108, 204.8, 30, (string) (int) ($b['nbpages'] ?? 0), 9.5);
        $f->text(96.5, 209.1, 30, (string) (int) ($b['nbrefs'] ?? 0), 9.5);

        // Résumés coupés à 1700 caractères, comme l'annonce le bordereau.
        $summaryfr = \core_text::substr((string) ($b['summaryfr'] ?? ''), 0, THESE_BORDEREAU_SUMMARY_MAX);
        $summaryen = \core_text::substr((string) ($b['summaryen'] ?? ''), 0, THESE_BORDEREAU_SUMMARY_MAX);
        $f->page(2);
        $f->block(27, 47.5, 166, 21, $d->title, 11, 7, 'L');
        $f->block(27, 96, 166, 21, $d->titleen, 11, 7, 'L');
        $f->block(27, 159, 168, 86, $summaryfr, 10.5, 6.5);

        $f->page(3);
        $f->block(27, 44, 168, 49, $summaryen, 10.5, 6.5);
        foreach (array_slice(array_values((array) ($b['keywords'] ?? [])), 0, THESE_BORDEREAU_KEYWORDS) as $i => $keyword) {
            $x = $i < 4 ? 29 : 115.5;
            $f->text($x, 121.3 + 4.27 * ($i % 4), 80, $keyword, 9.5);
        }
        $f->text(27, 177.5, 60, self::sigday($data, 'student'), 9.5);
        $f->signature(self::sig($data, 'student'), 27, 181.5, 45, 9, 64);
        return $f->output();
    }

    /**
     * Rapport du directeur de thèse.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function report(array $data) {
        $d = $data['dossier'];
        $f = self::filler('report')->page(1);
        $f->white(70.5, 45.2, 125, 5.2)->block(71, 45.7, 124, 11, $d->title, 11, 7, 'L');
        // Les consignes du modèle (« Prière d'effacer ces consignes ») laissent la place au rapport.
        $f->white(24, 58, 166, 72);
        $text = trim((string) $d->reporttext);
        if (!empty($d->reportfavorable)) {
            $text .= "\n\n" . get_string('pdfreportfavorable', 'mod_these');
        }
        $f->block(25, 59, 160, 96, $text, 11, 7);
        $f->white(49.7, 162.5, 39, 5.6)->text(50.5, 163.0, 134, self::student($data), 11, 'B');
        $f->white(141, 187.1, 45, 5.6)->text(95, 187.6, 90, self::director($data), 11, 'I', 'R');
        $f->signature(self::sig($data, 'director'), 135, 204, 50, 14, 55);
        return $f->output();
    }

    /**
     * Grille de conformité de la thèse d'exercice vétérinaire (08-2024).
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function grid(array $data) {
        $d = $data['dossier'];
        $f = self::filler('grid')->page(1);
        $f->white(79, 52.9, 52, 4.4)->text(14, 53.0, 182, self::student($data), 10, 'B', 'C');
        $f->white(65, 58.3, 80, 4.3)->text(14, 58.4, 182, $d->title, 10, 'B', 'C');
        $ethics = trim((string) $d->ethicsnumber);
        if ($ethics !== '') {
            $f->white(160.9, 63.6, 11, 4.4)->text(161.2, 63.8, 34, $ethics, 9.5);
        }
        $rows = [
            'question' => 92.2, 'answer' => 97.5, 'biblio' => 102.9, 'critique' => 108.2, 'ownwork' => 113.6,
            'tool' => 121.4, 'french' => 140.1, 'language' => 148.0, 'typing' => 156.0, 'thanks' => 161.3,
            'toc' => 166.7, 'staff' => 172.0, 'lists' => 177.3, 'covers' => 182.7, 'citations' => 188.0,
            'bibliography' => 193.4, 'figures' => 198.7, 'introduction' => 204.0, 'conclusion' => 209.4,
            'abstract' => 214.8, 'structure' => 222.6,
        ];
        foreach ($rows as $item => $y) {
            if (!empty($data['grid'][$item])) {
                $f->mark(188.5, $y);
            }
        }
        $f->white(160, 238.1, 37.5, 5.5)->text(110, 238.6, 86.5, self::director($data), 10, 'I', 'R');
        $f->signature(self::sig($data, 'director'), 150, 250, 45, 13, 47);
        return $f->output();
    }

    /**
     * Conclusions de la thèse (09-2026) : sur une page si elles y tiennent, sinon sur deux, le
     * dernier paragraphe restant sur la page des signatures comme le demande le modèle.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function conclusions(array $data) {
        $d = $data['dossier'];
        $text = trim((string) $d->conclusions);
        $paragraphs = array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", $text)), 'strlen'));

        // Une page si le texte tient au-dessus des signatures, sinon deux.
        $probe = self::filler('conclusions');
        $twopages = $probe->height(160, $text, 9.5) > 104;
        $f = self::filler('conclusions', $twopages ? 1 : 0);

        $last = 1;
        if ($twopages) {
            // Première page : titre du modèle conservé, tout le reste effacé.
            $last = 2;
            $first = $paragraphs;
            $remaining = [array_pop($first)];
            while (count($first) > 1 && $f->height(160, implode("\n\n", $first), 10.5) > 240) {
                array_unshift($remaining, array_pop($first));
            }
            $f->page(1)->white(15, 28, 180, 260)->block(25, 30, 160, 245, implode("\n\n", $first), 10.5, 7);
            $f->page(2)->white(15, 10, 180, 18);
            $text = implode("\n\n", $remaining);
        }

        $f->page($last);
        $f->white(30, 62, 152, 38);
        $f->block(25, 28, 160, 105, $text, 10.5, 6.5);
        $f->white(25, 136.8, 35, 5.2)->text(
            25,
            137.3,
            150,
            get_string('pdfthesisof', 'mod_these', self::student($data)),
            10.5,
            'B'
        );
        $f->white(25, 155.8, 28, 4.7)->text(25, 156.3, 80, self::director($data), 10);
        $president = trim((string) ($data['signers']['president']['name'] ?? ''));
        $f->white(25, 187.3, 36, 4.7)->text(25, 187.8, 80, $president, 10);
        $f->white(25, 227.3, 25.5, 5.2)->text(25, 227.8, 80, get_string('pdfseen', 'mod_these', (object) [
            'place' => (string) ($data['config']['visaplace'] ?? ''), 'date' => self::sigday($data, 'ccem'),
        ]), 10, 'BI');
        $f->signature(self::sig($data, 'director'), 110, 148, 50, 13, 72);
        $f->signature(self::sig($data, 'president'), 110, 179, 50, 13, 72);
        $f->signature(self::sig($data, 'dg'), 110, 198.5, 50, 12, 72);
        $f->signature(self::sig($data, 'ccem'), 120, 228, 50, 11, 72);
        return $f->output();
    }
}
