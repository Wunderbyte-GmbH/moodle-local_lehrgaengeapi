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
        'F/B-mobBSA-Hessen' => 'F-BSA',
        'F/B-BSA-Sem.' => 'F/B-BSA-Sem.',
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
     * @param array<string,mixed>|string|null $searchcriteria Optional filter,
     *        e.g. ['lehrgangVon' => 'YYYY-01-01', 'lehrgangBis' => 'YYYY-12-31'] for a manual year import.
     * @return array{created:int,skipped:int,total:int,failed:array,userreport:array,unfoundhlfscourses:array}
     * @throws \Throwable
     */
    public function sync($tenant, $searchcriteria = null): array {
        global $DB;
        if (empty($tenant['apikey'])) {
            return [
                'created' => 0,
                'skipped' => 0,
                'total' => 0,
                'failed' => [],
                'userreport' => [],
                'unfoundhlfscourses' => [],
            ];
        }
        $items = $this->endpoint->list($tenant, $searchcriteria);
        $total = is_array($items) ? count($items) : 0;

        $created = 0;
        $skipped = 0;
        // Participant-fetch failures for individual Lehrgaenge (e.g. an unrecoverable
        // API-side data issue on a single old record) must not abort the whole run -
        // the course itself is still created/mapped, only the participant sync for that
        // one Lehrgang is skipped and reported.
        $failed = [];

        $hlfscompany = $this->get_main_company();
        $company = $this->tenantcreator->get_tenant($tenant);
        $userreport = [];
        $unfoundhlfscourses = [];
        $participantdetails = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                continue;
            }
            $ishlfs = !empty($item['ort'])
                && strpos((string)$item['ort'], 'HLFS') === 0
                && $hlfscompany;
            $currentcompany = $ishlfs ? $hlfscompany : $company;
            if (!$currentcompany) {
                $skipped++;
                continue;
            }

            $externalid = (string)($item['id'] ?? '');
            if ($externalid === '') {
                $skipped++;
                continue;
            }

            // Check if course exists by naming convention (current year, or the year-less _alt course).
            $identifications = $this->set_course_identifications($item, $currentcompany);
            $shortname = implode('-', $identifications);
            $existing = $DB->get_record('course', ['shortname' => $shortname], '*', IGNORE_MISSING);
            if (!$existing) {
                $altshortname = $this->resolve_alt_shortname_for_past_course($identifications, $ishlfs);
                if ($altshortname !== null) {
                    $existing = $DB->get_record('course', ['shortname' => $altshortname], '*', IGNORE_MISSING);
                    if (!$existing) {
                        // Completion courses created before the year was dropped from the
                        // naming convention still carry it (e.g. TENANT-NAME-26_alt). They
                        // stay in use, so participants go there instead of into a duplicate.
                        $existing = $this->find_legacy_alt_course($identifications);
                    }
                }
            }

            if (!$existing) {
                // A course for this Lehrgang may exist under a name none of the conventions
                // above produce. Creating another one would fail on the taken idnumber and
                // abort the whole tenant, so reuse it instead.
                $existing = $DB->get_record('course', ['idnumber' => $externalid], '*', IGNORE_MULTIPLE);
            }

            if ($existing) {
                // Backfill the Florix id on courses found by shortname that have none yet.
                // Completion (_alt) courses stay without one, they serve many Lehrgaenge.
                if (
                    (string)$existing->idnumber === ''
                    && !str_ends_with((string)$existing->shortname, '_alt')
                    && !$DB->record_exists('course', ['idnumber' => $externalid])
                ) {
                    $DB->set_field('course', 'idnumber', $externalid, ['id' => $existing->id]);
                }
                $this->coursemap->set_courseid($externalid, (int)$existing->id);
                try {
                    $userreport[$existing->id] = $this->split_participant_details(
                        $this->participantssync->sync_for_course(
                            $externalid,
                            (int)$existing->id,
                            $this->build_assignment_course_payload($item, (string)$existing->shortname),
                            $tenant
                        ),
                        $externalid,
                        (int)$existing->id,
                        $participantdetails
                    );
                } catch (\Throwable $e) {
                    $failed[] = ['id' => $externalid, 'error' => $e->getMessage()];
                }
                $skipped++;
                continue;
            }

            try {
                $course = $this->coursecreator->create($currentcompany, $item, $identifications, $ishlfs);
            } catch (\Throwable $e) {
                // A single Lehrgang that cannot be created must never abort the tenant -
                // the remaining Lehrgaenge are still processed and the counters survive.
                $failed[] = ['id' => $externalid, 'error' => $e->getMessage()];
                $skipped++;
                continue;
            }

            if (!$course) {
                if ($ishlfs) {
                    $unfoundhlfscourses[$externalid] = [
                        'externalid' => $externalid,
                        'sourceshortname' => $shortname,
                        'shortname' => $this->resolve_alt_shortname_for_past_course($identifications, true),
                        'bezeichnung' => (string)($item['bezeichnung'] ?? ''),
                        'kurzbezeichnung' => (string)($item['kurzbezeichnung'] ?? ''),
                        'ort' => $item['ort'],
                    ];
                }
                $skipped++;
                continue;
            }
            $currentcompany->add_course($course);
            try {
                $userreport[$course->id] = $this->split_participant_details(
                    $this->participantssync->sync_for_course(
                        $externalid,
                        (int)$course->id,
                        $this->build_assignment_course_payload($item, (string)$course->shortname),
                        $tenant
                    ),
                    $externalid,
                    (int)$course->id,
                    $participantdetails
                );
            } catch (\Throwable $e) {
                $failed[] = ['id' => $externalid, 'error' => $e->getMessage()];
            }
            $this->coursemap->set_courseid($externalid, (int)$course->id);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => $total,
            'failed' => $failed,
            'userreport' => $userreport,
            'unfoundhlfscourses' => $unfoundhlfscourses,
            'participantdetails' => $participantdetails,
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
     * Get the main company in order to assign people to main courses.
     *
     * @return company|null
     */
    private function get_main_company(): company|null {
        $tenant = [
            'name' => 'Hessische Landesfeuerwehrschule',
            'abbr' => 'HLFS',
        ];
        return $this->tenantcreator->get_tenant($tenant);
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
     * Move the per-participant details out of a Lehrgang report into their own structure.
     *
     * The report is keyed by Moodle course id, so for a collection course the Lehrgaenge
     * overwrite each other there. The details are therefore keyed by course id and Florix id.
     *
     * @param array $report Result of participantssync->sync_for_course().
     * @param string $externalid External Lehrgang.id.
     * @param int $courseid Moodle course id.
     * @param array $details Collected details (by reference).
     * @return array The report without the details.
     */
    private function split_participant_details(array $report, string $externalid, int $courseid, array &$details): array {
        if (isset($report['assignments']['details'])) {
            $details[$courseid][$externalid] = $report['assignments']['details'];
            unset($report['assignments']['details']);
        }
        return $report;
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
     * Find a completion course created under the legacy <TENANT>-<NAME>-<YY>_alt convention.
     *
     * Those courses stay in use, so a past-year Lehrgang is enrolled into the existing one
     * instead of a second _alt course being created alongside it.
     *
     * @param array $identifications
     * @return stdClass|null
     */
    private function find_legacy_alt_course(array $identifications): ?stdClass {
        global $DB;

        $base = implode('-', [
            $identifications['tenant'],
            $identifications['coursename'],
        ]);

        $select = $DB->sql_like('shortname', ':shortname');
        $params = ['shortname' => $DB->sql_like_escape($base . '-') . '%' . $DB->sql_like_escape('_alt')];

        return $DB->get_record_select('course', $select, $params, '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Resolve the year-less <TENANT>-<NAME>_alt shortname if the source course year is in the past.
     *
     * Must stay in sync with course_creator::resolve_target_shortname().
     *
     * @param array $identifications
     * @return string|null
     * @param bool $always Build the name regardless of the year (HLFS collection courses).
     */
    private function resolve_alt_shortname_for_past_course(array $identifications, bool $always = false): ?string {
        $year = (string)($identifications['year'] ?? '');
        if (!$always) {
            if (!preg_match('/^\d{2}$/', $year)) {
                return null;
            }

            if ((int)$year >= (int)date('y')) {
                return null;
            }
        }

        return implode('-', [
            $identifications['tenant'],
            $identifications['coursename'],
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
