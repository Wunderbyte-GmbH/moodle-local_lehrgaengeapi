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
 * Tests for course_result_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

/**
 * Tests for course_result_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_result_repository_test extends \advanced_testcase {
    /**
     * A row as built by course_result_row::build().
     *
     * @param int $courseid
     * @param string $externalid
     * @param int $enrolled
     * @return array
     */
    private function row(int $courseid, string $externalid, int $enrolled = 1): array {
        return [
            'tenantabbr' => 'FD',
            'courseid' => $courseid,
            'courseshortname' => 'FD-F-ATR_alt',
            'externalid' => $externalid,
            'kurzbezeichnung' => 'F-Atr',
            'userscreated' => 1,
            'usersexisting' => 2,
            'usersskipped' => 0,
            'enrolled' => $enrolled,
            'alreadyenrolled' => 2,
            'unenrolled' => 0,
            'alreadyunenrolled' => 0,
            'noop' => 0,
            'completed' => 3,
            'assignskipped' => 0,
            'total' => 3,
        ];
    }

    /**
     * Rows are stored per run; several Lehrgaenge of the same course are kept apart.
     *
     * @covers \local_lehrgaengeapi\local\repository\course_result_repository::save_rows
     */
    public function test_save_rows_keeps_every_lehrgang(): void {
        global $DB;
        $this->resetAfterTest(true);

        (new course_result_repository())->save_rows(5, [$this->row(17, 'LG-A'), $this->row(17, 'LG-B', 4)]);

        $rows = array_values($DB->get_records('local_lehrgaengeapi_course_result', ['runid' => 5], 'externalid'));
        $this->assertCount(2, $rows);
        $this->assertSame(['LG-A', 'LG-B'], array_column($rows, 'externalid'));
        $this->assertSame('4', (string)$rows[1]->enrolled);
        $this->assertSame('FD', $rows[0]->tenantabbr);
        $this->assertGreaterThan(0, (int)$rows[0]->timecreated);
    }

    /**
     * Over-long API values are cut to the column size; an empty list writes nothing.
     *
     * @covers \local_lehrgaengeapi\local\repository\course_result_repository::save_rows
     */
    public function test_save_rows_truncates_and_ignores_empty_list(): void {
        global $DB;
        $this->resetAfterTest(true);
        $repo = new course_result_repository();
        $row = $this->row(1, str_repeat('9', 80));
        $row['kurzbezeichnung'] = str_repeat('ü', 150);

        $repo->save_rows(1, []);
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_course_result'));

        $repo->save_rows(1, [$row]);
        $stored = $DB->get_record('local_lehrgaengeapi_course_result', ['runid' => 1], '*', MUST_EXIST);
        $this->assertSame(64, \core_text::strlen($stored->externalid));
        $this->assertSame(100, \core_text::strlen($stored->kurzbezeichnung));
    }

    /**
     * Only the results of the newest import runs are kept; linking runs are not counted and
     * their rows are untouched, run rows stay.
     *
     * @covers \local_lehrgaengeapi\local\repository\course_result_repository::purge_old_runs
     */
    public function test_purge_old_runs_keeps_the_newest_import_runs(): void {
        global $DB;
        $this->resetAfterTest(true);
        $runs = new import_run_repository();
        $repo = new course_result_repository();

        $importruns = [];
        for ($i = 1; $i <= 3; $i++) {
            $run = $runs->start(2024, null);
            $DB->set_field('local_lehrgaengeapi_import_run', 'timestarted', 1000 + $i, ['id' => $run->id]);
            $repo->save_rows((int)$run->id, [$this->row(1, 'LG-' . $i)]);
            $importruns[] = (int)$run->id;
        }
        // A newer linking run must not push import results out.
        $link = $runs->start(null, null, import_run_repository::RUNTYPE_LINK);
        $DB->set_field('local_lehrgaengeapi_import_run', 'timestarted', 2000, ['id' => $link->id]);

        $this->assertSame(1, $repo->purge_old_runs(2));
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_course_result', ['runid' => $importruns[0]]));
        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_course_result', ['runid' => $importruns[1]]));
        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_course_result', ['runid' => $importruns[2]]));
        $this->assertSame(4, $DB->count_records('local_lehrgaengeapi_import_run'));
    }

    /**
     * Whether a run has rows decides between the new report and the legacy JSON view.
     *
     * @covers \local_lehrgaengeapi\local\repository\course_result_repository::has_rows
     */
    public function test_has_rows(): void {
        $this->resetAfterTest(true);
        $repo = new course_result_repository();
        $repo->save_rows(3, [$this->row(1, 'LG-1')]);

        $this->assertTrue($repo->has_rows(3));
        $this->assertFalse($repo->has_rows(4));
    }
}
