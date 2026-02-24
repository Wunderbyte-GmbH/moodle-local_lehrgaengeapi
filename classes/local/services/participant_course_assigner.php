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
 * Assign participants to Moodle courses.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

use context_course;
use local_lehrgaengeapi\local\repository\usermap_repository;

/**
 * Ensures participants are enrolled in a Moodle course.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_course_assigner {
    /** @var usermap_repository */
    private usermap_repository $usermap;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->usermap = new usermap_repository();
    }

    /**
     * Assign (enrol) participants to a course if not already enrolled.
     *
     * @param array $participants
     * @param int $courseid
     * @return array
     */
    public function assign(array $participants, int $courseid): array {
        global $DB;

        $enrolled = 0;
        $alreadyenrolled = 0;
        $skipped = 0;
        $total = count($participants);

        // Get manual enrol plugin + instance.
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return [
                'enrolled' => 0,
                'alreadyenrolled' => 0,
                'skipped' => $total,
                'total' => $total,
            ];
        }

        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MISSING);
        if (!$instance) {
            return [
                'enrolled' => 0,
                'alreadyenrolled' => 0,
                'skipped' => $total,
                'total' => $total,
            ];
        }

        foreach ($participants as $p) {
            if (!is_array($p)) {
                $skipped++;
                continue;
            }

            $initialid = trim((string)($p['initialId'] ?? ''));
            if ($initialid === '') {
                $skipped++;
                continue;
            }

            $map = $this->usermap->get_by_externalinitialid($initialid);
            if (!$map || empty($map->userid)) {
                $skipped++;
                continue;
            }

            $userid = (int)$map->userid;

            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', IGNORE_MISSING);
            if (!$user) {
                $skipped++;
                continue;
            }

            if (is_enrolled(context_course::instance($courseid), $userid, '', true)) {
                $alreadyenrolled++;
                continue;
            }

            $plugin->enrol_user($instance, $userid, (int)$instance->roleid);
            $enrolled++;
        }

        return [
            'enrolled' => $enrolled,
            'alreadyenrolled' => $alreadyenrolled,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }
}
