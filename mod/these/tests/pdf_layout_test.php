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

namespace mod_these;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/these_testcase.php');

/**
 * Lisibilité des documents PDF avec des noms et des titres très longs : pour chaque modèle de document,
 * le texte reste dans la page, aucun mot n'en recouvre un autre, et le titre et les noms cités par le
 * modèle figurent en entier.
 *
 * La position de chaque mot est lue par pdftotext -bbox-layout (poppler-utils, installé par la CI).
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\pdf\html_document
 * @covers     ::these_build_document_context
 */
final class pdf_layout_test extends these_testcase {
    /** @var string Titre très long, avec un mot plus long qu'une colonne de tableau. */
    const TITLE = 'Étude rétrospective multicentrique des hépatoencéphalopathiesportosystémiquescongénitales '
        . 'chez le chien et le chat : facteurs pronostiques, prise en charge médicale et chirurgicale, '
        . 'complications postopératoires à court et à long terme et qualité de vie rapportée par les propriétaires '
        . 'dans quarante-deux centres hospitaliers vétérinaires universitaires européens entre 2005 et 2025';

    /** @var string Titre anglais très long. */
    const TITLEEN = 'Multicentre retrospective study of congenital portosystemic hepatic encephalopathies in dogs and cats: '
        . 'prognostic factors, medical and surgical management, short- and long-term postoperative complications '
        . 'and owner-reported quality of life in forty-two European university veterinary hospitals';

    /** @var string[] Clés du contexte dont la valeur doit figurer en entier quand le modèle la cite. */
    const CHECKED = ['thesis.title', 'thesis.titleupper', 'thesis.titleen', 'student.firstname', 'student.lastname',
        'student.fullname', 'director.fullname', 'recipient.name'];

