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
 * Tests for usertenant_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

/**
 * Tests for usertenant_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class usertenant_repository_test extends \advanced_testcase {
    /**
     * A tenant is remembered once; remembering it again is a no-op.
     *
     * @covers \local_lehrgaengeapi\local\repository\usertenant_repository::remember
     */
    public function test_remember_stores_each_tenant_once(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $repo = new usertenant_repository();

        $this->assertTrue($repo->remember((int)$user->id, 'FD'));
        $this->assertFalse($repo->remember((int)$user->id, 'FD'));
        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_usertenant', ['userid' => $user->id]));
    }

    /**
     * Several tenants are returned in the order they were first remembered, so the
     * linking checks the tenant the user came from first before the later ones.
     *
     * @covers \local_lehrgaengeapi\local\repository\usertenant_repository::get_tenants
     */
    public function test_get_tenants_returns_all_in_order_of_first_appearance(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $repo = new usertenant_repository();

        $repo->remember((int)$user->id, 'MKK');
        $repo->remember((int)$user->id, 'FD');
        $repo->remember((int)$user->id, 'MKK');
        $repo->remember((int)$other->id, 'GI');

        $this->assertSame(['MKK', 'FD'], $repo->get_tenants((int)$user->id));
        $this->assertSame(['GI'], $repo->get_tenants((int)$other->id));
        $this->assertSame([], $repo->get_tenants(999999));
    }

    /**
     * Empty abbreviations or user ids are ignored instead of producing useless rows.
     *
     * @covers \local_lehrgaengeapi\local\repository\usertenant_repository::remember
     */
    public function test_remember_ignores_empty_input(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $repo = new usertenant_repository();

        $this->assertFalse($repo->remember((int)$user->id, ''));
        $this->assertFalse($repo->remember((int)$user->id, '   '));
        $this->assertFalse($repo->remember(0, 'FD'));
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_usertenant'));
    }
}
