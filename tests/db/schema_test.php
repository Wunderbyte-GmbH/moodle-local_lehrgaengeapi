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
 * Tests for the database schema of the Ausbildungen linking.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\db;

use local_lehrgaengeapi\local\repository\import_run_repository;

/**
 * Tests for the database schema of the Ausbildungen linking.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schema_test extends \advanced_testcase {
    /**
     * The tenant memory table exists with its fields.
     *
     * @coversNothing
     */
    public function test_usertenant_table_exists(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_lehrgaengeapi_usertenant');

        $this->assertTrue($dbman->table_exists($table));
        foreach (['id', 'userid', 'tenantabbr', 'timecreated', 'timemodified'] as $field) {
            $this->assertTrue($dbman->field_exists($table, $field), "Missing field {$field}");
        }
    }

    /**
     * A user can be remembered for several tenants, but each tenant only once.
     *
     * @coversNothing
     */
    public function test_usertenant_is_unique_per_user_and_tenant(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $row = ['userid' => $user->id, 'timecreated' => time(), 'timemodified' => time()];

        $DB->insert_record('local_lehrgaengeapi_usertenant', (object)($row + ['tenantabbr' => 'FD']));
        $DB->insert_record('local_lehrgaengeapi_usertenant', (object)($row + ['tenantabbr' => 'MKK']));
        $this->assertSame(2, $DB->count_records('local_lehrgaengeapi_usertenant', ['userid' => $user->id]));

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_lehrgaengeapi_usertenant', (object)($row + ['tenantabbr' => 'FD']));
    }

    /**
     * The report table exists with all fields needed for one row per run, user and Ausbildung.
     *
     * @coversNothing
     */
    public function test_link_result_table_exists(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_lehrgaengeapi_link_result');

        $this->assertTrue($dbman->table_exists($table));
        $fields = [
            'id', 'runid', 'userid', 'tenantabbr', 'bezeichnung', 'kurzbezeichnung', 'von', 'bis',
            'apistatus', 'outcome', 'courseid', 'message', 'timecreated',
        ];
        foreach ($fields as $field) {
            $this->assertTrue($dbman->field_exists($table, $field), "Missing field {$field}");
        }
    }

    /**
     * A report row without tenant (user without remembered tenant), course and message is valid,
     * and a long Ausbildung title fits.
     *
     * @coversNothing
     */
    public function test_link_result_accepts_minimal_and_long_rows(): void {
        global $DB;
        $this->resetAfterTest(true);

        $id = $DB->insert_record('local_lehrgaengeapi_link_result', (object)[
            'runid' => 1,
            'userid' => 2,
            'tenantabbr' => null,
            'bezeichnung' => str_repeat('Ä', 255),
            'kurzbezeichnung' => 'WB-BF/FF-Ma-Flurförder',
            'von' => '2021-11-13',
            'bis' => '2021-11-13',
            'apistatus' => 'TEILNAHMEWUNSCH',
            'outcome' => 'statusopen',
            'courseid' => null,
            'message' => null,
            'timecreated' => time(),
        ]);

        $row = $DB->get_record('local_lehrgaengeapi_link_result', ['id' => $id], '*', MUST_EXIST);
        $this->assertNull($row->tenantabbr);
        $this->assertNull($row->courseid);
        $this->assertSame(str_repeat('Ä', 255), $row->bezeichnung);
    }

    /**
     * Runs carry a type; runs started by the existing code stay 'import'.
     *
     * @covers \local_lehrgaengeapi\local\repository\import_run_repository::start
     */
    public function test_import_run_has_runtype_defaulting_to_import(): void {
        global $DB;
        $this->resetAfterTest(true);
        $dbman = $DB->get_manager();

        $this->assertTrue($dbman->field_exists(new \xmldb_table('local_lehrgaengeapi_import_run'), 'runtype'));

        $run = (new import_run_repository())->start(2024, null);
        $this->assertSame('import', $DB->get_field('local_lehrgaengeapi_import_run', 'runtype', ['id' => $run->id]));
    }

    /**
     * The course result table exists with one counter column per report figure.
     *
     * @coversNothing
     */
    public function test_course_result_table_exists(): void {
        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_lehrgaengeapi_course_result');

        $this->assertTrue($dbman->table_exists($table));
        $fields = [
            'id', 'runid', 'tenantabbr', 'courseid', 'courseshortname', 'externalid', 'kurzbezeichnung',
            'userscreated', 'usersexisting', 'usersskipped',
            'enrolled', 'alreadyenrolled', 'unenrolled', 'alreadyunenrolled', 'noop', 'completed', 'assignskipped',
            'total', 'timecreated',
        ];
        foreach ($fields as $field) {
            $this->assertTrue($dbman->field_exists($table, $field), "Missing field {$field}");
        }
    }

    /**
     * Counters default to 0, so a row with only the identifying fields is valid.
     *
     * @coversNothing
     */
    public function test_course_result_counters_default_to_zero(): void {
        global $DB;
        $this->resetAfterTest(true);

        $id = $DB->insert_record('local_lehrgaengeapi_course_result', (object)[
            'runid' => 1,
            'courseid' => 2,
            'externalid' => 'LG-1',
            'timecreated' => time(),
        ]);

        $row = $DB->get_record('local_lehrgaengeapi_course_result', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$row->enrolled);
        $this->assertSame(0, (int)$row->total);
        $this->assertNull($row->tenantabbr);
    }
}