    /**
     * Données d'un dossier aux valeurs très longues, au format des modèles de documents.
     *
     * @param \stdClass|null $recipient Destinataire d'une convocation.
     * @return array Voir these_build_document_context().
     */
    private function long_context(?\stdClass $recipient = null): array {
        $now = make_timestamp(2026, 11, 3, 14);
        $long = 'Centre hospitalier universitaire vétérinaire d’enseignement et de recherche clinique des animaux de compagnie';
        $bordereau = [
            'birthname' => 'De La Tour d’Auvergne-Lauraguais-Montmorency', 'firstnames' => 'Marie-Charlotte Alexandrine Eugénie',
            'usagename' => 'Saint-Exupéry-Villeneuve', 'publicationname' => '', 'birthdate' => make_timestamp(1999, 4, 12),
            'birthplace' => 'Saint-Rémy-de-Provence-sur-Durance', 'birthdept' => '13', 'nationality' => 'Française',
            'street' => '1234 boulevard du Maréchal-de-Lattre-de-Tassigny, résidence des Jardins suspendus, bâtiment B',
            'postcode' => '69007', 'city' => 'Villeurbanne-la-Doua-Campus-Universitaire', 'country' => 'France',
            'phone' => '06 12 34 56 78', 'personalemail' => 'marie-charlotte.delatourdauvergne-lauraguais@example.org',
            'emailconsent' => 1, 'defvdate' => make_timestamp(2023, 7, 3), 'defvschool' => 'lyon',
            'a6date' => make_timestamp(2025, 7, 1), 'a6school' => 'lyon', 'a6notyet' => 0, 'nbvolumes' => 1,
            'nbpages' => 312, 'nbrefs' => 486,
            'summaryfr' => str_repeat('Ce travail décrit les shunts portosystémiques congénitaux et leur pronostic. ', 14),
            'summaryen' => str_repeat('This work describes congenital portosystemic shunts and their prognosis. ', 14),
            'keywords' => ['Shunt portosystémique congénital', 'Encéphalopathie hépatique', 'Chirurgie', 'Pronostic',
                'Qualité de vie', 'Étude multicentrique'],
            'confidentialuntil' => 0, 'embargo' => 1, 'embargoreason' => 'Brevet en cours de dépôt', 'embargountil' => 0,
            'diffusion' => 'internet', 'visio' => 0,
        ];
        $dossier = (object) [
            'id' => 0, 'userid' => 0, 'directorid' => 0, 'title' => self::TITLE, 'titleen' => self::TITLEEN,
            'summary' => str_repeat('Résumé du sujet. ', 20), 'keywords' => 'Chien ; Chat ; Shunt ; Encéphalopathie hépatique',
            'codirectorname' => 'Dr Jean-Baptiste Fontaine-Vauvenargues de Montaigu-Beauregard', 'studyyear' => 'A5',
            'confidential' => 0, 'directortitle' => 'Pr', 'externaldirector' => 1,
            'extdirectorname' => 'Pr Anne-Sophie Lefèvre-Beauchamp de La Rochefoucauld',
            'extdirectorgrade' => 'Professeure des universités – praticienne hospitalière', 'extdirectorinstitution' => $long,
            'extdirectoremail' => 'anne-sophie.lefevre-beauchamp@example.org',
            'commitments' => THESE_COMMIT_GUIDE | THESE_COMMIT_DUPLICATE, 'ethicsnumber' => '2526-1234-5678',
            'bordereau' => json_encode($bordereau),
            'conclusions' => str_repeat('Les conclusions du jury tiennent sur plusieurs lignes. ', 10)
                . "\n\n" . 'Dernier paragraphe.',
            'reporttext' => str_repeat('Le manuscrit est clair, rigoureux et bien construit. ', 15), 'reportfavorable' => 1,
            'conformitygrid' => json_encode(array_fill_keys(
                array_merge(...array_map('array_keys', array_values(these_get_grid_items(null)))),
                1
            )),
            'agreement' => json_encode(['defvdate' => make_timestamp(2023, 7, 3), 'mobility' => 1,
                'a6date' => make_timestamp(2025, 7, 1), 'a6notyet' => 0,
                'directorfunction' => 'Professeur des universités, chef du service de chirurgie des tissus mous']),
            'devenumber' => '2026-042', 'libraryref' => 'THV-2026-042', 'proposeddate' => $now, 'defensedate' => $now,
            'defenselocation' => 'Grand amphithéâtre Claude-Bourgelat du campus vétérinaire, bâtiment des enseignements cliniques',
            'defenseresult' => 0, 'mention' => '',
        ];
        $jury = [];
        foreach (
            [
                ['president', 'Professeur', 'Jean-Christophe Delacroix-Montgolfier',
                    'Université Claude Bernard Lyon 1, faculté de médecine Lyon-Est'],
                ['member', 'Docteure', 'Claire-Amandine Bernard-Lefranc de Saint-Aubin', $long],
                ['member', 'Docteur', 'Paul-Henri Leroy-Duchemin', $long],
                ['director', 'Professeure', 'Anne-Sophie Durand-Villedieu', $long],
                ['guest', 'Docteur', 'Luc-Emmanuel Petit-Desrosiers',
                    'Clinique vétérinaire des Monts du Lyonnais et du Beaujolais'],
            ] as $i => [$role, $title, $name, $institution]
        ) {
            $jury[] = (object) ['juryrole' => $role, 'jurytitle' => $title, 'fullname' => $name, 'institution' => $institution,
                'email' => 'jury' . $i . '@example.org'];
        }
        $signatures = [];
        foreach (['student', 'director', 'extdirector', 'deve', 'library', 'dgrep', 'dg', 'ccem', 'ucbl', 'president'] as $slot) {
            $signatures[$slot] = ['name' => 'Signataire au nom particulièrement long pour ' . these_slot_label($slot),
                'time' => $now, 'hash' => '3f2a9c51d0e4', 'image' => these_sample_signature_image()];
        }
        return these_build_document_context([
            'these' => (object) [
                'establishmentname' => 'École nationale vétérinaire, agroalimentaire et de l’alimentation Nantes-Atlantique',
                'establishmentaddress' => "1 avenue Claude-Bourgelat\n69280 Marcy-l’Étoile",
                'establishmentcity' => 'Marcy-l’Étoile', 'signatoryname' => 'Hélène Aguesse-Montbrison',
                'signatorytitle' => 'Directrice des études, de la formation et de la vie étudiante',
            ],
            'dossier' => $dossier,
            'student' => (object) ['firstname' => 'Marie-Charlotte Alexandrine Eugénie',
                'lastname' => 'De La Tour d’Auvergne-Lauraguais-Montmorency', 'email' => 'mc.delatour@example.org'],
            'director' => (object) ['firstname' => 'Anne-Sophie', 'lastname' => 'Durand-Villedieu de Castelnau',
                'email' => 'as.durand@example.org'],
            'jury' => $jury,
            'signatures' => $signatures,
            'recipient' => $recipient,
            'scope' => null,
        ]);
    }

