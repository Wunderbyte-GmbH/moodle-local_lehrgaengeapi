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
 * Finds the _alt completion course of a tenant and course type.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

use stdClass;

/**
 * Finds the _alt completion course of a tenant and course type.
 *
 * Shared by the import and the Ausbildungen linking.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alt_course_finder {
    /**
     * Shortname of the current, year-less convention: <CODE>-<TYPE>_alt.
     *
     * @param string $tenantcode Company code, e.g. FD or HLFS.
     * @param string $coursetype Course type from course_type_resolver.
     * @return string
     */
    public static function alt_shortname(string $tenantcode, string $coursetype): string {
        return $tenantcode . '-' . $coursetype . '_alt';
    }

    /**
     * Find the _alt course: current convention first, then the legacy <CODE>-<TYPE>-YY_alt.
     *
     * @param string $tenantcode Company code.
     * @param string $coursetype Course type.
     * @return stdClass|null
     */
    public function find(string $tenantcode, string $coursetype): ?stdClass {
        global $DB;

        if ($tenantcode === '' || $coursetype === '') {
            return null;
        }

        $course = $DB->get_record('course', ['shortname' => self::alt_shortname($tenantcode, $coursetype)]);
        if ($course) {
            return $course;
        }

        // Legacy courses still carry a two-digit year. LIKE alone would also match longer
        // types (F-TH would find F-TH-VU-26_alt), so the exact pattern is checked in PHP.
        $base = $tenantcode . '-' . $coursetype . '-';
        $select = $DB->sql_like('shortname', ':shortname');
        $params = ['shortname' => $DB->sql_like_escape($base) . '%' . $DB->sql_like_escape('_alt')];
        $pattern = '/^' . preg_quote($base, '/') . '\d{2}_alt$/';

        foreach ($DB->get_records_select('course', $select, $params, 'id ASC') as $candidate) {
            if (preg_match($pattern, (string)$candidate->shortname)) {
                return $candidate;
            }
        }
        return null;
    }
}
