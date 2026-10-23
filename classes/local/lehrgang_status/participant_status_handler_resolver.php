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
    /** @var string[] Keywords of statuses that remove the participant from the course. */
    private const UNASSIGN_KEYWORDS = [
        'ABGELEHNT',
        'NICHT_',
        'ZURUECKGESTELLT',
        'STORNIERT',
        'ABGEBROCHEN',
        'TERMIN_ABGESAGT',
        'FEHLT_UNENTSCHULDIGT',
        'UEBERREGIONALER_LEHRGANG',
    ];

    /** @var string[] Keywords of statuses that enrol the participant and mark the course complete. */
    private const COMPLETE_KEYWORDS = [
        'TEILGENOMMEN',
        'BESTANDEN',
    ];

    /** @var string[] Keywords of statuses that enrol the participant. */
    private const ASSIGN_KEYWORDS = [
        'EINBERUFUNG',
        'ANGEMELDET_EIGENE',
    ];

    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $angemeldethandler;

    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $bestandenhandler;

    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $noophandler;

    /** @var participant_status_handler_interface */
    private participant_status_handler_interface $abmeldenhandler;

    /**
     * Constructor.
     * @param participant_status_handler_interface $angemeldethandler
     * @param participant_status_handler_interface $bestandenhandler
     * @param participant_status_handler_interface $noophandler
     * @param participant_status_handler_interface $abmeldenhandler
     */
    public function __construct(
        participant_status_handler_interface $angemeldethandler,
        participant_status_handler_interface $bestandenhandler,
        participant_status_handler_interface $noophandler,
        participant_status_handler_interface $abmeldenhandler
    ) {
        $this->angemeldethandler = $angemeldethandler;
        $this->bestandenhandler = $bestandenhandler;
        $this->noophandler = $noophandler;
        $this->abmeldenhandler = $abmeldenhandler;
    }

    /**
     * Resolve handler based on raw API status.
     *
     * Examples:
     * - S034_EINBERUFUNG_VERSCHICKT_KREIS => angemeldet handler
     * - S084_BESTANDEN_LAND               => bestanden handler
     * - S052_NICHT_BESTANDEN_KREIS        => abgemeldet handler
     * - anything else                     => noop
     *
     * @param string|null $rawstatus
     * @return participant_status_handler_interface
     */
    public function resolve(?string $rawstatus): participant_status_handler_interface {
        $status = $this->strip_status_code(strtoupper(trim((string)$rawstatus)));

        // Unassign rules come first, as e.g. NICHT_BESTANDEN also contains BESTANDEN.
        if ($this->contains_any($status, self::UNASSIGN_KEYWORDS)) {
            return $this->abmeldenhandler;
        }

        if ($this->contains_any($status, self::COMPLETE_KEYWORDS)) {
            return $this->bestandenhandler;
        }

        if ($this->contains_any($status, self::ASSIGN_KEYWORDS)) {
            return $this->angemeldethandler;
        }

        return $this->noophandler;
    }

    /**
     * Remove the leading status code (e.g. "S034_") from a raw status string.
     * @param string $rawstatus
     * @return string
     */
    private function strip_status_code(string $rawstatus): string {
        return preg_replace('/^S\d+_/', '', $rawstatus);
    }

    /**
     * Check whether the status contains at least one of the given keywords.
     * @param string $status
     * @param string[] $keywords
     * @return bool
     */
    private function contains_any(string $status, array $keywords): bool {
        foreach ($keywords as $keyword) {
            if (strpos($status, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
