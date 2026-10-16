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
 * Tests for ausbildungen_profile_field.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\users;

/**
 * Tests for ausbildungen_profile_field.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ausbildungen_profile_field_test extends \advanced_testcase {
    /**
     * The field is created as a locked textarea with the expected shortname.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_profile_field::ensure_exists
     */
    public function test_ensure_exists_creates_locked_textarea_field(): void {
        global $DB;
        $this->resetAfterTest(true);
        $DB->delete_records('user_info_field', ['shortname' => ausbildungen_profile_field::SHORTNAME]);

        $fieldid = ausbildungen_profile_field::ensure_exists();

        $field = $DB->get_record('user_info_field', ['id' => $fieldid], '*', MUST_EXIST);
        $this->assertSame('ausbildungen', $field->shortname);
        $this->assertSame('textarea', $field->datatype);
        $this->assertSame(1, (int)$field->locked);
        $this->assertGreaterThan(0, (int)$field->categoryid);
    }

    /**
     * Calling it again (e.g. on every upgrade run) must not create a second field.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_profile_field::ensure_exists
     */
    public function test_ensure_exists_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest(true);

        $first = ausbildungen_profile_field::ensure_exists();
        $second = ausbildungen_profile_field::ensure_exists();

        $this->assertSame($first, $second);
        $this->assertSame(1, $DB->count_records('user_info_field', ['shortname' => 'ausbildungen']));
    }

    /**
     * get_fieldid() returns the id of an existing field, and null if it was deleted.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_profile_field::get_fieldid
     */
    public function test_get_fieldid_returns_null_when_field_is_missing(): void {
        global $DB;
        $this->resetAfterTest(true);

        $fieldid = ausbildungen_profile_field::ensure_exists();
        $this->assertSame($fieldid, ausbildungen_profile_field::get_fieldid());

        $DB->delete_records('user_info_field', ['id' => $fieldid]);
        $this->assertNull(ausbildungen_profile_field::get_fieldid());
    }
}
