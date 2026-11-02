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
 * Tests for collection_course.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

/**
 * Tests for collection_course: recognising the Florix collection Lehrgang (users without
 * a real Lehrgang, e.g. last Lehrgang in 1999) by its Kurzbezeichnung.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class collection_course_test extends \advanced_testcase {
    /**
     * Without a saved setting the agreed default MOODLE-ALTBESTAND applies.
     *
     * @covers \local_lehrgaengeapi\local\course\collection_course::is_collection
     */
    public function test_default_kurzbezeichnung_applies_without_setting(): void {
        $this->resetAfterTest(true);
        unset_config('collectionkurzbezeichnung', 'local_lehrgaengeapi');

        $this->assertSame('MOODLE-ALTBESTAND', collection_course::DEFAULT_KURZBEZEICHNUNG);
        $this->assertTrue(collection_course::is_collection(['kurzbezeichnung' => 'MOODLE-ALTBESTAND']));
        $this->assertFalse(collection_course::is_collection(['kurzbezeichnung' => 'F-Atr']));
    }

    /**
     * Case and surrounding spaces do not matter, a part of the name does not match.
     *
     * @covers \local_lehrgaengeapi\local\course\collection_course::is_collection
     */
    public function test_match_ignores_case_and_spaces_but_not_parts(): void {
        $this->resetAfterTest(true);
        set_config('collectionkurzbezeichnung', ' Moodle-Altbestand ', 'local_lehrgaengeapi');

        $this->assertTrue(collection_course::is_collection(['kurzbezeichnung' => 'moodle-altbestand']));
        $this->assertTrue(collection_course::is_collection(['kurzbezeichnung' => '  MOODLE-ALTBESTAND  ']));
        $this->assertFalse(collection_course::is_collection(['kurzbezeichnung' => 'MOODLE-ALTBESTAND-2']));
        $this->assertFalse(collection_course::is_collection(['kurzbezeichnung' => 'ALTBESTAND']));
        $this->assertFalse(collection_course::is_collection([]));
    }

    /**
     * A custom Kurzbezeichnung replaces the default.
     *
     * @covers \local_lehrgaengeapi\local\course\collection_course::is_collection
     */
    public function test_custom_kurzbezeichnung(): void {
        $this->resetAfterTest(true);
        set_config('collectionkurzbezeichnung', 'SAMMEL', 'local_lehrgaengeapi');

        $this->assertTrue(collection_course::is_collection(['kurzbezeichnung' => 'SAMMEL']));
        $this->assertFalse(collection_course::is_collection(['kurzbezeichnung' => 'MOODLE-ALTBESTAND']));
    }

    /**
     * An empty setting switches the recognition off: every Lehrgang is a normal one.
     *
     * @covers \local_lehrgaengeapi\local\course\collection_course::is_collection
     */
    public function test_empty_setting_switches_recognition_off(): void {
        $this->resetAfterTest(true);
        set_config('collectionkurzbezeichnung', '', 'local_lehrgaengeapi');

        $this->assertFalse(collection_course::is_collection(['kurzbezeichnung' => 'MOODLE-ALTBESTAND']));
        $this->assertFalse(collection_course::is_collection(['kurzbezeichnung' => '']));
    }
}
