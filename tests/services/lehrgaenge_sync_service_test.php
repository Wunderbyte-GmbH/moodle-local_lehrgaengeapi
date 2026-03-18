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
 * Tests for lehrgaenge_sync_service.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi;

use local_lehrgaengeapi\local\course\course_creator;
use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\repository\coursemap_repository;
use local_lehrgaengeapi\local\services\lehrgaenge_sync_service;
use local_lehrgaengeapi\local\services\participant_course_assigner;
use local_lehrgaengeapi\local\services\participants_sync_service;
use local_lehrgaengeapi\local\tenants\tenant_creator;
use local_lehrgaengeapi\local\users\users_creator;

/**
 * Tests for lehrgaenge_sync_service.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_sync_service_test extends \advanced_testcase {
    /**
     * Creates a course when none exists and stores mapping.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_creates_course_and_mapping(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $endpoint = $this->fake_endpoint([
            [
                'id' => 'LG-100',
                'bezeichnung' => 'Atemschutzgeräteträgerlehrgang',
                'kurzbezeichnung' => 'AGT',
                'endTag' => '2026-01-01',
            ],
        ]);

        $repo = new coursemap_repository();
        $coursecreator = new course_creator();
        $usercreator = new users_creator();
        $tenantcreator = new tenant_creator();
        $participantassigner = new participant_course_assigner();

        $participantssync = new participants_sync_service(
            $endpoint,
            $usercreator,
            $participantassigner
        );

        $service = new lehrgaenge_sync_service(
            $endpoint,
            $repo,
            $coursecreator,
            $participantssync,
            $tenantcreator
        );

        $tenant = [
            'name' => "Landkreis Bergstraße",
            'abbr' => 'FD',
            'apikey' => 'Testing key',
        ];

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Test company category',
            'idnumber' => 'hp-company-category',
        ]);

        $previouscourse = $this->getDataGenerator()->create_course([
            'fullname'  => 'FD-AGT-2025',
            'shortname' => 'FD-AGT-2025',
            'idnumber'  => 'template-2024',
            'category'  => $category->id,
            'summary'   => 'Template course for previous year',
            'format'    => 'topics',
            'numsections' => 5,
        ]);

        $company = [
            'name' => "Landkreis Bergstraße",
            'shortname' => 'FD',
            'code' => 'FD',
            'city' => 'Fulda',
            'postcode' => 1234,
            'country' => 'DE',
            'category' => $category->id,
        ];
        $DB->insert_record('company', $company);

        $summary = $service->sync($tenant);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(0, $summary['skipped']);
        $this->assertSame(1, $summary['total']);

        $course = $DB->get_record('course', ['idnumber' => 'LG-100'], '*', MUST_EXIST);
        $this->assertSame('FD-AGT-2026', $course->fullname);
        $this->assertSame('FD-AGT-2026', $course->shortname);

        $map = $repo->get_by_externalid('LG-100');
        $this->assertNotNull($map);
        $this->assertSame((int)$course->id, (int)$map->courseid);
    }

    /**
     * Existing course is not modified (policy: create only).
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_existing_course_is_not_modified(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Keep Me',
            'shortname' => 'KEEP',
            'idnumber' => 'LG-200',
            'endTag' => '2026-01-01',
            'visible' => 1,
        ]);

        $endpoint = $this->fake_endpoint([
            [
                'id' => 'LG-200',
                'bezeichnung' => 'New Name That Should NOT Apply',
                'kurzbezeichnung' => 'NEW',
                'endTag' => '2026-01-01',
            ],
        ]);

        $repo = new coursemap_repository();
        $coursecreator = new course_creator();
        $tenantcreator = new tenant_creator();
        $usercreator = new users_creator();
        $participantassigner = new participant_course_assigner();

        $participantssync = new participants_sync_service(
            $endpoint,
            $usercreator,
            $participantassigner
        );

        $service = new lehrgaenge_sync_service(
            $endpoint,
            $repo,
            $coursecreator,
            $participantssync,
            $tenantcreator
        );

        $before = $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);

        $tenant = [
            'name' => "Landkreis Bergstraße",
            'abbr' => 'FD',
            'apikey' => 'Testing key',
        ];

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Test company category',
            'idnumber' => 'hp-company-category',
        ]);
        $company = [
            'name' => "Landkreis Bergstraße",
            'shortname' => 'FD',
            'code' => 'FD',
            'city' => 'Fulda',
            'postcode' => 1234,
            'country' => 'DE',
            'category' => $category->id,
        ];
        $DB->insert_record('company', $company);

        $summary = $service->sync($tenant);

        $after = $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['total']);

        // Verify NOTHING changed.
        $this->assertSame('Keep Me', $after->fullname);
        $this->assertSame('KEEP', $after->shortname);
        $this->assertSame((int)$before->timemodified, (int)$after->timemodified);
    }

    /**
     * Existing mapping courseid is respected and still no modification happens.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_existing_mapping_is_respected(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Mapped Course',
            'shortname' => 'MAP',
            'idnumber' => 'LG-300',
            'visible' => 1,
        ]);

        $endpoint = $this->fake_endpoint([
            [
                'id' => 'LG-300',
                'bezeichnung' => 'Should Not Apply',
                'kurzbezeichnung' => 'NOPE',
            ],
        ]);

        $repo = new coursemap_repository();
        $repo->set_courseid('LG-300', (int)$course->id);
        $coursecreator = new course_creator();
        $tenantcreator = new tenant_creator();
        $usercreator = new users_creator();
        $participantassigner = new participant_course_assigner();

        $participantssync = new participants_sync_service(
            $endpoint,
            $usercreator,
            $participantassigner
        );

        $service = new lehrgaenge_sync_service(
            $endpoint,
            $repo,
            $coursecreator,
            $participantssync,
            $tenantcreator,
        );

        $before = $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);

        $tenant = [
            'name' => "Landkreis Bergstraße",
            'abbr' => 'HP',
            'apikey' => 'Testing key',
        ];

        $summary = $service->sync($tenant);

        $after = $DB->get_record('course', ['id' => (int)$course->id], '*', MUST_EXIST);

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['total']);

        // Still unchanged.
        $this->assertSame('Mapped Course', $after->fullname);
        $this->assertSame('MAP', $after->shortname);
        $this->assertSame((int)$before->timemodified, (int)$after->timemodified);
    }

    /**
     * Create a fake endpoint instance that returns fixed list() data.
     *
     * @param array $items List payload.
     * @return lehrgaenge_endpoint_interface
     */
    private function fake_endpoint(array $items): lehrgaenge_endpoint_interface {
        return new class ($items) implements lehrgaenge_endpoint_interface {
            /** @var array<mixed> */
            private array $items;

            /**
             * Constructor.
             *
             * @param array $items Items to return from list().
             */
            public function __construct(array $items) {
                $this->items = $items;
            }

            /**
             * Return fixed data.
             *
             * @param array $searchcriteria Ignored.
             * @return array
             */
            public function list($searchcriteria = null): array {
                return $this->items;
            }

            /**
             * Not used by these tests.
             *
             * @param string $id Lehrgang ID.
             * @return array
             */
            public function get_by_id(string $id): array {
                return [];
            }

            /**
             * Not used by these tests.
             *
             * @param string $id Lehrgang ID.
             * @return array
             */
            public function participants(string $id): array {
                return [];
            }

            /**
             * Not used by these tests.
             *
             * @param string $id Lehrgang ID.
             * @param string $teilnehmerid Participant ID.
             * @return array
             */
            public function participant_extern(string $id, string $teilnehmerid): array {
                return [];
            }
        };
    }
}
