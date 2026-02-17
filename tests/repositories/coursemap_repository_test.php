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
 * Tests for coursemap_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\repositories;

use local_lehrgaengeapi\local\repository\coursemap_repository;

/**
 * Tests for coursemap_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursemap_repository_test extends \advanced_testcase {
    /**
     * Ensure() creates mapping row once and returns same row on repeated calls.
     *
     * @covers \local_lehrgaengeapi\local\repository\coursemap_repository::ensure
     * @covers \local_lehrgaengeapi\local\repository\coursemap_repository::get_by_externalid
     */
    public function test_ensure_creates_once(): void {
        global $DB;
        $this->resetAfterTest(true);

        $repo = new coursemap_repository();

        $row1 = $repo->ensure('LG-1');
        $row2 = $repo->ensure('LG-1');

        $this->assertSame($row1->id, $row2->id);
        $this->assertSame('LG-1', $row2->externalid);

        $count = $DB->count_records('local_lehrgaengeapi_coursemap', ['externalid' => 'LG-1']);
        $this->assertSame(1, $count);
    }

    /**
     * set_courseid() updates mapping and does not create duplicates.
     *
     * @covers \local_lehrgaengeapi\local\repository\coursemap_repository::set_courseid
     */
    public function test_set_courseid_updates(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['fullname' => 'X', 'shortname' => 'X1']);

        $repo = new coursemap_repository();
        $row = $repo->set_courseid('LG-2', (int)$course->id);

        $this->assertSame('LG-2', $row->externalid);
        $this->assertSame((int)$course->id, (int)$row->courseid);

        $count = $DB->count_records('local_lehrgaengeapi_coursemap', ['externalid' => 'LG-2']);
        $this->assertSame(1, $count);
    }
}
