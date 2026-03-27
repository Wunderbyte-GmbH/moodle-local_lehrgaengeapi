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
 * Wrapper for Lehrgaenge-related endpoints.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\api\endpoints;

use local_lehrgaengeapi\api\api_client;
use local_lehrgaengeapi\api\exceptions\api_exception;

/**
 * Wrapper for Lehrgaenge-related endpoints.
 *
 * Provides a typed facade over raw API paths:
 * - GET /lehrgaenge
 * - GET /lehrgaenge/{id}
 * - GET /lehrgaenge/{id}/teilnehmer
 * - GET /lehrgaenge/{id}/teilnehmer-extern/{teilnehmerId}
 *
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_endpoint implements lehrgaenge_endpoint_interface {
    /**
     * API client.
     * @var api_client
     */
    private api_client $client;

    /**
     * Constructor.
     * @param api_client $client API client.
     */
    public function __construct(api_client $client) {
        $this->client = $client;
    }

    /**
     * Receive all courses assigned to the respective organisation.
     *
     * OpenAPI: GET /lehrgaenge?searchCriteria=<object>
     *
     * NOTE: The OpenAPI describes `searchCriteria` as an object in the query string.
     * Many APIs encode such objects as JSON string.
     * We therefore accept either:
     * - array $searchcriteria (will be JSON encoded)
     * - string $searchcriteria (sent as-is)
     * - null (no filter)
     *
     * @param array $tenant Tenant information.
    * @param array<string,mixed>|string|null $searchcriteria Search criteria as array or pre-encoded string.
     * @return array<mixed> List of Lehrgang objects (decoded).
     * @throws api_exception
     * @throws \JsonException
     */
    public function list($tenant, $searchcriteria = null): array {
        $query = [];
        if (is_array($searchcriteria)) {
            $query['searchCriteria'] = json_encode($searchcriteria, JSON_THROW_ON_ERROR);
        } else if (is_string($searchcriteria) && $searchcriteria !== '') {
            $query['searchCriteria'] = $searchcriteria;
        }
        $response = $this->client->get('/lehrgaenge', $tenant, $query);
        return $response->json_array();
    }

    /**
     * Receive a course by id.
     *
     * OpenAPI: GET /lehrgaenge/{id}
     *
     * @param array $tenant Tenant information.
     * @param string $id Lehrgang ID.
     * @return array<string,mixed> Lehrgang object (decoded).
     * @throws api_exception
     * @throws \JsonException
     */
    public function get_by_id($tenant, string $id): array {
        $response = $this->client->get('/lehrgaenge/' . rawurlencode($id), $tenant);
        $data = $response->json_array();
        return is_array($data) ? $data : [];
    }

    /**
     * Receive all participants of a course.
     *
     * OpenAPI: GET /lehrgaenge/{id}/teilnehmer
     *
     * @param array $tenant Tenant information.
     * @param string $id Lehrgang ID.
     * @return array<mixed> List of LehrgangTeilnehmer objects (decoded).
     * @throws api_exception
     * @throws \JsonException
     */
    public function participants($tenant, string $id): array {
        $response = $this->client->get('/lehrgaenge/' . rawurlencode($id) . '/teilnehmer', $tenant);
        return $response->json_array();
    }

    /**
     * Receive a participant of a course (external participant endpoint).
     *
     * OpenAPI: GET /lehrgaenge/{id}/teilnehmer-extern/{teilnehmerId}
     *
     * @param array $tenant Tenant information.
     * @param string $id Lehrgang ID.
     * @param string $teilnehmerid Participant ID.
     * @return array<string,mixed> LehrgangTeilnehmer object (decoded).
     * @throws api_exception
     * @throws \JsonException
     */
    public function participant_extern($tenant, string $id, string $teilnehmerid): array {
        $path = '/lehrgaenge/' . rawurlencode($id) . '/teilnehmer-extern/' . rawurlencode($teilnehmerid);
        $response = $this->client->get($path, $tenant);
        $data = $response->json_array();
        return is_array($data) ? $data : [];
    }
}
