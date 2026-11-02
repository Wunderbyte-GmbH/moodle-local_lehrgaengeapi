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
 * Tests for link_result_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

/**
 * Tests for link_result_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_result_repository_test extends \advanced_testcase {
    /**
     * A report row as returned by ausbildungen_linker::link_user().
     *
     * @param string $outcome
     * @param int|null $courseid
     * @return array
     */
    private function row(string $outcome, ?int $courseid = null): array {
        return [
            'tenantabbr' => 'FD',
            'bezeichnung' => 'Lehrgang „Atemschutzgeräteträger“',
            'kurzbezeichnung' => 'F-Atr',
            'von' => '2019-04-29',
            'bis' => '2019-05-13',
            'apistatus' => 'BESTANDEN',
            'outcome' => $outcome,
            'courseid' => $courseid,
            'message' => null,
        ];
    }

    /**
     * Rows are stored with run, user and creation time; all values survive unchanged.
     *
     * @covers \local_lehrgaengeapi\local\repository\link_result_repository::save_rows
     */
    public function test_save_rows_stores_every_row(): void {
        global $DB;
        $this->resetAfterTest(true);

        (new link_result_repository())->save_rows(7, 42, [$this->row('linked_own', 3), $this->row('notfound')]);

        $rows = array_values($DB->get_records('local_lehrgaengeapi_link_result', ['runid' => 7], 'id ASC'));
        $this->assertCount(2, $rows);
        $this->assertSame('42', (string)$rows[0]->userid);
        $this->assertSame('linked_own', $rows[0]->outcome);
        $this->assertSame('3', (string)$rows[0]->courseid);
        $this->assertSame('Lehrgang „Atemschutzgeräteträger“', $rows[0]->bezeichnung);
        $this->assertNull($rows[1]->courseid);
        $this->assertGreaterThan(0, (int)$rows[1]->timecreated);
    }

    /**
     * Over-long values from the API are cut to the column size instead of failing the run.
     *
     * @covers \local_lehrgaengeapi\local\repository\link_result_repository::save_rows
     */
    public function test_save_rows_truncates_overlong_values(): void {
        global $DB;
        $this->resetAfterTest(true);
        $row = $this->row('notfound');
        $row['bezeichnung'] = str_repeat('ü', 300);
        $row['kurzbezeichnung'] = str_repeat('k', 150);
        $row['apistatus'] = str_repeat('S', 80);

        (new link_result_repository())->save_rows(1, 1, [$row]);

        $stored = $DB->get_record('local_lehrgaengeapi_link_result', ['runid' => 1], '*', MUST_EXIST);
        $this->assertSame(255, \core_text::strlen($stored->bezeichnung));
        $this->assertSame(100, \core_text::strlen($stored->kurzbezeichnung));
        $this->assertSame(64, \core_text::strlen($stored->apistatus));
    }

    /**
     * Counts per outcome of one run, for the run summary.
     *
     * @covers \local_lehrgaengeapi\local\repository\link_result_repository::count_by_outcome
     */
    public function test_count_by_outcome(): void {
        $this->resetAfterTest(true);
        $repo = new link_result_repository();
        $repo->save_rows(1, 10, [$this->row('linked_own', 3), $this->row('notfound'), $this->row('notfound')]);
        $repo->save_rows(1, 11, [$this->row('already', 3)]);
        $repo->save_rows(2, 10, [$this->row('linked_own', 3)]);

        $this->assertSame(['already' => 1, 'linked_own' => 1, 'notfound' => 2], $repo->count_by_outcome(1));
        $this->assertSame([], $repo->count_by_outcome(99));
    }

    /**
     * An empty row list writes nothing.
     *
     * @covers \local_lehrgaengeapi\local\repository\link_result_repository::save_rows
     */
    public function test_save_rows_with_empty_list(): void {
        global $DB;
        $this->resetAfterTest(true);

        (new link_result_repository())->save_rows(1, 1, []);

        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_link_result'));
    }

    /**
     * Only the results of the newest linking runs are kept; import runs and the run rows
     * themselves stay untouched.
     *
     * @covers \local_lehrgaengeapi\local\repository\link_result_repository::purge_old_runs
     */
    public function test_purge_old_runs_keeps_the_newest_link_runs(): void {
        global $DB;
        $this->resetAfterTest(true);
        $runs = new import_run_repository();
        $repo = new link_result_repository();

        $linkruns = [];
        for ($i = 1; $i <= 4; $i++) {
            $run = $runs->start(null, null, import_run_repository::RUNTYPE_LINK);
            // Distinct start times, oldest first.
            $DB->set_field('local_lehrgaengeapi_import_run', 'timestarted', 1000 + $i, ['id' => $run->id]);
            $repo->save_rows((int)$run->id, 1, [$this->row('notfound'), $this->row('notfound')]);
            $linkruns[] = (int)$run->id;
        }
        $importrun = $runs->start(2024, null);

        $deleted = $repo->purge_old_runs(2);

        $this->assertSame(4, $deleted);
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $linkruns[0]]));
        $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $linkruns[1]]));
        $this->assertSame(2, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $linkruns[2]]));
        $this->assertSame(2, $DB->count_records('local_lehrgaengeapi_link_result', ['runid' => $linkruns[3]]));
        $this->assertSame(5, $DB->count_records('local_lehrgaengeapi_import_run'));
        $this->assertTrue($DB->record_exists('local_lehrgaengeapi_import_run', ['id' => $importrun->id]));
    }

    /**
     * Fewer runs than the limit: nothing is deleted.
     *
     * @covers \local_lehrgaengeapi\local\repository\link_result_repository::purge_old_runs
     */
    public function test_purge_old_runs_below_limit_deletes_nothing(): void {
        $this->resetAfterTest(true);
        $run = (new import_run_repository())->start(null, null, import_run_repository::RUNTYPE_LINK);
        $repo = new link_result_repository();
        $repo->save_rows((int)$run->id, 1, [$this->row('notfound')]);

        $this->assertSame(0, $repo->purge_old_runs(10));
        $this->assertSame(['notfound' => 1], $repo->count_by_outcome((int)$run->id));
    }
}
