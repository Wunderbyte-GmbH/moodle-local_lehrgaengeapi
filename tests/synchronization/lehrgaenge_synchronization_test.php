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

namespace local_lehrgaengeapi\synchronization;

use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\course\course_creator;
use local_lehrgaengeapi\local\services\participant_course_assigner;
use local_lehrgaengeapi\local\services\participants_sync_service;
use local_lehrgaengeapi\local\tenants\tenant_creator;
use local_lehrgaengeapi\local\users\users_creator;
use local_lehrgaengeapi\local\repository\coursemap_repository;
use local_lehrgaengeapi\local\services\lehrgaenge_sync_service;

/**
 * Tests for lehrgaenge_synchronization.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_synchronization_test extends \advanced_testcase {
    /**
     * Sync creates courses + mappings from the dummy-data fixture.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_sync_creates_courses_from_dummydata_fixture(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $items = $this->load_json_fixture('200_lehrgaenge.json');
        $this->assertCount(3, $items);

        $endpoint = $this->fake_endpoint($items);
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

        $this->assertSame(3, $summary['total']);
        $this->assertSame(0, $summary['created']);
        $this->assertSame(3, $summary['skipped']);

        $service = new lehrgaenge_sync_service(
            $endpoint,
            $repo,
            $coursecreator,
            $participantssync,
            $tenantcreator
        );

        $summary = $service->sync($tenant);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(0, $summary['created']);
        $this->assertSame(3, $summary['skipped']);
    }

    /**
     * Load a JSON fixture from tests/dummy_data.
     *
     * (Named differently to avoid clashing with advanced_testcase::load_fixture()).
     *
     * @param string $filename Fixture filename.
     * @return array<int, array<string,mixed>>
     */
    private function load_json_fixture(string $filename): array {
        $path = __DIR__ . '/../dummy_data/' . $filename;
        $this->assertFileExists($path, 'Fixture file not found: ' . $path);

        $json = file_get_contents($path);
        $this->assertNotFalse($json);

        /** @var array<int, array<string,mixed>> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Fake endpoint that returns fixture items for list().
     *
     * @param array $items Fixture items.
     * @return lehrgaenge_endpoint_interface
     */
    private function fake_endpoint(array $items): lehrgaenge_endpoint_interface {
        return new class ($items) implements lehrgaenge_endpoint_interface {
            /** @var array */
            private array $items;

            /**
             * Constructor.
             *
             * @param array $items List payload.
             */
            public function __construct(array $items) {
                $this->items = $items;
            }

            /**
             * Get list of courses.
             * @param array $searchcriteria Ignored.
             * @return array
             */
            public function list($searchcriteria = null): array {
                return $this->items;
            }

            /**
             * Get by id.
             * @param string $id
             * @return array
             */
            public function get_by_id(string $id): array {
                return [];
            }

            /**
             * Get participants for a given id.
             * @param string $id
             * @return array
             */
            public function participants(string $id): array {
                return [];
            }

            /**
             * Get participant_extern for a given id.
             * @param string $id
             * @param string $teilnehmerid
             * @return array
             */
            public function participant_extern(string $id, string $teilnehmerid): array {
                return [];
            }
        };
    }
}
