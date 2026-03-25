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
 * Tests for copy_course_content_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\task;

/**
 * Tests for copy_course_content_task.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class copy_course_content_task_test extends \advanced_testcase {
    /**
     * Task copies modules from the template course into the target course.
     *
     * @covers \local_lehrgaengeapi\task\copy_course_content_task::execute
     */
    public function test_execute_copies_modules_from_template(): void {
        global $DB, $CFG;

        if (!file_exists($CFG->dirroot . '/backup/util/includes/backup_includes.php')) {
            $this->markTestSkipped('Backup utilities not available');
        }

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();

        // Create template course with a forum activity.
        $template = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname'  => 'Template Course',
            'shortname' => 'TMPL-1',
            'format'    => 'topics',
            'numsections' => 2,
        ]);
        $this->getDataGenerator()->create_module('forum', ['course' => $template->id, 'name' => 'Template Forum']);

        // Create empty target course.
        $newcourse = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname'  => 'New Course',
            'shortname' => 'NEW-1',
        ]);

        $modulesbefore = $DB->count_records('course_modules', ['course' => $newcourse->id]);

        // Run the task.
        $task = new copy_course_content_task();
        $task->set_custom_data([
            'templatecourseid' => (int)$template->id,
            'newcourseid'      => (int)$newcourse->id,
            'adminid'          => (int)get_admin()->id,
        ]);
        $task->execute();

        $modulesafter = $DB->count_records('course_modules', ['course' => $newcourse->id]);
        $this->assertGreaterThan($modulesbefore, $modulesafter, 'Modules should have been copied to the new course.');
    }

    /**
     * Task exits cleanly when the template course does not exist.
     *
     * @covers \local_lehrgaengeapi\task\copy_course_content_task::execute
     */
    public function test_execute_does_nothing_when_template_missing(): void {
        global $CFG;

        if (!file_exists($CFG->dirroot . '/backup/util/includes/backup_includes.php')) {
            $this->markTestSkipped('Backup utilities not available');
        }

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $newcourse = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname'  => 'New Course',
            'shortname' => 'NEW-2',
        ]);

        $task = new copy_course_content_task();
        $task->set_custom_data([
            'templatecourseid' => 999999, // Non-existent.
            'newcourseid'      => (int)$newcourse->id,
            'adminid'          => (int)get_admin()->id,
        ]);

        // Must not throw.
        $task->execute();
        $this->assertTrue(true);
    }

    /**
     * Task exits cleanly when the target course does not exist.
     *
     * @covers \local_lehrgaengeapi\task\copy_course_content_task::execute
     */
    public function test_execute_does_nothing_when_target_missing(): void {
        global $CFG;

        if (!file_exists($CFG->dirroot . '/backup/util/includes/backup_includes.php')) {
            $this->markTestSkipped('Backup utilities not available');
        }

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $template = $this->getDataGenerator()->create_course([
            'category' => $category->id,
            'fullname'  => 'Template Course',
            'shortname' => 'TMPL-2',
        ]);

        $task = new copy_course_content_task();
        $task->set_custom_data([
            'templatecourseid' => (int)$template->id,
            'newcourseid'      => 999999, // Non-existent.
            'adminid'          => (int)get_admin()->id,
        ]);

        // Must not throw.
        $task->execute();
        $this->assertTrue(true);
    }
}
