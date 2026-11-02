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
 * Shared filter SQL for the per-course and per-Lehrgang import result tables.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\table;

/**
 * Shared filter SQL for the per-course and per-Lehrgang import result tables.
 *
 * Both views must show the same rows for the same filters, so the WHERE clause lives here once.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_result_filter {
    /**
     * WHERE clause on {local_lehrgaengeapi_course_result} r for a run and the active filters.
     *
     * @param int $runid
     * @param array $filters Optional 'tenant' (abbr), 'course' (part of the shortname, any case),
     *                       'externalid' (Florix id, exact) and 'courseid'.
     * @return array [string $where, array $params]
     */
    public static function where(int $runid, array $filters): array {
        global $DB;

        $where = ['r.runid = :runid'];
        $params = ['runid' => $runid];

        $tenant = trim((string)($filters['tenant'] ?? ''));
        if ($tenant !== '') {
            $where[] = 'r.tenantabbr = :tenant';
            $params['tenant'] = $tenant;
        }

        $course = trim((string)($filters['course'] ?? ''));
        if ($course !== '') {
            $where[] = $DB->sql_like('r.courseshortname', ':course', false);
            $params['course'] = '%' . $DB->sql_like_escape($course) . '%';
        }

        $externalid = trim((string)($filters['externalid'] ?? ''));
        if ($externalid !== '') {
            $where[] = 'r.externalid = :externalid';
            $params['externalid'] = $externalid;
        }

        $courseid = (int)($filters['courseid'] ?? 0);
        if ($courseid > 0) {
            $where[] = 'r.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        return [implode(' AND ', $where), $params];
    }
}
