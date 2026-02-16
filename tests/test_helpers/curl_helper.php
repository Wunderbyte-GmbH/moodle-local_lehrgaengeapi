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

namespace local_lehrgaengeapi\tests\test_helpers;

/**
 * Tests for api response.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curl_helper {
    /**
     * Fake curl implementation for unit tests.
     * @param string $body
     * @param int $httpcode
     * @param string $rawheaders
     * We extend \curl so api_client can type-hint \curl and we can still inject.
     */
    public function make_fake_curl(string $body, int $httpcode, string $rawheaders = ''): \curl {
        return new class ($body, $httpcode, $rawheaders) extends \curl {
            /** @var string */
            private string $body;

            /** @var int */
            private int $httpcode;

            /** @var string */
            private string $rawheaders;

            /** @var array<int,string> Captured headers set by api_client */
            public array $setheaders = [];

            /** @var string Last requested URL */
            public string $lasturl = '';

            /**
             * Constructor.
             *
             * @param string $body Response body to return.
             * @param int $httpcode HTTP code to report via get_info().
             * @param string $rawheaders Raw response headers to return via getResponse().
             */
            public function __construct(string $body, int $httpcode, string $rawheaders) {
                $this->body = $body;
                $this->httpcode = $httpcode;
                $this->rawheaders = $rawheaders;
            }

            /**
             * Capture headers set by api_client.
             *
             * @param string $header Header line like "X-MoodleAuthToken: abc".
             * @return void
             */
            public function setheader($header): void {
                $this->setheaders[] = (string)$header;
            }

            /**
             * Fake GET.
             *
             * @param string $url URL.
             * @param array<mixed> $params Params (ignored).
             * @param array<mixed> $options Options (ignored).
             * @return string
             */
            public function get($url, $params = [], $options = []): string {
                $this->lasturl = (string)$url;
                return $this->body;
            }

            /**
             * Return fake curl info.
             *
             * @return array<string,mixed>
             */
            public function get_info(): array {
                return ['http_code' => $this->httpcode];
            }

            /**
             * Return raw response headers.
             *
             * @return string
             */
            public function getresponse(): string {
                return $this->rawheaders;
            }
        };
    }
}
