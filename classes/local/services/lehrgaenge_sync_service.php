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

use company;
use stdClass;
use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\repository\coursemap_repository;
use local_lehrgaengeapi\local\course\course_creator;
use local_lehrgaengeapi\local\tenants\tenant_creator;
use local_lehrgaengeapi\local\services\participants_sync_service;

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

    /** @var course_creator */
    private course_creator $coursecreator;

    /** @var participants_sync_service */
    private participants_sync_service $participantssync;

    /** @var tenant_creator */
    private tenant_creator $tenantcreator;

    /** @var array */
    private array $coursematching = [
        'F-I' => 'F-I',
        'F-II' => 'F-II',
        'F-Atr' => 'F-ATR',
        'F-Ma' => 'F-MA',
        'F/K-Sprechfunk' => 'F/K-SPF',
        'F-TH-VU' => 'F-TH-VU',
        'F/B-mobBSA-Hesser' => 'F-BSA',
        'F/B-BSA-Sem.' => 'F-BSA',
        'F-III' => 'F-III-elearn',
    ];

    /**
     * Constructor.
     *
     * @param lehrgaenge_endpoint_interface $endpoint Endpoint wrapper.
     * @param coursemap_repository $coursemap Course mapping repo.
     * @param course_creator $coursecreator Course creator.
     * @param participants_sync_service $participantssync User creator.
     * @param tenant_creator $tenantcreator Tenant creator.
     */
    public function __construct(
        lehrgaenge_endpoint_interface $endpoint,
        coursemap_repository $coursemap,
        course_creator $coursecreator,
        participants_sync_service $participantssync,
        tenant_creator $tenantcreator
    ) {
        $this->endpoint = $endpoint;
        $this->coursemap = $coursemap;
        $this->coursecreator = $coursecreator;
        $this->participantssync = $participantssync;
        $this->tenantcreator = $tenantcreator;
    }

    /**
     * Sync all Lehrgaenge.
     * @param array $tenant
     * @return array{created:int,skipped:int,total:int,userreport:array}
     * @throws \Throwable
     */
    public function sync($tenant): array {
        global $DB;
        if (empty($tenant['apikey'])) {
            return [
                'created' => 0,
                'skipped' => 0,
                'total' => 0,
                'userreport' => [],
            ];
        }
        $items = $this->endpoint->list($tenant);
        $total = is_array($items) ? count($items) : 0;

        $created = 0;
        $skipped = 0;

        $company = $this->tenantcreator->get_tenant($tenant);

        $userreport = [];
        foreach ($items as $item) {
            if (
                !is_array($item) ||
                !$company
            ) {
                $skipped++;
                continue;
            }

            $externalid = (string)($item['id'] ?? '');
            if ($externalid === '') {
                $skipped++;
                continue;
            }

            // Check if course exists by naming convention (current or CURRENTYEAR_alt).
            $identifications = $this->set_course_identifications($item, $company);
            $shortname = implode('-', $identifications);
            $existing = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);

            if (!$existing) {
                $altshortname = $this->resolve_alt_shortname_for_past_course($identifications);
                if ($altshortname !== null) {
                    $existing = $DB->get_record('course', ['shortname' => $altshortname], '*', IGNORE_MISSING);
                }
            }

            if ($existing) {
                $this->coursemap->set_courseid($externalid, (int)$existing->id);
                $userreport[$existing->id] = $this->participantssync->sync_for_course(
                    $externalid,
                    (int)$existing->id,
                    $this->build_assignment_course_payload($item, (string)$existing->shortname),
                    $tenant
                );
                $skipped++;
                continue;
            }

            $course = $this->coursecreator->create($company, $item, $identifications);
            if (!$course) {
                $skipped++;
                continue;
            }
            $company->add_course($course);
            $userreport[$course->id] = $this->participantssync->sync_for_course(
                $externalid,
                (int)$course->id,
                $this->build_assignment_course_payload($item, (string)$course->shortname),
                $tenant
            );
            $this->coursemap->set_courseid($externalid, (int)$course->id);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $total,
            'userreport' => $userreport,
        ];
    }

    /**
     * Create a Moodle course in a given category.
     *
     * @param array $item
     * @param company $company
     * @return array
     */
    private function set_course_identifications(array $item, company $company): array {
        $coursename = $this->resolve_coursename_identifier($item);
        return [
            'tenant' => $company->get('code'),
            'coursename' => $coursename,
            'year' => substr($item['endTag'], 2, 2),
        ];
    }

    /**
     * Resolve course identifier used in naming convention.
     *
     * Special case: F-III e-learning is mapped to F-IIIe.
     *
     * @param array $item
     * @return string
     */
    private function resolve_coursename_identifier(array $item): string {
        $kurzbezeichnung = (string)($item['kurzbezeichnung'] ?? '');
        if (isset($this->coursematching[$kurzbezeichnung])) {
            $kurzbezeichnung = $this->coursematching[$kurzbezeichnung];
        }
        if ($kurzbezeichnung !== 'F-III') {
            return $kurzbezeichnung;
        }

        $bezeichnung = mb_strtolower(trim((string)($item['bezeichnung'] ?? '')));
        if ($bezeichnung === '') {
            return $kurzbezeichnung;
        }

        if (mb_strpos($bezeichnung, 'e-learning') !== false) {
            return $this->coursematching['F-III'];
        }

        return $kurzbezeichnung;
    }

    /**
     * Build course payload for participant assignment with optional flags.
     *
     * @param array $item
     * @param string $shortname
     * @return array
     */
    private function build_assignment_course_payload(array $item, string $shortname): array {
        if (substr($shortname, -4) === '_alt') {
            $item['_skipgroupassignment'] = true;
        }

        return $item;
    }

    /**
     * Resolve CURRENTYEAR_alt shortname if the source course year is in the past.
     *
     * @param array $identifications
     * @return string|null
     */
    private function resolve_alt_shortname_for_past_course(array $identifications): ?string {
        $year = (string)($identifications['year'] ?? '');
        if (!preg_match('/^\d{2}$/', $year)) {
            return null;
        }

        if ((int)$year >= (int)date('y')) {
            return null;
        }

        return implode('-', [
            $identifications['tenant'],
            $identifications['coursename'],
            date('y'),
        ]) . '_alt';
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
