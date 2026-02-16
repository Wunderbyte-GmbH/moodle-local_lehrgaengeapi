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
 * Exeption handeling class.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\api\exceptions;

/**
 * Exeption handeling class.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {
    /** @var int|null HTTP status code */
    protected ?int $httpstatus;

    /** @var string|null Raw response body */
    protected ?string $responsebody;

    /** @var array<string,string> Response headers */
    protected array $responseheaders;

    /** @var \Throwable|null Previous exception (not passed to moodle_exception for compatibility) */
    private ?\Throwable $previous;

    /**
     * Constructor for api exceptions.
     * @param string $message Human-readable error message.
     * @param int|null $httpstatus HTTP status code if known.
     * @param string|null $responsebody Raw response body if available.
     * @param array<string,string> $responseheaders Response headers if available.
     * @param \Throwable|null $previous Previous exception.
     */
    public function __construct(
        string $message,
        ?int $httpstatus = null,
        ?string $responsebody = null,
        array $responseheaders = [],
        ?\Throwable $previous = null
    ) {
        $this->httpstatus = $httpstatus;
        $this->responsebody = $responsebody;
        $this->responseheaders = $responseheaders;
        $this->previous = $previous;

        // We use the plugin as component so Moodle can render a meaningful error page if needed.
        parent::__construct(
            'apirequestfailed',
            'local_lehrgaengeapi',
            '',
            null,
            $message
        );
    }

    /**
     * HTTP status code if available.
     *
     * @return int|null
     */
    public function get_http_status(): ?int {
        return $this->httpstatus;
    }

    /**
     * Raw response body if available.
     *
     * @return string|null
     */
    public function get_response_body(): ?string {
        return $this->responsebody;
    }

    /**
     * Response headers.
     *
     * @return array<string,string>
     */
    public function get_response_headers(): array {
        return $this->responseheaders;
    }

    /**
     * Previous exception if provided.
     *
     * @return \Throwable|null
     */
    public function get_previous_exception(): ?\Throwable {
        return $this->previous;
    }
}
