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
     * @return \stdClass
     */
    public function create(company $tenant, array $item): \stdClass {
        $identifications = $this->set_course_identifications($item, $tenant);
        $templatecourse = null;
        // Find master course of courseshortname.
        $templatecourse = $this->get_local_template_course($identifications['coursename']);
        // Find global master course.
        if (empty($templatecourse)) {
            $templatecourse = $this->get_global_template_course();
        }

        // Start with template-derived defaults (if available).
        $data = $this->build_course_data_from_template($templatecourse);

        $year = (int)$identifications['year'];
        if ($year > 0) {
            $data->startdate = make_timestamp($year, 1, 1, 0, 0, 0);
            $data->enddate   = make_timestamp($year, 12, 31, 23, 59, 59);
        }

        $fullname = implode('-', $identifications);
        $data->category  = $tenant->get('category');
        $data->fullname  = $fullname;
        $data->shortname = $fullname;
        $data->idnumber  = $item['id'];
        $data->visible   = 1;

        return create_course($data);
    }

    /**
     * Create a Moodle course in a given category.
     *
     * @param array $item
     * @param company $tenant
     * @return array
     */
    private function set_course_identifications(array $item, company $tenant): array {
        return [
            'tenant' => $tenant->get('shortname'),
            'coursename' => $item['kurzbezeichnung'],
            'year' => substr($item['endTag'], 0, 4),
        ];
    }

    /**
     * Get the configured global template course (master course).
     * @param string $localcourseid
     * @return \stdClass|null
     */
    private function get_local_template_course($localcourseid): \stdClass|null {
        global $DB;

        $course = $DB->get_record('course', ['fullname' => $localcourseid]);
        if (!$course) {
            return null;
        }
        if ((int)$course->id === (int)SITEID) {
            return null;
        }
        return $course;
    }

    /**
     * Get the configured global template course (master course).
     *
     * @return \stdClass|null
     */
    private function get_global_template_course(): \stdClass|null {
        global $DB;

        $globalmasterid = (int)get_config('local_lehrgaengeapi', 'targetcourseid');
        if ($globalmasterid <= 0) {
            return null;
        }

        $course = $DB->get_record('course', ['id' => $globalmasterid]);
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
}
