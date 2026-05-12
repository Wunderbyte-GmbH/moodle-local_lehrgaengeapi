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
                'initialId' => 'INIT-200',
                'status' => 'S100_STORNIERT_KREIS',
            ],
        ], (int)$course->id, []);

        $this->assertSame(0, $report['skipped']);
        $this->assertSame(1, $report['unenrolled']);

        $context = context_course::instance((int)$course->id);
        $this->assertFalse(is_enrolled($context, (int)$user->id, '', true));
    }
}
