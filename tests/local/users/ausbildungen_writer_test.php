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
 * Tests for ausbildungen_writer.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\users;

/**
 * Tests for ausbildungen_writer.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ausbildungen_writer_test extends \advanced_testcase {
    /**
     * Make sure the profile field exists, independent of the test database snapshot.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();
    }

    /**
     * Read the stored value of the field for a user.
     *
     * @param int $userid
     * @return string|null Null if the user has no data row at all.
     */
    private function stored(int $userid): ?string {
        global $DB;
        $fieldid = ausbildungen_profile_field::get_fieldid();
        $data = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
        return $data === false ? null : (string)$data;
    }

    /**
     * Example participant with two trainings, one of them with extra API keys.
     *
     * @return array
     */
    private function participant(): array {
        return [
            'id' => '1047871',
            'ausbildungen' => [
                [
                    'bezeichnung' => 'Fortbildung „Interviewtraining“',
                    'kurzBezeichnung' => 'F/B-Interview',
                    'von' => '2026-09-16',
                    'bis' => '2026-09-17',
                    'status' => 'TEILGENOMMEN',
                    'unknownkey' => 'must not be stored',
                ],
                [
                    'bezeichnung' => 'Führerschein Flurförderfahrzeuge',
                    'kurzBezeichnung' => 'WB-BF/FF-Ma-Flurförder',
                    'von' => '2021-11-13',
                    'bis' => '2021-11-13',
                    'status' => 'BESTANDEN',
                ],
            ],
        ];
    }

    /**
     * The list is stored as JSON in API order, reduced to the known keys, and umlauts,
     * quotes and slashes stay readable (no ü escapes).
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_writer::write
     */
    public function test_write_stores_normalized_json(): void {
        $user = $this->getDataGenerator()->create_user();

        $changed = (new ausbildungen_writer())->write((int)$user->id, $this->participant());

        $this->assertTrue($changed);
        $stored = $this->stored((int)$user->id);
        $this->assertStringContainsString('Führerschein', $stored);
        $this->assertStringContainsString('F/B-Interview', $stored);
        $decoded = json_decode($stored, true);
        $this->assertCount(2, $decoded);
        $this->assertSame('F/B-Interview', $decoded[0]['kurzBezeichnung']);
        $this->assertSame('BESTANDEN', $decoded[1]['status']);
        $this->assertSame(
            ['bezeichnung', 'kurzBezeichnung', 'von', 'bis', 'status'],
            array_keys($decoded[0])
        );
    }

    /**
     * Writing the same list again is a no-op, so a user appearing in many Lehrgaenge
     * does not cause a database write per Lehrgang.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_writer::write
     */
    public function test_write_unchanged_list_returns_false(): void {
        $user = $this->getDataGenerator()->create_user();
        $writer = new ausbildungen_writer();

        $this->assertTrue($writer->write((int)$user->id, $this->participant()));
        $before = $this->stored((int)$user->id);

        $this->assertFalse($writer->write((int)$user->id, $this->participant()));
        $this->assertFalse((new ausbildungen_writer())->write((int)$user->id, $this->participant()));
        $this->assertSame($before, $this->stored((int)$user->id));
    }

    /**
     * A changed list replaces the stored one (new trainings are picked up on every sync).
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_writer::write
     */
    public function test_write_changed_list_updates_value(): void {
        $user = $this->getDataGenerator()->create_user();
        $writer = new ausbildungen_writer();
        $writer->write((int)$user->id, $this->participant());

        $participant = $this->participant();
        $participant['ausbildungen'][] = [
            'bezeichnung' => 'Begehung Fire Dragon',
            'kurzBezeichnung' => 'BFD',
            'von' => '2024-05-22',
            'bis' => '2024-05-22',
            'status' => 'TEILGENOMMEN',
        ];

        $this->assertTrue($writer->write((int)$user->id, $participant));
        $this->assertCount(3, json_decode($this->stored((int)$user->id), true));
    }

    /**
     * An empty or missing list is still saved: the user gets an (empty) value for the
     * field, and the stale value of an earlier import is cleared.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_writer::write
     */
    public function test_write_empty_or_missing_list_is_saved_as_empty_value(): void {
        $emptylist = $this->getDataGenerator()->create_user();
        $nokey = $this->getDataGenerator()->create_user();
        $cleared = $this->getDataGenerator()->create_user();
        $writer = new ausbildungen_writer();

        $this->assertTrue($writer->write((int)$emptylist->id, ['id' => '1', 'ausbildungen' => []]));
        $this->assertSame('', $this->stored((int)$emptylist->id));
        $this->assertFalse($writer->write((int)$emptylist->id, ['id' => '1', 'ausbildungen' => []]));

        $this->assertTrue($writer->write((int)$nokey->id, ['id' => '2']));
        $this->assertSame('', $this->stored((int)$nokey->id));

        $writer->write((int)$cleared->id, $this->participant());
        $this->assertTrue($writer->write((int)$cleared->id, ['id' => '3', 'ausbildungen' => []]));
        $this->assertSame('', $this->stored((int)$cleared->id));
    }

    /**
     * Malformed entries (not arrays) are skipped, valid ones are kept.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_writer::write
     */
    public function test_write_skips_malformed_entries(): void {
        $user = $this->getDataGenerator()->create_user();

        (new ausbildungen_writer())->write((int)$user->id, [
            'id' => '1',
            'ausbildungen' => ['garbage', null, ['bezeichnung' => 'Ok', 'kurzBezeichnung' => 'OK']],
        ]);

        $decoded = json_decode($this->stored((int)$user->id), true);
        $this->assertCount(1, $decoded);
        $this->assertSame('Ok', $decoded[0]['bezeichnung']);
    }

    /**
     * If the profile field does not exist (e.g. an admin deleted it) nothing is written
     * and - above all - the import does not fail.
     *
     * @covers \local_lehrgaengeapi\local\users\ausbildungen_writer::write
     */
    public function test_write_without_profile_field_does_nothing_and_does_not_throw(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $DB->delete_records('user_info_field', ['shortname' => ausbildungen_profile_field::SHORTNAME]);
        $rowsbefore = $DB->count_records('user_info_data');

        // The writer reports the missing field once via mtrace.
        $this->expectOutputRegex('/profile field "ausbildungen" not found/');
        $this->assertFalse((new ausbildungen_writer())->write((int)$user->id, $this->participant()));
        $this->assertSame($rowsbefore, $DB->count_records('user_info_data'));
    }
}
