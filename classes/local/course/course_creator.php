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
 * Course creator wrapper.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

use company;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Course creator wrapper.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_creator {
    /**
     * Create a Moodle course in a given category.
     *
     * @param company $tenant
     * @param array $item
     * @param array $identifications
     * @param bool $ishlfs Whether the Lehrgang belongs to the HLFS tenant (enables the master course fallback).
     * @return \stdClass|null
     */
    public function create(company $tenant, array $item, array $identifications, bool $ishlfs = false): \stdClass|null {
        global $DB;

        $templatecourse = $this->get_previous_course($identifications);
        // Find master course of courseshortname.
        if (empty($templatecourse)) {
            $templatecourse = $this->get_local_template_course($identifications['coursename']);
        }

        // Last resort for back-dated Lehrgaenge: this year's course is the closest match
        // for building the _alt completion course.
        if (empty($templatecourse) && $this->is_past_year((string)($identifications['year'] ?? ''))) {
            $templatecourse = $this->get_current_year_course($identifications);
        }

        // HLFS collection courses: without any other template, build them from the master course.
        $usedmaster = false;
        if (empty($templatecourse) && $ishlfs) {
            $templatecourse = $this->get_master_course();
            $usedmaster = !empty($templatecourse);
        }

        if (empty($templatecourse)) {
            return null;
        }
        // Start with template-derived defaults (if available).
        $data = $this->build_course_data_from_template($templatecourse);

        $year = (int)$identifications['year'];
        if ($year > 0 && $year < 100) {
            $year = 2000 + $year;
        }
        if ($year > 0) {
            $data->startdate = make_timestamp($year, 1, 1, 0, 0, 0);
            $data->enddate   = make_timestamp($year, 12, 31, 23, 59, 59);
        }

        $baseshortname = implode('-', $identifications);
        $shortname = $this->resolve_target_shortname($identifications, $baseshortname, $usedmaster);
        $data->category  = $tenant->get('category');
        $data->fullname  = $identifications['coursename'];
        if (str_ends_with($shortname, '_alt')) {
            $data->fullname .= ' ' . get_string('completion', 'local_lehrgaengeapi');
        }
        $data->shortname = $shortname;
        // A completion course collects the participants of many past Lehrgaenge, so no
        // single Florix id belongs in its idnumber - and claiming one there makes
        // create_course() reject every later Lehrgang that reuses the course.
        $data->idnumber  = str_ends_with($shortname, '_alt') ? '' : $item['id'];
        $data->visible   = 1;

        if ($DB->get_record('course', ['shortname' => $shortname])) {
            return null;
        }
        $newcourse = create_course($data);

        // Copy course content from template if course was created successfully.
        if ($newcourse && $templatecourse) {
            $this->copy_course_content($templatecourse, $newcourse);
        }

        return $newcourse;
    }

    /**
     * Get this year's course for the same tenant and Lehrgang type.
     *
     * @param array $identifications
     * @return \stdClass|null
     */
    private function get_current_year_course(array $identifications): ?\stdClass {
        global $DB;

        $shortname = implode('-', [
            $identifications['tenant'],
            $identifications['coursename'],
            date('y'),
        ]);

        return $DB->get_record('course', ['shortname' => $shortname]) ?: null;
    }

    /**
     * Resolve the shortname that should be created for a given Lehrgang.
     *
     * If the Lehrgang year is in the past, we create it under a year-less <TENANT>-<NAME>_alt
     * course, so that back-dated Lehrgaenge of every past year share one completion course.
     *
     * @param array $identifications
     * @param string $baseshortname
     * @param bool $forcealt Always build the year-less _alt name (HLFS master course fallback).
     * @return string
     */
    private function resolve_target_shortname(array $identifications, string $baseshortname, bool $forcealt = false): string {
        if (!$forcealt && !$this->is_past_year((string)($identifications['year'] ?? ''))) {
            return $baseshortname;
        }

        return implode('-', [
            $identifications['tenant'],
            $identifications['coursename'],
        ]) . '_alt';
    }

    /**
     * Whether the given two-digit year is in the past.
     *
     * @param string $year
     * @return bool
     */
    private function is_past_year(string $year): bool {
        if (!preg_match('/^\d{2}$/', $year)) {
            return false;
        }

        return (int)$year < (int)date('y');
    }

    /**
     * Search for a course i the prior year.
     *
     * @param array $identifications
     * @return array
     */
    private function get_previous_course(array $identifications): ?\stdClass {
        global $DB;
        $year = (int)$identifications['year'];
        if ($year <= 0) {
            return null;
        }
        $previousshortname = implode('-', [
            $identifications['tenant'],
            $identifications['coursename'],
            $year - 1,
        ]);
        return $DB->get_record('course', ['shortname' => $previousshortname]) ?: null;
    }

    /**
     * Get the configured global template course (master course).
     * @param string $localcourseid
     * @return \stdClass|null
     */
    private function get_local_template_course($localcourseid): \stdClass|null {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => $localcourseid]);
        if (!$course) {
            return null;
        }
        if ((int)$course->id === (int)SITEID) {
            return null;
        }
        return $course;
    }

    /**
     * Build a create_course() payload derived from template (if present).
     *
     * @param \stdClass|null $templatecourse
     * @return \stdClass
     */
    private function build_course_data_from_template(?\stdClass $templatecourse): \stdClass {
        $data = new \stdClass();

        if (!$templatecourse) {
            // Minimal defaults; create_course() will apply other defaults.
            $data->summary = '';
            $data->summaryformat = FORMAT_HTML;
            return $data;
        }

        $fields = [
            'summary',
            'summaryformat',
            'format',
            'numsections',
            'startdate',
            'enddate',
            'lang',
            'newsitems',
            'showgrades',
            'groupmode',
            'groupmodeforce',
            'enablecompletion',
            'completionnotify',
            'maxbytes',
            'showreports',
            'visibleold',
        ];

        foreach ($fields as $field) {
            if (property_exists($templatecourse, $field)) {
                $data->{$field} = $templatecourse->{$field};
            }
        }

        return $data;
    }

    /**
     * Queue an adhoc task to copy all course content from template into new course.
     *
     * The actual backup/restore runs asynchronously so the sync task is not blocked
     * and participant assignment can proceed immediately after course creation.
     *
     * @param \stdClass $templatecourse
     * @param \stdClass $newcourse
     * @return void
     */
    private function copy_course_content(\stdClass $templatecourse, \stdClass $newcourse): void {
        $task = new \local_lehrgaengeapi\task\copy_course_content_task();
        $task->set_custom_data([
            'templatecourseid' => $templatecourse->id,
            'newcourseid'      => $newcourse->id,
            'adminid'          => get_admin()->id,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Get the configured master course (setting targetcourseid) used for HLFS collection courses.
     *
     * @return \stdClass|null
     */
    private function get_master_course(): ?\stdClass {
        global $DB;

        $courseid = (int)get_config('local_lehrgaengeapi', 'targetcourseid');
        if ($courseid <= 0 || $courseid === (int)SITEID) {
            return null;
        }

        return $DB->get_record('course', ['id' => $courseid]) ?: null;
    }
}
