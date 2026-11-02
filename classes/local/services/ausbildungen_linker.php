<?php
// (Standard-GPL-Header)

/**
 * Links a user's completed Ausbildungen to the matching _alt completion courses.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\services;

use local_lehrgaengeapi\local\course\alt_course_finder;
use local_lehrgaengeapi\local\course\course_type_resolver;
use local_lehrgaengeapi\local\lehrgang_status\abgemeldet_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\angemeldet_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\bestanden_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\noop_participant_status_handler;
use local_lehrgaengeapi\local\lehrgang_status\participant_status_handler_resolver;
use local_lehrgaengeapi\local\repository\usertenant_repository;
use local_lehrgaengeapi\local\users\ausbildungen_profile_field;

/**
 * Links a user's completed Ausbildungen to the matching _alt completion courses.
 *
 * Search order per Ausbildung: the user's remembered tenants in order, then HLFS.
 * Tenant abbreviation and IOMAD company code are identical by definition (see tenants docs).
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ausbildungen_linker {
    /** @var string Enrolled and completed in an _alt course of one of the user's tenants. */
    public const OUTCOME_LINKED_OWN = 'linked_own';
    /** @var string Enrolled and completed in the HLFS _alt course (fallback). */
    public const OUTCOME_LINKED_HLFS = 'linked_hlfs';
    /** @var string The user was already enrolled in the target course. */
    public const OUTCOME_ALREADY = 'already';
    /** @var string No matching _alt course in any tenant nor HLFS. */
    public const OUTCOME_NOTFOUND = 'notfound';
    /** @var string Status not completed yet (e.g. TEILNAHMEWUNSCH, ANGEMELDET). */
    public const OUTCOME_STATUSOPEN = 'statusopen';
    /** @var string Status final but not completed (e.g. FEHLT_*, NICHT_BESTANDEN, ABGEBROCHEN). */
    public const OUTCOME_NOTCOMPLETED = 'notcompleted';
    /** @var string No kurzBezeichnung, so no course type. */
    public const OUTCOME_NOTYPE = 'notype';
    /** @var string Course found, but enrolment failed. */
    public const OUTCOME_ERROR = 'error';

    /** @var string Tenant used as last fallback. */
    private const HLFS = 'HLFS';

    /** @var string Status handed to the assigner: always the completing one, never an unassign. */
    private const COMPLETING_STATUS = 'BESTANDEN';

    /** @var course_type_resolver */
    private course_type_resolver $types;

    /** @var alt_course_finder */
    private alt_course_finder $finder;

    /** @var usertenant_repository */
    private usertenant_repository $tenants;

    /** @var participant_course_assigner */
    private participant_course_assigner $assigner;

    /** @var participant_status_handler_resolver */
    private participant_status_handler_resolver $statusresolver;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->types = new course_type_resolver();
        $this->finder = new alt_course_finder();
        $this->tenants = new usertenant_repository();
        $this->assigner = new participant_course_assigner();
        $this->statusresolver = new participant_status_handler_resolver(
            new angemeldet_participant_status_handler(),
            new bestanden_participant_status_handler(),
            new noop_participant_status_handler(),
            new abgemeldet_participant_status_handler()
        );
    }

    /**
     * Link all Ausbildungen of one user.
     *
     * @param int $userid Moodle user id.
     * @return array[] One report row per Ausbildung, in the order stored in the profile field.
     */
    public function link_user(int $userid): array {
        $ausbildungen = $this->load_ausbildungen($userid);
        if (!$ausbildungen) {
            return [];
        }

        $tenants = $this->tenants->get_tenants($userid);
        $externalid = $this->get_externalid($userid);

        $rows = [];
        foreach ($ausbildungen as $ausbildung) {
            $rows[] = $this->link_one($externalid, $tenants, $ausbildung);
        }
        return $rows;
    }

    /**
     * Link one Ausbildung and describe what happened.
     *
     * @param string|null $externalid Florix participant id of the user.
     * @param string[] $tenants Remembered tenants of the user.
     * @param array $ausbildung One entry of the profile field.
     * @return array Report row.
     */
    private function link_one(?string $externalid, array $tenants, array $ausbildung): array {
        $row = [
            'tenantabbr' => $tenants[0] ?? null,
            'bezeichnung' => (string)($ausbildung['bezeichnung'] ?? ''),
            'kurzbezeichnung' => (string)($ausbildung['kurzBezeichnung'] ?? ''),
            'von' => (string)($ausbildung['von'] ?? ''),
            'bis' => (string)($ausbildung['bis'] ?? ''),
            'apistatus' => (string)($ausbildung['status'] ?? ''),
            'outcome' => self::OUTCOME_NOTFOUND,
            'courseid' => null,
            'message' => null,
        ];

        $statusoutcome = $this->classify_status($row['apistatus']);
        if ($statusoutcome !== null) {
            $row['outcome'] = $statusoutcome;
            return $row;
        }

        $type = $this->types->resolve($row['kurzbezeichnung'], $row['bezeichnung']);
        if ($type === '') {
            $row['outcome'] = self::OUTCOME_NOTYPE;
            return $row;
        }

        [$course, $tenant] = $this->find_course($tenants, $type);
        if (!$course) {
            return $row;
        }
        $row['courseid'] = (int)$course->id;
        $row['tenantabbr'] = $tenant;

        if ($externalid === null) {
            $row['outcome'] = self::OUTCOME_ERROR;
            $row['message'] = get_string('linkerrornomapping', 'local_lehrgaengeapi');
            return $row;
        }

        try {
            $report = $this->assigner->assign(
                [['id' => $externalid, 'status' => self::COMPLETING_STATUS]],
                (int)$course->id,
                ['_skipgroupassignment' => true]
            );
        } catch (\Throwable $e) {
            $row['outcome'] = self::OUTCOME_ERROR;
            $row['message'] = $e->getMessage();
            return $row;
        }

        if (!empty($report['enrolled'])) {
            $row['outcome'] = in_array($tenant, $tenants, true) ? self::OUTCOME_LINKED_OWN : self::OUTCOME_LINKED_HLFS;
        } else if (!empty($report['alreadyenrolled'])) {
            $row['outcome'] = self::OUTCOME_ALREADY;
        } else {
            $row['outcome'] = self::OUTCOME_ERROR;
            $row['message'] = get_string('linkerrornomanualenrol', 'local_lehrgaengeapi');
        }
        return $row;
    }

    /**
     * Decide whether an Ausbildung counts as completed.
     *
     * @param string $status Status of the Ausbildung.
     * @return string|null Null if completed, otherwise the outcome to report.
     */
    private function classify_status(string $status): ?string {
        $handler = $this->statusresolver->resolve($status);
        if ($handler instanceof bestanden_participant_status_handler) {
            return null;
        }
        // FEHLT_ENTSCHULDIGT is a no-op for the import, but for an Ausbildung it is final.
        if ($handler instanceof abgemeldet_participant_status_handler || str_contains(strtoupper($status), 'FEHLT')) {
            return self::OUTCOME_NOTCOMPLETED;
        }
        return self::OUTCOME_STATUSOPEN;
    }

    /**
     * Find the _alt course: remembered tenants in order, then HLFS.
     *
     * @param string[] $tenants Remembered tenants.
     * @param string $type Course type.
     * @return array{0:?\stdClass,1:?string} Course and the tenant it was found in.
     */
    private function find_course(array $tenants, string $type): array {
        $candidates = $tenants;
        if (!in_array(self::HLFS, $candidates, true)) {
            $candidates[] = self::HLFS;
        }
        foreach ($candidates as $tenant) {
            $course = $this->finder->find($tenant, $type);
            if ($course) {
                return [$course, $tenant];
            }
        }
        return [null, null];
    }

    /**
     * Read the Ausbildungen from the profile field.
     *
     * @param int $userid
     * @return array[] Entries that are arrays; empty if none or the field is missing.
     */
    private function load_ausbildungen(int $userid): array {
        global $DB;

        $fieldid = ausbildungen_profile_field::get_fieldid();
        if ($fieldid === null) {
            return [];
        }
        $data = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($data === false || $data === '') {
            return [];
        }
        $decoded = json_decode((string)$data, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * Florix participant id of a user, needed by the assigner.
     *
     * @param int $userid
     * @return string|null
     */
    private function get_externalid(int $userid): ?string {
        global $DB;
        $externalid = $DB->get_field(
            'local_lehrgaengeapi_usermap',
            'externalinitialid',
            ['userid' => $userid],
            IGNORE_MULTIPLE
        );
        return $externalid === false ? null : (string)$externalid;
    }
}
