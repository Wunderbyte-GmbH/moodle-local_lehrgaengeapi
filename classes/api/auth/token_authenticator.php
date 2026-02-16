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
 * Concrete authentication class.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\api\auth;

/**
 * Concrete authentication class.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class token_authenticator implements authenticator_interface {
    /**
     * Token used for API authentication.
     * @var string
     */
    private readonly string $token;

    /**
     * Header name used to send the token.
     * @var string
     */
    private readonly string $headername;

    /**
     * Plugin constructor.
     *
     * @param string $token Token used for API authentication.
     * @param string $headername Header name used to send the token.
     */
    public function __construct(string $token, string $headername = 'X-MoodleAuthToken') {
        $this->token = $token;
        $this->headername = $headername;
    }

    /**
     * Function to get the header for api calls.
     * @return array<string,string> HTTP headers to apply to a request.
     */
    public function get_headers(): array {
        if ($this->token === '') {
            return [];
        }
        return [
            $this->headername => $this->token,
        ];
    }
}
