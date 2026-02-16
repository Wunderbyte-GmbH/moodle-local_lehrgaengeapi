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

        $data = $endpoint->list();

        $this->assertSame([['id' => '1'], ['id' => '2']], $data);
        $this->assertStringContainsString('/lehrgaenge', $fake->lasturl);
    }

    /**
     * Ensure list() includes searchCriteria when array is provided (JSON encoded).
     *
     * @covers \local_lehrgaengeapi\api\endpoints\lehrgaenge_endpoint::list
     */
    public function test_list_encodes_searchcriteria_array(): void {
        $curlhelper = new curl_helper();
        $fake = $curlhelper->make_fake_curl('[]', 200, "HTTP/1.1 200 OK\r\n\r\n");

        $client = new api_client(
            'https://example.test/rest/services/moodle-services',
            new token_authenticator('tkn'),
            30,
            $fake
        );

        $endpoint = new lehrgaenge_endpoint($client);

        $endpoint->list(['beschreibung' => 'Atemschutz', 'versteckeGeschlosseneLehrgaenge' => true]);

        $this->assertStringContainsString('/lehrgaenge?', $fake->lasturl);
        $this->assertStringContainsString('searchCriteria=', $fake->lasturl);

        // Ensure it is JSON-ish (encoded). We check for a key fragment in URL-encoded form.
        $this->assertTrue(
            (bool)preg_match('/searchCriteria=.*beschreibung/', $fake->lasturl),
            'Expected searchCriteria to contain JSON encoded "beschreibung".'
        );
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

        $data = $endpoint->get_by_id('abc');

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

        $data = $endpoint->participants('xyz');

        $this->assertSame([['id' => 'p1']], $data);
        $this->assertStringContainsString('/lehrgaenge/xyz/teilnehmer', $fake->lasturl);
    }
}
