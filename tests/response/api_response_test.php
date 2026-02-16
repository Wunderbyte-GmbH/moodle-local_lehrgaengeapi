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

namespace local_lehrgaengeapi\response;

use local_lehrgaengeapi\api\api_response;

/**
 * Tests for api response.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_response_test extends \advanced_testcase {
    /**
     * Test json_array decoding.
     *
     * @covers \local_lehrgaengeapi\api\api_response::json_array
     */
    public function test_json_array_decodes(): void {
        $resp = new api_response(200, '{"a":1,"b":"x"}', []);

        $data = $resp->json_array();

        $this->assertSame(['a' => 1, 'b' => 'x'], $data);
    }

    /**
     * Test get_header is case-insensitive.
     *
     * @covers \local_lehrgaengeapi\api\api_response::get_header
     */
    public function test_get_header_case_insensitive(): void {
        $resp = new api_response(200, 'ok', ['X-Rate-Limit-Remaining' => '7']);

        $this->assertSame('7', $resp->get_header('x-rate-limit-remaining'));
        $this->assertSame('7', $resp->get_header('X-Rate-Limit-Remaining'));
        $this->assertSame('7', $resp->get_header('X-RATE-LIMIT-REMAINING'));
        $this->assertNull($resp->get_header('Does-Not-Exist'));
    }
}