    /**
     * Données du planning avec des soutenances aux noms et titres très longs.
     *
     * @return array Voir these_planning_document_context().
     */
    private function long_planning_context(): array {
        $context = these_sample_planning_context(null, null);
        foreach ($context['days'][0]['defenses'] as $i => $defense) {
            $context['days'][0]['defenses'][$i] = array_merge($defense, [
                'student' => 'Marie-Charlotte De La Tour d’Auvergne-Lauraguais-Montmorency',
                'title' => self::TITLE,
                'director' => 'Pr Anne-Sophie Durand-Villedieu de Castelnau (Dr Jean-Baptiste Fontaine-Vauvenargues)',
                'president' => 'Professeur Jean-Christophe Delacroix-Montgolfier',
                'jury' => 'Docteure Claire-Amandine Bernard-Lefranc de Saint-Aubin, Docteur Paul-Henri Leroy-Duchemin, '
                    . 'Professeure Anne-Sophie Durand-Villedieu – Directeur de thèse (membre du jury)',
                'location' => 'Grand amphithéâtre Claude-Bourgelat, bâtiment des enseignements cliniques',
            ]);
        }
        return $context;
    }

    /**
     * Ignore le test si pdftotext n'est pas installé, sauf quand la CI l'exige
     * (MOD_THESE_REQUIRE_PDFTOTEXT) : il échoue alors.
     *
     * @return void
     */
    private function require_pdftotext(): void {
        exec('command -v pdftotext', $output, $status);
        if ($status === 0) {
            return;
        }
        $message = 'pdftotext (poppler-utils) est nécessaire pour lire la position des mots.';
        if (getenv('MOD_THESE_REQUIRE_PDFTOTEXT')) {
            $this->fail($message);
        }
        $this->markTestSkipped($message);
    }

