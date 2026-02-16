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
 * Tests for token_authenticator.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\auth;

use local_lehrgaengeapi\api\auth\token_authenticator;

/**
 * Tests for token_authenticator.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class token_authenticator_test extends \advanced_testcase {
    /**
     * Ensure header is returned with default header name.
     *
     * @covers \local_lehrgaengeapi\api\auth\token_authenticator::get_headers
     */
    public function test_get_headers_default_header(): void {
        $auth = new token_authenticator('abc123');

        $headers = $auth->get_headers();

        $this->assertArrayHasKey('X-MoodleAuthToken', $headers);
        $this->assertSame('abc123', $headers['X-MoodleAuthToken']);
    }

    /**
     * Ensure empty token returns empty header array.
     *
     * @covers \local_lehrgaengeapi\api\auth\token_authenticator::get_headers
     */
    public function test_get_headers_empty_token(): void {
        $auth = new token_authenticator('');

        $headers = $auth->get_headers();

        $this->assertSame([], $headers);
    }

    /**
     * Ensure custom header name works.
     *
     * @covers \local_lehrgaengeapi\api\auth\token_authenticator::get_headers
     */
    public function test_get_headers_custom_header(): void {
        $auth = new token_authenticator('abc123', 'X-Custom');

        $headers = $auth->get_headers();

        $this->assertArrayHasKey('X-Custom', $headers);
        $this->assertSame('abc123', $headers['X-Custom']);
    }
}
