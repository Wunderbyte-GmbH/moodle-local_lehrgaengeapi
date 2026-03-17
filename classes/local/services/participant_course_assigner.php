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

    /** @var \enrol_plugin|null */
    private \enrol_plugin|null $plugin;

    /** @var \context_course|null */
    private context_course|null $context;

    /** @var array|null */
    private array|null $course;

    /** @var int */
    private int $courseid;

    /** @var object|null */
    private object|null $manualinstance;

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
        $this->courseid = 0;
        $this->context = null;
        $this->course = null;
        $this->manualinstance = null;
        $this->plugin = enrol_get_plugin('manual');
    }

    /**
     * Assign (enrol) participants to a course if not already enrolled.
     *
     * @param array $participants
     * @param int $courseid
     * @return array
     */
    public function assign(array $participants, int $courseid, array $course): array {
        global $DB;

        $total = count($participants);

        $report = [
            'skipped' => 0,
            'noop' => 0,
            'enrolled' => 0,
            'alreadyenrolled' => 0,
            'completed' => 0,
            'total' => $total,
        ];
        // Get manual enrol plugin + instance.
        if (!$this->plugin) {
            $report['skipped'] = $total;
            return $report;
        }

        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MISSING);
        if (!$instance) {
            $report['skipped'] = $total;
            return $report;
        }
        $this->set_courseid($courseid);
        $this->set_manualinstance($courseid);
        $this->set_context($courseid);
        $this->set_course($course);

        foreach ($participants as $participant) {
            $this->subscribe_participant(
                $participant,
                $report
            );
        }

        return $report;
    }

    /**
     * Sets the course context.
     * @param array $course
     * @return void
     */
    private function set_course(array $course): void {
        $this->course = $course;
        return;
    }

    /**
     * Sets the course context.
     * @param int $courseid
     * @return void
     */
    private function set_context(int $courseid): void {
        $this->context = context_course::instance($courseid);
        return;
    }

    /**
     * Sets the course context.
     * @param int $courseid
     * @return void
     */
    private function set_courseid(int $courseid): void {
        $this->courseid = $courseid;
        return;
    }


    /**
     * Sets the course manual instance.
     * @param int $courseid
     * @return void
     */
    private function set_manualinstance(int $courseid): void {
        global $DB;
        $this->manualinstance = $DB->get_record(
            'enrol',
            ['courseid' => $courseid, 'enrol' => 'manual'],
            '*',
            IGNORE_MISSING
        );
        return;
    }

    /**
     * Check and subscribe participant to course completion.
     *
     * @param array $participant
     * @param array $report
     * @return void
     */
    private function subscribe_participant($participant, &$report): void {
        global $DB;
        if (!is_array($participant)) {
            $report['skipped']++;
            return;
        }
        if (!isset($participant['initialId'])) {
            $report['skipped']++;
            return;
        }

        $initialid = trim((string)($participant['initialId'] ?? ''));
        if ($initialid === '') {
            $report['skipped']++;
            return;
        }

        $map = $this->usermap->get_by_externalinitialid($initialid);
        if (!$map || empty($map->userid)) {
            $report['skipped']++;
            return;
        }

        $userid = (int)$map->userid;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', IGNORE_MISSING);
        if (!$user) {
            $report['skipped']++;
            return;
        }

        $status = (string)($participant['status'] ?? '');
        $handler = $this->resolver->resolve($status);
        $action = $handler->process();

        if (!$action->should_assign() && !$action->should_complete()) {
            $report['noop']++;
            return;
        }

        if ($action->should_assign()) {
            $isenrolled = is_enrolled($this->context, $userid, '', true);

            if ($isenrolled) {
                $report['alreadyenrolled']++;
            } else if ($this->plugin && $this->manualinstance) {
                $this->plugin->enrol_user($this->manualinstance, $userid, (int)$this->manualinstance->roleid);
                $report['enrolled']++;
                $isenrolled = true;
            }

            // If user is enrolled (already or newly), ensure group membership.
            if ($isenrolled) {
                $this->ensure_participant_group_membership($participant, $userid);
            }
        }

        if ($action->should_complete()) {
            if ($this->mark_course_completed($userid)) {
                $report['completed']++;
            }
        }
    }

    /**
     * Mark course completion for a user (if possible).
     *
     * @param int $userid
     * @return bool True if completion was marked or already complete.
     */
    private function mark_course_completed(int $userid): bool {
        global $DB;

        // Course completion record helper class.
        $completion = new completion_completion([
            'userid' => $userid,
            'course' => $this->courseid,
        ]);

        // If already completed, nothing to do.
        if (!empty($completion->timecompleted)) {
            return true;
        }

        $completion->mark_complete(time());
        $record = $DB->get_record('course_completions', [
            'userid' => $userid,
            'course' => $this->courseid,
        ], 'timecompleted', IGNORE_MISSING);

        return !empty($record->timecompleted);
    }

    /**
     * Ensure participant is in the target course group (create group if needed).
     *
     * @param array $participant
     * @param int $userid
     * @return bool True if group membership exists/was added, false otherwise.
     */
    private function ensure_participant_group_membership(array $participant, int $userid): bool {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $groupname = $this->resolve_group_name_from_participant();

        if ($groupname === '') {
            return false;
        }

        $groupid = $this->get_or_create_course_group($groupname);
        if ($groupid <= 0) {
            return false;
        }
        return \groups_add_member($groupid, $userid);
    }

    /**
     * Resolve course group name from participant payload.
     *
     * @return string
     */
    private function resolve_group_name_from_participant(): string {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $this->course['startTag']);
        if (!$dt) {
            return '';
        }

        $year = $dt->format('o');
        $week = $dt->format('W'); // always 2 digits
        $week = ltrim($week, '0'); // optional: "05" -> "5"

        return 'CW' . $week . '-' . $year;
    }

    /**
     * Get existing group in course by name or create it.
     *
     * @param string $groupname
     * @return int Group ID or 0 on failure.
     */
    private function get_or_create_course_group(string $groupname): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $groupname = trim($groupname);
        if ($groupname === '') {
            return 0;
        }

        $existing = $DB->get_record('groups', [
            'courseid' => $this->courseid,
            'name' => $groupname,
        ], 'id', IGNORE_MISSING);

        if ($existing && !empty($existing->id)) {
            return (int)$existing->id;
        }

        $groupdata = new \stdClass();
        $groupdata->courseid = $this->courseid;
        $groupdata->name = $groupname;
        $groupdata->description = '';
        $groupdata->descriptionformat = FORMAT_HTML;

        $newgroupid = \groups_create_group($groupdata);

        return $newgroupid ? (int)$newgroupid : 0;
    }
}
