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

defined('MOODLE_INTERNAL') || die();

use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\course\course_creator;
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

        $items = $this->load_json_fixture('200_lehrgaenge.json');
        $this->assertCount(3, $items);

        $endpoint = $this->fake_endpoint($items);
        $repo = new coursemap_repository();
        $creator = new course_creator();

        $service = new lehrgaenge_sync_service($endpoint, $repo, $creator);

        $summary = $service->sync();

        $this->assertSame(3, $summary['total']);
        $this->assertSame(3, $summary['created']);
        $this->assertSame(0, $summary['skipped']);

        foreach ($items as $item) {
            $externalid = (string)$item['id'];

            $course = $DB->get_record('course', ['idnumber' => $externalid], '*', MUST_EXIST);
            $this->assertStringContainsString((string)$course->fullname, $externalid);
            $this->assertSame($externalid, (string)$course->shortname);

            $map = $repo->get_by_externalid($externalid);
            $this->assertNotNull($map);
            $this->assertSame((int)$course->id, (int)$map->courseid);
        }

        $service = new lehrgaenge_sync_service($endpoint, $repo, $creator);

        $summary = $service->sync();

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
     * @param array<int, array<string,mixed>> $items Fixture items.
     * @return lehrgaenge_endpoint_interface
     */
    private function fake_endpoint(array $items): lehrgaenge_endpoint_interface {
        return new class($items) implements lehrgaenge_endpoint_interface {
            /** @var array<int, array<string,mixed>> */
            private array $items;

            /**
             * Constructor.
             *
             * @param array<int, array<string,mixed>> $items List payload.
             */
            public function __construct(array $items) {
                $this->items = $items;
            }

            /**
             * @param array<string,mixed>|string|null $searchcriteria Ignored.
             * @return array<mixed>
             */
            public function list($searchcriteria = null): array {
                return $this->items;
            }

            /** @return array<string,mixed> */
            public function get_by_id(string $id): array {
                return [];
            }

            /** @return array<mixed> */
            public function participants(string $id): array {
                return [];
            }

            /** @return array<string,mixed> */
            public function participant_extern(string $id, string $teilnehmerid): array {
                return [];
            }
        };
    }
}
