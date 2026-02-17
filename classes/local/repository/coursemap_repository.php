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
 * Repository for course mappings.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\repository;

use stdClass;

/**
 * Repository for course mappings.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursemap_repository {
    /** @var string DB table name */
    private const TABLE = 'local_lehrgaengeapi_coursemap';

    /**
     * Get mapping by external id.
     *
     * @param string $externalid External Lehrgang.id.
     * @return stdClass|null
     */
    public function get_by_externalid(string $externalid): ?stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['externalid' => $externalid]) ?: null;
    }

    /**
     * Ensure mapping row exists (creates if missing).
     *
     * @param string $externalid External Lehrgang.id.
     * @return \stdClass Mapping row.
     */
    public function ensure(string $externalid): stdClass {
        global $DB;

        $existing = $this->get_by_externalid($externalid);
        if ($existing) {
            return $existing;
        }

        $now = time();
        $record = (object)[
            'externalid' => $externalid,
            'courseid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $record->id = $DB->insert_record(self::TABLE, $record);
        return $DB->get_record(self::TABLE, ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Set (or update) Moodle courseid for a given external id.
     *
     * @param string $externalid External Lehrgang.id.
     * @param int $courseid Moodle course.id.
     * @return stdClass Updated mapping row.
     */
    public function set_courseid(string $externalid, int $courseid): stdClass {
        global $DB;

        $row = $this->ensure($externalid);

        if ((int)($row->courseid ?? 0) === $courseid) {
            return $row;
        }

        $row->courseid = $courseid;
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        return $this->get_by_externalid($externalid) ?? $row;
    }
}
