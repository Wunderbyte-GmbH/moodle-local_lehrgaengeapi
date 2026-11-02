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
 * Tests for course_result_table and course_summary_table.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\table;

use local_lehrgaengeapi\local\repository\course_result_repository;

/**
 * Tests for course_result_table (one row per Lehrgang) and course_summary_table (one row per Moodle course).
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_result_table_test extends \advanced_testcase {
    /** @var int _alt course that receives three Lehrgänge of two tenants. */
    private int $altcourse;

    /** @var int Course that receives one Lehrgang. */
    private int $singlecourse;

    /**
     * Run 1: three Lehrgänge into FD-F-ATR_alt (FD, FD, MKK) and one into MKK-F-SPR-26.
     * Run 2: one row that must never show up.
     *
     * @return void
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest(true);
        $PAGE->set_url('/local/lehrgaengeapi/admin/import_results.php');

        $this->altcourse = (int)$this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR_alt'])->id;
        $this->singlecourse = (int)$this->getDataGenerator()->create_course(['shortname' => 'MKK-F-SPR-26'])->id;

        $repo = new course_result_repository();
        $repo->save_rows(1, [
            $this->row('FD', $this->altcourse, 'FD-F-ATR_alt', 'LG-A', 'F-Atr 1/19', 4, 2, 1),
            $this->row('FD', $this->altcourse, 'FD-F-ATR_alt', 'LG-B', 'F-Atr 2/19', 3, 0, 2),
            $this->row('MKK', $this->altcourse, 'FD-F-ATR_alt', 'LG-C', 'F-Atr 1/20', 1, 5, 0),
            $this->row('MKK', $this->singlecourse, 'MKK-F-SPR-26', 'LG-D', 'F-Spr 1/26', 7, 0, 0),
        ]);
        $repo->save_rows(2, [
            $this->row('FD', $this->altcourse, 'FD-F-ATR_alt', 'LG-X', 'F-Atr 9/99', 99, 99, 99),
        ]);
    }

    /**
     * Result row as built by course_result_row::build().
     *
     * @param string $tenant
     * @param int $courseid
     * @param string $shortname
     * @param string $externalid
     * @param string $kurz
     * @param int $enrolled
     * @param int $alreadyenrolled
     * @param int $unenrolled
     * @return array
     */
    private function row(
        string $tenant,
        int $courseid,
        string $shortname,
        string $externalid,
        string $kurz,
        int $enrolled,
        int $alreadyenrolled,
        int $unenrolled
    ): array {
        return [
            'tenantabbr' => $tenant,
            'courseid' => $courseid,
            'courseshortname' => $shortname,
            'externalid' => $externalid,
            'kurzbezeichnung' => $kurz,
            'userscreated' => 1,
            'usersexisting' => $enrolled + $alreadyenrolled + $unenrolled - 1,
            'usersskipped' => 0,
            'enrolled' => $enrolled,
            'alreadyenrolled' => $alreadyenrolled,
            'unenrolled' => $unenrolled,
            'alreadyunenrolled' => 0,
            'noop' => 0,
            'completed' => 1,
            'assignskipped' => 0,
            'total' => $enrolled + $alreadyenrolled + $unenrolled,
        ];
    }

    /**
     * Query a table and return its raw rows.
     *
     * @param \table_sql $table
     * @return \stdClass[]
     */
    private function query(\table_sql $table): array {
        $table->define_baseurl(new \moodle_url('/local/lehrgaengeapi/admin/import_results.php', ['runid' => 1]));
        $table->setup();
        $table->query_db(1000, false);
        return array_values($table->rawdata);
    }

    /**
     * Rows of the per-Lehrgang table keyed by Florix id.
     *
     * @param int $runid
     * @param array $filters
     * @return array<string,\stdClass>
     */
    private function lehrgaenge(int $runid, array $filters = []): array {
        $byid = [];
        foreach ($this->query(new course_result_table($runid, $filters)) as $row) {
            $byid[$row->externalid] = $row;
        }
        return $byid;
    }

    /**
     * Rows of the per-course table keyed by course id.
     *
     * @param int $runid
     * @param array $filters
     * @return array<int,\stdClass>
     */
    private function courses(int $runid, array $filters = []): array {
        $byid = [];
        foreach ($this->query(new course_summary_table($runid, $filters)) as $row) {
            $byid[(int)$row->courseid] = $row;
        }
        return $byid;
    }

    /**
     * The per-Lehrgang view lists every Lehrgang of the run once, none of other runs.
     *
     * @covers \local_lehrgaengeapi\table\course_result_table
     */
    public function test_lehrgang_view_lists_every_lehrgang_of_the_run(): void {
        $rows = $this->lehrgaenge(1);

        $this->assertEqualsCanonicalizing(['LG-A', 'LG-B', 'LG-C', 'LG-D'], array_keys($rows));
        $this->assertSame('FD-F-ATR_alt', $rows['LG-B']->courseshortname);
        $this->assertSame('F-Atr 2/19', $rows['LG-B']->kurzbezeichnung);
        $this->assertSame(3, (int)$rows['LG-B']->enrolled);
        $this->assertSame(2, (int)$rows['LG-B']->unenrolled);
    }

    /**
     * The per-Lehrgang view filters by tenant, Florix id, course shortname (part, any case) and course id.
     *
     * @covers \local_lehrgaengeapi\table\course_result_table
     */
    public function test_lehrgang_view_filters(): void {
        $this->assertEqualsCanonicalizing(['LG-A', 'LG-B'], array_keys($this->lehrgaenge(1, ['tenant' => 'FD'])));
        $this->assertSame(['LG-C'], array_keys($this->lehrgaenge(1, ['externalid' => 'LG-C'])));
        $this->assertEqualsCanonicalizing(
            ['LG-A', 'LG-B', 'LG-C'],
            array_keys($this->lehrgaenge(1, ['course' => 'f-atr']))
        );
        $this->assertSame(['LG-D'], array_keys($this->lehrgaenge(1, ['courseid' => $this->singlecourse])));
        $this->assertSame(['LG-C'], array_keys($this->lehrgaenge(1, ['tenant' => 'MKK', 'course' => 'ATR'])));
        $this->assertSame([], $this->lehrgaenge(1, ['externalid' => 'LG-X']));
    }

    /**
     * The per-course view shows one row per Moodle course: sums of all its Lehrgänge,
     * how many there are and which ones.
     *
     * @covers \local_lehrgaengeapi\table\course_summary_table
     */
    public function test_course_view_sums_all_lehrgaenge_of_a_course(): void {
        $rows = $this->courses(1);

        $this->assertEqualsCanonicalizing([$this->altcourse, $this->singlecourse], array_keys($rows));

        $alt = $rows[$this->altcourse];
        $this->assertSame('FD-F-ATR_alt', $alt->courseshortname);
        $this->assertSame(3, (int)$alt->lehrgaenge);
        $this->assertSame(8, (int)$alt->enrolled);
        $this->assertSame(7, (int)$alt->alreadyenrolled);
        $this->assertSame(3, (int)$alt->unenrolled);
        $this->assertSame(3, (int)$alt->completed);
        $this->assertSame(18, (int)$alt->total);
        foreach (['LG-A', 'LG-B', 'LG-C', 'F-Atr 1/19', 'F-Atr 2/19', 'F-Atr 1/20'] as $part) {
            $this->assertStringContainsString($part, $alt->lehrgaengelist);
        }
        $this->assertStringNotContainsString('LG-X', $alt->lehrgaengelist);

        $single = $rows[$this->singlecourse];
        $this->assertSame(1, (int)$single->lehrgaenge);
        $this->assertSame(7, (int)$single->enrolled);
    }

    /**
     * Filters apply before summing: a course only counts the Lehrgänge that match.
     *
     * @covers \local_lehrgaengeapi\table\course_summary_table
     */
    public function test_course_view_filters_before_summing(): void {
        $rows = $this->courses(1, ['tenant' => 'FD']);
        $this->assertSame([$this->altcourse], array_keys($rows));
        $this->assertSame(2, (int)$rows[$this->altcourse]->lehrgaenge);
        $this->assertSame(7, (int)$rows[$this->altcourse]->enrolled);
        $this->assertStringNotContainsString('LG-C', $rows[$this->altcourse]->lehrgaengelist);

        $this->assertSame([$this->singlecourse], array_keys($this->courses(1, ['course' => 'spr'])));
        $this->assertSame([$this->altcourse], array_keys($this->courses(1, ['externalid' => 'LG-B'])));
        $this->assertSame([], $this->courses(1, ['externalid' => 'LG-X']));
    }

    /**
     * Both tables page and count correctly despite the grouping.
     *
     * @covers \local_lehrgaengeapi\table\course_summary_table
     * @covers \local_lehrgaengeapi\table\course_result_table
     */
    public function test_tables_count_rows_for_paging(): void {
        $summary = new course_summary_table(1, []);
        $this->query($summary);
        $this->assertSame(2, (int)$summary->totalrows);

        $results = new course_result_table(1, []);
        $this->query($results);
        $this->assertSame(4, (int)$results->totalrows);
    }

    /**
     * The Florix collection Lehrgang has no Moodle course (course id 0); both views show it
     * as such instead of an empty link.
     *
     * @covers \local_lehrgaengeapi\table\course_result_table::col_courseshortname
     * @covers \local_lehrgaengeapi\table\course_summary_table::col_courseshortname
     */
    public function test_collection_lehrgang_is_labelled_without_course(): void {
        (new course_result_repository())->save_rows(3, [
            $this->row('FD', 0, '', 'LG-AB', 'MOODLE-ALTBESTAND', 0, 0, 0),
        ]);
        $label = get_string('collectioncourse', 'local_lehrgaengeapi');
        $collection = (object)['courseid' => 0, 'courseshortname' => '', 'lehrgaenge' => 1];

        $this->assertSame(['LG-AB'], array_keys($this->lehrgaenge(3)));
        $this->assertSame($label, (new course_result_table(3, []))->col_courseshortname($collection));

        $courses = $this->courses(3);
        $this->assertSame([0], array_keys($courses));
        $this->assertSame(1, (int)$courses[0]->lehrgaenge);
        $this->assertSame($label, (new course_summary_table(3, []))->col_courseshortname($collection));
    }
}
