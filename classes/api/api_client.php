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
 * HTTP client for the external API.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\api;

use local_lehrgaengeapi\api\auth\authenticator_interface;
use local_lehrgaengeapi\api\exceptions\api_bad_request_exception;
use local_lehrgaengeapi\api\exceptions\api_exception;
use local_lehrgaengeapi\api\exceptions\api_forbidden_exception;
use local_lehrgaengeapi\api\exceptions\api_not_found_exception;
use local_lehrgaengeapi\api\exceptions\api_rate_limited_exception;
use local_lehrgaengeapi\api\exceptions\api_server_error_exception;
use local_lehrgaengeapi\api\exceptions\api_unauthorized_exception;

/**
 * HTTP client for the external API.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_client {
    /**
     * Base URL like https://<zms>/rest/services/moodle-services
     *
     * @var string
     */
    private string $baseurl;

    /**
     * Auth header strategy.
     *
     * @var authenticator_interface
     */
    private authenticator_interface $authenticator;

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    private int $timeoutseconds;

    /**
     * Moodle curl wrapper.
     *
     * @var \curl
     */
    private \curl $curl;

    /**
     * Constructor.
     *
     * @param string $baseurl Base URL like https://<zms>/rest/services/moodle-services
     * @param authenticator_interface $authenticator Auth header strategy.
     * @param int $timeoutseconds Request timeout in seconds.
     * @param \curl|null $curl Optional curl instance (useful for tests).
     */
    public function __construct(
        string $baseurl,
        authenticator_interface $authenticator,
        int $timeoutseconds = 30,
        ?\curl $curl = null
    ) {
        global $CFG;
        $this->baseurl = $baseurl;
        $this->authenticator = $authenticator;
        $this->timeoutseconds = $timeoutseconds;
        require_once($CFG->libdir . '/filelib.php');
        $this->curl = $curl ?? new \curl();
    }

    /**
     * Perform a GET request.
     *
     * @param string $path Relative path like '/lehrgaenge'.
     * @param array $query Query params (null values are ignored).
     * @return api_response
     * @throws api_exception
     */
    public function get(string $path, array $query = []): api_response {
        $url = $this->build_url($path, $query);

        $options = [
            'CURLOPT_TIMEOUT' => $this->timeoutseconds,
        ];

        foreach ($this->authenticator->get_headers() as $name => $value) {
            $this->curl->setHeader($name . ': ' . $value);
        }

        $body = $this->curl->get($url, [], $options);

        $info = $this->curl->get_info();
        $status = (int)($info['http_code'] ?? 0);

        $rawresponse = $this->curl->getResponse() ?? '';
        if (is_array($rawresponse)) {
            // Some Moodle versions store response data as an array on failure/blocked calls.
            $rawresponse = '';
        }
        $headers = $this->parse_response_headers((string)$rawresponse);

        if ($status >= 200 && $status < 300) {
            return new api_response($status, (string)$body, $headers);
        }

        $this->throw_for_status($status, (string)$body, $headers, $url);
    }

    /**
     * Build absolute request URL.
     *
     * @param string $path Relative path like '/lehrgaenge'.
     * @param array $query Query params (null values are ignored).
     * @return string
     */
    private function build_url(string $path, array $query): string {
        $base = rtrim($this->baseurl, '/');
        $path = '/' . ltrim($path, '/');
        $url = $base . $path;

        $filtered = array_filter($query, static function ($v): bool {
            return $v !== null;
        });

        if (!empty($filtered)) {
            $url .= '?' . http_build_query($filtered);
        }

        return $url;
    }

    /**
     * Parse raw response headers into an associative array.
     *
     * Moodle's curl->getResponse() often contains raw header string and may contain multiple header blocks (redirects).
     * We take the last header block.
     *
     * @param string $raw Raw headers string.
     * @return array
     */
    private function parse_response_headers(string $raw): array {
        $headers = [];

        if ($raw === '') {
            return $headers;
        }

        // Some Moodle versions include multiple header blocks. Take the last non-empty block.
        $parts = preg_split("/\r\n\r\n|\n\n|\r\r/", $raw);
        if (is_array($parts)) {
            $parts = array_filter($parts, static function ($p): bool {
                return trim((string)$p) !== '';
            });
            $block = !empty($parts) ? trim((string)end($parts)) : '';
        } else {
            $block = trim($raw);
        }

        foreach (preg_split("/\r\n|\n|\r/", $block) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }

    /**
     * Throw a typed exception based on HTTP status code.
     *
     * @param int $status HTTP status code.
     * @param string $body Raw response body.
     * @param array $headers Response headers.
     * @param string $url Requested URL (for debugging).
     * @return never
     * @throws api_exception
     */
    private function throw_for_status(int $status, string $body, array $headers, string $url): never {
        $message = "External API request failed: HTTP {$status} for {$url}";

        if ($status === 400) {
            throw new api_bad_request_exception($message, $status, $body, $headers);
        }

        if ($status === 401) {
            throw new api_unauthorized_exception($message, $status, $body, $headers);
        }

        if ($status === 403) {
            throw new api_forbidden_exception($message, $status, $body, $headers);
        }

        if ($status === 404) {
            throw new api_not_found_exception($message, $status, $body, $headers);
        }

        if ($status === 429) {
            throw new api_rate_limited_exception($message, $status, $body, $headers);
        }

        if ($status >= 500) {
            throw new api_server_error_exception($message, $status, $body, $headers);
        }

        throw new api_exception($message, $status, $body, $headers);
    }
}
