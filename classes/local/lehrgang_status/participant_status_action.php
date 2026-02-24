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
 * Immutable action decision for participant status.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\lehrgang_status;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Immutable action decision for participant status.
 * @package local_lehrgaengeapi
 * @author Jacob Viertel
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_status_action {
    /** @var bool */
    private bool $shouldassign;

    /** @var bool */
    private bool $shouldcomplete;

    public function __construct(bool $shouldassign, bool $shouldcomplete) {
        $this->shouldassign = $shouldassign;
        $this->shouldcomplete = $shouldcomplete;
    }

    public static function noop(): self {
        return new self(false, false);
    }

    public static function assign_only(): self {
        return new self(true, false);
    }

    public static function assign_and_complete(): self {
        return new self(true, true);
    }

    public function should_assign(): bool {
        return $this->shouldassign;
    }

    public function should_complete(): bool {
        return $this->shouldcomplete;
    }
}