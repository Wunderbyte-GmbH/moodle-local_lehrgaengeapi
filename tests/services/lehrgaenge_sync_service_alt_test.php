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
     * Past-year Lehrgaenge should be created as a year-less <TENANT>-<NAME>_alt course and
     * assign users without groups. Multiple past-year Lehrgaenge with the same
     * kurzbezeichnung should share that one _alt course.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_past_year_course_uses_yearless_alt_and_assigns_without_group(): void {
        global $CFG;
        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }
        global $DB;

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $yeartwoyearsbefore = (int)date('Y') - 2;
        $yearoneyearbefore = (int)date('Y') - 1;

        $lehrgangid1 = 'LG-ALT-100';
        $lehrgangid2 = 'LG-ALT-200';

        $participants1 = [
            [
                'initialId' => 'p-alt-100',
                'vorname' => 'Anna',
                'nachname' => 'Altfall',
                'status' => 'S018_EINBERUFUNG_KREIS',
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
                'status' => 'S018_EINBERUFUNG_KREIS',
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

        // First sync: first past-year Lehrgang creates the year-less _alt course,
        // second past-year Lehrgang with same kurzbezeichnung finds and reuses it.
        $summary = $service->sync($tenant);
        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(2, $summary['total']);

        $altshortname = 'FD-AGT_alt';
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
     * When a past-year Lehrgang has neither a previous-year course nor a master template,
     * this year's course for the same Lehrgang type is used as the template for the _alt
     * completion course.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     */
    public function test_past_year_course_falls_back_to_current_year_course_as_template(): void {
        global $CFG, $DB;

        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $yeartwoyearsbefore = (int)date('Y') - 2;

        $endpoint = $this->fake_endpoint([
            [
                'id' => 'LG-CY-100',
                'bezeichnung' => 'Altfall ohne Vorlage',
                'kurzbezeichnung' => 'CYF',
                'startTag' => '01.02.' . $yeartwoyearsbefore,
                'endTag' => $yeartwoyearsbefore . '-12-31',
            ],
        ]);

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Current year fallback category',
            'idnumber' => 'cy-fallback-category',
        ]);

        // Deliberately NO master course 'CYF' and NO previous-year course: the current
        // year's course is the only template candidate left.
        $currentyearcourse = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'CYF aktueller Jahrgang',
            'shortname' => 'FD-CYF-' . date('y'),
            'summary' => 'Inhalt aus dem aktuellen Jahreskurs',
            'format' => 'topics',
        ]);

        $DB->insert_record('company', [
            'name' => 'Landkreis Bergstrasse',
            'shortname' => 'FD',
            'code' => 'FD',
            'city' => 'Fulda',
            'postcode' => 1234,
            'country' => 'DE',
            'category' => $category->id,
        ]);

        $service = $this->build_service($endpoint);
        $summary = $service->sync([
            'name' => 'Landkreis Bergstrasse',
            'abbr' => 'FD',
            'apikey' => 'Testing key',
        ]);

        $this->assertSame(1, $summary['created']);
        $this->assertEmpty($summary['unfoundhlfscourses']);

        $altcourse = $DB->get_record('course', ['shortname' => 'FD-CYF_alt'], '*', MUST_EXIST);
        $this->assertSame(
            'Inhalt aus dem aktuellen Jahreskurs',
            $altcourse->summary,
            'The _alt course should inherit its settings from the current year course.'
        );
        $this->assertStringEndsWith(
            get_string('completion', 'local_lehrgaengeapi'),
            $altcourse->fullname
        );

        // The current year's course must be left untouched - it is only a template.
        $this->assertNotSame((int)$currentyearcourse->id, (int)$altcourse->id);
        $this->assertTrue($DB->record_exists('course', ['shortname' => 'FD-CYF-' . date('y')]));
    }

    /**
     * An HLFS Lehrgang that cannot be created because no template of any kind exists is
     * reported in unfoundhlfscourses, carrying both the source and the intended shortname.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_hlfs_lehrgang_without_any_template_is_reported(): void {
        global $CFG, $DB;

        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $yeartwoyearsbefore = (int)date('Y') - 2;
        $pastyearshort = substr((string)$yeartwoyearsbefore, 2, 2);

        $endpoint = $this->fake_endpoint([
            [
                'id' => 'LG-UNF-100',
                'bezeichnung' => 'Lehrgang ohne jede Vorlage',
                'kurzbezeichnung' => 'UNF',
                'ort' => 'HLFS Kassel',
                'startTag' => '01.02.' . $yeartwoyearsbefore,
                'endTag' => $yeartwoyearsbefore . '-12-31',
            ],
        ]);

        $category = $this->getDataGenerator()->create_category([
            'name' => 'Unfound category',
            'idnumber' => 'unfound-category',
        ]);

        // The HLFS company must exist, otherwise get_main_company() returns null and the
        // Lehrgang would never be classified as an HLFS one in the first place.
        $DB->insert_record('company', [
            'name' => 'Hessische Landesfeuerwehrschule',
            'shortname' => 'HLFS',
            'code' => 'HLFS',
            'city' => 'Kassel',
            'postcode' => 34121,
            'country' => 'DE',
            'category' => $category->id,
        ]);

        $service = $this->build_service($endpoint);
        $summary = $service->sync([
            'name' => 'Hessische Landesfeuerwehrschule',
            'abbr' => 'HLFS',
            'apikey' => 'Testing key',
        ]);

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertCount(1, $summary['unfoundhlfscourses']);

        $entry = $summary['unfoundhlfscourses']['LG-UNF-100'];
        $this->assertSame('LG-UNF-100', $entry['externalid']);
        $this->assertSame('HLFS-UNF-' . $pastyearshort, $entry['sourceshortname']);
        $this->assertSame('HLFS-UNF_alt', $entry['shortname']);
        $this->assertSame('Lehrgang ohne jede Vorlage', $entry['bezeichnung']);
        $this->assertSame('UNF', $entry['kurzbezeichnung']);
        $this->assertSame('HLFS Kassel', $entry['ort']);

        $this->assertFalse($DB->record_exists('course', ['shortname' => 'HLFS-UNF_alt']));
    }

    /**
     * Completion courses created under the legacy <TENANT>-<NAME>-<YY>_alt convention are
     * found regardless of the year they carry, and the lookup does not leak across
     * Lehrgang types - including names that contain an SQL LIKE wildcard themselves.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::find_legacy_alt_course
     */
    public function test_legacy_alt_course_is_found_regardless_of_year_suffix(): void {
        $this->resetAfterTest(true);

        $this->getDataGenerator()->create_course([
            'shortname' => 'HLFS-F/B-VB f. Fü-24_alt',
            'fullname' => 'Legacy Abschlusskurs',
        ]);
        // A different Lehrgang type must not be matched...
        $this->getDataGenerator()->create_course([
            'shortname' => 'HLFS-F-IV-25_alt',
            'fullname' => 'Anderer Abschlusskurs',
        ]);
        // ...and neither must a name that only matches if '_' is treated as a wildcard.
        $this->getDataGenerator()->create_course([
            'shortname' => 'HLFS-AXB-24_alt',
            'fullname' => 'Wildcard-Falle',
        ]);

        $service = $this->build_service($this->fake_endpoint([]));
        $method = new \ReflectionMethod($service, 'find_legacy_alt_course');
        $method->setAccessible(true);

        $found = $method->invoke($service, [
            'tenant' => 'HLFS',
            'coursename' => 'F/B-VB f. Fü',
            'year' => '18',
        ]);
        $this->assertNotNull($found, 'The legacy _alt course should be reused, not duplicated.');
        $this->assertSame('HLFS-F/B-VB f. Fü-24_alt', $found->shortname);

        $this->assertNull(
            $method->invoke($service, ['tenant' => 'HLFS', 'coursename' => 'F-Gibtsnicht', 'year' => '18']),
            'A Lehrgang type without any legacy course must not match anything.'
        );

        $this->assertNull(
            $method->invoke($service, ['tenant' => 'HLFS', 'coursename' => 'A_B', 'year' => '18']),
            'An underscore in the course name must be escaped, not treated as a LIKE wildcard.'
        );
    }

    /**
     * Build a sync service wired to the given endpoint.
     *
     * @param lehrgaenge_endpoint_interface $endpoint Endpoint stub.
     * @return lehrgaenge_sync_service
     */
    private function build_service(lehrgaenge_endpoint_interface $endpoint): lehrgaenge_sync_service {
        $usercreator = new users_creator();
        $participantassigner = new participant_course_assigner();

        return new lehrgaenge_sync_service(
            $endpoint,
            new coursemap_repository(),
            new course_creator(),
            new participants_sync_service($endpoint, $usercreator, $participantassigner),
            new tenant_creator()
        );
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
             * @param mixed $tenant Ignored.
             * @param mixed $searchcriteria Ignored.
             * @return array
             */
            public function list($tenant, $searchcriteria = null): array {
                return $this->items;
            }

            /**
             * Not used by these tests.
             *
             * @param mixed $tenant Ignored.
             * @param string $id Lehrgang ID.
             * @return array
             */
            public function get_by_id($tenant, string $id): array {
                return [];
            }

            /**
             * Not used by these tests.
             *
             * @param mixed $tenant Ignored.
             * @param string $id Lehrgang ID.
             * @return array
             */
            public function participants($tenant, string $id): array {
                return $this->participantsbyid[$id] ?? [];
            }

            /**
             * Not used by these tests.
             *
             * @param mixed $tenant Ignored.
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
