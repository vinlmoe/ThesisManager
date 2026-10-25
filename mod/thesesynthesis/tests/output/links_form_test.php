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

namespace mod_thesesynthesis\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/output_testcase.php');

/**
 * Formulaire des promotions suivies.
 *
 * @package    mod_thesesynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_thesesynthesis\output\links_form
 */
final class links_form_test extends output_testcase {
    /**
     * Contexte : une case par activité proposée, cochée si elle est suivie ; badge d'un cours caché.
     */
    public function test_export_for_template(): void {
        $this->setup_env();
        $available = thesesynthesis_get_available_activities($this->env->deve->id);
        $this->assertNotEmpty($available);
        $activity = reset($available);
        $activity->coursevisible = 0;
        $url = new \moodle_url('/mod/thesesynthesis/administration.php', ['id' => $this->env->cm->id]);
        $data = (new links_form($url, [$activity], thesesynthesis_get_links($this->env->synthesis->id)))
            ->export_for_template($this->renderer());
        $this->assertSame(sesskey(), $data['sesskey']);
        $cells = $data['table']['rows'][0]['cells'];
        $checkbox = $cells[0]['parts'][0]['pcheckbox'];
        $this->assertSame(['name' => 'thesecmid[]', 'value' => (string) $activity->thesecmid, 'checked' => true,
            'id' => 'thesecmid_' . $activity->thesecmid], $checkbox);
        $this->assertSame('badge badge-secondary', $cells[1]['parts'][2]['pspan']['class']);
        $this->assertCount(1, $cells[2]['parts']);
    }

    /**
     * Rendu : formulaire POST avec sesskey, cases à cocher, bouton.
     */
    public function test_render(): void {
        $this->setup_env();
        $available = thesesynthesis_get_available_activities($this->env->deve->id);
        $url = new \moodle_url('/mod/thesesynthesis/administration.php', ['id' => $this->env->cm->id]);
        $html = $this->renderer()->render(new links_form($url, $available, []));
        $start = '<form method="post" action="' . s($url->out(false)) . '"><input type="hidden" name="sesskey"';
        $this->assertStringStartsWith($start, $html);
        $checkbox = '#<input id="thesecmid_\d+" type="checkbox" value="\d+" name="thesecmid\[\]" />#';
        $this->assertMatchesRegularExpression($checkbox, $html);
        $end = '<button type="submit" class="btn btn-primary">' . get_string('savechanges') . '</button></form>';
        $this->assertStringEndsWith($end, $html);
    }
}
