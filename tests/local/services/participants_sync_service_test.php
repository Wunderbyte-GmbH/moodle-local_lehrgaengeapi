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
 * Tests for participants_sync_service.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\repository\usertenant_repository;
use local_lehrgaengeapi\local\users\users_creator;

/**
 * Tests for participants_sync_service.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participants_sync_service_test extends \advanced_testcase {
    /**
     * Endpoint double that returns a fixed participant list.
     *
     * @param array $participants
     * @return lehrgaenge_endpoint_interface
     */
    private function endpoint(array $participants): lehrgaenge_endpoint_interface {
        return new class ($participants) implements lehrgaenge_endpoint_interface {
            /** @var array */
            private array $participants;

            /**
             * Constructor.
             * @param array $participants
             */
            public function __construct(array $participants) {
                $this->participants = $participants;
            }

            /**
             * Not used.
             * @param mixed $tenant
             * @param mixed $searchcriteria
             * @return array
             */
            public function list($tenant, $searchcriteria = null): array {
                return [];
            }

            /**
             * Not used.
             * @param mixed $tenant
             * @param string $id
             * @return array
             */
            public function get_by_id($tenant, string $id): array {
                return [];
            }

            /**
             * The fixed participant list.
             * @param mixed $tenant
             * @param string $id
             * @return array
             */
            public function participants($tenant, string $id): array {
                return $this->participants;
            }

            /**
             * Not used.
             * @param mixed $tenant
             * @param string $id
             * @param string $teilnehmerid
             * @return array
             */
            public function participant_extern($tenant, string $id, string $teilnehmerid): array {
                return [];
            }
        };
    }

    /**
     * The tenant whose API delivered the participants is passed on and remembered per user.
     *
     * @covers \local_lehrgaengeapi\local\services\participants_sync_service::sync_for_course
     */
    public function test_sync_for_course_remembers_the_delivering_tenant(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('requestdelayms', 0, 'local_lehrgaengeapi');
        $course = $this->getDataGenerator()->create_course();

        $service = new participants_sync_service(
            $this->endpoint([[
                'id' => 'PS-1',
                'initialId' => 'ps-1',
                'vorname' => 'Test',
                'nachname' => 'Person',
                'status' => 'S010_ANGEMELDET_GEMEINDE',
                'ausbildungen' => [],
            ]]),
            new users_creator(),
            new participant_course_assigner()
        );

        $service->sync_for_course('L-1', (int)$course->id, [], ['name' => 'Landkreis Fulda', 'abbr' => 'FD', 'apikey' => 'x']);

        $userid = (int)$DB->get_field('user', 'id', ['idnumber' => 'PS-1'], MUST_EXIST);
        $this->assertSame(['FD'], (new usertenant_repository())->get_tenants($userid));
    }

    /**
     * For the Florix collection Lehrgang only users, their Ausbildungen and the tenant are
     * stored - nobody is enrolled anywhere, whatever the participant status says.
     *
     * @covers \local_lehrgaengeapi\local\services\participants_sync_service::sync_users_only
     */
    public function test_sync_users_only_creates_users_without_enrolment(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('requestdelayms', 0, 'local_lehrgaengeapi');

        $service = new participants_sync_service(
            $this->endpoint([[
                'id' => 'AB-1',
                'initialId' => 'ab-1',
                'vorname' => 'Alt',
                'nachname' => 'Bestand',
                'status' => 'S050_TEILGENOMMEN_KREIS',
                'ausbildungen' => [[
                    'bezeichnung' => 'Truppmann',
                    'kurzbezeichnung' => 'F-I',
                    'von' => '1999-03-01',
                    'bis' => '1999-03-10',
                    'status' => 'BESTANDEN',
                ]],
            ]]),
            new users_creator(),
            new participant_course_assigner()
        );

        $report = $service->sync_users_only('L-AB', ['name' => 'Landkreis Fulda', 'abbr' => 'FD', 'apikey' => 'x']);

        $userid = (int)$DB->get_field('user', 'id', ['idnumber' => 'AB-1'], MUST_EXIST);
        $this->assertSame(['FD'], (new usertenant_repository())->get_tenants($userid));
        $this->assertSame(0, $DB->count_records('user_enrolments', ['userid' => $userid]));
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $userid]));

        $this->assertSame('L-AB', $report['florixid']);
        $this->assertSame(1, $report['users']['created']);
        $this->assertSame(1, $report['users']['total']);
        $this->assertSame([], $report['assignments']);
    }
}
