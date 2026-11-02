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
 * Recognises the Florix collection Lehrgang.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

/**
 * Recognises the Florix collection Lehrgang by its Kurzbezeichnung.
 *
 * Florix keeps people without a real Lehrgang (e.g. last Lehrgang in 1999) in one collection
 * Lehrgang. The import takes over its people, Ausbildungen and tenant, but creates no course.
 * @package local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class collection_course {
    /** @var string Kurzbezeichnung used while the setting was never saved. */
    public const DEFAULT_KURZBEZEICHNUNG = 'MOODLE-ALTBESTAND';

    /**
     * Whether a Lehrgang is the collection Lehrgang. An empty setting switches this off.
     *
     * @param array $item Lehrgang payload.
     * @return bool
     */
    public static function is_collection(array $item): bool {
        $configured = get_config('local_lehrgaengeapi', 'collectionkurzbezeichnung');
        if ($configured === false) {
            $configured = self::DEFAULT_KURZBEZEICHNUNG;
        }
        $configured = \core_text::strtolower(trim((string)$configured));
        if ($configured === '') {
            return false;
        }
        return \core_text::strtolower(trim((string)($item['kurzbezeichnung'] ?? ''))) === $configured;
    }
}
