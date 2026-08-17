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
 * Tests for sync_lehrgaenge_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

use local_lehrgaengeapi\local\repository\import_run_repository;

/**
 * Tests for sync_lehrgaenge_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_lehrgaenge_task_test extends \advanced_testcase {
    /**
     * settings.php declares an admin_setting_configtext for every tenant's apikey with
     * an empty-string default. Once those defaults are persisted (as they are in this
     * test environment via init.php), tenants::get_all_with_keys() returns ALL tenants,
     * each with an empty apikey - sync() then short-circuits per tenant with a zero
     * summary instead of omitting them. execute() still logs a single successful run.
     *
     * @covers \local_lehrgaengeapi\task\sync_lehrgaenge_task::execute
     */
    public function test_execute_logs_success_run_with_zero_totals_when_no_real_apikey_set(): void {
        global $DB;
        $this->resetAfterTest(true);

        $task = new sync_lehrgaenge_task();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_import_run'));

        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(import_run_repository::STATUS_SUCCESS, $run->status);
        $this->assertNull($run->year);
        $this->assertNull($run->triggeredby);

        $decoded = import_run_repository::decode_summary($run);
        $this->assertNotEmpty($decoded);
        foreach ($decoded as $tenantsummary) {
            $this->assertSame(0, $tenantsummary['total']);
        }
    }

    /**
     * When the sync_lehrgaenge lock is already held (e.g. by a manual import), execute()
     * throws immediately instead of silently returning, so Moodle retries via faildelay.
     * No run row is created for the rejected attempt.
     *
     * Uses the file lock factory explicitly: the DB-native lock factories used by
     * default (e.g. Postgres advisory locks) are reentrant per DB session, and PHPUnit
     * runs the whole test in a single session, so a self-conflict would never occur
     * against the default factory.
     *
     * @covers \local_lehrgaengeapi\task\sync_lehrgaenge_task::execute
     */
    public function test_execute_throws_and_logs_nothing_when_lock_busy(): void {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        $CFG->lock_factory = '\core\lock\file_lock_factory';

        $factory = \core\lock\lock_config::get_lock_factory('local_lehrgaengeapi');
        $lock = $factory->get_lock('sync_lehrgaenge', 0);
        $this->assertNotFalse($lock, 'Test setup failed to acquire the lock.');

        try {
            $task = new sync_lehrgaenge_task();

            $threw = false;
            ob_start();
            try {
                $task->execute();
            } catch (\moodle_exception $e) {
                $threw = true;
                $this->assertSame('lockbusy', $e->errorcode);
            } finally {
                ob_end_clean();
            }
            $this->assertTrue($threw, 'execute() should throw when the lock is busy.');
            $this->assertSame(0, $DB->count_records('local_lehrgaengeapi_import_run'));
        } finally {
            $lock->release();
        }
    }

    /**
     * A genuine sync failure (here: connection failure to an unreachable configured
     * baseurl) is logged as an error run and rethrown, so faildelay still applies.
     *
     * @covers \local_lehrgaengeapi\task\sync_lehrgaenge_task::execute
     */
    public function test_execute_logs_error_run_and_rethrows_on_failure(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Port 1 on loopback is refused instantly by the OS - no real network access
        // needed and no slow timeout to wait out.
        set_config('baseurl', 'http://127.0.0.1:1', 'local_lehrgaengeapi');
        set_config('timeout', 2, 'local_lehrgaengeapi');
        set_config('apikey_hp', 'testkey', 'local_lehrgaengeapi');

        $task = new sync_lehrgaenge_task();

        $threw = false;
        ob_start();
        try {
            $task->execute();
        } catch (\Throwable $e) {
            $threw = true;
        }
        ob_end_clean();
        $this->assertTrue($threw, 'execute() should rethrow the underlying failure.');

        $this->assertSame(1, $DB->count_records('local_lehrgaengeapi_import_run'));
        $run = $DB->get_record('local_lehrgaengeapi_import_run', [], '*', MUST_EXIST);
        $this->assertSame(import_run_repository::STATUS_ERROR, $run->status);
        $decoded = import_run_repository::decode_summary($run);
        $this->assertArrayHasKey('error', $decoded);
    }
}
