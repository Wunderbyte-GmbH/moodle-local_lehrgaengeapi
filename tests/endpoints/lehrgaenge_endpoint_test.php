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
 * Tests for api response.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\endpoints;

use local_lehrgaengeapi\api\api_client;
use local_lehrgaengeapi\api\auth\token_authenticator;
use local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint;
use local_lehrgaengeapi\tests\test_helpers\curl_helper;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../test_helpers/curl_helper.php');

/**
 * Tests for api response.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lehrgaenge_endpoint_test extends \advanced_testcase {
    /**
     * Ensure list() calls /lehrgaenge and returns decoded array.
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::list
     */
    public function test_list_calls_correct_path_and_decodes(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('[{"id":"1"},{"id":"2"}]', 200, "HTTP/1.1 200 OK\r\n\r\n");

        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $endpoint = new lehrgaenge_endpoint($client);

        $data = $endpoint->list([]);

        $this->assertSame([['id' => '1'], ['id' => '2']], $data);
        $this->assertStringContainsString('/lehrgaenge', $fake->lasturl);
    }

    /**
     * Ensure list() sends array searchcriteria as flat top-level query params, not
     * wrapped in a searchCriteria=<json> parameter - confirmed via a live HTTP 400
     * that the real API rejects the wrapped form.
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::list
     */
    public function test_list_sends_searchcriteria_array_as_flat_params(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('[]', 200, "HTTP/1.1 200 OK\r\n\r\n");

        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $endpoint = new lehrgaenge_endpoint($client);

        $endpoint->list([], ['lehrgangVon' => '2024-01-01', 'lehrgangBis' => '2024-12-31']);

        $this->assertStringContainsString('/lehrgaenge?', $fake->lasturl);
        $this->assertStringNotContainsString('searchCriteria=', $fake->lasturl);
        $this->assertStringContainsString('lehrgangVon=2024-01-01', $fake->lasturl);
        $this->assertStringContainsString('lehrgangBis=2024-12-31', $fake->lasturl);
    }

    /**
     * Ensure list() still wraps a pre-encoded string searchcriteria under the literal
     * searchCriteria= parameter, for API paths that genuinely expect that shape.
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::list
     */
    public function test_list_wraps_string_searchcriteria(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('[]', 200, "HTTP/1.1 200 OK\r\n\r\n");

        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $endpoint = new lehrgaenge_endpoint($client);

        $endpoint->list([], 'raw-precomputed-value');

        $this->assertStringContainsString('searchCriteria=raw-precomputed-value', $fake->lasturl);
    }

    /**
     * Ensure get_by_id() calls /lehrgaenge/{id}.
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::get_by_id
     */
    public function test_get_by_id_calls_correct_path(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('{"id":"abc"}', 200, "HTTP/1.1 200 OK\r\n\r\n");

        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $endpoint = new lehrgaenge_endpoint($client);

        $data = $endpoint->get_by_id([], 'abc');

        $this->assertSame(['id' => 'abc'], $data);
        $this->assertStringContainsString('/lehrgaenge/abc', $fake->lasturl);
    }

    /**
     * Ensure participants() calls /lehrgaenge/{id}/teilnehmer.
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::participants
     */
    public function test_participants_calls_correct_path(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('[{"id":"p1"}]', 200, "HTTP/1.1 200 OK\r\n\r\n");

        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $endpoint = new lehrgaenge_endpoint($client);

        $data = $endpoint->participants([], 'xyz');

        $this->assertSame([['id' => 'p1']], $data);
        $this->assertStringContainsString('/lehrgaenge/xyz/teilnehmer', $fake->lasturl);
    }
}
