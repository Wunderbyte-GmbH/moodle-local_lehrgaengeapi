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
 * Tests for api client.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\client;

use local_lehrgaengeapi\api\api_client;
use local_lehrgaengeapi\api\auth\token_authenticator;
use local_lehrgaengeapi\api\exceptions\api_rate_limited_exception;
use local_lehrgaengeapi\api\exceptions\api_unauthorized_exception;
use local_lehrgaengeapi\tests\test_helpers\curl_helper;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../test_helpers/curl_helper.php');

/**
 * Tests for api client.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_client_test extends \advanced_testcase {
    /**
     * Successful GET returns api_response and parses headers.
     *
     * @covers \local_lehrgaengeapi\api\api_client::get
     */
    public function test_get_success(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('{"ok":true}', 200, "HTTP/1.1 200 OK\r\nX-Rate-Limit-Remaining: 9\r\n\r\n");
        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $resp = $client->get('/lehrgaenge', []);

        $this->assertSame(200, $resp->status);
        $this->assertSame(['ok' => true], $resp->json_array());
        $this->assertSame('9', $resp->get_header('x-rate-limit-remaining'));

        // Ensure auth header got applied.
        $this->assertNotEmpty($fake->setheaders);
        $this->assertStringContainsString('X-MoodleAuthToken: tkn', implode("\n", $fake->setheaders));
    }

    /**
     * 401 triggers api_unauthorized_exception.
     *
     * @covers \local_lehrgaengeapi\api\api_client::get
     */
    public function test_get_unauthorized_throws(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('{"title":"Unauthorized"}', 401, "HTTP/1.1 401 Unauthorized\r\n\r\n");
        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $this->expectException(api_unauthorized_exception::class);
        $client->get('/lehrgaenge', []);
    }

    /**
     * 429 triggers api_rate_limited_exception and Retry-After can be read.
     *
     * @covers \local_lehrgaengeapi\api\api_client::get
     */
    public function test_get_rate_limited_throws_and_retry_after(): void {
        $headers = "HTTP/1.1 429 Too Many Requests\r\nRetry-After: 2\r\n\r\n";
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('quota', 429, $headers);
        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );
        try {
            $client->get('/lehrgaenge', []);
            $this->fail('Expected api_rate_limited_exception not thrown.');
        } catch (api_rate_limited_exception $e) {
            $this->assertSame(429, $e->get_http_status());
            $this->assertSame('quota', $e->get_response_body());
            $this->assertSame(2, $e->get_retry_after_seconds());
        }
    }
}
