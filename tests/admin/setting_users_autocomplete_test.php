<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for setting_users_autocomplete.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// The admin_setting base class is not autoloaded; admin pages load it themselves.
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for setting_users_autocomplete.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class setting_users_autocomplete_test extends \advanced_testcase {
    /**
     * Build the setting under test.
     *
     * @return setting_users_autocomplete
     */
    private function create_setting(): setting_users_autocomplete {
        return new setting_users_autocomplete(
            'local_lehrgaengeapi/notifyusers',
            'Notify users',
            'Description'
        );
    }

    /**
     * Selected user ids are stored as a comma separated list in the submitted order.
     *
     * @covers \local_lehrgaengeapi\admin\setting_users_autocomplete::write_setting
     * @covers \local_lehrgaengeapi\admin\setting_users_autocomplete::get_setting
     */
    public function test_write_setting_stores_selected_users(): void {
        $this->resetAfterTest(true);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $setting = $this->create_setting();
        $error = $setting->write_setting([(string)$user2->id, (string)$user1->id]);

        $this->assertSame('', $error);
        $this->assertSame(
            $user2->id . ',' . $user1->id,
            get_config('local_lehrgaengeapi', 'notifyusers')
        );
        $this->assertSame($user2->id . ',' . $user1->id, $setting->get_setting());
    }

    /**
     * The placeholder value that keeps an empty selection submittable, unknown ids,
     * deleted users and duplicates are dropped.
     *
     * @covers \local_lehrgaengeapi\admin\setting_users_autocomplete::write_setting
     */
    public function test_write_setting_drops_invalid_entries(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $deleted = $this->getDataGenerator()->create_user();
        delete_user($deleted);

        $setting = $this->create_setting();
        $setting->write_setting(['xxxxx', (string)$user->id, '999999', (string)$deleted->id, (string)$user->id, 'abc']);

        $this->assertSame((string)$user->id, get_config('local_lehrgaengeapi', 'notifyusers'));
    }

    /**
     * Clearing the selection (only the placeholder is submitted) empties the setting.
     *
     * @covers \local_lehrgaengeapi\admin\setting_users_autocomplete::write_setting
     */
    public function test_write_setting_with_empty_selection_clears_setting(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        set_config('notifyusers', (string)$user->id, 'local_lehrgaengeapi');

        $setting = $this->create_setting();
        $error = $setting->write_setting(['xxxxx']);

        $this->assertSame('', $error);
        $this->assertSame('', get_config('local_lehrgaengeapi', 'notifyusers'));
    }

    /**
     * Only the already selected users are rendered as options (the rest is searched via
     * AJAX), they are marked selected and submitted as an array.
     *
     * @covers \local_lehrgaengeapi\admin\setting_users_autocomplete::output_html
     */
    public function test_output_html_renders_only_selected_users(): void {
        $this->resetAfterTest(true);
        $selected = $this->getDataGenerator()->create_user(['firstname' => 'Selecta', 'lastname' => 'Person']);
        $other = $this->getDataGenerator()->create_user(['firstname' => 'Unselecta', 'lastname' => 'Person']);
        set_config('notifyusers', (string)$selected->id, 'local_lehrgaengeapi');

        $setting = $this->create_setting();
        $html = $setting->output_html($setting->get_setting());

        $this->assertStringContainsString('name="s_local_lehrgaengeapi_notifyusers[]"', $html);
        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('value="' . $selected->id . '" selected', $html);
        $this->assertStringContainsString('Selecta Person', $html);
        $this->assertStringNotContainsString('Unselecta', $html);
        $this->assertStringNotContainsString('value="' . $other->id . '"', $html);
    }
}
