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
 * Tests for alt_course_finder.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\local\course;

/**
 * Tests for alt_course_finder.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class alt_course_finder_test extends \advanced_testcase {
    /**
     * The current, year-less convention.
     *
     * @covers \local_lehrgaengeapi\local\course\alt_course_finder::alt_shortname
     */
    public function test_alt_shortname(): void {
        $this->assertSame('FD-F-ATR_alt', alt_course_finder::alt_shortname('FD', 'F-ATR'));
        $this->assertSame('HLFS-F/K-SPF_alt', alt_course_finder::alt_shortname('HLFS', 'F/K-SPF'));
    }

    /**
     * The year-less course is found.
     *
     * @covers \local_lehrgaengeapi\local\course\alt_course_finder::find
     */
    public function test_find_returns_current_convention(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR_alt']);

        $found = (new alt_course_finder())->find('FD', 'F-ATR');

        $this->assertSame((int)$course->id, (int)$found->id);
    }

    /**
     * A course of the legacy convention with year (<CODE>-<TYPE>-YY_alt) is found if
     * there is no year-less one.
     *
     * @covers \local_lehrgaengeapi\local\course\alt_course_finder::find
     */
    public function test_find_falls_back_to_legacy_convention(): void {
        $this->resetAfterTest(true);
        $legacy = $this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR-26_alt']);

        $found = (new alt_course_finder())->find('FD', 'F-ATR');

        $this->assertSame((int)$legacy->id, (int)$found->id);
    }

    /**
     * If both exist, the current convention wins.
     *
     * @covers \local_lehrgaengeapi\local\course\alt_course_finder::find
     */
    public function test_find_prefers_current_over_legacy(): void {
        $this->resetAfterTest(true);
        $this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR-26_alt']);
        $current = $this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR_alt']);

        $this->assertSame((int)$current->id, (int)(new alt_course_finder())->find('FD', 'F-ATR')->id);
    }

    /**
     * A type that is a prefix of another type must not pick up the other type's legacy
     * course: F-TH must not find FD-F-TH-VU-26_alt, F-I must not find FD-F-II-26_alt.
     *
     * @covers \local_lehrgaengeapi\local\course\alt_course_finder::find
     */
    public function test_find_does_not_match_longer_type_as_legacy(): void {
        $this->resetAfterTest(true);
        $this->getDataGenerator()->create_course(['shortname' => 'FD-F-TH-VU-26_alt']);
        $this->getDataGenerator()->create_course(['shortname' => 'FD-F-II-26_alt']);

        $finder = new alt_course_finder();
        $this->assertNull($finder->find('FD', 'F-TH'));
        $this->assertNull($finder->find('FD', 'F-I'));
    }

    /**
     * Other tenants' courses and non-alt courses are not found.
     *
     * @covers \local_lehrgaengeapi\local\course\alt_course_finder::find
     */
    public function test_find_returns_null_when_nothing_matches(): void {
        $this->resetAfterTest(true);
        $this->getDataGenerator()->create_course(['shortname' => 'MKK-F-ATR_alt']);
        $this->getDataGenerator()->create_course(['shortname' => 'FD-F-ATR-26']);

        $finder = new alt_course_finder();
        $this->assertNull($finder->find('FD', 'F-ATR'));
        $this->assertNull($finder->find('FD', ''));
        $this->assertNull($finder->find('', 'F-ATR'));
    }
}
