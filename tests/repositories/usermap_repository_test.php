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
 * Tests for usermap_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\repositories;

use local_lehrgaengeapi\local\repository\usermap_repository;

/**
 * Tests for usermap_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usermap_repository_test extends \advanced_testcase {
    /**
     * Ensure() creates mapping row once and returns same row on repeated calls.
     *
     * @covers \local_lehrgaengeapi\local\repository\usermap_repository::ensure
     * @covers \local_lehrgaengeapi\local\repository\usermap_repository::get_by_externalinitialid
     */
    public function test_ensure_creates_once(): void {
        global $DB;
        $this->resetAfterTest(true);

        $repo = new usermap_repository();

        $row1 = $repo->ensure('INIT-1');
        $row2 = $repo->ensure('INIT-1');

        $this->assertSame($row1->id, $row2->id);
        $this->assertSame('INIT-1', $row2->externalinitialid);

        $count = $DB->count_records('local_lehrgaengeapi_usermap', ['externalinitialid' => 'INIT-1']);
        $this->assertSame(1, $count);
    }

    /**
     * set_userid() updates mapping and does not create duplicates.
     *
     * @covers \local_lehrgaengeapi\local\repository\usermap_repository::set_userid
     */
    public function test_set_userid_updates(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user(['email' => 'u1@example.test']);

        $repo = new usermap_repository();
        $row = $repo->set_userid('INIT-2', (int)$user->id);

        $this->assertSame('INIT-2', $row->externalinitialid);
        $this->assertSame((int)$user->id, (int)$row->userid);

        $count = $DB->count_records('local_lehrgaengeapi_usermap', ['externalinitialid' => 'INIT-2']);
        $this->assertSame(1, $count);
    }
}
