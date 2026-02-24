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

use completion_completion;
use context_course;
use local_lehrgaengeapi\local\repository\usermap_repository;
use local_lehrgaengeapi\local\lehrgang_status\participant_status_handler_resolver;
use local_lehrgaengeapi\local\lehrgang_status\angemeldet_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\bestanden_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\noop_participant_status_handler;

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

    /** @var participant_status_handler_resolver */
    private participant_status_handler_resolver $resolver;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->usermap = new usermap_repository();
        $this->resolver = new participant_status_handler_resolver(
            new angemeldet_participant_status_handler(),
            new bestanden_participant_status_handler(),
            new noop_participant_status_handler()
        );
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

        $total = count($participants);
        $skipped = 0;
        $noop = 0;
        $enrolled = 0;
        $alreadyenrolled = 0;
        $completed = 0;

        $context = context_course::instance($courseid);

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

        $manualinstance = $DB->get_record(
            'enrol',
            ['courseid' => $courseid, 'enrol' => 'manual'],
            '*',
            IGNORE_MISSING
        );


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

            $status = (string)($p['status'] ?? '');
            $handler = $this->resolver->resolve($status);
            $action = $handler->process();

            if (!$action->should_assign() && !$action->should_complete()) {
                $noop++;
                continue;
            }

            if ($action->should_assign()) {
                if (is_enrolled($context, $userid, '', true)) {
                    $alreadyenrolled++;
                } else if ($plugin && $manualinstance) {
                    $plugin->enrol_user($manualinstance, $userid, (int)$manualinstance->roleid);
                    $enrolled++;
                }
            }

            if ($action->should_complete()) {
                if ($this->mark_course_completed($userid, $courseid)) {
                    $completed++;
                }
            }
        }

        return [
            'enrolled' => $enrolled,
            'alreadyenrolled' => $alreadyenrolled,
            'completed' => $completed,
            'noop' => $noop,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * Mark course completion for a user (if possible).
     *
     * @param int $userid
     * @param int $courseid
     * @return bool True if completion was marked or already complete.
     */
    private function mark_course_completed(int $userid, int $courseid): bool {
        global $DB;

        // Course completion record helper class.
        $completion = new completion_completion([
            'userid' => $userid,
            'course' => $courseid,
        ]);

        // If already completed, nothing to do.
        if (!empty($completion->timecompleted)) {
            return true;
        }

        $completion->mark_complete(time());
        $record = $DB->get_record('course_completions', [
            'userid' => $userid,
            'course' => $courseid,
        ], 'timecompleted', IGNORE_MISSING);

        return !empty($record->timecompleted);
    }
}
