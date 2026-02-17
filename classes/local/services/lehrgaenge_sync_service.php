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
 * Lehrgaenge sync service.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

use stdClass;
use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\repository\coursemap_repository;

/**
 * Lehrgaenge sync service.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_sync_service {
    /** @var lehrgaenge_endpoint_interface */
    private lehrgaenge_endpoint_interface $endpoint;

    /** @var coursemap_repository */
    private coursemap_repository $coursemap;

    /**
     * Constructor.
     *
     * @param lehrgaenge_endpoint_interface $endpoint Endpoint wrapper.
     * @param coursemap_repository $coursemap Course mapping repo.
     */
    public function __construct(lehrgaenge_endpoint_interface $endpoint, coursemap_repository $coursemap) {
        $this->endpoint = $endpoint;
        $this->coursemap = $coursemap;
    }

    /**
     * Sync all Lehrgaenge.
     *
     * @return array{created:int,skipped:int,total:int}
     * @throws \Throwable
     */
    public function sync(): array {
        $items = $this->endpoint->list();
        $total = is_array($items) ? count($items) : 0;

        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $externalid = isset($item['id']) ? (string)$item['id'] : '';
            if ($externalid === '') {
                continue;
            }

            $result = $this->sync_one($externalid, $item);
            if ($result === 'created') {
                $created++;
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $total,
        ];
    }

    /**
     * Sync a single Lehrgang to a Moodle course.
     *
     * @param string $externalid External Lehrgang.id.
     * @param array $lehrgang Lehrgang payload (decoded).
     * @return string
     * @throws \Throwable
     */
    private function sync_one(string $externalid, array $lehrgang): string {
        global $DB;

        // Ensure mapping row exists.
        $map = $this->coursemap->ensure($externalid);

        // Resolve course: mapping -> by idnumber.
        $course = null;

        if (!empty($map->courseid)) {
            $course = $DB->get_record('course', ['id' => (int)$map->courseid]);
        }

        if (!$course) {
            $course = $DB->get_record('course', ['idnumber' => $externalid]);
        }

        if ($course) {
            // Persist mapping if it was missing courseid, but DO NOT update the course.
            $this->coursemap->set_courseid($externalid, (int)$course->id);
            return 'skipped';
        }

        // Create new course.
        $createdcourse = $this->create_course_from_lehrgang($externalid, $lehrgang);

        // Store mapping.
        $this->coursemap->set_courseid($externalid, (int)$createdcourse->id);

        return 'created';
    }

    /**
     * Create a Moodle course from Lehrgang payload.
     *
     * Uses site default course category.
     *
     * @param string $externalid External Lehrgang.id.
     * @param array $lehrgang Lehrgang payload.
     * @return stdClass Created course record.
     * @throws \Throwable
     */
    private function create_course_from_lehrgang(string $externalid, array $lehrgang): stdClass {
        $categoryid = $this->get_default_categoryid();
        $record = (object)$this->desired_course_fields($externalid, $lehrgang, $categoryid);
        return create_course($record);
    }

    /**
     * Get the site default category id.
     *
     * @return int
     */
    private function get_default_categoryid(): int {
        $default = (int)get_config('moodlecourse', 'defaultcategory');
        return $default > 0 ? $default : 1;
    }

    /**
     * Compute the course fields used when creating a new course.
     *
     * @param string $externalid External Lehrgang.id.
     * @param array $lehrgang Lehrgang payload.
     * @param int $categoryid Target category id.
     * @return array
     */
    private function desired_course_fields(string $externalid, array $lehrgang, int $categoryid): array {
        $fullname = isset($lehrgang['bezeichnung']) ? trim((string)$lehrgang['bezeichnung']) : '';
        $shortname = isset($lehrgang['kurzbezeichnung']) ? trim((string)$lehrgang['kurzbezeichnung']) : '';

        if ($fullname === '') {
            $fullname = 'Lehrgang ' . $externalid;
        }
        if ($shortname === '') {
            $shortname = 'LG-' . $externalid;
        }

        $shortname = mb_substr($shortname, 0, 100);

        return [
            'category' => $categoryid,
            'fullname' => $fullname,
            'shortname' => $shortname,
            'idnumber' => $externalid,
            'visible' => 1,
        ];
    }
}
