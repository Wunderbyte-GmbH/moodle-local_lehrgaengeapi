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
 * Factory for wiring API services.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi;

use local_lehrgaengeapi\api\api_client;
use local_lehrgaengeapi\api\auth\token_authenticator;
use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint;

/**
 * HTTP client for the external API.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class factory {
    /**
     * Build Lehrgaenge endpoint from plugin config.
     *
     * Requires plugin settings:
     * - baseurl
     * - token
     * - timeoutseconds (optional)
     *
     * @return lehrgaenge_endpoint
     */
    public static function lehrgaenge_endpoint(): lehrgaenge_endpoint {
        $config = get_config('local_lehrgaengeapi');

        $baseurl = isset($config->baseurl) ? (string)$config->baseurl : '';
        $token = isset($config->token) ? (string)$config->token : '';
        $timeout = isset($config->timeoutseconds) ? (int)$config->timeoutseconds : 30;

        $auth = new token_authenticator($token);
        $client = new api_client($baseurl, $auth, $timeout);

        return new lehrgaenge_endpoint($client);
    }
}
