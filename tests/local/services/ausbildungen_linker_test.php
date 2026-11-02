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
 * Tests for ausbildungen_linker.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

use context_course;
use local_lehrgaengeapi\local\repository\usermap_repository;
use local_lehrgaengeapi\local\repository\usertenant_repository;
use local_lehrgaengeapi\local\users\ausbildungen_profile_field;
use local_lehrgaengeapi\local\users\ausbildungen_writer;

/**
 * Tests for ausbildungen_linker.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ausbildungen_linker_test extends \advanced_testcase {
    /** @var int Running number for external participant ids. */
    private int $nextid = 1;

    /**
     * Profile field for every test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        ausbildungen_profile_field::ensure_exists();
    }

    /**
     * Create an imported participant: Moodle user, mapping row, remembered tenants, Ausbildungen.
     *
     * @param string[] $tenants Remembered tenants in order.
     * @param array $ausbildungen List of [kurzBezeichnung, status] pairs.
     * @return int User id.
     */
    private function participant(array $tenants, array $ausbildungen): int {
        $user = $this->getDataGenerator()->create_user();
        $externalid = 'EXT-' . $this->nextid++;
        (new usermap_repository())->set_userid($externalid, (int)$user->id);
        foreach ($tenants as $tenant) {
            (new usertenant_repository())->remember((int)$user->id, $tenant);
        }
        (new ausbildungen_writer())->write((int)$user->id, [
            'id' => $externalid,
            'ausbildungen' => array_map(static fn($a) => [
                'bezeichnung' => 'Lehrgang ' . $a[0],
                'kurzBezeichnung' => $a[0],
                'von' => '2019-04-29',
                'bis' => '2019-05-13',
                'status' => $a[1],
            ], $ausbildungen),
        ]);
        return (int)$user->id;
    }

    /**
     * Create an _alt course with completion enabled.
     *
     * @param string $shortname
     * @return \stdClass
     */
    private function course(string $shortname): \stdClass {
        return $this->getDataGenerator()->create_course(['shortname' => $shortname, 'enablecompletion' => 1]);
    }

    /**
     * Is the user enrolled and has the course completion?
     *
     * @param int $userid
     * @param \stdClass $course
     * @return array{0:bool,1:bool}
     */
    private function state(int $userid, \stdClass $course): array {
        global $DB;
        $enrolled = is_enrolled(context_course::instance((int)$course->id), $userid, '', true);
        $completed = (bool)$DB->get_field('course_completions', 'timecompleted', [
            'userid' => $userid,
            'course' => $course->id,
        ]);
        return [$enrolled, $completed];
    }

    /**
     * A completed Ausbildung is linked to the _alt course of the user's own tenant:
     * enrolled, completed, reported with tenant and course.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_completed_ausbildung_is_linked_in_own_tenant(): void {
        $course = $this->course('FD-F-ATR_alt');
        $userid = $this->participant(['FD'], [['F-Atr', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertCount(1, $rows);
        $this->assertSame(ausbildungen_linker::OUTCOME_LINKED_OWN, $rows[0]['outcome']);
        $this->assertSame('FD', $rows[0]['tenantabbr']);
        $this->assertSame((int)$course->id, $rows[0]['courseid']);
        $this->assertSame('F-Atr', $rows[0]['kurzbezeichnung']);
        $this->assertSame('Lehrgang F-Atr', $rows[0]['bezeichnung']);
        $this->assertSame('2019-04-29', $rows[0]['von']);
        $this->assertSame('2019-05-13', $rows[0]['bis']);
        $this->assertSame('BESTANDEN', $rows[0]['apistatus']);
        $this->assertSame([true, true], $this->state($userid, $course));
    }

    /**
     * TEILGENOMMEN counts as completed as well.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_teilgenommen_counts_as_completed(): void {
        $course = $this->course('FD-F/K-SPF_alt');
        $userid = $this->participant(['FD'], [['F/K-Sprechfunk', 'TEILGENOMMEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_LINKED_OWN, $rows[0]['outcome']);
        $this->assertSame([true, true], $this->state($userid, $course));
    }

    /**
     * Without a course in the own tenant the HLFS _alt course is used.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_falls_back_to_hlfs(): void {
        $this->course('MKK-F-ATR_alt'); // Other tenant, must not be used.
        $hlfs = $this->course('HLFS-F-ATR_alt');
        $userid = $this->participant(['FD'], [['F-Atr', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_LINKED_HLFS, $rows[0]['outcome']);
        $this->assertSame('HLFS', $rows[0]['tenantabbr']);
        $this->assertSame((int)$hlfs->id, $rows[0]['courseid']);
        $this->assertSame([true, true], $this->state($userid, $hlfs));
    }

    /**
     * A user delivered by several tenants is checked tenant by tenant before HLFS.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_second_own_tenant_before_hlfs(): void {
        $this->course('HLFS-F-ATR_alt');
        $mkk = $this->course('MKK-F-ATR_alt');
        $userid = $this->participant(['FD', 'MKK'], [['F-Atr', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_LINKED_OWN, $rows[0]['outcome']);
        $this->assertSame('MKK', $rows[0]['tenantabbr']);
        $this->assertSame((int)$mkk->id, $rows[0]['courseid']);
    }

    /**
     * A user without remembered tenant is only checked against HLFS.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_user_without_tenant_is_only_checked_against_hlfs(): void {
        $fd = $this->course('FD-F-ATR_alt');
        $hlfs = $this->course('HLFS-F-MA_alt');
        $userid = $this->participant([], [['F-Atr', 'BESTANDEN'], ['F-Ma', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_NOTFOUND, $rows[0]['outcome']);
        $this->assertNull($rows[0]['tenantabbr']);
        $this->assertNull($rows[0]['courseid']);
        $this->assertSame([false, false], $this->state($userid, $fd));

        $this->assertSame(ausbildungen_linker::OUTCOME_LINKED_HLFS, $rows[1]['outcome']);
        $this->assertSame((int)$hlfs->id, $rows[1]['courseid']);
    }

    /**
     * No course anywhere: the Ausbildung is ignored, but reported.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_not_found_is_reported_with_first_tenant(): void {
        $userid = $this->participant(['FD', 'MKK'], [['F-Atr', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_NOTFOUND, $rows[0]['outcome']);
        $this->assertSame('FD', $rows[0]['tenantabbr']);
        $this->assertNull($rows[0]['courseid']);
    }

    /**
     * Legacy _alt courses with year are found too.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_legacy_alt_course_is_used(): void {
        $legacy = $this->course('FD-F-ATR-26_alt');
        $userid = $this->participant(['FD'], [['F-Atr', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame((int)$legacy->id, $rows[0]['courseid']);
        $this->assertSame([true, true], $this->state($userid, $legacy));
    }

    /**
     * Open and not completed statuses never enrol, even if the course exists. Above all,
     * a missed attempt must not unenrol a user who completed the course another time.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_open_and_not_completed_statuses_do_not_touch_enrolment(): void {
        global $DB;
        $course = $this->course('FD-F-ATR_alt');
        $userid = $this->participant(['FD'], [
            ['F-Atr', 'TEILNAHMEWUNSCH'],
            ['F-Atr', 'ANGEMELDET'],
            ['F-Atr', 'FEHLT_ENTSCHULDIGT'],
            ['F-Atr', 'NICHT_BESTANDEN'],
            ['F-Atr', 'ABGEBROCHEN'],
        ]);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->enrol_user($instance, $userid, (int)$instance->roleid);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(
            [
                ausbildungen_linker::OUTCOME_STATUSOPEN,
                ausbildungen_linker::OUTCOME_STATUSOPEN,
                ausbildungen_linker::OUTCOME_NOTCOMPLETED,
                ausbildungen_linker::OUTCOME_NOTCOMPLETED,
                ausbildungen_linker::OUTCOME_NOTCOMPLETED,
            ],
            array_column($rows, 'outcome')
        );
        $this->assertSame([null, null, null, null, null], array_column($rows, 'courseid'));
        $this->assertSame([true, false], $this->state($userid, $course), 'Enrolment must stay untouched.');
    }

    /**
     * An Ausbildung without kurzBezeichnung cannot be mapped to a course.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_empty_kurzbezeichnung_is_reported_as_notype(): void {
        $userid = $this->participant(['FD'], [['', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_NOTYPE, $rows[0]['outcome']);
    }

    /**
     * Running again (or two Ausbildungen pointing to the same course) reports "already linked".
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_second_run_and_duplicate_report_already_linked(): void {
        $course = $this->course('FD-F-ATR_alt');
        $userid = $this->participant(['FD'], [['F-Atr', 'BESTANDEN'], ['F-Atr', 'TEILGENOMMEN']]);
        $linker = new ausbildungen_linker();

        $first = $linker->link_user($userid);
        $this->assertSame(
            [ausbildungen_linker::OUTCOME_LINKED_OWN, ausbildungen_linker::OUTCOME_ALREADY],
            array_column($first, 'outcome')
        );

        $second = $linker->link_user($userid);
        $this->assertSame(
            [ausbildungen_linker::OUTCOME_ALREADY, ausbildungen_linker::OUTCOME_ALREADY],
            array_column($second, 'outcome')
        );
        $this->assertSame([(int)$course->id, (int)$course->id], array_column($second, 'courseid'));
    }

    /**
     * A course without manual enrolment cannot be used - reported as error with a message,
     * the remaining Ausbildungen are still processed.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_course_without_manual_enrolment_is_reported_as_error(): void {
        global $DB;
        $broken = $this->course('FD-F-ATR_alt');
        $DB->delete_records('enrol', ['courseid' => $broken->id, 'enrol' => 'manual']);
        $ok = $this->course('FD-F-MA_alt');
        $userid = $this->participant(['FD'], [['F-Atr', 'BESTANDEN'], ['F-Ma', 'BESTANDEN']]);

        $rows = (new ausbildungen_linker())->link_user($userid);

        $this->assertSame(ausbildungen_linker::OUTCOME_ERROR, $rows[0]['outcome']);
        $this->assertSame((int)$broken->id, $rows[0]['courseid']);
        $this->assertNotEmpty($rows[0]['message']);
        $this->assertSame(ausbildungen_linker::OUTCOME_LINKED_OWN, $rows[1]['outcome']);
        $this->assertSame([true, true], $this->state($userid, $ok));
    }

    /**
     * A user without Ausbildungen (empty field or no row) produces no report rows.
     *
     * @covers \local_lehrgaengeapi\local\services\ausbildungen_linker::link_user
     */
    public function test_user_without_ausbildungen_returns_no_rows(): void {
        $empty = $this->participant(['FD'], []);
        $norow = (int)$this->getDataGenerator()->create_user()->id;

        $linker = new ausbildungen_linker();
        $this->assertSame([], $linker->link_user($empty));
        $this->assertSame([], $linker->link_user($norow));
    }
}
