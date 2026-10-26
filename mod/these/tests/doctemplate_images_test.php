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

use mod_these\local\doctemplates;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/these/locallib.php');

/**
 * Images fournies avec le module pour les modèles de documents, lues sans expansion d'accolades
 * de glob() (absente des PHP compilés avec musl, image Docker Alpine).
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\local\doctemplates::doctemplate_image_files
 * @covers     \mod_these\local\doctemplates::doctemplate_images
 */
final class doctemplate_images_test extends \advanced_testcase {
    /**
     * Tous les fichiers PNG et JPEG du dossier doctemplates/images sont trouvés, PNG d'abord.
     */
    public function test_image_files(): void {
        global $CFG;

        $dir = $CFG->dirroot . '/mod/these/doctemplates/images/';
        $paths = doctemplates::doctemplate_image_files();
        $this->assertContains($dir . 'abes.png', $paths);
        $this->assertContains($dir . 'vetagrosup.png', $paths);
        $this->assertContains($dir . 'bulyon1.jpg', $paths);

        $expected = [];
        foreach (scandir($dir) as $filename) {
            if (preg_match('/\.(png|jpg|jpeg)$/', $filename)) {
                $expected[] = $dir . $filename;
            }
        }
        $this->assertEqualsCanonicalizing($expected, $paths);

        $extensions = array_map(fn($path) => pathinfo($path, PATHINFO_EXTENSION), $paths);
        $this->assertSame($extensions, array_values(array_merge(
            array_filter($extensions, fn($extension) => $extension === 'png'),
            array_filter($extensions, fn($extension) => $extension === 'jpg'),
            array_filter($extensions, fn($extension) => $extension === 'jpeg')
        )));
    }

    /**
     * Sans portée, les images du module sont chargées sous leur nom de modèle, contenu en base64.
     */
    public function test_module_images(): void {
        global $CFG;

        $images = these_doctemplate_images(null);
        foreach (['abes', 'vetagrosup'] as $name) {
            $this->assertArrayHasKey($name, $images);
            $content = file_get_contents($CFG->dirroot . '/mod/these/doctemplates/images/' . $name . '.png');
            $this->assertSame('@' . base64_encode($content), $images[$name]);
        }
        $this->assertCount(count(doctemplates::doctemplate_image_files()), $images);
    }
}
