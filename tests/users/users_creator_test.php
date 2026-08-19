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
 * Tests for users_creator.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\users;

use local_lehrgaengeapi\local\repository\usermap_repository;
use local_lehrgaengeapi\local\users\users_creator;

/**
 * Tests for users_creator.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class users_creator_test extends \advanced_testcase {
    /**
     * Existing users matched by idnumber should be reused and not duplicated.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_existing_user_with_matching_idnumber_is_reused(): void {
        global $DB;

        $this->resetAfterTest(true);

        $existinguser = $this->getDataGenerator()->create_user([
            'username' => 'existing-user',
            'idnumber' => 'P-00004561',
            'email' => 'existing@example.invalid',
            'firstname' => 'Existing',
            'lastname' => 'User',
        ]);

        $participants = [[
            'initialId' => 'P-00004561',
            'vorname' => 'Changed',
            'nachname' => 'Name',
            'emails' => [
                'emailBusiness' => 'different@example.invalid',
            ],
        ]];

        $creator = new users_creator();
        $summary = $creator->create($participants);

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['existing']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $DB->count_records('user', ['idnumber' => 'P-00004561', 'deleted' => 0]));

        $storeduser = $DB->get_record('user', ['id' => $existinguser->id], '*', MUST_EXIST);
        $this->assertSame('Existing', $storeduser->firstname);
        $this->assertSame('User', $storeduser->lastname);
        $this->assertSame('existing@example.invalid', $storeduser->email);

        $repo = new usermap_repository();
        $map = $repo->get_by_externalid('P-00004561');
        $this->assertNotNull($map);
        $this->assertSame((int)$existinguser->id, (int)$map->userid);
    }

    /**
     * A participant created without an initialId (e.g. legacy/archival API data) is
     * recognised again via their stable external id on a later import, and has their
     * username upgraded once the API starts supplying an initialId for them.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_user_created_without_initialid_is_updated_once_initialid_appears(): void {
        global $DB;

        $this->resetAfterTest(true);

        $firstimport = [[
            'id' => 'P-100',
            'initialId' => '',
            'vorname' => 'Jonas',
            'nachname' => 'Bleuel',
            'emails' => [
                'emailBusiness' => 'jonas.bleuel@example.invalid',
            ],
        ]];

        $creator = new users_creator();
        $summary1 = $creator->create($firstimport);

        $this->assertSame(1, $summary1['created']);
        $this->assertSame(0, $summary1['existing']);
        $this->assertSame(0, $summary1['skipped']);

        $user = $DB->get_record('user', ['idnumber' => 'P-100', 'deleted' => 0], '*', MUST_EXIST);
        $this->assertNotSame('INIT-100', $user->username);
        $originaluserid = (int)$user->id;

        $repo = new usermap_repository();
        $map = $repo->get_by_externalid('P-100');
        $this->assertNotNull($map);
        $this->assertSame($originaluserid, (int)$map->userid);

        // Later import: same participant (same external id), API now provides an initialId.
        $secondimport = [[
            'id' => 'P-100',
            'initialId' => 'INIT-100',
            'vorname' => 'Jonas',
            'nachname' => 'Bleuel',
            'emails' => [
                'emailBusiness' => 'jonas.bleuel@example.invalid',
            ],
        ]];

        $summary2 = $creator->create($secondimport);

        $this->assertSame(0, $summary2['created']);
        $this->assertSame(1, $summary2['existing']);
        $this->assertSame(0, $summary2['skipped']);
        $this->assertSame(
            1,
            $DB->count_records('user', ['idnumber' => 'P-100', 'deleted' => 0]),
            'No duplicate user should have been created.'
        );

        $updateduser = $DB->get_record('user', ['id' => $originaluserid], '*', MUST_EXIST);
        $this->assertSame('INIT-100', $updateduser->username);

        $mapafter = $repo->get_by_externalid('P-100');
        $this->assertSame($originaluserid, (int)$mapafter->userid, 'Mapping should still point at the same user.');
    }
}
