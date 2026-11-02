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
 * Tests for import_result_recorder.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

/**
 * Tests for import_result_recorder.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_result_recorder_test extends \advanced_testcase {
    /**
     * The per-Lehrgang rows of a tenant summary go into the course result table; the summary
     * that is kept in the run log keeps only the totals.
     *
     * @covers \local_lehrgaengeapi\local\services\import_result_recorder::record
     */
    public function test_record_saves_rows_and_strips_them_from_the_summary(): void {
        global $DB;
        $this->resetAfterTest(true);
        $row = course_result_row::build(
            'FD',
            ['id' => 'LG-1', 'kurzbezeichnung' => 'F-Atr'],
            (object)['id' => 9, 'shortname' => 'FD-F-ATR_alt'],
            ['users' => ['total' => 2], 'assignments' => ['enrolled' => 2, 'total' => 2]]
        );
        $summary = [
            'created' => 0,
            'skipped' => 1,
            'total' => 1,
            'failed' => [],
            'courseresults' => [$row],
            'unfoundhlfscourses' => [],
        ];

        $kept = (new import_result_recorder())->record(12, $summary);

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_course_result', ['runid' => 12, 'courseid' => 9]));
        $this->assertArrayNotHasKey('courseresults', $kept);
        $this->assertSame(1, $kept['skipped']);
        $this->assertSame(1, $kept['total']);
        $this->assertSame([], $kept['unfoundhlfscourses']);
    }

    /**
     * A tenant summary without rows (no API key, tenant failed) is passed through unchanged.
     *
     * @covers \local_lehrgaengeapi\local\services\import_result_recorder::record
     */
    public function test_record_without_rows_keeps_summary(): void {
        global $DB;
        $this->resetAfterTest(true);
        $summary = ['created' => 0, 'skipped' => 0, 'total' => 0, 'failed' => [], 'error' => 'HTTP 400'];

        $this->assertSame($summary, (new import_result_recorder())->record(12, $summary));
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_course_result'));
    }

    /**
     * Recording the same tenant summary twice (progress update during the run, then the
     * final summary) stores the rows only once.
     *
     * @covers \local_lehrgaengeapi\local\services\import_result_recorder::record
     */
    public function test_record_returns_summary_that_can_be_recorded_again(): void {
        global $DB;
        $this->resetAfterTest(true);
        $recorder = new import_result_recorder();
        $summary = ['total' => 1, 'courseresults' => [
            course_result_row::build('FD', ['id' => 'LG-1'], (object)['id' => 9, 'shortname' => 'X'], []),
        ]];

        $kept = $recorder->record(12, $summary);
        $recorder->record(12, $kept);

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_course_result'));
    }
}
