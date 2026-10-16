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
use local_lehrgaengeapi\local\users\ausbildungen_profile_field;
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
            'id' => 'P-00004561',
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

    /**
     * Read the stored "ausbildungen" profile field value of a user.
     *
     * @param int $userid
     * @return string|null Null if the user has no data row for the field.
     */
    private function stored_ausbildungen(int $userid): ?string {
        global $DB;
        $data = $DB->get_field('user_info_data', 'data', [
            'userid' => $userid,
            'fieldid' => ausbildungen_profile_field::get_fieldid(),
        ]);
        return $data === false ? null : (string)$data;
    }

    /**
     * Build a participant payload with the given trainings.
     *
     * @param string $id External participant id.
     * @param array $ausbildungen Short names of the trainings.
     * @return array
     */
    private function participant_with_ausbildungen(string $id, array $ausbildungen): array {
        return [
            'id' => $id,
            'initialId' => 'init-' . strtolower($id), // Moodle usernames must be lower case.
            'vorname' => 'Test',
            'nachname' => 'Person',
            'emails' => ['emailBusiness' => $id . '@example.invalid'],
            'ausbildungen' => array_map(static fn($kurz) => [
                'bezeichnung' => 'Lang ' . $kurz,
                'kurzBezeichnung' => $kurz,
                'von' => '2024-01-01',
                'bis' => '2024-01-02',
                'status' => 'BESTANDEN',
            ], $ausbildungen),
        ];
    }

    /**
     * A newly created user gets the trainings saved in the profile field, also when
     * the list is empty.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_new_user_gets_ausbildungen_saved(): void {
        global $DB;
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();

        $summary = (new users_creator())->create([
            $this->participant_with_ausbildungen('P-1', ['B-II', 'F-Atr']),
            $this->participant_with_ausbildungen('P-2', []),
        ]);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(2, $summary['ausbildungen_updated']);

        $user1 = $DB->get_record('user', ['idnumber' => 'P-1'], '*', MUST_EXIST);
        $decoded = json_decode($this->stored_ausbildungen((int)$user1->id), true);
        $this->assertSame(['B-II', 'F-Atr'], array_column($decoded, 'kurzBezeichnung'));

        $user2 = $DB->get_record('user', ['idnumber' => 'P-2'], '*', MUST_EXIST);
        $this->assertSame('', $this->stored_ausbildungen((int)$user2->id));
    }

    /**
     * A user already known via the mapping table gets changed trainings on the next sync,
     * an unchanged list is not counted as an update.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_mapped_user_gets_ausbildungen_updated_on_next_sync(): void {
        global $DB;
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();
        $creator = new users_creator();

        $creator->create([$this->participant_with_ausbildungen('P-1', ['B-II'])]);
        $userid = (int)$DB->get_field('user', 'id', ['idnumber' => 'P-1'], MUST_EXIST);

        $unchanged = $creator->create([$this->participant_with_ausbildungen('P-1', ['B-II'])]);
        $this->assertSame(1, $unchanged['existing']);
        $this->assertSame(0, $unchanged['ausbildungen_updated']);

        $changed = $creator->create([$this->participant_with_ausbildungen('P-1', ['B-II', 'B-III'])]);
        $this->assertSame(1, $changed['existing']);
        $this->assertSame(1, $changed['ausbildungen_updated']);
        $decoded = json_decode($this->stored_ausbildungen($userid), true);
        $this->assertSame(['B-II', 'B-III'], array_column($decoded, 'kurzBezeichnung'));
    }

    /**
     * An existing Moodle user found by idnumber (no mapping yet) gets the trainings too.
     * This path used to end right after the mapping was stored.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_user_found_by_idnumber_gets_ausbildungen_saved(): void {
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();
        $existing = $this->getDataGenerator()->create_user(['idnumber' => 'P-7', 'username' => 'someone-else']);

        $summary = (new users_creator())->create([$this->participant_with_ausbildungen('P-7', ['F-Ma'])]);

        $this->assertSame(1, $summary['existing']);
        $this->assertSame(1, $summary['ausbildungen_updated']);
        $decoded = json_decode($this->stored_ausbildungen((int)$existing->id), true);
        $this->assertSame(['F-Ma'], array_column($decoded, 'kurzBezeichnung'));
    }

    /**
     * An existing Moodle user found by username (no mapping, other idnumber) gets the
     * trainings too.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_user_found_by_username_gets_ausbildungen_saved(): void {
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();
        $existing = $this->getDataGenerator()->create_user(['username' => 'p-8', 'idnumber' => 'other']);

        $summary = (new users_creator())->create([$this->participant_with_ausbildungen('p-8', ['F-Atr'])]);

        $this->assertSame(1, $summary['existing']);
        $this->assertSame(1, $summary['ausbildungen_updated']);
        $decoded = json_decode($this->stored_ausbildungen((int)$existing->id), true);
        $this->assertSame(['F-Atr'], array_column($decoded, 'kurzBezeichnung'));
    }

    /**
     * A deleted profile field must not break the user import.
     *
     * @covers \local_lehrgaengeapi\local\users\users_creator::create
     */
    public function test_missing_profile_field_does_not_break_user_creation(): void {
        global $DB;
        $this->resetAfterTest(true);
        $DB->delete_records('user_info_field', ['shortname' => ausbildungen_profile_field::SHORTNAME]);

        $this->expectOutputRegex('/profile field "ausbildungen" not found/');
        $summary = (new users_creator())->create([$this->participant_with_ausbildungen('P-9', ['B-II'])]);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['ausbildungen_updated']);
    }
}
