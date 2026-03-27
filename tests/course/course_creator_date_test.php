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
 * Tests for course_creator — year conversion and adhoc task queuing.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi;

use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\course\course_creator;
use local_lehrgaengeapi\local\repository\coursemap_repository;
use local_lehrgaengeapi\local\services\lehrgaenge_sync_service;
use local_lehrgaengeapi\local\services\participant_course_assigner;
use local_lehrgaengeapi\local\services\participants_sync_service;
use local_lehrgaengeapi\local\tenants\tenant_creator;
use local_lehrgaengeapi\local\users\users_creator;

/**
 * Tests that course_creator converts 2-digit years and queues the copy adhoc task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_creator_date_test extends \advanced_testcase {
    /**
     * A 2-digit year like "26" is stored as a full 4-digit year (2026) in startdate/enddate.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     */
    public function test_two_digit_year_is_expanded_to_four_digits(): void {
        global $CFG, $DB;

        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $currentyearshort = date('y');
        $currentyearfull  = (int)date('Y');

        $endpoint = $this->fake_endpoint([
            [
                'id'               => 'LG-YEAR-1',
                'bezeichnung'      => 'Jahrestest',
                'kurzbezeichnung'  => 'YEAR',
                'endTag'           => date('Y') . '-12-31',
            ],
        ]);

        $category = $this->getDataGenerator()->create_category([
            'name'     => 'Year test category',
            'idnumber' => 'year-test-category',
        ]);

        // Template course required so course_creator does not bail out.
        $this->getDataGenerator()->create_course([
            'category'  => $category->id,
            'fullname'  => 'YEAR master',
            'shortname' => 'YEAR',
        ]);

        $company = [
            'name'     => 'Year Test Feuerwehr',
            'shortname' => 'YT',
            'code'     => 'YT',
            'city'     => 'Teststadt',
            'postcode' => 99999,
            'country'  => 'DE',
            'category' => $category->id,
        ];
        $DB->insert_record('company', $company);

        $service = $this->build_sync_service($endpoint);
        $service->sync(['name' => 'Year Test Feuerwehr', 'abbr' => 'YT', 'apikey' => 'Testing key']);

        $course = $DB->get_record('course', ['shortname' => 'YT-YEAR-' . $currentyearshort], '*', MUST_EXIST);

        $expectedstart = make_timestamp($currentyearfull, 1, 1, 0, 0, 0);
        $expectedend   = make_timestamp($currentyearfull, 12, 31, 23, 59, 59);

        $this->assertSame($expectedstart, (int)$course->startdate, 'startdate should use 4-digit year');
        $this->assertSame($expectedend, (int)$course->enddate, 'enddate should use 4-digit year');
    }

    /**
     * After course_creator creates a course, a copy_course_content_task adhoc task is queued.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     */
    public function test_adhoc_task_is_queued_after_course_creation(): void {
        global $CFG, $DB;

        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $currentyearshort = date('y');

        $endpoint = $this->fake_endpoint([
            [
                'id'              => 'LG-TASK-1',
                'bezeichnung'     => 'Task Test Lehrgang',
                'kurzbezeichnung' => 'TASK',
                'endTag'          => date('Y') . '-12-31',
            ],
        ]);

        $category = $this->getDataGenerator()->create_category([
            'name'     => 'Task test category',
            'idnumber' => 'task-test-category',
        ]);

        $this->getDataGenerator()->create_course([
            'category'  => $category->id,
            'fullname'  => 'TASK master',
            'shortname' => 'TASK',
        ]);

        $company = [
            'name'     => 'Task Test Feuerwehr',
            'shortname' => 'TT',
            'code'     => 'TT',
            'city'     => 'Teststadt',
            'postcode' => 88888,
            'country'  => 'DE',
            'category' => $category->id,
        ];
        $DB->insert_record('company', $company);

        $tasksbefore = $DB->count_records('task_adhoc', [
            'classname' => '\local_lehrgaengeapi\task\copy_course_content_task',
        ]);

        $service = $this->build_sync_service($endpoint);
        $service->sync(['name' => 'Task Test Feuerwehr', 'abbr' => 'TT', 'apikey' => 'Testing key']);

        $tasksafter = $DB->count_records('task_adhoc', [
            'classname' => '\local_lehrgaengeapi\task\copy_course_content_task',
        ]);

        $this->assertGreaterThan($tasksbefore, $tasksafter, 'An adhoc copy task should have been queued.');

        // Verify the task payload references the correct new course.
        $newcourse = $DB->get_record('course', ['shortname' => 'TT-TASK-' . $currentyearshort], '*', MUST_EXIST);
        $taskrow = $DB->get_record('task_adhoc', [
            'classname' => '\local_lehrgaengeapi\task\copy_course_content_task',
        ]);
        $this->assertNotNull($taskrow);
        $payload = json_decode($taskrow->customdata);
        $this->assertSame((int)$newcourse->id, (int)$payload->newcourseid);
    }

    /**
     * Build a fully wired sync service using a fake endpoint.
     *
     * @param lehrgaenge_endpoint_interface $endpoint
     * @return lehrgaenge_sync_service
     */
    private function build_sync_service(lehrgaenge_endpoint_interface $endpoint): lehrgaenge_sync_service {
        $repo               = new coursemap_repository();
        $coursecreator      = new course_creator();
        $usercreator        = new users_creator();
        $tenantcreator      = new tenant_creator();
        $participantassigner = new participant_course_assigner();

        $participantssync = new participants_sync_service($endpoint, $usercreator, $participantassigner);

        return new lehrgaenge_sync_service(
            $endpoint,
            $repo,
            $coursecreator,
            $participantssync,
            $tenantcreator
        );
    }

    /**
     * Fake endpoint stub that returns preset items and empty participants.
     *
     * @param array $items
     * @return lehrgaenge_endpoint_interface
     */
    private function fake_endpoint(array $items): lehrgaenge_endpoint_interface {
        return new class ($items) implements lehrgaenge_endpoint_interface {
            /** @var array */
            private array $items;

            /**
             * Constructor.
             * @param array $items
             */
            public function __construct(array $items) {
                $this->items = $items;
            }

            /**
             * Return all preset Lehrgaenge.
             *
             * @param mixed $tenant Ignored.
             * @param mixed $searchcriteria Optional filter criteria.
             * @return array
             */
            public function list($tenant, $searchcriteria = null): array {
                return $this->items;
            }

            /**
             * Return one preset Lehrgang by ID.
             *
             * @param mixed $tenant Ignored.
             * @param string $id
             * @return array
             */
            public function get_by_id($tenant, string $id): array {
                foreach ($this->items as $item) {
                    if (($item['id'] ?? '') === $id) {
                        return $item;
                    }
                }
                return [];
            }

            /**
             * Return an empty participant list.
             *
             * @param mixed $tenant Ignored.
             * @param string $lehrgangid
             * @return array
             */
            public function participants($tenant, string $lehrgangid): array {
                return [];
            }

            /**
             * Return empty external participant details.
             *
             * @param mixed $tenant Ignored.
             * @param string $id
             * @param string $teilnehmerid
             * @return array
             */
            public function participant_extern($tenant, string $id, string $teilnehmerid): array {
                return [];
            }
        };
    }
}
