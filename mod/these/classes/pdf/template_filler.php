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
require_once($CFG->dirroot . '/mod/these/thirdparty/vendor/autoload.php');

/**
 * Remplit un formulaire officiel en PDF : chaque page du gabarit (le document de l'établissement
 * tel quel) est réimportée par FPDI, puis les données et les signatures y sont écrites aux
 * emplacements prévus. La mise en page reste ainsi exactement celle du document d'origine.
 *
 * Les coordonnées sont en millimètres depuis le coin supérieur gauche de la page. Les textes de
 * substitution du gabarit (« NOM Prénom Etudiant », « Date », consignes à effacer…) sont masqués
 * par un aplat blanc avant d'écrire la valeur à leur place.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_filler {
    /** @var \setasign\Fpdi\Tcpdf\Fpdi */
    protected $pdf;

    /** @var string Police des valeurs écrites. */
    const FONT = 'freesans';

    /**
     * Importe toutes les pages du gabarit.
     *
     * @param string $template Contenu du PDF gabarit.
     * @param int $repeatlast Nombre de pages supplémentaires à ajouter en reprenant la dernière page
     *                        du gabarit (conclusions sur deux pages, par exemple).
     */
    public function __construct($template, $repeatlast = 0) {
        // Dossier de travail de TCPDF (images des signatures) : la classe \pdf de Moodle le crée
        // à sa construction, ce que FPDI ne fait pas.
        make_cache_directory('tcpdf');
        $this->pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->SetMargins(0, 0, 0);
        $this->pdf->setCellPaddings(0, 0, 0, 0);
        $count = $this->pdf->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($template));
        for ($pageno = 1; $pageno <= $count + $repeatlast; $pageno++) {
            $tplidx = $this->pdf->importPage(min($pageno, $count));
            $size = $this->pdf->getTemplateSize($tplidx);
            $this->pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $this->pdf->useTemplate($tplidx);
        }
        $this->pdf->SetTextColor(0, 0, 0);
    }

    /**
     * Se place sur une page du document.
     *
     * @param int $pageno
     * @return self
     */
    public function page($pageno) {
        $this->pdf->setPage($pageno);
        return $this;
    }

    /**
     * Masque une zone du gabarit (texte de substitution, consigne).
     *
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     * @return self
     */
    public function white($x, $y, $w, $h) {
        $this->pdf->Rect($x, $y, $w, $h, 'F', [], [255, 255, 255]);
        return $this;
    }

    /**
     * Écrit une valeur sur une ligne, en réduisant la police jusqu'à ce qu'elle tienne dans la
     * largeur donnée.
     *
     * @param float $x
     * @param float $y Haut de la ligne.
     * @param float $w Largeur disponible.
     * @param string $value
     * @param float $size Taille de police souhaitée (pt).
     * @param string $style '' ou 'B', 'I'...
     * @param string $align 'L', 'C' ou 'R'.
     * @return self
     */
    public function text($x, $y, $w, $value, $size = 10, $style = '', $align = 'L') {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($value === '') {
            return $this;
        }
        $this->pdf->SetFont(self::FONT, $style, $size);
        while ($size > 5 && $this->pdf->GetStringWidth($value) > $w) {
            $size -= 0.5;
            $this->pdf->SetFont(self::FONT, $style, $size);
        }
        $this->pdf->SetXY($x, $y);
        $this->pdf->Cell($w, $size * 0.4, $value, 0, 0, $align, false, '', 1);
        return $this;
    }

    /**
     * Écrit un texte sur plusieurs lignes dans un cadre, en réduisant la police jusqu'à ce qu'il
     * tienne en hauteur.
     *
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h Hauteur disponible.
     * @param string $value
     * @param float $size Taille souhaitée.
     * @param float $minsize Taille minimale.
     * @param string $align 'L', 'J', 'C'...
     * @param string $style
     * @return bool True si le texte tient dans le cadre (sinon il est écrit à la taille minimale).
     */
    public function block($x, $y, $w, $h, $value, $size = 10, $minsize = 6, $align = 'L', $style = '') {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }
        $fits = false;
        for (; $size >= $minsize; $size -= 0.5) {
            $this->pdf->SetFont(self::FONT, $style, $size);
            if ($this->pdf->getStringHeight($w, $value) <= $h) {
                $fits = true;
                break;
            }
        }
        $size = max($size, $minsize);
        $this->pdf->SetFont(self::FONT, $style, $size);
        $this->pdf->SetXY($x, $y);
        $this->pdf->MultiCell($w, $size * 0.45, $value, 0, $align, false, 1, $x, $y, true, 0, false, true, $h, 'T', false);
        return $fits;
    }

    /**
     * Hauteur qu'occuperait un texte sur une largeur et à une taille données.
     *
     * @param float $w
     * @param string $value
     * @param float $size
     * @return float
     */
    public function height($w, $value, $size) {
        $this->pdf->SetFont(self::FONT, '', $size);
        return $this->pdf->getStringHeight($w, (string) $value);
    }

    /**
     * Coche une case du gabarit (croix tracée dans le carré).
     *
     * @param float $x Coin supérieur gauche de la case.
     * @param float $y
     * @param float $size Côté de la case.
     * @return self
     */
    public function check($x, $y, $size = 3) {
        $this->pdf->SetLineStyle(['width' => 0.45, 'color' => [0, 0, 0]]);
        $this->pdf->Line($x + 0.5, $y + 0.5, $x + $size - 0.5, $y + $size - 0.5);
        $this->pdf->Line($x + $size - 0.5, $y + 0.5, $x + 0.5, $y + $size - 0.5);
        return $this;
    }

    /**
     * Croix centrée dans une cellule (colonne « Cocher si validé »).
     *
     * @param float $cx Centre horizontal.
     * @param float $cy Centre vertical.
     * @return self
     */
    public function mark($cx, $cy) {
        return $this->check($cx - 1.75, $cy - 1.75, 3.5);
    }

    /**
     * Appose une signature : l'image tracée, et en dessous la mention de signature électronique
     * (nom, date, début de l'empreinte du contenu signé). Rien n'est écrit pour un emplacement
     * qui n'est pas encore signé : il reste vierge, comme sur le papier.
     *
     * @param array|null $signature Voir these_signed_document_data()['signatures'] ; 'caption' => false
     *                              n'écrit que l'image, 'captiononly' => true que la mention.
     * @param float $x
     * @param float $y
     * @param float $w Largeur maximale de l'image.
     * @param float $h Hauteur maximale de l'image.
     * @param float $captionw Largeur de la mention (par défaut, celle de l'image).
     * @return self
     */
    public function signature($signature, $x, $y, $w, $h, $captionw = 0) {
        if (!$signature || (empty($signature['image']) && empty($signature['captiononly']))) {
            return $this;
        }
        $info = !empty($signature['image']) ? @getimagesizefromstring($signature['image']) : false;
        if ($info) {
            $ratio = $info[0] / max(1, $info[1]);
            $iw = min($w, $h * $ratio);
            $ih = $iw / $ratio;
            $this->pdf->Image('@' . $signature['image'], $x, $y, $iw, $ih, 'PNG');
        }
        if (isset($signature['caption']) && $signature['caption'] === false) {
            return $this;
        }
        $this->pdf->SetTextColor(80, 80, 80);
        $this->pdf->SetFont(self::FONT, '', 5.5);
        $this->pdf->SetXY($x, $y + $h);
        $this->pdf->MultiCell($captionw ?: $w, 2.3, get_string('pdfsignedby', 'mod_these', (object) [
            'name' => $signature['name'],
            'date' => $signature['date'],
            'hash' => $signature['hash'],
        ]), 0, 'L');
        $this->pdf->SetTextColor(0, 0, 0);
        return $this;
    }

    /**
     * Contenu du PDF rempli.
     *
     * @return string
     */
    public function output() {
        return $this->pdf->Output('', 'S');
    }
}
