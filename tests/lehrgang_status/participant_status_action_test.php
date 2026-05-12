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
 * Tests for participant_status_action.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_lehrgaengeapi\lehrgang_status;

use local_lehrgaengeapi\local\lehrgang_status\participant_status_action;

/**
 * Tests for participant_status_action.
 *
 * @package   local_lehrgaengeapi
 * @copyright 2026 Wunderbyte GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participant_status_action_test extends \advanced_testcase {
    /**
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::assign_only
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_assign
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_unassign
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_complete
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_not_do_anything
     */
    public function test_assign_only_action_flags(): void {
        $action = participant_status_action::assign_only();

        $this->assertTrue($action->should_assign());
        $this->assertFalse($action->should_unassign());
        $this->assertFalse($action->should_complete());
        $this->assertFalse($action->should_not_do_anything());
    }

    /**
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::unassign_only
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_assign
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_unassign
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_complete
     */
    public function test_unassign_only_action_flags(): void {
        $action = participant_status_action::unassign_only();

        $this->assertFalse($action->should_assign());
        $this->assertTrue($action->should_unassign());
        $this->assertFalse($action->should_complete());
    }

    /**
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::assign_and_complete
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_assign
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_complete
     */
    public function test_assign_and_complete_action_flags(): void {
        $action = participant_status_action::assign_and_complete();

        $this->assertTrue($action->should_assign());
        $this->assertTrue($action->should_complete());
    }

    /**
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::noop
     * @covers \local_lehrgaengeapi\local\lehrgang_status\participant_status_action::should_not_do_anything
     */
    public function test_noop_action_flags(): void {
        $action = participant_status_action::noop();

        $this->assertTrue($action->should_not_do_anything());
        $this->assertFalse($action->should_assign());
        $this->assertFalse($action->should_unassign());
        $this->assertFalse($action->should_complete());
    }
}
