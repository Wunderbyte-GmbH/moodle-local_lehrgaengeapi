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
 * Value object holding an API response.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\api;

/**
 * Value object holding an API response.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api_response {
    /** @var int|null HTTP status code */
    public readonly int $status;

    /** @var string|null Raw response body */
    public readonly string $body;

    /** @var array<string,string> Response headers */
    public readonly array $headers;

    /**
     * Constructor.
     *
     * @param int $status HTTP status code.
     * @param string $body Raw response body.
     * @param array<string,string> $headers Response headers.
     */
    public function __construct(
        $status,
        $body,
        $headers = []
    ) {
        $this->status = $status;
        $this->body = $body;
        $this->headers = $headers;
    }

    /**
     * Decode response as associative array.
     *
     * @return array<mixed>
     * @throws \JsonException
     */
    public function json_array(): array {
        $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get header value by name (case-insensitive).
     *
     * @param string $name Header name.
     * @return string|null
     */
    public function get_header(string $name): ?string {
        $lower = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lower) {
                return $v;
            }
        }
        return null;
    }
}
