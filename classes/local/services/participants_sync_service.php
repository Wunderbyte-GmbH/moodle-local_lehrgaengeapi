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

use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint_interface;
use local_lehrgaengeapi\local\users\users_creator;

/**
 * Lehrgaenge sync service.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participants_sync_service {
    /** @var lehrgaenge_endpoint_interface */
    private lehrgaenge_endpoint_interface $endpoint;

    /** @var users_creator */
    private users_creator $usercreator;

    /** @var participant_course_assigner */
    private participant_course_assigner $assigner;

    /**
     * Constructor.
     *
     * @param lehrgaenge_endpoint_interface $endpoint Endpoint wrapper.
     * @param users_creator $usercreator User creator.
     * @param participant_course_assigner $assigner Participant course assigner.
     */
    public function __construct(
        lehrgaenge_endpoint_interface $endpoint,
        users_creator $usercreator,
        participant_course_assigner $assigner
    ) {
        $this->endpoint = $endpoint;
        $this->usercreator = $usercreator;
        $this->assigner = $assigner;
    }

    /**
     * Sync participants for a single Lehrgang into a Moodle course.
     *
     * For now this only ensures users exist. Enrolment/status actions follow in next steps.
     *
     * @param string $externalid External Lehrgang ID.
     * @param int $courseid Moodle course ID.
     * @param array $course Lehrgang payload (decoded).
     * @param array $tenant Tenant payload (decoded).
     * @return array{users:array,assignments:array}
     * @throws \Throwable
     */
    public function sync_for_course(string $externalid, int $courseid, array $course, array $tenant): array {
        $delayms = (int)get_config('local_lehrgaengeapi', 'requestdelayms');
        if ($delayms > 0) {
            usleep($delayms * 1000);
        }

        $participants = $this->endpoint->participants($tenant, $externalid);

        if (
            !is_array($participants) ||
            empty($participants)
        ) {
            return [
                'users' => [
                    'created' => 0,
                    'existing' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ],
                'assignments' => [
                    'created' => 0,
                    'existing' => 0,
                    'skipped' => 0,
                    'total' => 0,
                ],
            ];
        }
        $usersummary = $this->usercreator->create($participants);
        $assignsummary = $this->assigner->assign($participants, $courseid, $course);

        return [
            'users' => $usersummary,
            'assignments' => $assignsummary,
        ];
    }
}
