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
 * Tests for link_result_table.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\table;

use local_lehrgaengeapi\local\repository\link_result_repository;
use local_lehrgaengeapi\local\services\ausbildungen_linker;

/**
 * Tests for link_result_table.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class link_result_table_test extends \advanced_testcase {
    /** @var int User of the first row set. */
    private int $anna;

    /** @var int User of the second row set. */
    private int $ben;

    /** @var int Course the linked rows point to. */
    private int $courseid;

    /**
     * Two users in run 1, one row in run 2 that must never show up.
     *
     * @return void
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest(true);
        $PAGE->set_url('/local/lehrgaengeapi/admin/link_results.php');

        $this->anna = (int)$this->getDataGenerator()->create_user(['firstname' => 'Anna', 'lastname' => 'Aksoy'])->id;
        $this->ben = (int)$this->getDataGenerator()->create_user(['firstname' => 'Ben', 'lastname' => 'Bauer'])->id;
        $this->courseid = (int)$this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR_alt'])->id;

        $repo = new link_result_repository();
        $repo->save_rows(1, $this->anna, [
            $this->row('FD', 'F-Atr', ausbildungen_linker::OUTCOME_LINKED_OWN, $this->courseid),
            $this->row('FD', 'F-Unbekannt', ausbildungen_linker::OUTCOME_NOTFOUND),
        ]);
        $repo->save_rows(1, $this->ben, [
            $this->row(null, 'F-Atr', ausbildungen_linker::OUTCOME_STATUSOPEN),
        ]);
        $repo->save_rows(2, $this->anna, [
            $this->row('FD', 'F-Atr', ausbildungen_linker::OUTCOME_ALREADY, $this->courseid),
        ]);
    }

    /**
     * Report row.
     *
     * @param string|null $tenant
     * @param string $kurz
     * @param string $outcome
     * @param int|null $courseid
     * @return array
     */
    private function row(?string $tenant, string $kurz, string $outcome, ?int $courseid = null): array {
        return [
            'tenantabbr' => $tenant,
            'bezeichnung' => 'Lehrgang ' . $kurz,
            'kurzbezeichnung' => $kurz,
            'von' => '2019-01-01',
            'bis' => '2019-01-02',
            'apistatus' => 'BESTANDEN',
            'outcome' => $outcome,
            'courseid' => $courseid,
            'message' => null,
        ];
    }

    /**
     * Query the table and return its raw rows.
     *
     * @param int $runid
     * @param array $filters
     * @return \stdClass[]
     */
    private function rows(int $runid, array $filters = []): array {
        $table = new link_result_table($runid, $filters);
        $table->define_baseurl(new \moodle_url('/local/lehrgaengeapi/admin/link_results.php', ['runid' => $runid]));
        $table->setup();
        $table->query_db(1000, false);
        return array_values($table->rawdata);
    }

    /**
     * Without filters all rows of the run are listed, none of other runs.
     *
     * @covers \local_lehrgaengeapi\table\link_result_table
     */
    public function test_lists_all_rows_of_the_run(): void {
        $rows = $this->rows(1);

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(
            [$this->anna, $this->anna, $this->ben],
            array_map(static fn($r) => (int)$r->userid, $rows)
        );
    }

    /**
     * Filter by outcome.
     *
     * @covers \local_lehrgaengeapi\table\link_result_table
     */
    public function test_filter_by_outcome(): void {
        $rows = $this->rows(1, ['outcome' => ausbildungen_linker::OUTCOME_NOTFOUND]);

        $this->assertCount(1, $rows);
        $this->assertSame('F-Unbekannt', $rows[0]->kurzbezeichnung);
    }

    /**
     * Filter by tenant, including "without tenant".
     *
     * @covers \local_lehrgaengeapi\table\link_result_table
     */
    public function test_filter_by_tenant_and_without_tenant(): void {
        $this->assertCount(2, $this->rows(1, ['tenant' => 'FD']));

        $none = $this->rows(1, ['tenant' => link_result_table::TENANT_NONE]);
        $this->assertCount(1, $none);
        $this->assertSame($this->ben, (int)$none[0]->userid);
    }

    /**
     * Filter by user id, combinable with the other filters.
     *
     * @covers \local_lehrgaengeapi\table\link_result_table
     */
    public function test_filter_by_userid_combined(): void {
        $this->assertCount(2, $this->rows(1, ['userid' => $this->anna]));
        $this->assertCount(1, $this->rows(1, [
            'userid' => $this->anna,
            'outcome' => ausbildungen_linker::OUTCOME_LINKED_OWN,
        ]));
        $this->assertCount(0, $this->rows(1, ['userid' => $this->ben, 'tenant' => 'FD']));
    }

    /**
     * The rows carry user name and course shortname for display and CSV.
     *
     * @covers \local_lehrgaengeapi\table\link_result_table
     */
    public function test_rows_include_user_and_course(): void {
        $rows = $this->rows(1, ['outcome' => ausbildungen_linker::OUTCOME_LINKED_OWN]);

        $this->assertSame('Anna', $rows[0]->firstname);
        $this->assertSame('Aksoy', $rows[0]->lastname);
        $this->assertSame('FD-F-ATR_alt', $rows[0]->courseshortname);
    }

    /**
     * Every outcome of the linker has a readable label, and the outcome column shows it.
     *
     * @covers \local_lehrgaengeapi\table\link_result_table::outcome_options
     * @covers \local_lehrgaengeapi\table\link_result_table::col_outcome
     */
    public function test_outcome_labels(): void {
        $options = link_result_table::outcome_options();
        $expected = [
            ausbildungen_linker::OUTCOME_LINKED_OWN,
            ausbildungen_linker::OUTCOME_LINKED_HLFS,
            ausbildungen_linker::OUTCOME_ALREADY,
            ausbildungen_linker::OUTCOME_NOTFOUND,
            ausbildungen_linker::OUTCOME_STATUSOPEN,
            ausbildungen_linker::OUTCOME_NOTCOMPLETED,
            ausbildungen_linker::OUTCOME_NOTYPE,
            ausbildungen_linker::OUTCOME_ERROR,
        ];
        $this->assertSame($expected, array_keys($options));
        foreach ($options as $label) {
            $this->assertStringNotContainsString('[[', $label);
        }

        $table = new link_result_table(1, []);
        $this->assertSame(
            $options[ausbildungen_linker::OUTCOME_NOTFOUND],
            $table->col_outcome((object)['outcome' => ausbildungen_linker::OUTCOME_NOTFOUND])
        );
    }
}
