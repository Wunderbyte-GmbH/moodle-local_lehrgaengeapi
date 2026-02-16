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
 * Concrete config read class.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_settings_repository implements settings_repository_interface {
    /**
     * Plugin name of the configuration.
     * @var string
     */
    private const PLUGIN = 'local_lehrgaengeapi';

    /**
     * Function to get baseurl.
     * @return string
     */
    public function get_baseurl(): string {
        return rtrim((string)get_config(self::PLUGIN, 'baseurl'), '/');
    }

    /**
     * Function to get auth token.
     * @return string
     */
    public function get_token(): string {
        return (string)get_config(self::PLUGIN, 'token');
    }

    /**
     * Function to get timeout seconds.
     * @return string
     */
    public function get_timeout_seconds(): int {
        $value = (int)get_config(self::PLUGIN, 'timeout');
        return $value > 0 ? $value : 30;
    }

    /**
     * Function to get the interval lenght between lehrgaenge-api calls.
     * @return string
     */
    public function get_interval_lehrgaenge_seconds(): int {
        $value = (int)get_config(self::PLUGIN, 'interval_lehrgaenge');
        return $value > 0 ? $value : 900;
    }

    /**
     * Function to get the interval lenght between teilnehmer-api calls.
     * @return string
     */
    public function get_interval_teilnehmer_seconds(): int {
        $value = (int)get_config(self::PLUGIN, 'interval_teilnehmer');
        return $value > 0 ? $value : 3600;
    }
}
