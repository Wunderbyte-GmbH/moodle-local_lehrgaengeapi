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
 * Tests for course_result_row.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

/**
 * Tests for course_result_row.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_result_row_test extends \basic_testcase {
    /**
     * The participant sync result of one Lehrgang becomes one flat row with course, Florix id
     * and every counter.
     *
     * @covers \local_lehrgaengeapi\local\services\course_result_row::build
     */
    public function test_build_flattens_the_participant_sync_result(): void {
        $course = (object)['id' => 17, 'shortname' => 'HLFS-F-ATR_alt'];
        $item = ['id' => 'LG-100', 'kurzbezeichnung' => 'F-Atr', 'bezeichnung' => 'Atemschutz'];
        $report = [
            'florixid' => 'LG-100',
            'users' => ['created' => 2, 'existing' => 5, 'skipped' => 1, 'total' => 8],
            'assignments' => [
                'enrolled' => 2, 'alreadyenrolled' => 4, 'unenrolled' => 1, 'alreadyunenrolled' => 0,
                'noop' => 1, 'completed' => 3, 'skipped' => 0, 'total' => 8,
            ],
        ];

        $row = course_result_row::build('MKK', $item, $course, $report);

        $this->assertSame([
            'tenantabbr' => 'MKK',
            'courseid' => 17,
            'courseshortname' => 'HLFS-F-ATR_alt',
            'externalid' => 'LG-100',
            'kurzbezeichnung' => 'F-Atr',
            'userscreated' => 2,
            'usersexisting' => 5,
            'usersskipped' => 1,
            'enrolled' => 2,
            'alreadyenrolled' => 4,
            'unenrolled' => 1,
            'alreadyunenrolled' => 0,
            'noop' => 1,
            'completed' => 3,
            'assignskipped' => 0,
            'total' => 8,
        ], $row);
    }

    /**
     * A Lehrgang whose participant sync failed or returned nothing still gets a row with zeros,
     * so it shows up under its course instead of disappearing.
     *
     * @covers \local_lehrgaengeapi\local\services\course_result_row::build
     */
    public function test_build_with_empty_report_has_zero_counters(): void {
        $row = course_result_row::build('FD', ['id' => 'LG-1'], (object)['id' => 3, 'shortname' => 'FD-AGT-26'], []);

        $this->assertSame('LG-1', $row['externalid']);
        $this->assertSame('', $row['kurzbezeichnung']);
        foreach (['userscreated', 'enrolled', 'alreadyenrolled', 'unenrolled', 'completed', 'total'] as $counter) {
            $this->assertSame(0, $row[$counter], $counter);
        }
    }
}
