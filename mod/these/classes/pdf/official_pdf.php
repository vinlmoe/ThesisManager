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

require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Pages produites par le module pour les documents qui n'ont pas de gabarit à remplir : pages
 * de données et de signatures du contrat de diffusion électronique (E04, dont le texte est le PDF
 * officiel déposé par l'administrateur) et attestation de conformité de la bibliothèque. Les
 * formulaires officiels, eux, sont remplis sur leur propre gabarit (voir official_forms).
 *
 * Chaque emplacement de signature reçoit l'image tracée par le signataire, avec la mention
 * « Signé électroniquement par ... le ... » et le début de l'empreinte SHA-256 du contenu signé ;
 * un emplacement encore vide porte « En attente de signature ».
 *
 * Les pages sont composées en HTML (sous-ensemble interprété par TCPDF) : les cadres, cases à
 * cocher et colonnes des formulaires d'origine s'y expriment simplement.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class official_pdf extends \pdf {
    /** @var array Données du document (voir these_signed_document_data()). */
    protected $data = [];

    /**
     * Prépare un document A4 portrait avec les logos de l'établissement et la référence du
     * formulaire en haut à droite.
     *
     * @param array $data
     * @param string $title Titre (métadonnées et titre affiché).
     * @param string $reference Référence du formulaire (« ENS-INI-P308-E01 V9 »), facultative.
     * @return self
     */
    protected static function start(array $data, $title, $reference = '') {
        $pdf = new self('P', 'mm', 'A4');
        $pdf->data = $data;
        $pdf->SetCreator('Moodle mod_these');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(15, 12, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        if ($reference !== '') {
            $pdf->SetFont('freesans', '', 7);
            $pdf->Cell(0, 4, $reference, 0, 1, 'R');
        }
        $top = $pdf->GetY();
        $x = 15;
        foreach ((array) ($data['logos'] ?? []) as $logo) {
            $info = @getimagesizefromstring($logo);
            if (!$info) {
                continue;
            }
            $height = 16;
            $width = $height * $info[0] / max(1, $info[1]);
            $pdf->Image('@' . $logo, $x, $top, $width, $height);
            $x += $width + 8;
        }
        if ($x > 15) {
            $pdf->SetY($top + 19);
        }
        $pdf->SetFont('freesans', 'B', 14);
        $pdf->MultiCell(0, 7, $title, 0, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('freesans', '', 9);
        return $pdf;
    }

    /**
     * Écrit un fragment HTML.
     *
     * @param string $html
     * @return void
     */
    protected function html($html) {
        $this->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Texte échappé pour le HTML, avec retours à la ligne conservés.
     *
     * @param string|null $text
     * @return string
     */
    protected static function e($text) {
        return nl2br(s(trim((string) $text)));
    }

    /**
     * Case à cocher : « ■ » cochée, « □ » vide (glyphes présents dans FreeSans, contrairement
     * à « ☐ » et « ☒ »).
     *
     * @param bool $checked
     * @return string HTML
     */
    protected static function box($checked) {
        return $checked ? '&#9632;' : '&#9633;';
    }

    /**
     * Libellé d'une chaîne de mod_these.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    protected static function str($key, $a = null) {
        return get_string($key, 'mod_these', $a);
    }

    /**
     * Valeur d'un réglage du site.
     *
     * @param string $name
     * @return string
     */
    protected function config($name) {
        return (string) ($this->data['config'][$name] ?? '');
    }

    /**
     * Date courte (jj/mm/aaaa) d'un horodatage, ou chaîne vide.
     *
     * @param int|null $time
     * @return string
     */
    protected static function day($time) {
        return $time ? userdate((int) $time, '%d/%m/%Y') : '';
    }

    /**
     * Emplacement de signature : image tracée et mention de signature électronique, ou mention
     * d'attente.
     *
     * @param string $slot
     * @return string HTML
     */
    protected function sig($slot) {
        $signature = $this->data['signatures'][$slot] ?? null;
        if (!$signature || empty($signature['image'])) {
            return '<br><span style="color:#999999;font-size:7.5pt;">' . self::str('pdfsignaturepending') . '</span><br><br>';
        }
        return '<img src="@' . base64_encode($signature['image']) . '" height="42">'
            . '<br><span style="color:#555555;font-size:6.5pt;">'
            . s(self::str('pdfsignedby', (object) [
                'name' => $signature['name'],
                'date' => $signature['date'],
                'hash' => $signature['hash'],
            ])) . '</span>';
    }

    /**
     * Date de signature d'un emplacement, ou chaîne vide.
     *
     * @param string $slot
     * @return string
     */
    protected function sigday($slot) {
        return (string) ($this->data['signatures'][$slot]['day'] ?? '');
    }

    /**
     * Titre du directeur et nom.
     *
     * @return string
     */
    protected function directorname() {
        return trim($this->data['directortitle'] . ' ' . $this->data['director']);
    }

    /**
     * Valeur du bordereau.
     *
     * @param string $field
     * @return mixed
     */
    protected function b($field) {
        return $this->data['bordereau'][$field] ?? '';
    }

    /**
     * Page « données et choix de l'auteur » du contrat de diffusion électronique
     * (ENS-INI-P308-E04) : elle précède le contrat type déposé par l'administrateur (voir
     * these_signed_document_pdf()), dont l'article 1 renvoie à « la Thèse indiquée en première
     * page ». Elle reprend l'identité de l'auteur, la thèse et ses choix des articles 2
     * (confidentialité, embargo) et 4 (diffusion).
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function contract_data(array $data) {
        $pdf = self::start($data, self::str('pdfcontracttitle'), self::str('pdfcontractref'));
        $d = $data['dossier'];
        $date = (int) ($d->defensedate ?: $d->proposeddate);
        $embargo = !empty($pdf->b('embargo'));
        $row = function ($label, $value) {
            return '<tr><td width="38%"><b>' . $label . '</b></td><td width="62%">' . $value . '</td></tr>';
        };
        $html = '<p style="text-align:center;"><b>' . self::str('pdfcontractdatapage') . '</b></p>'
            . '<table border="1" cellpadding="4"><tr><td colspan="2" style="background-color:#dddddd;"><b>'
            . self::str('pdfcontractauthor') . '</b></td></tr>'
            . $row(self::str('pdfname'), s(\core_text::strtoupper($pdf->b('birthname') ?: $data['studentlastname'])))
            . $row(self::str('pdffirstname'), s($pdf->b('firstnames') ?: $data['studentfirstname']))
            . $row(self::str('pdfbornon'), self::day($pdf->b('birthdate')))
            . $row(self::str('pdfpersonaladdress'), s(trim($pdf->b('street') . ', ' . $pdf->b('postcode') . ' '
                . $pdf->b('city') . ', ' . $pdf->b('country'), ' ,')))
            . $row(self::str('bpersonalemail'), s($pdf->b('personalemail')))
            . $row(self::str('pdfentitled'), self::e($d->title))
            . $row(self::str('pdfdefendedon'), self::day($date))
            . $row(self::str('director'), s($pdf->directorname()))
            . '</table><br>'
            . '<table border="1" cellpadding="4"><tr><td style="background-color:#dddddd;"><b>'
            . self::str('pdfcontractart2title') . '</b></td></tr><tr><td>'
            . '<b>2.1. ' . self::str('pdfcontractconfidentiality') . '</b><br>'
            . self::box(empty($d->confidential)) . ' ' . self::str('pdfcontractnotconfidential') . '<br>'
            . self::box(!empty($d->confidential)) . ' ' . self::str('pdfcontractconfidentialuntil') . ' '
            . (!empty($d->confidential) ? self::day($pdf->b('confidentialuntil')) : '') . '<br><br>'
            . '<b>2.2. ' . self::str('pdfcontractembargo') . '</b><br>'
            . self::box(!$embargo) . ' ' . self::str('pdfcontractnoembargo') . '<br>'
            . self::box($embargo) . ' ' . self::str('pdfcontractembargountil') . ' '
            . ($embargo ? self::day($pdf->b('embargountil')) : '') . '</td></tr></table><br>'
            . '<table border="1" cellpadding="4"><tr><td style="background-color:#dddddd;"><b>'
            . self::str('pdfcontractart4title') . '</b></td></tr><tr><td>'
            . self::box($pdf->b('diffusion') === 'internet') . ' ' . self::str('diffusioninternet') . '<br>'
            . self::box($pdf->b('diffusion') === 'restricted') . ' ' . self::str('diffusionrestricted')
            . '</td></tr></table>';
        if (empty($data['contracttemplate'])) {
            $html .= '<br><p style="color:#a00000;"><i>' . self::str('pdfcontractnotemplate') . '</i></p>';
        }
        $pdf->html($html);
        return $pdf->Output('', 'S');
    }

    /**
     * Page de signatures du contrat de diffusion électronique, placée après le contrat type :
     * l'auteur, la direction générale, la présidence de l'université, et le visa de l'encadrant.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function contract_signatures(array $data) {
        $pdf = self::start($data, self::str('pdfcontractsignaturepage'), self::str('pdfcontractref'));
        $html = '<p>' . s(self::str('pdfcontractsignaturetext', (object) [
                'student' => $data['student'],
                'title' => $data['dossier']->title,
            ])) . '</p>'
            . '<p>' . s(self::str('pdfdoneatshort', (object) [
                'city' => $pdf->config('visaplace'), 'date' => $pdf->sigday('student'),
            ])) . '<br>' . self::str('pdfcontractcopies') . '</p><br>'
            . '<table border="1" cellpadding="5"><tr>'
            . '<td width="34%"><b><i>' . self::str('pdfauthorsignature') . '</i></b><br>' . s($data['student']) . '<br>'
            . $pdf->sig('student') . '</td>'
            . '<td width="33%"><b><i>' . s($pdf->config('dgtitle')) . '</i></b><br>' . s($pdf->config('dgname')) . '<br>'
            . $pdf->sig('dg') . '</td>'
            . '<td width="33%"><b><i>' . s($pdf->config('ucbltitle')) . '</i></b><br>' . s($pdf->config('ucblname')) . '<br>'
            . $pdf->sig('ucbl') . '</td></tr>'
            . '<tr><td colspan="3"><b><i>' . self::str('pdfcontractsupervisorvisa') . '</i></b> ' . s($pdf->directorname())
            . '<br>' . $pdf->sig('director') . '</td></tr></table>';
        $pdf->html($html);
        return $pdf->Output('', 'S');
    }

    /**
     * Attestation de conformité du manuscrit, délivrée par la bibliothèque.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function conformity(array $data) {
        $pdf = self::start($data, self::str('docconformity'));
        $d = $data['dossier'];
        $html = '<table border="1" cellpadding="4"><tr><td><b>' . self::str('student') . ' :</b> ' . s($data['student'])
            . '<br><b>' . self::str('pdfthesistitle') . ' :</b> ' . self::e($d->title) . '<br><b>' . self::str('devenumber')
            . ' :</b> ' . s($d->devenumber) . '<br><b>' . self::str('director') . ' :</b> ' . s($pdf->directorname())
            . '</td></tr></table><br><p style="text-align:justify;">' . s(self::str('pdfconformitytext', (object) [
                'student' => $data['student'], 'date' => $pdf->sigday('library') ?: $data['today'],
            ])) . '</p><br><table cellpadding="2"><tr><td width="45%"></td><td width="55%" style="text-align:center;"><b>'
            . self::str('actorlibrary') . '</b><br>' . $pdf->sig('library') . '</td></tr></table>';
        $pdf->html($html);
        return $pdf->Output('', 'S');
    }
}
