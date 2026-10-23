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
 * Tests for participant_course_assigner.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\services;

use context_course;
use local_lehrgaengeapi\local\repository\usermap_repository;
use local_lehrgaengeapi\local\services\participant_course_assigner;

/**
 * Tests for participant_course_assigner.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_course_assigner_test extends \advanced_testcase {
    /**
     * BESTANDEN state enrolls and marks completion.
     *
     * @covers \local_lehrgaengeapi\local\services\participant_course_assigner::assign
     */
    public function test_assign_bestanden_enrolls_and_completes(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'enablecompletion' => 1,
        ]);
        $user = $this->getDataGenerator()->create_user();

        $repo = new usermap_repository();
        $repo->set_userid('INIT-100', (int)$user->id);

        $assigner = new participant_course_assigner();
        $report = $assigner->assign([
            [
                'id' => 'INIT-100',
                'initialId' => 'INIT-100',
                'status' => 'S050_TEILGENOMMEN_KREIS',
            ],
        ], (int)$course->id, []);

        $this->assertSame(0, $report['skipped']);
        $this->assertSame(1, $report['enrolled']);
        $this->assertSame(1, $report['completed']);

        $context = context_course::instance((int)$course->id);
        $this->assertTrue(is_enrolled($context, (int)$user->id, '', true));

        $completion = $DB->get_record('course_completions', [
            'userid' => (int)$user->id,
            'course' => (int)$course->id,
        ], 'timecompleted', IGNORE_MISSING);

        $this->assertNotEmpty($completion);
        $this->assertNotEmpty($completion->timecompleted);
    }

    /**
     * STORNIERT state unenrolls an already enrolled participant.
     *
     * @covers \local_lehrgaengeapi\local\services\participant_course_assigner::assign
     */
    public function test_assign_storniert_unenrolls_user(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $manualinstance = $DB->get_record('enrol', [
            'courseid' => (int)$course->id,
            'enrol' => 'manual',
        ], '*', MUST_EXIST);

        $plugin = enrol_get_plugin('manual');
        $plugin->enrol_user($manualinstance, (int)$user->id, (int)$manualinstance->roleid);

        $repo = new usermap_repository();
        $repo->set_userid('INIT-200', (int)$user->id);

        $assigner = new participant_course_assigner();
        $report = $assigner->assign([
            [
                'id' => 'INIT-200',
                'initialId' => 'INIT-200',
                'status' => 'S100_STORNIERT_KREIS',
            ],
        ], (int)$course->id, []);

        $this->assertSame(0, $report['skipped']);
        $this->assertSame(1, $report['unenrolled']);

        $context = context_course::instance((int)$course->id);
        $this->assertFalse(is_enrolled($context, (int)$user->id, '', true));
    }

    /**
     * A course listed in detailcourseids gets one detail entry per participant with the
     * outcome of what was done - without any names in it.
     *
     * @covers \local_lehrgaengeapi\local\services\participant_course_assigner::assign
     */
    public function test_assign_reports_details_for_configured_course(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $other = $this->getDataGenerator()->create_course();
        set_config('detailcourseids', ' ' . $other->id . ' , ' . $course->id, 'local_lehrgaengeapi');

        $passed = $this->getDataGenerator()->create_user();
        $noop = $this->getDataGenerator()->create_user();
        $left = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();

        $manualinstance = $DB->get_record('enrol', ['courseid' => (int)$course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->enrol_user($manualinstance, (int)$left->id, (int)$manualinstance->roleid);

        $repo = new usermap_repository();
        $repo->set_userid('D-1', (int)$passed->id);
        $repo->set_userid('D-2', (int)$noop->id);
        $repo->set_userid('D-3', (int)$left->id);
        $repo->set_userid('D-4', (int)$stranger->id);

        $report = (new participant_course_assigner())->assign([
            ['id' => 'D-1', 'status' => 'S050_TEILGENOMMEN_KREIS', 'vorname' => 'Anna'],
            ['id' => 'D-2', 'status' => 'S084_BESTANDEN_LAND'],
            ['id' => 'D-3', 'status' => 'S090_ZURUECKGESTELLT_LAND'],
            ['id' => 'D-4', 'status' => 'S090_ZURUECKGESTELLT_LAND'],
            ['id' => 'D-UNKNOWN', 'status' => 'S050_TEILGENOMMEN_KREIS'],
            ['status' => 'S050_TEILGENOMMEN_KREIS'],
        ], (int)$course->id, []);

        $byparticipant = [];
        foreach ($report['details'] as $detail) {
            $byparticipant[$detail['participantid']] = $detail;
        }

        $this->assertCount(6, $report['details']);
        $this->assertSame('enrolled+completed', $byparticipant['D-1']['outcome']);
        $this->assertSame((int)$passed->id, $byparticipant['D-1']['userid']);
        $this->assertSame('S050_TEILGENOMMEN_KREIS', $byparticipant['D-1']['status']);
        $this->assertSame('noop', $byparticipant['D-2']['outcome']);
        $this->assertSame('unenrolled', $byparticipant['D-3']['outcome']);
        $this->assertSame('alreadyunenrolled', $byparticipant['D-4']['outcome']);
        $this->assertSame('skipped_nouser', $byparticipant['D-UNKNOWN']['outcome']);
        $this->assertSame(0, $byparticipant['D-UNKNOWN']['userid']);
        $this->assertSame('skipped_noid', $byparticipant['']['outcome']);

        // No personal data beyond ids and status ends up in the report.
        $this->assertStringNotContainsString('Anna', json_encode($report['details']));

        // The detail counts agree with the aggregate counters.
        $this->assertSame(1, $report['enrolled']);
        $this->assertSame(1, $report['noop']);
        $this->assertSame(1, $report['unenrolled']);
        $this->assertSame(1, $report['alreadyunenrolled']);
        $this->assertSame(1, $report['completed']);
        $this->assertSame(2, $report['skipped']);
    }

    /**
     * Courses that are not listed in detailcourseids keep the report exactly as before.
     *
     * @covers \local_lehrgaengeapi\local\services\participant_course_assigner::assign
     */
    public function test_assign_reports_no_details_for_other_courses(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        (new usermap_repository())->set_userid('N-1', (int)$user->id);

        $assigner = new participant_course_assigner();
        $payload = [['id' => 'N-1', 'status' => 'S033_EINBERUFEN_KREIS']];

        $report = $assigner->assign($payload, (int)$course->id, []);
        $this->assertArrayNotHasKey('details', $report);

        set_config('detailcourseids', '', 'local_lehrgaengeapi');
        $this->assertArrayNotHasKey('details', $assigner->assign($payload, (int)$course->id, []));

        set_config('detailcourseids', '99999', 'local_lehrgaengeapi');
        $this->assertArrayNotHasKey('details', $assigner->assign($payload, (int)$course->id, []));
    }

    /**
     * Details of one course must not leak into the report of the next course.
     *
     * @covers \local_lehrgaengeapi\local\services\participant_course_assigner::assign
     */
    public function test_assign_details_are_reset_per_course(): void {
        $this->resetAfterTest(true);
        $first = $this->getDataGenerator()->create_course();
        $second = $this->getDataGenerator()->create_course();
        set_config('detailcourseids', $first->id . ',' . $second->id, 'local_lehrgaengeapi');
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $repo = new usermap_repository();
        $repo->set_userid('R-1', (int)$u1->id);
        $repo->set_userid('R-2', (int)$u2->id);

        $assigner = new participant_course_assigner();
        $one = $assigner->assign([['id' => 'R-1', 'status' => 'S033_EINBERUFEN_KREIS']], (int)$first->id, []);
        $two = $assigner->assign([['id' => 'R-2', 'status' => 'S033_EINBERUFEN_KREIS']], (int)$second->id, []);

        $this->assertCount(1, $one['details']);
        $this->assertCount(1, $two['details']);
        $this->assertSame('R-2', $two['details'][0]['participantid']);
    }
}
