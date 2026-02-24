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
 * Resolves participant status handlers from raw status strings.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\lehrgang_status;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Resolves participant status handlers from raw status strings.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_status_handler_resolver {
    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $angemeldethandler;

    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $bestandenhandler;

    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $noophandler;

    public function __construct(
        participant_status_handler_interface $angemeldethandler,
        participant_status_handler_interface $bestandenhandler,
        participant_status_handler_interface $noophandler
    ) {
        $this->angemeldethandler = $angemeldethandler;
        $this->bestandenhandler = $bestandenhandler;
        $this->noophandler = $noophandler;
    }

    /**
     * Resolve handler based on raw API status.
     *
     * Examples:
     * - S018_ANGEMELDET_KREIS => angemeldet handler
     * - S084_BESTANDEN_LAND   => bestanden handler
     * - anything else         => noop
     *
     * @param string|null $rawstatus
     * @return participant_status_handler_interface
     */
    public function resolve(?string $rawstatus): participant_status_handler_interface {
        $status = $this->get_main_status_identifer(strtoupper(trim((string)$rawstatus)));

        if ($status === '') {
            return $this->noophandler;
        }

        if (strpos($status, 'ANGEMELDET') !== false) {
            return $this->angemeldethandler;
        }

        if (strpos($status, 'BESTANDEN') !== false) {
            return $this->bestandenhandler;
        }

        return $this->noophandler;
    }

    /**
     * Extract main status identifier from raw status string.
     * @param string $rawstatus
     * @return string
     */
    private function get_main_status_identifer(string $rawstatus): string {
        $parts = explode('_', $rawstatus);
        array_shift($parts); // Remove leading code (e.g. S018, S084)
        array_pop($parts); // Remove trailing context (e.g. KREIS, LAND)
        return implode('_', $parts);
    }


}