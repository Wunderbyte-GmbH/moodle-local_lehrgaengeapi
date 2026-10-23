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
 * Tests for the HLFS master course fallback in lehrgaenge_sync_service.
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
 * Tests for the HLFS master course fallback (setting targetcourseid).
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_sync_service_hlfs_master_test extends \advanced_testcase {
    /** @var array HLFS tenant as passed to sync(). */
    private const HLFS = [
        'name' => 'Hessische Landesfeuerwehrschule',
        'abbr' => 'HLFS',
        'apikey' => 'Testing key',
    ];

    /**
     * Without any other template an HLFS Lehrgang of the current year is built from the master
     * course as a year-less <HLFS>-<NAME>_alt collection course, and further Lehrgaenge of the
     * same type are put into that one course instead of trying to create it again.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_current_year_hlfs_lehrgang_uses_master_course_as_alt_collection_course(): void {
        global $DB;
        $this->prepare_environment();

        $master = $this->create_master_course();
        $this->create_hlfs_company();

        $service = $this->build_service($this->fake_endpoint([
            $this->lehrgang('LG-MC-100', 'MCX', 'Master Lehrgang A'),
            $this->lehrgang('LG-MC-200', 'MCX', 'Master Lehrgang B'),
        ], [
            'LG-MC-100' => [$this->participant('p-mc-100', 'Anna', 'Muster')],
            'LG-MC-200' => [$this->participant('p-mc-200', 'Bob', 'Muster')],
        ]));

        $summary = $service->sync(self::HLFS);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertEmpty($summary['unfoundhlfscourses']);

        $alt = $DB->get_record('course', ['shortname' => 'HLFS-MCX_alt'], '*', MUST_EXIST);
        $this->assertNotSame((int)$master->id, (int)$alt->id);
        $this->assertSame('MCX ' . get_string('completion', 'local_lehrgaengeapi'), $alt->fullname);
        $this->assertSame('Inhalt aus dem Musterkurs', $alt->summary);
        $this->assertSame('', (string)$alt->idnumber, 'A collection course must not claim a single Florix id.');
        $this->assertSame(1, $DB->count_records_select('course', 'shortname LIKE ?', ['HLFS-MCX%']));

        // Both Lehrgaenge map to the one collection course and both participants are in it.
        $repo = new coursemap_repository();
        $this->assertSame((int)$alt->id, (int)$repo->get_by_externalid('LG-MC-100')->courseid);
        $this->assertSame((int)$alt->id, (int)$repo->get_by_externalid('LG-MC-200')->courseid);
        $context = \context_course::instance((int)$alt->id);
        $this->assertTrue(is_enrolled($context, (int)$DB->get_field('user', 'id', ['idnumber' => 'p-mc-100'], MUST_EXIST)));
        $this->assertTrue(is_enrolled($context, (int)$DB->get_field('user', 'id', ['idnumber' => 'p-mc-200'], MUST_EXIST)));

        // A second run changes nothing.
        $second = $service->sync(self::HLFS);
        $this->assertSame(0, $second['created']);
        $this->assertEmpty($second['unfoundhlfscourses']);
        $this->assertSame(1, $DB->count_records_select('course', 'shortname LIKE ?', ['HLFS-MCX%']));
    }

    /**
     * An existing year course of the same Lehrgang type keeps precedence over the collection
     * course, so nothing changes for HLFS Lehrgaenge that are already handled today.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_existing_year_course_takes_precedence_over_alt(): void {
        global $DB;
        $this->prepare_environment();

        $this->create_master_course();
        $this->create_hlfs_company();
        $yearcourse = $this->getDataGenerator()->create_course([
            'fullname' => 'MCY Jahreskurs',
            'shortname' => 'HLFS-MCY-' . date('y'),
        ]);

        $service = $this->build_service($this->fake_endpoint([
            $this->lehrgang('LG-MY-100', 'MCY', 'Jahreskurs Lehrgang'),
        ]));
        $summary = $service->sync(self::HLFS);

        $this->assertSame(0, $summary['created']);
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'HLFS-MCY_alt']));
        $repo = new coursemap_repository();
        $this->assertSame((int)$yearcourse->id, (int)$repo->get_by_externalid('LG-MY-100')->courseid);
    }

    /**
     * Without a configured master course nothing is created and the Lehrgang is reported with
     * the collection course name it would have received.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     */
    public function test_without_master_course_hlfs_lehrgang_is_reported(): void {
        global $DB;
        $this->prepare_environment();

        $this->create_hlfs_company();

        $service = $this->build_service($this->fake_endpoint([
            $this->lehrgang('LG-NM-100', 'NMX', 'Ohne Musterkurs'),
        ]));
        $summary = $service->sync(self::HLFS);

        $this->assertSame(0, $summary['created']);
        $this->assertCount(1, $summary['unfoundhlfscourses']);
        $this->assertSame('HLFS-NMX_alt', $summary['unfoundhlfscourses']['LG-NM-100']['shortname']);
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'HLFS-NMX_alt']));
    }

    /**
     * A configured master course that no longer exists behaves like no master course.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     */
    public function test_deleted_master_course_is_ignored(): void {
        global $DB;
        $this->prepare_environment();

        set_config('targetcourseid', 99999999, 'local_lehrgaengeapi');
        $this->create_hlfs_company();

        $service = $this->build_service($this->fake_endpoint([
            $this->lehrgang('LG-DM-100', 'DMX', 'Gelöschter Musterkurs'),
        ]));
        $summary = $service->sync(self::HLFS);

        $this->assertSame(0, $summary['created']);
        $this->assertCount(1, $summary['unfoundhlfscourses']);
        $this->assertFalse($DB->record_exists('course', ['shortname' => 'HLFS-DMX_alt']));
    }

    /**
     * The master course is a HLFS-only fallback: other tenants never get a course from it.
     *
     * @covers \local_lehrgaengeapi\local\course\course_creator::create
     */
    public function test_master_course_is_not_used_for_other_tenants(): void {
        global $DB;
        $this->prepare_environment();

        $this->create_master_course();
        $category = $this->getDataGenerator()->create_category();
        $DB->insert_record('company', [
            'name' => 'Landkreis Bergstrasse',
            'shortname' => 'FD',
            'code' => 'FD',
            'city' => 'Fulda',
            'postcode' => 1234,
            'country' => 'DE',
            'category' => $category->id,
        ]);

        $service = $this->build_service($this->fake_endpoint([
            $this->lehrgang('LG-FD-100', 'FDX', 'Anderer Mandant', ''),
        ]));
        $summary = $service->sync([
            'name' => 'Landkreis Bergstrasse',
            'abbr' => 'FD',
            'apikey' => 'Testing key',
        ]);

        $this->assertSame(0, $summary['created']);
        $this->assertSame(0, $DB->count_records_select('course', 'shortname LIKE ?', ['FD-FDX%']));
    }

    /**
     * The details of several Lehrgaenge of one collection course are kept apart, while the
     * per-course report stays free of them.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::split_participant_details
     */
    public function test_split_participant_details_keeps_lehrgaenge_of_one_course_apart(): void {
        $service = $this->build_service($this->fake_endpoint([]));
        $method = new \ReflectionMethod($service, 'split_participant_details');
        $method->setAccessible(true);

        $first = ['florixid' => 'A', 'assignments' => ['noop' => 1, 'details' => [['participantid' => 'p1', 'outcome' => 'noop']]]];
        $second = ['florixid' => 'B', 'assignments' => ['noop' => 0, 'details' => [['participantid' => 'p2', 'outcome' => 'enrolled']]]];
        $without = ['florixid' => 'C', 'assignments' => ['noop' => 0]];

        $details = [];
        $cleanedfirst = $method->invokeArgs($service, [$first, 'A', 417, &$details]);
        $method->invokeArgs($service, [$second, 'B', 417, &$details]);
        $cleanedwithout = $method->invokeArgs($service, [$without, 'C', 417, &$details]);

        $this->assertSame('noop', $details[417]['A'][0]['outcome']);
        $this->assertSame('enrolled', $details[417]['B'][0]['outcome']);
        $this->assertArrayNotHasKey('C', $details[417]);
        $this->assertArrayNotHasKey('details', $cleanedfirst['assignments']);
        $this->assertSame(1, $cleanedfirst['assignments']['noop']);
        $this->assertSame($without, $cleanedwithout);
    }

    /**
     * For a collection course in detailcourseids the sync result lists every participant of
     * every Lehrgang with the action taken, and the per-course report no longer carries them.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_sync_reports_participant_details_per_course_and_lehrgang(): void {
        global $DB;
        $this->prepare_environment();

        $this->create_master_course();
        $this->create_hlfs_company();

        $service = $this->build_service($this->fake_endpoint([
            $this->lehrgang('LG-PD-100', 'PDX', 'Details A'),
            $this->lehrgang('LG-PD-200', 'PDX', 'Details B'),
        ], [
            'LG-PD-100' => [$this->participant('p-pd-100', 'Anna', 'Muster')],
            'LG-PD-200' => [$this->participant('p-pd-200', 'Bob', 'Muster')],
        ]));

        // The first run creates the collection course; the second one reports on it.
        $service->sync(self::HLFS);
        $alt = $DB->get_record('course', ['shortname' => 'HLFS-PDX_alt'], '*', MUST_EXIST);
        set_config('detailcourseids', (string)$alt->id, 'local_lehrgaengeapi');

        $summary = $service->sync(self::HLFS);

        $this->assertArrayHasKey((int)$alt->id, $summary['participantdetails']);
        $this->assertSame(['LG-PD-100', 'LG-PD-200'], array_keys($summary['participantdetails'][(int)$alt->id]));
        $this->assertSame('alreadyenrolled', $summary['participantdetails'][(int)$alt->id]['LG-PD-100'][0]['outcome']);
        $this->assertArrayNotHasKey('details', $summary['userreport'][(int)$alt->id]['assignments']);
    }

    /**
     * Common setup for all tests.
     *
     * @return void
     */
    private function prepare_environment(): void {
        global $CFG;
        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }
        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');
    }

    /**
     * Create the master course and configure it as targetcourseid.
     *
     * @return \stdClass
     */
    private function create_master_course(): \stdClass {
        $master = $this->getDataGenerator()->create_course([
            'fullname' => 'HLFS Musterkurs',
            'shortname' => 'HLFS_MUSTERKURS',
            'summary' => 'Inhalt aus dem Musterkurs',
            'format' => 'topics',
        ]);
        set_config('targetcourseid', (int)$master->id, 'local_lehrgaengeapi');
        return $master;
    }

    /**
     * Create the HLFS company with its own category.
     *
     * @return void
     */
    private function create_hlfs_company(): void {
        global $DB;
        $category = $this->getDataGenerator()->create_category(['name' => 'HLFS category']);
        $DB->insert_record('company', [
            'name' => 'Hessische Landesfeuerwehrschule',
            'shortname' => 'HLFS',
            'code' => 'HLFS',
            'city' => 'Kassel',
            'postcode' => 34121,
            'country' => 'DE',
            'category' => $category->id,
        ]);
    }

    /**
     * Build a Lehrgang item ending in the current year.
     *
     * @param string $id Florix id.
     * @param string $kurz Kurzbezeichnung.
     * @param string $bezeichnung Bezeichnung.
     * @param string $ort Ort.
     * @return array
     */
    private function lehrgang(string $id, string $kurz, string $bezeichnung, string $ort = 'HLFS Kassel'): array {
        return [
            'id' => $id,
            'bezeichnung' => $bezeichnung,
            'kurzbezeichnung' => $kurz,
            'ort' => $ort,
            'startTag' => '01.02.' . date('Y'),
            'endTag' => date('Y') . '-12-31',
        ];
    }

    /**
     * Build a participant payload.
     *
     * @param string $initialid Participant id.
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @return array
     */
    private function participant(string $initialid, string $firstname, string $lastname): array {
        return [
            'initialId' => $initialid,
            'vorname' => $firstname,
            'nachname' => $lastname,
            'status' => 'S018_EINBERUFUNG_KREIS',
            'emails' => ['emailBusiness' => $initialid . '@example.invalid'],
        ];
    }

    /**
     * Build a sync service wired to the given endpoint.
     *
     * @param lehrgaenge_endpoint_interface $endpoint Endpoint stub.
     * @return lehrgaenge_sync_service
     */
    private function build_service(lehrgaenge_endpoint_interface $endpoint): lehrgaenge_sync_service {
        return new lehrgaenge_sync_service(
            $endpoint,
            new coursemap_repository(),
            new course_creator(),
            new participants_sync_service($endpoint, new users_creator(), new participant_course_assigner()),
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
             * Participants of a Lehrgang.
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
