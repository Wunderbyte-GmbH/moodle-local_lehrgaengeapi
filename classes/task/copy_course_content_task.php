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
 * Adhoc task: copy course content from a template into a newly created course.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

/**
 * Copies all activities/sections from a template course into a new course via backup/restore.
 */
class copy_course_content_task extends \core\task\adhoc_task {
    /**
     * Execute the adhoc task.
     */
    public function execute(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $data = $this->get_custom_data();
        $templatecourseid = (int)$data->templatecourseid;
        $newcourseid      = (int)$data->newcourseid;
        $adminid          = (int)$data->adminid;

        $templatecourse = $DB->get_record('course', ['id' => $templatecourseid]);
        $newcourse      = $DB->get_record('course', ['id' => $newcourseid]);

        if (!$templatecourse || !$newcourse) {
            mtrace("copy_course_content_task: course not found (template=$templatecourseid, new=$newcourseid)");
            return;
        }

        // Remove the auto-created default announcements forum from the target course.
        // The import will bring the template's forum, so this prevents duplicates.
        $this->remove_default_announcements_forum((int)$newcourse->id);

        // Backup the template course (no user data, import mode).
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $templatecourse->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $adminid
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        // Restore into the already-created new course (add content only).
        $rc = new \restore_controller(
            $backupid,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $adminid,
            \backup::TARGET_EXISTING_ADDING
        );

        if ($rc->execute_precheck()) {
            $rc->execute_plan();
        } else {
            $results = $rc->get_precheck_results();
            $resultsjson = json_encode($results);
            if ($resultsjson === false) {
                $resultsjson = 'Unable to encode precheck results';
            }
            mtrace("copy_course_content_task: precheck failed for course $newcourseid: " . $resultsjson);
        }
        $rc->destroy();
        mtrace("copy_course_content_task: finished copying content from course $templatecourseid to $newcourseid");
    }

    /**
     * Remove auto-generated announcements forum modules (forum type "news") from a course.
     *
     * @param int $courseid
     * @return void
     */
    private function remove_default_announcements_forum(int $courseid): void {
        global $DB;

        $sql = "SELECT cm.id
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {forum} f ON f.id = cm.instance
                 WHERE cm.course = :courseid
                   AND m.name = :modname
                   AND f.type = :forumtype";

        $params = [
            'courseid' => $courseid,
            'modname' => 'forum',
            'forumtype' => 'news',
        ];

        $cms = $DB->get_records_sql($sql, $params);
        foreach ($cms as $cm) {
            course_delete_module((int)$cm->id);
        }
    }
}
