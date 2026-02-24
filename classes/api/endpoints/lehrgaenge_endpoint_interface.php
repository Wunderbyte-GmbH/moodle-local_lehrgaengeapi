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
 * Interface for Lehrgaenge endpoint wrapper.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\api\endpoints;

use local_lehrgaengeapi\api\exceptions\api_exception;

/**
 * Interface for Lehrgaenge endpoint wrapper.
 *
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface lehrgaenge_endpoint_interface {
    /**
     * Receive all courses assigned to the respective organisation.
     *
     * @param array<string,mixed>|string|null $searchcriteria Search criteria as array or pre-encoded string.
     * @return array<mixed>
     * @throws api_exception
     * @throws \JsonException
     */
    public function list($searchcriteria = null): array;

    /**
     * Receive a course by id.
     *
     * @param string $id Lehrgang ID.
     * @return array<string,mixed>
     * @throws api_exception
     * @throws \JsonException
     */
    public function get_by_id(string $id): array;

    /**
     * Receive all participants of a course.
     *
     * @param string $id Lehrgang ID.
     * @return array<mixed>
     * @throws api_exception
     * @throws \JsonException
     */
    public function participants(string $id): array;

    /**
     * Receive a participant of a course (external participant endpoint).
     *
     * @param string $id Lehrgang ID.
     * @param string $teilnehmerid Participant ID.
     * @return array<string,mixed>
     * @throws api_exception
     * @throws \JsonException
     */
    public function participant_extern(string $id, string $teilnehmerid): array;
}
