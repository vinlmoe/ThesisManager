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

namespace mod_these\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/output_testcase.php');

/**
 * Diffusion et embargo : motif réservé à qui connaît le sujet.
 *
 * @package    mod_these
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_these\output\embargo
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_these\output\embargo::class)]
final class embargo_test extends output_testcase {
    /**
     * Contexte : diffusion, embargo daté ; motif seulement pour qui connaît le sujet.
     */
    public function test_export_for_template(): void {
        $this->setTimezone('Europe/Paris');
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $viewall = $this->viewall_user();

        $library = $this->context_json(new embargo($dossier, these_get_rights($this->context, $dossier, $this->librarian->id)));
        $this->assertStringContainsString(self::SECRETS['embargoreason'], $library);
        $until = userdate(make_timestamp(2027, 12, 3), get_string('strftimedate', 'langconfig'));
        $this->assertStringContainsString($until, $library);
        $this->assertStringContainsString(get_string('diffusionrestricted', 'mod_these'), $library);

        $rights = these_get_rights($this->context, $dossier, $viewall->id);
        $this->assertFalse($rights->knowssubject);
        $this->assert_no_secret($this->context_json(new embargo($dossier, $rights)));

        $dossier->bordereau = '';
        $this->assertFalse((new embargo($dossier, $rights))->export_for_template($this->renderer())['hasrows']);
    }

    /**
     * Rendu : badge d'embargo ; rien sans bordereau.
     */
    public function test_render(): void {
        $this->setup_activity();
        $dossier = $this->confidential_dossier();
        $rights = these_get_rights($this->context, $dossier, $this->viewall_user()->id);
        $html = $this->renderer()->render(new embargo($dossier, $rights));
        $this->assert_html_has($html, './*[1][self::h4][normalize-space() = "' . get_string('diffusion', 'mod_these') . '"]');
        $this->assert_html_has($html, './/span[' . self::xclass('badge') . ' and ' . self::xclass('badge-warning') . ']');
        $this->assert_no_secret($html);
        $dossier->bordereau = '';
        $this->assertSame('', these_render_embargo($dossier, $rights));
    }
}
