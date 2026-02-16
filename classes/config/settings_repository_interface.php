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
 * Observer for given events.
 *
 * @package   local_lehrgaengeapi
 * @author    Jacob Viertel
 * @copyright   2026 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\config;

/**
 * Interface for configuration values.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface settings_repository_interface {
    /**
     * Function to get baseurl.
     * @return string
     */
    public function get_baseurl(): string;

    /**
     * Function to get auth token.
     * @return string
     */
    public function get_token(): string;

    /**
     * Function to get timeout seconds.
     * @return string
     */
    public function get_timeout_seconds(): int;

    /**
     * Function to get the interval lenght between lehrgaenge-api calls.
     * @return string
     */
    public function get_interval_lehrgaenge_seconds(): int;

    /**
     * Function to get the interval lenght between teilnehmer-api calls.
     * @return string
     */
    public function get_interval_teilnehmer_seconds(): int;
}
