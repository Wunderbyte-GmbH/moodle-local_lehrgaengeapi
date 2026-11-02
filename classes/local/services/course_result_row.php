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
 * Builds the result row of one Lehrgang for the import report.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

/**
 * Builds the result row of one Lehrgang for the import report.
 *
 * One row per Lehrgang, so several Lehrgaenge synced into the same Moodle course stay apart.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_result_row {
    /** @var array<string,string> Row column => key in participants_sync_service 'users' part. */
    private const USER_COUNTERS = [
        'userscreated' => 'created',
        'usersexisting' => 'existing',
        'usersskipped' => 'skipped',
    ];

    /** @var array<string,string> Row column => key in participant_course_assigner report. */
    private const ASSIGNMENT_COUNTERS = [
        'enrolled' => 'enrolled',
        'alreadyenrolled' => 'alreadyenrolled',
        'unenrolled' => 'unenrolled',
        'alreadyunenrolled' => 'alreadyunenrolled',
        'noop' => 'noop',
        'completed' => 'completed',
        'assignskipped' => 'skipped',
    ];

    /**
     * Build the row.
     *
     * @param string $tenantabbr Tenant whose API delivered the Lehrgang.
     * @param array $item Lehrgang payload from the API.
     * @param \stdClass $course Moodle course the Lehrgang was synced into.
     * @param array $report Result of participants_sync_service::sync_for_course(), [] if it failed.
     * @return array
     */
    public static function build(string $tenantabbr, array $item, \stdClass $course, array $report): array {
        $users = $report['users'] ?? [];
        $assignments = $report['assignments'] ?? [];

        $row = [
            'tenantabbr' => $tenantabbr,
            'courseid' => (int)$course->id,
            'courseshortname' => (string)$course->shortname,
            'externalid' => (string)($item['id'] ?? ''),
            'kurzbezeichnung' => (string)($item['kurzbezeichnung'] ?? ''),
        ];
        foreach (self::USER_COUNTERS as $column => $key) {
            $row[$column] = (int)($users[$key] ?? 0);
        }
        foreach (self::ASSIGNMENT_COUNTERS as $column => $key) {
            $row[$column] = (int)($assignments[$key] ?? 0);
        }
        $row['total'] = (int)($users['total'] ?? 0);

        return $row;
    }
}
