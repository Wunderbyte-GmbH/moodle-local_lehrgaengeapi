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
 * Tests for import_run_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\repositories;

use local_lehrgaengeapi\local\repository\import_run_repository;

/**
 * Tests for import_run_repository.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class import_run_repository_test extends \advanced_testcase {
    /**
     * start() persists a running row with the given year and triggeredby.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::start
     */
    public function test_start_creates_running_row(): void {
        $this->resetAfterTest(true);

        $repo = new import_run_repository();
        $run = $repo->start(2024, 5);

        $this->assertSame(2024, (int)$run->year);
        $this->assertSame(5, (int)$run->triggeredby);
        $this->assertSame(import_run_repository::STATUS_RUNNING, $run->status);
        $this->assertGreaterThan(0, (int)$run->timestarted);
        $this->assertNull($run->timeended);
        $this->assertNull($run->summary);
    }

    /**
     * start() accepts null year/triggeredby for automatic/cron runs.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::start
     */
    public function test_start_allows_null_year_and_triggeredby(): void {
        $this->resetAfterTest(true);

        $repo = new import_run_repository();
        $run = $repo->start(null, null);

        $this->assertNull($run->year);
        $this->assertNull($run->triggeredby);
    }

    /**
     * mark_success() sets status, end time and JSON-encodes the summary.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::mark_success
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::decode_summary
     */
    public function test_mark_success_persists_summary(): void {
        $this->resetAfterTest(true);

        $repo = new import_run_repository();
        $run = $repo->start(2024, null);

        $summary = ['HP' => ['created' => 1, 'skipped' => 2, 'total' => 3]];
        $repo->mark_success($run->id, $summary);

        $updated = $repo->get($run->id);
        $this->assertSame(import_run_repository::STATUS_SUCCESS, $updated->status);
        $this->assertGreaterThan(0, (int)$updated->timeended);
        $this->assertSame($summary, import_run_repository::decode_summary($updated));
    }

    /**
     * mark_error() wraps the message under an 'error' key.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::mark_error
     */
    public function test_mark_error_wraps_message(): void {
        $this->resetAfterTest(true);

        $repo = new import_run_repository();
        $run = $repo->start(null, null);

        $repo->mark_error($run->id, 'boom');

        $updated = $repo->get($run->id);
        $this->assertSame(import_run_repository::STATUS_ERROR, $updated->status);
        $this->assertSame(['error' => 'boom'], import_run_repository::decode_summary($updated));
    }

    /**
     * mark_skipped() wraps the reason under a 'reason' key.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::mark_skipped
     */
    public function test_mark_skipped_wraps_reason(): void {
        $this->resetAfterTest(true);

        $repo = new import_run_repository();
        $run = $repo->start(2024, 7);

        $repo->mark_skipped($run->id, 'Ein anderer Sync-Lauf war aktiv.');

        $updated = $repo->get($run->id);
        $this->assertSame(import_run_repository::STATUS_SKIPPED, $updated->status);
        $this->assertSame(['reason' => 'Ein anderer Sync-Lauf war aktiv.'], import_run_repository::decode_summary($updated));
    }

    /**
     * get() returns null for an id that does not exist.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::get
     */
    public function test_get_returns_null_for_missing_id(): void {
        $this->resetAfterTest(true);

        $repo = new import_run_repository();
        $this->assertNull($repo->get(999999));
    }

    /**
     * get_recent() orders by timestarted descending and respects the limit.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::get_recent
     */
    public function test_get_recent_orders_newest_first_and_respects_limit(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Insert directly with controlled timestarted values to avoid relying on
        // real-clock ordering between fast successive start() calls.
        foreach ([100, 300, 200] as $timestarted) {
            $DB->insert_record('local_lehrgaengeapi_import_run', (object)[
                'year' => null,
                'status' => import_run_repository::STATUS_SUCCESS,
                'triggeredby' => null,
                'timestarted' => $timestarted,
                'timeended' => $timestarted + 1,
                'summary' => null,
            ]);
        }

        $repo = new import_run_repository();

        // get_recent() is keyed by record id (like all $DB->get_records() calls), so
        // compare values only, not keys.
        $all = $repo->get_recent(10);
        $this->assertCount(3, $all);
        $this->assertSame([300, 200, 100], array_values(array_map(fn($run) => (int)$run->timestarted, $all)));

        $limited = $repo->get_recent(2);
        $this->assertCount(2, $limited);
        $this->assertSame([300, 200], array_values(array_map(fn($run) => (int)$run->timestarted, $limited)));
    }

    /**
     * decode_summary() returns an empty array for null/empty/invalid JSON.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::decode_summary
     */
    public function test_decode_summary_handles_missing_and_invalid_json(): void {
        $this->assertSame([], import_run_repository::decode_summary((object)['summary' => null]));
        $this->assertSame([], import_run_repository::decode_summary((object)['summary' => '']));
        $this->assertSame([], import_run_repository::decode_summary((object)['summary' => 'not-json']));
    }
}
