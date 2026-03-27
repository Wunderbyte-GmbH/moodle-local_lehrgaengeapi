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
final class lehrgaenge_sync_service_f_iii_test extends \advanced_testcase {
    /**
     * F-III courses are split by long name into F-III and F-IIIe.
     *
     * @covers \local_lehrgaengeapi\local\services\lehrgaenge_sync_service::sync
     */
    public function test_f_iii_courses_are_split_by_bezeichnung_and_participants_go_to_correct_course(): void {
        global $CFG;
        if (!file_exists($CFG->dirroot . '/local/iomad/lib/company.php')) {
            $this->markTestSkipped('IOMAD not available');
        }
        global $DB;

        $this->resetAfterTest(true);
        set_config('apikey_hp', 'testing_the_api_key_function', 'local_lehrgaengeapi');

        $currentyearfull = (int)date('Y');
        $currentyearshort = date('y');
        $items = [
            [
                'id' => 'LG-FIII-100',
                'bezeichnung' => 'Gruppenführer',
                'kurzbezeichnung' => 'F-III',
                'startTag' => '01.02.' . $currentyearfull,
                'endTag' => $currentyearfull . '-03-31',
            ],
            [
                'id' => 'LG-FIII-200',
                'bezeichnung' => 'Gruppenführer (e-learning)',
                'kurzbezeichnung' => 'F-III',
                'startTag' => '01.04.' . $currentyearfull,
                'endTag' => $currentyearfull . '-05-31',
            ],
            [
                'id' => 'LG-AGT-300',
                'bezeichnung' => 'Atemschutzgeräteträgerlehrgang',
                'kurzbezeichnung' => 'AGT',
                'startTag' => '01.06.' . $currentyearfull,
                'endTag' => $currentyearfull . '-07-31',
            ],
        ];

        $endpoint = $this->fake_endpoint(
            $items,
            [
                'LG-FIII-100' => [[
                    'initialId' => 'p-fiii-100',
                    'vorname' => 'Fritz',
                    'nachname' => 'Praesenz',
                    'status' => 'S018_ANGEMELDET_KREIS',
                    'emails' => ['emailBusiness' => 'fritz.praesenz@example.invalid'],
                ]],
                'LG-FIII-200' => [[
                    'initialId' => 'p-fiiie-200',
                    'vorname' => 'Erika',
                    'nachname' => 'Elearning',
                    'status' => 'S018_ANGEMELDET_KREIS',
                    'emails' => ['emailBusiness' => 'erika.elearning@example.invalid'],
                ]],
                'LG-AGT-300' => [[
                    'initialId' => 'p-agt-300',
                    'vorname' => 'Anton',
                    'nachname' => 'Agt',
                    'status' => 'S018_ANGEMELDET_KREIS',
                    'emails' => ['emailBusiness' => 'anton.agt@example.invalid'],
                ]],
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

        // Local templates for all expected target course keys.
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'F-III master',
            'shortname' => 'F-III',
            'summary' => 'Template',
            'format' => 'topics',
            'numsections' => 5,
        ]);
        $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname' => 'F-IIIe master',
            'shortname' => 'F-IIIe',
            'summary' => 'Template',
            'format' => 'topics',
            'numsections' => 5,
        ]);
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

        $summary = $service->sync($tenant);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(2, $summary['skipped']);
        $this->assertSame(3, $summary['total']);

        $agtcourse = $DB->get_record('course', ['shortname' => 'FD-AGT-' . $currentyearshort], '*', MUST_EXIST);
        $useragt = (int)$DB->get_field('user', 'id', ['idnumber' => 'p-agt-300'], MUST_EXIST);

        $this->assertTrue(is_enrolled(\context_course::instance((int)$agtcourse->id), $useragt));
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