    /**
     * Mots d'un PDF avec leur position, lus par pdftotext -bbox-layout.
     *
     * @param string $pdf
     * @return array[] Pages : width, height, words (text, xmin, ymin, xmax, ymax).
     */
    private function read_words(string $pdf): array {
        $dir = make_request_directory();
        file_put_contents($dir . '/document.pdf', $pdf);
        $command = 'pdftotext -bbox-layout ' . escapeshellarg($dir . '/document.pdf') . ' ' . escapeshellarg($dir . '/words.html');
        exec($command . ' 2>&1', $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));
        $xml = simplexml_load_string(preg_replace('/<!DOCTYPE[^>]*>/', '', file_get_contents($dir . '/words.html')));
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
        $pages = [];
        foreach ($xml->xpath('//x:page') as $page) {
            $page->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $words = [];
            foreach ($page->xpath('.//x:word') as $word) {
                $words[] = ['text' => (string) $word, 'xmin' => (float) $word['xMin'], 'ymin' => (float) $word['yMin'],
                    'xmax' => (float) $word['xMax'], 'ymax' => (float) $word['yMax']];
            }
            $pages[] = ['width' => (float) $page['width'], 'height' => (float) $page['height'], 'words' => $words];
        }
        return $pages;
    }

    /**
     * Vérifie qu'un PDF est lisible : texte dans la page, pas de mots superposés.
     *
     * @param string $pdf
     * @param string $name Document (messages d'échec).
     * @return string Texte du document, sans espaces.
     */
    private function assert_readable(string $pdf, string $name): string {
        $pages = $this->read_words($pdf);
        $this->assertNotEmpty($pages, $name);
        $text = '';
        foreach ($pages as $number => $page) {
            $words = $page['words'];
            foreach ($words as $word) {
                $where = sprintf('%s, page %d, « %s »', $name, $number + 1, $word['text']);
                $this->assertGreaterThanOrEqual(-0.5, $word['xmin'], "$where : déborde à gauche");
                $this->assertGreaterThanOrEqual(-0.5, $word['ymin'], "$where : déborde en haut");
                $this->assertLessThanOrEqual($page['width'] + 0.5, $word['xmax'], "$where : déborde à droite");
                $this->assertLessThanOrEqual($page['height'] + 0.5, $word['ymax'], "$where : déborde en bas");
                $text .= $word['text'];
            }
            // Deux mots se recouvrent quand leurs rectangles se croisent sur plus d'un point dans les deux
            // directions et sur plus du quart de la hauteur du plus petit (interlignes serrés tolérés).
            $count = count($words);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    [$a, $b] = [$words[$i], $words[$j]];
                    $overlapx = min($a['xmax'], $b['xmax']) - max($a['xmin'], $b['xmin']);
                    $overlapy = min($a['ymax'], $b['ymax']) - max($a['ymin'], $b['ymin']);
                    $height = min($a['ymax'] - $a['ymin'], $b['ymax'] - $b['ymin']);
                    if ($overlapx > 1 && $overlapy > max(1, $height / 4)) {
                        $this->fail(sprintf('%s, page %d : « %s » recouvre « %s »', $name, $number + 1, $a['text'], $b['text']));
                    }
                }
            }
        }
        return $text;
    }

    /**
     * Vérifie que chaque mot d'une valeur figure dans le texte du document. Mot par mot : dans un tableau,
     * l'ordre de lecture de pdftotext entrelace les lignes d'une cellule et le libellé voisin ; un mot
     * composé se coupe en fin de ligne après un trait d'union. Sans casse : certains modèles mettent les
     * noms en capitales.
     *
     * @param string $value
     * @param string $text Texte du document, sans espaces (voir assert_readable()).
     * @param string $message
     * @return void
     */
    private function assert_words_present(string $value, string $text, string $message): void {
        $text = \core_text::strtolower($text);
        foreach (preg_split('/\s+|(?<=-)/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $this->assertStringContainsString(\core_text::strtolower($word), $text, "$message : « $word » absent ou coupé");
        }
    }

    /**
     * Valeur d'une clé pointée (thesis.title) dans un contexte de modèle.
     *
     * @param array $context
     * @param string $key
     * @return string|null
     */
    private static function value(array $context, string $key): ?string {
        foreach (explode('.', $key) as $part) {
            if (!is_array($context) || !array_key_exists($part, $context)) {
                return null;
            }
            $context = $context[$part];
        }
        return is_scalar($context) ? (string) $context : null;
    }

    /**
     * Chaque modèle de document, rendu avec des noms et des titres très longs, reste lisible ; le titre et
     * les noms qu'il cite figurent en entier.
     */
    public function test_documents_with_long_names_and_titles(): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Paris');
        $this->require_pdftotext();
        $president = (object) ['juryrole' => 'president', 'jurytitle' => 'Professeur',
            'fullname' => 'Jean-Christophe Delacroix-Montgolfier de Saint-Exupéry',
            'institution' => 'Université Claude Bernard Lyon 1, faculté de médecine et de pharmacie Lyon-Est'];
        foreach (array_keys(these_doctemplate_types()) as $type) {
            $template = these_doctemplate_default($type);
            $context = $type === 'planning' ? $this->long_planning_context()
                : $this->long_context($type === 'convocation' ? $president : null);
            $text = $this->assert_readable(these_render_scope_doctemplate(null, $type, $context), $type);
            foreach (self::CHECKED as $key) {
                $value = self::value($context, $key);
                if ($value !== null && strpos($template, '{{' . $key . '}}') !== false) {
                    $this->assert_words_present($value, $text, "$type : {{$key}}");
                }
            }
            if ($type === 'planning') {
                $this->assert_words_present(self::TITLE, $text, 'planning : titre');
            }
        }
    }

    /**
     * Le contrôle rejette bien un PDF dont deux mots se recouvrent, ou dont un mot sort de la page.
     */
    public function test_detects_overlap_and_overflow(): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $this->resetAfterTest();
        $this->require_pdftotext();
        $make = function (array $texts): string {
            $pdf = new \pdf();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->SetFont('freesans', '', 12);
            foreach ($texts as [$x, $y, $text]) {
                $pdf->Text($x, $y, $text);
            }
            return $pdf->Output('', 'S');
        };
        $this->assert_readable($make([[20, 20, 'Premier'], [20, 40, 'Second']]), 'témoin');
        foreach (
            [
                'recouvre' => [[20, 20, 'Premier'], [24, 20, 'Second']],
                'déborde à droite' => [[20, 20, 'Premier'], [200, 60, 'Débordement']],
            ] as $expected => $texts
        ) {
            try {
                $this->assert_readable($make($texts), 'défaut');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
                continue;
            }
            $this->fail("Défaut non détecté : $expected");
        }
    }
}
