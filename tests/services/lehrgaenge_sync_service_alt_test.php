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
final class lehrgaenge_sync_service_alt_test extends \advanced_testcase {
    /**
     * Past-year Lehrgaenge should be created as CURRENTYEAR_alt and assign users without groups.
     * Multiple past-year Lehrgaenge with same kurzbezeichnung should share the same _alt course.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_past_year_course_uses_current_year_alt_and_assigns_without_group(): void {
        global $CFG;
        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }
        global $DB;

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $currentyearshort = date('y');
        $yeartwoyearsbefore = (int)date('Y') - 2;
        $yearoneyearbefore = (int)date('Y') - 1;

        $lehrgangid1 = 'LG-ALT-100';
        $lehrgangid2 = 'LG-ALT-200';

        $participants1 = [
            [
                'initialId' => 'p-alt-100',
                'vorname' => 'Anna',
                'nachname' => 'Altfall',
                'status' => 'S018_ANGEMELDET_KREIS',
                'emails' => [
                    'emailBusiness' => 'anna.altfall@example.invalid',
                ],
            ],
        ];

        $participants2 = [
            [
                'initialId' => 'p-alt-200',
                'vorname' => 'Bob',
                'nachname' => 'Altvater',
                'status' => 'S018_ANGEMELDET_KREIS',
                'emails' => [
                    'emailBusiness' => 'bob.altvater@example.invalid',
                ],
            ],
        ];

        $endpoint = $this->fake_endpoint(
            [
                [
                    'id' => $lehrgangid1,
                    'bezeichnung' => 'Altfall AGT 2024',
                    'kurzbezeichnung' => 'AGT',
                    'startTag' => '01.02.' . $yeartwoyearsbefore,
                    'endTag' => $yeartwoyearsbefore . '-12-31',
                ],
                [
                    'id' => $lehrgangid2,
                    'bezeichnung' => 'Altfall AGT 2025',
                    'kurzbezeichnung' => 'AGT',
                    'startTag' => '01.02.' . $yearoneyearbefore,
                    'endTag' => $yearoneyearbefore . '-12-31',
                ],
            ],
            [
                $lehrgangid1 => $participants1,
                $lehrgangid2 => $participants2,
            ]
        );

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

        $tenant = [
            'name' => 'Landkreis Bergstrasse',
            'abbr' => 'FD',
            'apikey' => 'Testing key',
        ];

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Test company category',
            'idnumber' => 'hp-company-category',
        ]);

        // Template for fallback cloning when previous year course does not exist.
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'AGT master',
            'shortname' => 'AGT',
            'summary' => 'Template',
            'format' => 'topics',
            'numsections' => 5,
        ]);

        $company = [
            'name' => 'Landkreis Bergstrasse',
            'shortname' => 'FD',
            'code' => 'FD',
            'city' => 'Fulda',
            'postcode' => 1234,
            'country' => 'DE',
            'category' => $category->id,
        ];
        $DB->insert_record('company', $company);

        // First sync: first past-year Lehrgang creates the CURRENTYEAR_alt course,
        // second past-year Lehrgang with same kurzbezeichnung finds and reuses it.
        $summary = $service->sync($tenant);
        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(2, $summary['total']);

        $altshortname = 'FD-AGT-' . $currentyearshort . '_alt';
        $altcourse = $DB->get_record('course', ['shortname' => $altshortname], '*', MUST_EXIST);

        // Verify both external IDs are mapped to the same course (the _alt one takes precedence).
        $map1 = $repo->get_by_externalid($lehrgangid1);
        $map2 = $repo->get_by_externalid($lehrgangid2);
        $this->assertNotNull($map1);
        $this->assertNotNull($map2);
        $this->assertSame((int)$altcourse->id, (int)$map1->courseid);
        $this->assertSame((int)$altcourse->id, (int)$map2->courseid);

        // Verify both participants are enrolled in the alt course.
        $userid1 = (int)$DB->get_field('user', 'id', ['idnumber' => 'p-alt-100'], MUST_EXIST);
        $userid2 = (int)$DB->get_field('user', 'id', ['idnumber' => 'p-alt-200'], MUST_EXIST);
        $this->assertTrue(is_enrolled(\context_course::instance((int)$altcourse->id), $userid1));
        $this->assertTrue(is_enrolled(\context_course::instance((int)$altcourse->id), $userid2));

        // Verify NO groups were created and no group memberships assigned.
        $this->assertSame(0, $DB->count_records('groups', ['courseid' => (int)$altcourse->id]));
        $this->assertSame(0, $DB->count_records('groups_members', ['userid' => $userid1]));
        $this->assertSame(0, $DB->count_records('groups_members', ['userid' => $userid2]));

        // Second sync: should skip both (already exist).
        $summarysecond = $service->sync($tenant);
        $this->assertSame(0, $summarysecond['created']);
        $this->assertSame(2, $summarysecond['skipped']);
        $this->assertSame(2, $summarysecond['total']);

        // Verify only one _alt course exists.
        $allmatching = $DB->get_records('course', ['shortname' => $altshortname]);
        $this->assertCount(1, $allmatching);
    }

    /**
     * Create a fake endpoint instance that returns fixed list() data.
     *
     * @param array $items List payload.
     * @param array $participantsbyid Participants by ID payload.
     * @return lehrgaenge_endpoint_interface
     */
    private function fake_endpoint(array $items, array $participantsbyid = []): lehrgaenge_endpoint_interface {
        return new class ($items, $participantsbyid) implements lehrgaenge_endpoint_interface {
            /** @var array<mixed> */
            private array $items;

            /** @var array<string, array> */
            private array $participantsbyid;

            /**
             * Constructor.
             *
             * @param array $items Items to return from list().
             * @param array $participantsbyid Participants by ID payload.
             */
            public function __construct(array $items, array $participantsbyid) {
                $this->items = $items;
                $this->participantsbyid = $participantsbyid;
            }

            /**
             * Return fixed data.
             *
             * @param array $searchcriteria Ignored.
             * @return array
             */
            public function list($tenant, $searchcriteria = null): array {
                return $this->items;
            }

            /**
             * Not used by these tests.
             *
             * @param string $id Lehrgang ID.
             * @return array
             */
            public function get_by_id($tenant, string $id): array {
                return [];
            }

            /**
             * Not used by these tests.
             *
             * @param string $id Lehrgang ID.
             * @return array
             */
            public function participants($tenant, string $id): array {
                return $this->participantsbyid[$id] ?? [];
            }

            /**
             * Not used by these tests.
             *
             * @param string $id Lehrgang ID.
             * @param string $teilnehmerid Participant ID.
             * @return array
             */
            public function participant_extern($tenant, string $id, string $teilnehmerid): array {
                return [];
            }
        };
    }
}
