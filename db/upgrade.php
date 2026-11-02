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
 * Upgrade steps for local_lehrgaengeapi.
 *
 * @package     local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_lehrgaengeapi upgrade from the given old version.
 *
 * @param int $oldversion Old plugin version.
 * @return bool
 */
function xmldb_local_lehrgaengeapi_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    // 2026021700: Add minimal mapping tables.
    if ($oldversion < 2026021700) {
        $table = new xmldb_table('local_lehrgaengeapi_coursemap');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('externalid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table);
        }

        // Add indexes if missing.
        $table = new xmldb_table('local_lehrgaengeapi_coursemap');

        $index = new xmldb_index('externalid_uix', XMLDB_INDEX_UNIQUE, ['externalid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('local_lehrgaengeapi_usermap');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('externalinitialid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table);
        }

        // Add indexes if missing.
        $table = new xmldb_table('local_lehrgaengeapi_usermap');

        $index = new xmldb_index('externalinitialid_uix', XMLDB_INDEX_UNIQUE, ['externalinitialid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Savepoint.
        upgrade_plugin_savepoint(true, 2026021700, 'local', 'lehrgaengeapi');
    }

        // 2026081700: Add import run log table.
    if ($oldversion < 2026081700) {
        $table = new xmldb_table('local_lehrgaengeapi_import_run');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('year', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'running');
            $table->add_field('triggeredby', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timestarted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timeended', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('summary', XMLDB_TYPE_TEXT, null, null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_lehrgaengeapi_import_run');
        $index = new xmldb_index('timestarted_ix', XMLDB_INDEX_NOTUNIQUE, ['timestarted']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026081700, 'local', 'lehrgaengeapi');
    }

    // 2026101600: Add custom profile field for the Ausbildungen of a participant.
    if ($oldversion < 2026101600) {
        \local_lehrgaengeapi\local\users\ausbildungen_profile_field::ensure_exists();

        upgrade_plugin_savepoint(true, 2026101600, 'local', 'lehrgaengeapi');
    }

    // 2026103000: Ausbildungen linking - remembered tenants, link report, run type.
    if ($oldversion < 2026103000) {
        $table = new xmldb_table('local_lehrgaengeapi_import_run');
        $field = new xmldb_field('runtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'import', 'summary');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('local_lehrgaengeapi_usertenant');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tenantabbr', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('userid_tenantabbr_uix', XMLDB_INDEX_UNIQUE, ['userid', 'tenantabbr']);

            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_lehrgaengeapi_link_result');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tenantabbr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('bezeichnung', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('kurzbezeichnung', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('von', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('bis', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('apistatus', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('outcome', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid_fk', XMLDB_KEY_FOREIGN, ['runid'], 'local_lehrgaengeapi_import_run', ['id']);
            $table->add_index('runid_outcome_ix', XMLDB_INDEX_NOTUNIQUE, ['runid', 'outcome']);
            $table->add_index('userid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026103000, 'local', 'lehrgaengeapi');
    }
    // 2026110200: Import results per course and Lehrgang.
    if ($oldversion < 2026110200) {
        $table = new xmldb_table('local_lehrgaengeapi_course_result');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('runid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('tenantabbr', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('courseshortname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('externalid', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $table->add_field('kurzbezeichnung', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $counters = [
                'userscreated', 'usersexisting', 'usersskipped',
                'enrolled', 'alreadyenrolled', 'unenrolled', 'alreadyunenrolled',
                'noop', 'completed', 'assignskipped', 'total',
            ];
            foreach ($counters as $counter) {
                $table->add_field($counter, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            }
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('runid_fk', XMLDB_KEY_FOREIGN, ['runid'], 'local_lehrgaengeapi_import_run', ['id']);
            $table->add_index('runid_courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['runid', 'courseid']);
            $table->add_index('runid_externalid_ix', XMLDB_INDEX_NOTUNIQUE, ['runid', 'externalid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026110200, 'local', 'lehrgaengeapi');
    }

    return true;
}
