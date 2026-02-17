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

defined('MOODLE_INTERNAL') || die();

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
    return true;
}
