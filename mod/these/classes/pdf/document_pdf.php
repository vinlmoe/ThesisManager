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
 * Documents PDF de soutenance d'un dossier de thèse : convocations, feuille de jury et
 * procès-verbal. Les documents de l'établissement signés dans le module (dépôt de sujet,
 * agréments, fiche signalétique, contrat de diffusion, bordereau, rapport, grille de conformité,
 * conclusions, attestation de conformité) relèvent de official_pdf.
 *
 * Tous partagent le même en-tête (établissement) et la même mise en page sobre, entièrement
 * produite ici : aucun gabarit n'est à fournir. Chaque méthode statique prend les données
 * préparées par these_document_data() et retourne le contenu binaire du PDF.
 *
 * @package   mod_these
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class document_pdf extends \pdf {
    /** @var array Couleur des bandeaux de titre (RGB). */
    const BAND_COLOR = [0, 61, 100];

    /** @var array Données du dossier (voir these_document_data()). */
    protected $data = [];

    /**
     * Prépare un document d'une page A4 portrait, avec l'en-tête de l'établissement.
     *
     * @param array $data
     * @param string $title Titre du document (métadonnées).
     * @return self
     */
    protected static function start(array $data, $title) {
        $pdf = new self('P', 'mm', 'A4');
        $pdf->data = $data;
        $pdf->SetCreator('Moodle mod_these');
        $pdf->SetTitle($title);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();
        $pdf->letterhead();
        return $pdf;
    }

    /**
     * En-tête : nom et adresse de l'établissement, filet de séparation.
     *
     * @return void
     */
    protected function letterhead() {
        $this->SetFont('freesans', 'B', 13);
        $this->SetTextColor(...self::BAND_COLOR);
        $this->MultiCell(0, 6, $this->data['establishmentname'] ?: ' ', 0, 'L');
        $this->SetTextColor(0, 0, 0);
        if (trim($this->data['establishmentaddress']) !== '') {
            $this->SetFont('freesans', '', 9);
            $this->MultiCell(0, 4, $this->data['establishmentaddress'], 0, 'L');
        }
        $this->Ln(2);
        $this->SetDrawColor(...self::BAND_COLOR);
        $this->Line($this->GetX(), $this->GetY(), $this->getPageWidth() - 20, $this->GetY());
        $this->Ln(8);
    }

    /**
     * Titre de document centré.
     *
     * @param string $text
     * @return void
     */
    protected function doctitle($text) {
        $this->SetFont('freesans', 'B', 16);
        $this->MultiCell(0, 8, $text, 0, 'C');
        $this->Ln(4);
    }

    /**
     * Bandeau de section.
     *
     * @param string $text
     * @return void
     */
    protected function band($text) {
        $this->Ln(2);
        $this->SetFillColor(...self::BAND_COLOR);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('freesans', 'B', 11);
        $this->Cell(0, 7, ' ' . $text, 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    /**
     * Ligne « libellé : valeur ».
     *
     * @param string $label
     * @param string $value
     * @return void
     */
    protected function field($label, $value) {
        $this->SetFont('freesans', 'B', 10);
        $this->Cell(55, 6, $label, 0, 0, 'L');
        $this->SetFont('freesans', '', 10);
        $this->MultiCell(0, 6, $value !== '' ? $value : '-', 0, 'L');
    }

    /**
     * Paragraphe de texte courant.
     *
     * @param string $text
     * @param string $style '' ou 'B', 'I'...
     * @return void
     */
    protected function paragraph($text, $style = '') {
        $this->SetFont('freesans', $style, 10.5);
        $this->MultiCell(0, 5.5, $text, 0, 'L');
        $this->Ln(2);
    }

    /**
     * Rappel du dossier : étudiant, titre, numéro, directeur.
     *
     * @return void
     */
    protected function dossier_fields() {
        $this->field(get_string('student', 'mod_these'), $this->data['student']);
        $this->field(get_string('subjecttitle', 'mod_these'), $this->data['title']);
        if ($this->data['titleen'] !== '') {
            $this->field(get_string('subjecttitleen', 'mod_these'), $this->data['titleen']);
        }
        $this->field(get_string('devenumber', 'mod_these'), $this->data['devenumber']);
        $this->field(get_string('director', 'mod_these'), $this->data['director']);
        if ($this->data['codirector'] !== '') {
            $this->field(get_string('codirector', 'mod_these'), $this->data['codirector']);
        }
    }

    /**
     * Mention « Fait à ..., le ... » et bloc de signature.
     *
     * @param string $name Signataire.
     * @param string $title Qualité du signataire.
     * @return void
     */
    protected function signature($name, $title) {
        $this->Ln(6);
        $this->SetFont('freesans', '', 10.5);
        $place = (object) ['city' => $this->data['city'] ?: '…', 'date' => $this->data['today']];
        $this->Cell(0, 6, get_string('pdfdoneat', 'mod_these', $place), 0, 1, 'R');
        $this->Ln(14);
        $this->SetFont('freesans', 'B', 10.5);
        $this->Cell(0, 5, $name, 0, 1, 'R');
        $this->SetFont('freesans', '', 10);
        $this->Cell(0, 5, $title, 0, 1, 'R');
    }

    /**
     * Libellé du rôle d'un membre du jury.
     *
     * @param \stdClass $member
     * @return string
     */
    protected static function role_label(\stdClass $member) {
        $roles = these_jury_role_options();
        return $roles[$member->juryrole] ?? $member->juryrole;
    }

    /**
     * Convocation à la soutenance, pour l'étudiant (membre null) ou pour un membre du jury.
     *
     * @param array $data
     * @param \stdClass|null $member
     * @return string Contenu PDF.
     */
    public static function convocation(array $data, ?\stdClass $member) {
        $pdf = self::start($data, get_string('convocation', 'mod_these'));
        $pdf->doctitle(get_string('convocation', 'mod_these'));

        $a = (object) [
            'name' => $member ? trim($member->jurytitle . ' ' . $member->fullname) : $data['student'],
            'student' => $data['student'],
            'date' => $data['date'],
            'time' => $data['time'],
            'location' => $data['location'] !== '' ? $data['location'] : '-',
            'role' => $member ? self::role_label($member) : '',
        ];
        $pdf->SetFont('freesans', '', 10.5);
        $pdf->Cell(0, 6, $a->name, 0, 1, 'R');
        if ($member && $member->institution !== '') {
            $pdf->Cell(0, 6, $member->institution, 0, 1, 'R');
        }
        $pdf->Ln(6);
        $pdf->paragraph(get_string($member ? 'pdfconvocationjury' : 'pdfconvocationstudent', 'mod_these', $a));
        $pdf->band(get_string('defense', 'mod_these'));
        $pdf->field(get_string('defensedate', 'mod_these'), $data['date'] . ' — ' . $data['time']);
        $pdf->field(get_string('defenselocation', 'mod_these'), $a->location);
        $pdf->dossier_fields();
        $pdf->band(get_string('jury', 'mod_these'));
        foreach ($data['jury'] as $juror) {
            $pdf->field(self::role_label($juror), trim($juror->jurytitle . ' ' . $juror->fullname)
                . ($juror->institution !== '' ? ' (' . $juror->institution . ')' : ''));
        }
        $pdf->signature($data['signatoryname'], $data['signatorytitle']);
        return $pdf->Output('', 'S');
    }

    /**
     * Feuille de jury : liste d'émargement des membres du jury, avec une case de signature.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function jury_sheet(array $data) {
        $pdf = self::start($data, get_string('jurysheet', 'mod_these'));
        $pdf->doctitle(get_string('jurysheet', 'mod_these'));
        $pdf->dossier_fields();
        $pdf->field(get_string('defensedate', 'mod_these'), $data['date'] . ' — ' . $data['time']);
        $pdf->field(get_string('defenselocation', 'mod_these'), $data['location']);
        $pdf->Ln(4);

        $widths = [38, 72, 60];
        $pdf->SetFont('freesans', 'B', 10);
        $pdf->SetFillColor(230, 236, 241);
        $headers = [get_string('juryrole', 'mod_these'), get_string('juryname', 'mod_these'),
            get_string('pdfsignature', 'mod_these')];
        foreach ($headers as $i => $header) {
            $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('freesans', '', 10);
        foreach ($data['jury'] as $member) {
            $name = trim($member->jurytitle . ' ' . $member->fullname)
                . ($member->institution !== '' ? "\n" . $member->institution : '');
            $pdf->MultiCell($widths[0], 16, self::role_label($member), 1, 'L', false, 0, '', '', true, 0, false, true, 16, 'M');
            $pdf->MultiCell($widths[1], 16, $name, 1, 'L', false, 0, '', '', true, 0, false, true, 16, 'M');
            $pdf->Cell($widths[2], 16, '', 1, 1);
        }
        return $pdf->Output('', 'S');
    }

    /**
     * Procès-verbal de soutenance, à compléter et signer par le jury le jour de la soutenance,
     * puis à déposer par la DEVE (phase 2, étape 6). Si le résultat est déjà connu, il est
     * imprimé ; sinon, les cases restent à cocher.
     *
     * @param array $data
     * @return string Contenu PDF.
     */
    public static function minutes(array $data) {
        $pdf = self::start($data, get_string('minutes', 'mod_these'));
        $pdf->doctitle(get_string('minutes', 'mod_these'));
        $pdf->dossier_fields();
        $pdf->field(get_string('defensedate', 'mod_these'), $data['date'] . ' — ' . $data['time']);
        $pdf->field(get_string('defenselocation', 'mod_these'), $data['location']);

        $pdf->band(get_string('jury', 'mod_these'));
        foreach ($data['jury'] as $member) {
            $pdf->field(self::role_label($member), trim($member->jurytitle . ' ' . $member->fullname));
        }

        $pdf->band(get_string('pdfdeliberation', 'mod_these'));
        $pdf->paragraph(get_string('pdfminutestext', 'mod_these', (object) ['student' => $data['student']]));
        $pdf->SetFont('freesans', '', 11);
        // Cases à cocher dessinées : la police ne contient pas le caractère « ☐ ».
        foreach (these_result_options() as $label) {
            $pdf->Rect($pdf->GetX(), $pdf->GetY() + 2, 4, 4);
            $pdf->Cell(7, 8, '', 0, 0);
            $pdf->Cell(0, 8, $label, 0, 1, 'L');
        }
        $pdf->Cell(0, 8, get_string('mention', 'mod_these') . ' : ' . str_repeat('.', 80), 0, 1, 'L');
        $pdf->Ln(4);

        $pdf->band(get_string('pdfsignatures', 'mod_these'));
        $pdf->SetFont('freesans', '', 10);
        foreach ($data['jury'] as $member) {
            if ($member->juryrole === 'guest') {
                continue;
            }
            $pdf->Cell(90, 14, trim($member->jurytitle . ' ' . $member->fullname), 'B', 0, 'L');
            $pdf->Cell(0, 14, '', 'B', 1);
        }
        return $pdf->Output('', 'S');
    }
}
